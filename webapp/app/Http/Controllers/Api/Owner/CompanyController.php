<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Platform\Tenancy\CompanyContext;
use App\Platform\Tenancy\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Business tab of the owner app's profile (M28, 13-parity #28): the
 * company as it prints on LRs and bills — read by everyone in the office,
 * changed with `settings.company`, the same rules as Settings → Company.
 */
class CompanyController extends Controller
{
    public function __construct(private CompanyContext $context, private CompanyProfile $profile) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profile->toApi($this->context->company(), $request->user()->can('settings.company')));
    }

    /** Multipart (for the logo); saving the same thing twice is harmless, so no Idempotency-Key. */
    public function save(Request $request): JsonResponse
    {
        $company = $this->context->company();
        $data = $request->validate($this->profile->rules($company), $this->profile->messages());

        return response()->json($this->profile->toApi($this->profile->save($company, $data, $request->file('logo')), true));
    }

    public function logo(): Response
    {
        $path = $this->context->company()->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
