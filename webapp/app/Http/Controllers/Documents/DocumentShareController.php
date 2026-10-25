<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Modules\Documents\Models\DocumentShare;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Documents\Services\DocumentFiles;
use App\Modules\Documents\Services\DocumentShares;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Share links: made by a member (document.manage), opened by anyone with the
 * link until it expires or is ended. The public page never shows personal ID
 * papers, whatever was picked.
 */
class DocumentShareController extends Controller
{
    public function __construct(private CompanyContext $context, private DocumentShares $shares, private Compliance $compliance) {}

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'documents'   => ['required', 'array', 'min:1', 'max:30'],
            'documents.*' => ['integer', Rule::exists('documents', 'id')->where('company_id', $companyId)],
            'purpose'     => ['required', 'string', 'min:3', 'max:150'],
            'days'        => ['required', 'integer', 'min:1', 'max:' . DocumentShares::MAX_DAYS],
        ]);

        $ids = Document::whereIn('id', $data['documents'])->get()
            ->reject(fn ($d) => $this->compliance->isSensitive($d->document_type))->pluck('id')->all();
        if ($ids === []) {
            return back()->with('error', __('Personal ID papers cannot be shared by link.'));
        }

        $token = $this->shares->create($ids, trim($data['purpose']), (int) $data['days']);

        return back()->with('share_link', route('share.show', $token));
    }

    public function revoke(DocumentShare $share): RedirectResponse
    {
        $share->forceFill(['revoked_at' => now()])->save();

        return back()->with('status', __('The link no longer works.'));
    }

    /** The public page. */
    public function show(string $token): Response
    {
        $share = $this->shares->open($token);
        if (! $share) {
            return response()->view('documents.share-gone', [], 410);
        }

        $company = Company::find($share->company_id);

        return $this->context->runAs($company, function () use ($share, $company, $token) {
            $docs = Document::with('files')->whereIn('id', $share->document_ids)->get()
                ->reject(fn ($d) => $this->compliance->isSensitive($d->document_type));

            // Rendered here, while the company is in context (the page reads its lists).
            return response(view('documents.share', [
                'share'      => $share,
                'company'    => $company,
                'documents'  => $docs,
                'labels'     => $this->compliance->ownerLabels($docs),
                'compliance' => $this->compliance,
                'token'      => $token,
            ])->render())->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'private, no-store');
        });
    }

    /** A file on the public page: images watermarked, PDFs as they are. */
    public function file(string $token, int $file, DocumentFiles $files): Response
    {
        $share = $this->shares->open($token, count: false);
        abort_unless($share, 410);

        return $this->context->runAs(Company::findOrFail($share->company_id), function () use ($share, $file, $files) {
            $row = DocumentFile::findOrFail($file);
            abort_unless(in_array((int) $row->document_id, array_map('intval', $share->document_ids), true), 404);
            $document = Document::findOrFail($row->document_id);
            abort_if($this->compliance->isSensitive($document->document_type), 404);

            $headers = ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex'];
            if ($row->isImage()) {
                return response($files->watermarked($row, $share->watermark), 200, $headers + ['Content-Type' => 'image/jpeg']);
            }

            return Storage::disk('local')->response($row->path, $row->original_name ?: 'document.pdf', $headers + ['Content-Type' => $row->mime], 'inline');
        });
    }
}
