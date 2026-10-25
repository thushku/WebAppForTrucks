<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Models\MessageTemplate;
use App\Modules\Invoicing\Services\Messages;
use App\Modules\Invoicing\Services\Reminders;
use App\Modules\Settings\Services\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Settings → Messages (M18): the words sent with bills, receipts and
 * overdue reminders, by email and WhatsApp, with {placeholders}; and the
 * reminder switch and days. Wording left as shipped keeps following the
 * shipped text.
 */
class MessagesController extends Controller
{
    public function __construct(private Messages $messages, private CompanySettings $settings) {}

    public function edit(): View
    {
        $rows = [];
        foreach (array_keys(Messages::PURPOSES) as $purpose) {
            foreach (array_keys(Messages::CHANNELS) as $channel) {
                $template = $this->messages->template($purpose, $channel);
                $rows[$purpose][$channel] = $template + ['custom' => MessageTemplate::where('purpose', $purpose)->where('channel', $channel)->exists(),
                    'preview' => $this->messages->sample($purpose, $channel, $template['subject'], $template['body'])];
            }
        }

        return view('settings.messages', ['rows' => $rows, 'reminders' => (bool) $this->settings->get('reminders_enabled'), 'days' => (string) $this->settings->get('reminder_days')]);
    }

    public function update(Request $request, Reminders $reminders): RedirectResponse
    {
        $rules = ['reminders_enabled' => ['nullable', 'boolean'], 'reminder_days' => ['nullable', 'string', 'max:60', 'regex:/^[0-9 ,;]*$/']];
        foreach (array_keys(Messages::PURPOSES) as $purpose) {
            foreach (array_keys(Messages::CHANNELS) as $channel) {
                $rules["{$purpose}_{$channel}_body"] = ['required', 'string', 'max:3000'];
                $rules["{$purpose}_{$channel}_subject"] = $channel === 'email' ? ['required', 'string', 'max:200'] : ['nullable'];
            }
        }
        $data = $request->validate($rules, ['reminder_days.regex' => __('Days as numbers, for example 3, 15, 30.')]);
        $steps = $reminders->steps((string) ($data['reminder_days'] ?? ''));
        if ($request->boolean('reminders_enabled') && $steps === []) {
            return back()->withInput()->withErrors(['reminder_days' => __('Give at least one day, for example 3, 15, 30.')]);
        }

        DB::transaction(function () use ($data, $request, $steps) {
            foreach (array_keys(Messages::PURPOSES) as $purpose) {
                foreach (array_keys(Messages::CHANNELS) as $channel) {
                    $subject = $channel === 'email' ? trim(strip_tags((string) $data["{$purpose}_{$channel}_subject"])) : null;
                    $body = trim(strip_tags(str_replace("\r\n", "\n", (string) $data["{$purpose}_{$channel}_body"])));
                    $shipped = $this->messages->shipped($purpose, $channel);
                    // As shipped (or reset): no row, so later wording improvements reach this company too.
                    if ($request->boolean("{$purpose}_{$channel}_reset") || ($subject === $shipped['subject'] && $body === trim($shipped['body']))) {
                        MessageTemplate::where('purpose', $purpose)->where('channel', $channel)->get()->each->delete();
                    } else {
                        MessageTemplate::updateOrCreate(['purpose' => $purpose, 'channel' => $channel], ['subject' => $subject, 'body' => $body]);
                    }
                }
            }
            $this->settings->save(['reminders_enabled' => $request->boolean('reminders_enabled'), 'reminder_days' => implode(', ', $steps ?: [3, 15, 30])]);
        });

        return back()->with('status', __('Messages saved.'));
    }
}
