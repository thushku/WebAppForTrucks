<?php

namespace App\Http\Controllers;

use App\Platform\Auth\Profile;
use App\Platform\Settings\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * My profile (M28) — name, screen language, date of birth and photo — on the
 * web and for both phone apps; and Help: the provider's support contacts
 * (console → Settings), the FAQ and the legal pages.
 */
class ProfileController extends Controller
{
    public function __construct(private Profile $profile) {}

    public function edit(Request $request): View
    {
        return view('profile', ['account' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->profile->rules(), $this->profile->messages());
        $account = $this->profile->save($request->user(), $data, $request->file('photo'));
        // The language chosen here wins over one picked earlier with ?lang=.
        $request->session()->put('locale', $account->locale);

        return back()->with('status', __('Profile saved.'));
    }

    /** The signed-in person's own photo (files are never public). */
    public function photo(Request $request): Response
    {
        $path = $request->user()->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function help(PlatformSettings $settings): View
    {
        return view('help', ['help' => self::contacts($settings)]);
    }

    // ---- The phone apps -----------------------------------------------------------------

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profile->toApi($request->user()));
    }

    /** Multipart (for the photo). Saving the same thing twice is harmless, so no Idempotency-Key. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate($this->profile->rules(), $this->profile->messages());

        return response()->json($this->profile->toApi($this->profile->save($request->user(), $data, $request->file('photo'))));
    }

    public function helpApi(PlatformSettings $settings): JsonResponse
    {
        return response()->json(self::contacts($settings));
    }

    /** Support contacts and links, the same for the web page and the apps. */
    public static function contacts(PlatformSettings $settings): array
    {
        $whatsapp = preg_replace('/\D/', '', (string) $settings->get('support_whatsapp'));

        return [
            'brand' => $settings->brandName(),
            'email' => $settings->get('support_email') ?: null,
            'phone' => $settings->get('support_phone') ?: null,
            'whatsapp' => $whatsapp ?: null,
            'whatsapp_url' => $whatsapp ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode(__('Hello :brand, I need help with', ['brand' => $settings->brandName()])) : null,
            'faq_url' => route('site.faq'), 'contact_url' => route('site.contact'),
            'terms_url' => route('site.legal', 'terms'), 'privacy_url' => route('site.legal', 'privacy'),
            'version' => config('app.version'),
        ];
    }
}
