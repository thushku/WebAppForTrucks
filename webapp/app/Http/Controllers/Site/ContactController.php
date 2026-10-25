<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Platform\Auth\Captcha;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Website\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Contact page (website guideline §3): checked fields, the picture
 * challenge, a hidden field robots fill in, at most a few messages a minute.
 * Each message is kept (console → Overview) and sent to the support address.
 */
class ContactController extends Controller
{
    public function __construct(private PlatformSettings $settings, private Captcha $captcha) {}

    public function show(): View
    {
        return view('site.contact', ['contact' => [
            'email' => $this->settings->get('support_email'), 'phone' => $this->settings->get('support_phone'), 'whatsapp' => $this->settings->get('support_whatsapp'),
        ]]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:filter', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^[+0-9 ()-]+$/'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'captcha' => ['required', 'string', 'max:12'],
            'website' => ['nullable', 'string', 'max:255'],
        ], ['mobile.regex' => __('Only digits, spaces and + ( ) -.')]);

        $thanks = __('Thank you — we have your message and will answer soon.');
        // A robot filled in the hidden field: thank it and keep nothing.
        if (filled($data['website'] ?? null)) {
            return redirect()->route('site.contact')->with('status', $thanks);
        }
        if (! $this->captcha->passes($request->session(), $data['captcha'])) {
            return back()->withInput($request->except('captcha'))->withErrors(['captcha' => __('The characters did not match the picture. Try the new one.')]);
        }

        $message = DB::transaction(fn () => ContactMessage::create([
            'name' => strip_tags($data['name']), 'company' => isset($data['company']) ? strip_tags($data['company']) : null,
            'email' => mb_strtolower($data['email']), 'mobile' => $data['mobile'] ?? null, 'topic' => $data['topic'],
            'message' => strip_tags($data['message']), 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]));

        if ($to = $this->settings->get('support_email')) {
            try {
                Mail::raw("From: {$message->name} <{$message->email}>" . ($message->mobile ? ", {$message->mobile}" : '') . ($message->company ? "\nCompany: {$message->company}" : '')
                    . "\nAbout: " . ContactMessage::TOPICS[$message->topic] . "\n\n{$message->message}", function ($mail) use ($to, $message) {
                        $mail->to($to)->replyTo($message->email, $message->name)->subject('[' . $this->settings->brandName() . '] ' . ContactMessage::TOPICS[$message->topic] . ' — ' . $message->name);
                    });
            } catch (\Throwable $e) {
                // Kept in the console either way.
                Log::warning('Contact message mail failed', ['id' => $message->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->route('site.contact')->with('status', $thanks);
    }
}
