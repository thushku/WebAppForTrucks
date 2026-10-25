<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Data\CompanyDeletion;
use App\Platform\Data\CompanyExport;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Settings → Your data (blueprint 09 §8): download everything the company
 * has, and delete the company — after 72 hours anyone allowed can cancel in.
 * Both work while the company is read-only.
 */
class DataController extends Controller
{
    public function __construct(private CompanyContext $context, private CompanyDeletion $deletion) {}

    public function show(): View
    {
        $company = $this->context->company();

        return view('settings.data', [
            'company' => $company, 'due' => $company->deletion_requested_at ? $this->deletion->dueAt($company) : null,
            'requestedBy' => $company->deletion_requested_by ? \App\Models\Account::whereKey($company->deletion_requested_by)->value('name') : null,
        ]);
    }

    /** The ZIP, made on the spot and removed after it is sent. */
    public function export(CompanyExport $export, AuditRecorder $audit): BinaryFileResponse
    {
        $company = $this->context->company();
        $built = $export->build($company, 'exports/' . Str::random(32) . '.zip');
        $audit->record('data.exported', 'Downloaded all the company’s data (' . array_sum($built['tables']) . ' rows, ' . $built['files'] . ' files)', 'company', (string) $company->id, $company->name, severity: 'sensitive');

        return response()->download(Storage::disk('local')->path($built['path']), Str::slug($company->name) . '-data-' . now()->format('Ymd') . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $company = $this->context->company();
        $data = $request->validate(['confirm' => ['required', 'string', 'max:150'], 'password' => ['required', 'string', 'max:1024'], 'reason' => ['required', 'string', 'min:3', 'max:255']]);
        if (mb_strtolower(trim($data['confirm'])) !== mb_strtolower(trim($company->name))) {
            throw ValidationException::withMessages(['confirm' => __('Type the company’s name exactly as it is: :name', ['name' => $company->name])]);
        }
        if (! Hash::check($data['password'], (string) $request->user()->password_hash)) {
            throw ValidationException::withMessages(['password' => __('That is not your password.')]);
        }
        $this->deletion->request($company, $request->user(), $data['reason']);

        return back()->with('status', __(':company will be deleted on :when. Until then you can cancel here.', ['company' => $company->name, 'when' => $this->deletion->dueAt($company->fresh())->timezone($company->timezone ?: 'UTC')->translatedFormat('j F Y, H:i')]));
    }

    public function cancelDeletion(Request $request): RedirectResponse
    {
        $this->deletion->cancel($this->context->company(), $request->user());

        return back()->with('status', __('The deletion is cancelled. Nothing was removed.'));
    }
}
