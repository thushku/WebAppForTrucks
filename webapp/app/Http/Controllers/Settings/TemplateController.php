<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Design\DesignSchema;
use App\Modules\Invoicing\Design\SampleDocuments;
use App\Modules\Invoicing\Models\DocumentTemplate;
use App\Modules\Invoicing\Services\DocumentTemplates;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Templates (blueprint 10 §9, ported from Clinic Ledger's
 * designer): the named designs of bills, LRs and receipts, which one is the
 * default, which clients and branches print with which, and the editor —
 * every choice on the left, the document itself on the right, redrawn from
 * the form as it changes. Saving keeps the old version for a rollback.
 */
class TemplateController extends Controller
{
    public function __construct(private DocumentTemplates $templates) {}

    public function index(Request $request): View
    {
        $family = array_key_exists((string) $request->query('family'), DesignSchema::FAMILIES) ? $request->query('family') : 'invoice';
        $all = DocumentTemplate::orderByDesc('is_default')->orderBy('name')->get()->groupBy('family');

        return view('settings.templates.index', [
            'family'    => $family,
            'templates' => $all->get($family, collect()),
            'counts'    => $all->map->count(),
            'parties'   => Party::whereIn('id', $all->flatten()->pluck('party_ids')->flatten()->filter()->unique()->all())->pluck('name', 'id'),
            'branches'  => Branch::pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'family' => ['required', Rule::in(array_keys(DesignSchema::FAMILIES))],
            'theme'  => ['required', 'string'],
            'name'   => ['required', 'string', 'max:70'],
        ]);
        abort_unless(isset(DesignSchema::THEMES[$data['family']][$data['theme']]), 422);
        $template = $this->templates->create($data['family'], $data['name'], DesignSchema::defaults($data['family'], $data['theme']), $request->user()->id);

        return redirect()->route('settings.templates.edit', $template)->with('status', __('Template created. Change what you like; the preview follows.'));
    }

    /** A design file (from Export, or another company) becomes a new template; what does not fit is listed. */
    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'family' => ['required', Rule::in(array_keys(DesignSchema::FAMILIES))],
            'file'   => ['required', 'file', 'max:64', 'mimetypes:application/json,text/plain'],
        ]);
        $input = json_decode((string) file_get_contents($data['file']->getRealPath()), true);
        if (! is_array($input)) {
            return back()->withErrors(['file' => __('That file is not a design: it should be the JSON file Export gives.')]);
        }
        $name = is_string($input['name'] ?? null) ? mb_substr(strip_tags($input['name']), 0, 60) : __('Imported design');
        unset($input['name']);
        $template = $this->templates->create($data['family'], $name, $input, $request->user()->id, $dropped);

        return redirect()->route('settings.templates.edit', $template)
            ->with('status', __('Design imported.'))->with('dropped', $dropped);
    }

    public function edit(DocumentTemplate $template, SampleDocuments $samples): View
    {
        $template->load('versions.author');
        $design = $template->designNormalized();

        return view('settings.templates.edit', [
            'template' => $template,
            'design'   => $design,
            'family'   => $template->family,
            'previewHtml' => view('documents.' . $template->family, ['doc' => $samples->for($template->family, $design)])->render(),
            'sampleNote'  => $samples->hasReal($template->family) ? __('Your latest document, with this design') : __('A sample with your name and bank'),
            'parties'  => Party::withRole('client')->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:70'], 'design' => ['required', 'array']]);
        $template = $this->templates->save($template, $this->designFrom($request, $template->family), $request->input('name'), $request->user()->id, $dropped);

        return redirect()->route('settings.templates.edit', $template)
            ->with('status', __('Saved as version :v.', ['v' => $template->version]))->with('dropped', $dropped);
    }

    /** The editor's live preview: the form as it is now, laid over the latest document (or a sample). Nothing is saved. */
    public function preview(Request $request, string $family, SampleDocuments $samples): Response
    {
        abort_unless(isset(DesignSchema::FAMILIES[$family]), 404);
        $design = DesignSchema::normalize($family, $this->designFrom($request, $family));

        return response(view('documents.' . $family, ['doc' => $samples->for($family, $design)])->render())
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src 'self'")
            ->header('X-Robots-Tag', 'noindex');
    }

    public function assign(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'party_ids'    => ['nullable', 'array'], 'party_ids.*' => ['integer', Rule::exists('parties', 'id')->where('company_id', $template->company_id)],
            'branch_ids'   => ['nullable', 'array'], 'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('company_id', $template->company_id)],
        ]);
        $this->templates->assign($template, $data['party_ids'] ?? [], $data['branch_ids'] ?? []);

        return redirect()->to(route('settings.templates.edit', $template) . '#who')->with('status', __('Who prints with it: saved.'));
    }

    public function duplicate(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $copy = $this->templates->duplicate($template, $request->user()->id);

        return redirect()->route('settings.templates.edit', $copy)->with('status', __('Copied as “:name”.', ['name' => $copy->name]));
    }

    public function makeDefault(DocumentTemplate $template): RedirectResponse
    {
        $this->templates->makeDefault($template);

        return back()->with('status', __('“:name” is now the default.', ['name' => $template->name]));
    }

    public function rollback(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $template = $this->templates->rollback($template, (int) $data['version'], $request->user()->id);

        return redirect()->route('settings.templates.edit', $template)->with('status', __('Version :old brought back as version :new.', ['old' => $data['version'], 'new' => $template->version]));
    }

    public function export(DocumentTemplate $template): Response
    {
        $json = json_encode($this->templates->export($template), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $file = preg_replace('/[^A-Za-z0-9-]+/', '-', $template->name) . '.design.json';

        return response($json, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="' . trim($file, '-') . '"']);
    }

    public function destroy(DocumentTemplate $template): RedirectResponse
    {
        $this->templates->delete($template);

        return redirect()->route('settings.templates', ['family' => $template->family])->with('status', __('Template removed. Bills already issued keep their look.'));
    }

    /**
     * The design as the form posts it. A switch that is off posts nothing, so
     * every switch of the family is read as on or off here.
     */
    private function designFrom(Request $request, string $family): array
    {
        $design = (array) $request->input('design', []);
        foreach (array_keys(DesignSchema::FIELDS[$family]) as $field) {
            $design['fields'][$field] = (bool) ($design['fields'][$field] ?? false);
        }
        if (isset($design['copies']) && is_array($design['copies'])) {
            $design['copies'] = array_values(array_filter($design['copies'], fn ($c) => is_array($c) && empty($c['remove'])));
            foreach ($design['copies'] as &$copy) {
                $copy['hide_freight'] = ! empty($copy['hide_freight']);
                unset($copy['remove']);
            }
            unset($copy);
        }

        return $design + ['family' => $family];
    }
}
