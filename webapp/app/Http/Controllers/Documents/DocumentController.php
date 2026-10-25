<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Modules\Documents\Models\DocumentShare;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Documents\Services\DocumentFiles;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Parties\Models\Party;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Document Manager: the compliance dashboard (expired, due, missing),
 * documents of one owner, adding, correcting and renewing, and the files.
 *
 * Personal ID papers (types marked "Personal ID" in Settings → Lists) are
 * seen only with `people.id_documents`, and every look at one is logged.
 */
class DocumentController extends Controller
{
    public function __construct(private CompanyContext $context, private Compliance $compliance, private DocumentService $documents, private AuditRecorder $audit) {}

    public function index(Request $request): View
    {
        $filter = (string) $request->query('show', 'attention');
        $owner  = (string) $request->query('owner', '');
        $today  = now($this->context->company()->timezone)->startOfDay();

        $query = Document::query()
            ->when(isset(Document::OWNER_TYPES[$owner]), fn ($q) => $q->where('owner_type', $owner))
            ->when($filter === 'expired', fn ($q) => $q->whereNotNull('expires_on')->where('expires_on', '<', $today->toDateString()))
            ->when($filter === 'd7', fn ($q) => $q->whereBetween('expires_on', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]))
            ->when($filter === 'd15', fn ($q) => $q->whereBetween('expires_on', [$today->copy()->addDays(8)->toDateString(), $today->copy()->addDays(15)->toDateString()]))
            ->when($filter === 'd30', fn ($q) => $q->whereBetween('expires_on', [$today->copy()->addDays(16)->toDateString(), $today->copy()->addDays(30)->toDateString()]))
            ->when($filter === 'attention', fn ($q) => $q->whereNotNull('expires_on')->where('expires_on', '<=', $today->copy()->addDays(30)->toDateString()))
            ->when(! $this->canSeeSensitive(), fn ($q) => $q->whereNotIn('document_type', $this->sensitiveTypes()))
            ->withCount('files')
            ->orderByRaw('expires_on is null, expires_on')
            ->orderBy('id');

        $documents = $filter === 'missing' ? collect() : $query->paginate(50)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'labels'    => $filter === 'missing' ? [] : $this->compliance->ownerLabels($documents),
            'counts'    => $this->compliance->counts(),
            'missing'   => $filter === 'missing' ? $this->compliance->missingAll() : [],
            'filter'    => $filter,
            'owner'     => $owner,
            'compliance' => $this->compliance,
        ]);
    }

    /** The documents of one owner (truck, person, party or the company). */
    public function owner(string $ownerType, int $ownerId = 0): View
    {
        $model = $this->documents->owner($ownerType, $ownerId);
        $docs  = Document::where('owner_type', $ownerType)->where('owner_id', $ownerType === 'company' ? 0 : $ownerId)
            ->with('files')->orderBy('document_type')->get()
            ->reject(fn ($d) => $this->compliance->isSensitive($d->document_type) && ! $this->canSeeSensitive());

        return view('documents.owner', [
            'ownerType' => $ownerType,
            'ownerId'   => $ownerType === 'company' ? 0 : $ownerId,
            'model'     => $model,
            'title'     => $this->ownerTitle($ownerType, $model),
            'documents' => $docs,
            'missing'   => $ownerType === 'truck' || $ownerType === 'person' ? $this->compliance->missing($model) : ($ownerType === 'company' ? $this->compliance->missing(null) : []),
            'types'     => $this->compliance->types()->filter(fn ($t) => $t['owner'] === $ownerType && $t['active'] && (! $t['sensitive'] || $this->canSeeSensitive())),
            'shares'    => DocumentShare::whereNull('revoked_at')->where('expires_at', '>', now())->latest()->limit(20)->get()
                ->filter(fn ($s) => array_intersect($s->document_ids, $docs->pluck('id')->all())),
            'compliance' => $this->compliance,
        ]);
    }

    public function create(Request $request, string $ownerType, int $ownerId = 0): View
    {
        $model = $this->documents->owner($ownerType, $ownerId);
        $type  = (string) $request->query('type', '');

        return view('documents.form', [
            'document'  => new Document(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'document_type' => $type]),
            'mode'      => 'create',
            'title'     => $this->ownerTitle($ownerType, $model),
            'types'     => $this->typeOptions($ownerType),
            'vendors'   => Party::active()->withRole('vendor')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, string $ownerType, int $ownerId = 0): RedirectResponse
    {
        $this->documents->owner($ownerType, $ownerId);
        [$data, $uploads] = $this->validated($request, $ownerType);
        $document = $this->documents->create($ownerType, $ownerId, $data, $uploads);

        return redirect()->route('documents.show', $document)->with('status', __(':type saved.', ['type' => $this->compliance->typeName($document->document_type)]));
    }

    public function show(Document $document): View
    {
        $this->guardSensitive($document);
        $document->load(['files', 'versions.renewer', 'vendor']);
        $model = $this->documents->owner($document->owner_type, $document->owner_id);

        return view('documents.show', [
            'document'   => $document,
            'title'      => $this->ownerTitle($document->owner_type, $model),
            'ownerUrl'   => route('documents.owner', [$document->owner_type, $document->owner_id]),
            'compliance' => $this->compliance,
        ]);
    }

    public function edit(Request $request, Document $document): View
    {
        $this->guardSensitive($document);
        $mode  = $request->routeIs('documents.renew.form') ? 'renew' : 'edit';
        $model = $this->documents->owner($document->owner_type, $document->owner_id);

        return view('documents.form', [
            'document' => $document,
            'mode'     => $mode,
            'title'    => $this->ownerTitle($document->owner_type, $model),
            'types'    => $this->typeOptions($document->owner_type),
            'vendors'  => Party::active()->withRole('vendor')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->guardSensitive($document);
        [$data, $uploads] = $this->validated($request, $document->owner_type);
        $this->documents->update($document, $data, $uploads);

        return redirect()->route('documents.show', $document)->with('status', __('Saved.'));
    }

    public function renew(Request $request, Document $document): RedirectResponse
    {
        $this->guardSensitive($document);
        [$data, $uploads] = $this->validated($request, $document->owner_type, renewing: true);
        unset($data['document_type']);
        $this->documents->renew($document, $data, $uploads);

        return redirect()->route('documents.show', $document)->with('status', __('Renewed. The old version is kept in the history.'));
    }

    public function snooze(Request $request, Document $document): RedirectResponse
    {
        $this->guardSensitive($document);
        $data = $request->validate([
            'until'  => ['required', 'date_format:Y-m-d', 'after:today', 'before_or_equal:' . now()->addDays(60)->toDateString()],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ]);
        $this->documents->snooze($document, $data['until'], $data['reason']);

        return back()->with('status', __('Reminders paused until :date.', ['date' => \Carbon\Carbon::parse($data['until'])->format('d M Y')]));
    }

    /** A file, to members who may see the document. Personal ID files are logged on every look. */
    public function file(DocumentFile $file): Response
    {
        $document = Document::findOrFail($file->document_id);
        $this->guardSensitive($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        if ($this->compliance->isSensitive($document->document_type)) {
            $this->audit->record('document.file_viewed', "Opened a file of {$document->document_type} ({$document->owner_type} #{$document->owner_id})", 'document', (string) $document->id, $document->number, severity: 'sensitive');
        }

        return Storage::disk('local')->response($file->path, ($file->original_name ?: 'document'), [
            'Content-Type'  => $file->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    // ---- Helpers ------------------------------------------------------------------------

    private function canSeeSensitive(): bool
    {
        return (bool) auth()->user()?->can('people.id_documents');
    }

    /** @return string[] */
    private function sensitiveTypes(): array
    {
        return $this->compliance->types()->filter(fn ($t) => $t['sensitive'])->keys()->all();
    }

    private function guardSensitive(Document $document): void
    {
        abort_if($this->compliance->isSensitive($document->document_type) && ! $this->canSeeSensitive(), 403, __('Only people allowed to see ID papers can open this.'));
    }

    private function ownerTitle(string $type, $model): string
    {
        return match ($type) {
            'truck'   => $model->reg_no,
            'person', 'party' => $model->name,
            default   => $this->context->company()->name,
        };
    }

    /** @return array<string, string> code => name, for the owner kind (sensitive ones only with the permission) */
    private function typeOptions(string $ownerType): array
    {
        return $this->compliance->types()
            ->filter(fn ($t) => $t['owner'] === $ownerType && $t['active'] && (! $t['sensitive'] || $this->canSeeSensitive()))
            ->map(fn ($t) => $t['name'])->all();
    }

    /** @return array{0: array<string, mixed>, 1: \Illuminate\Http\UploadedFile[]} */
    private function validated(Request $request, string $ownerType, bool $renewing = false): array
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'document_type'     => [$renewing ? 'nullable' : 'required', Rule::in(array_keys($this->typeOptions($ownerType)))],
            'number'            => ['nullable', 'string', 'max:60'],
            'authority'         => ['nullable', 'string', 'max:120'],
            'issued_on'         => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'valid_from'        => ['nullable', 'date_format:Y-m-d'],
            'expires_on'        => [$renewing ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'amount'            => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'vendor_party_id'   => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'original_location' => ['nullable', 'string', 'max:120'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'files'             => ['nullable', 'array', 'max:6'],
            'files.*'           => ['file', 'max:' . DocumentFiles::UPLOAD_MAX_KB],
        ], ['files.*.max' => __('Each file may be at most 8 MB before it is shrunk.')]);

        $uploads = $request->file('files', []);

        return [collect($data)->except('files')->all(), is_array($uploads) ? $uploads : [$uploads]];
    }
}
