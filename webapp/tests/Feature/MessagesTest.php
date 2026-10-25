<?php

use App\Modules\Invoicing\Models\DocumentDelivery;
use App\Modules\Invoicing\Models\Invoice;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

// Sending documents and reminders (M18): a bill and a receipt by email with
// the company's wording, WhatsApp text from the same templates, the wording
// edited in Settings → Messages, and overdue reminders on chosen days.

/** Mail goes to an in-memory transport; the messages sent so far. */
function mailbox(): \Illuminate\Support\Collection
{
    return collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage());
}

/** A finalised bill for ACC Limited (₹75,520 with GST, due in 30 days), the client with an email. */
function msgBill(): array
{
    config(['mail.default' => 'array']);
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    $w = invWorld();
    $invoice = invDraft($w);
    test()->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $w['client']->forceFill(['email' => 'accounts@acc.test', 'mobile' => '+919820000101'])->save();

    return $w + ['invoice' => $invoice->fresh()];
}

it('emails a bill with the company’s wording, the secure link and a copy, and notes it on the bill', function () {
    $w = msgBill();
    $bill = $w['invoice'];

    $this->get(route('invoices.show', $bill))->assertOk()->assertSeeText('Send by email')
        ->assertSee('value="accounts@acc.test"', false)->assertSee(e("Tax invoice {$bill->number} from Sharma Roadlines"), false);

    $this->post(route('invoices.email', $bill), ['to' => 'accounts@acc.test, not-an-email', 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('to');
    $this->post(route('invoices.email', $bill), ['to' => 'accounts@acc.test; ops@acc.test', 'cc' => 'boss@acc.test', 'subject' => "Bill {$bill->number}", 'body' => "Dear ACC,\nPlease pay.\n" . route('documents.public', $bill->public_token)])
        ->assertSessionHas('status', 'Sent to accounts@acc.test, ops@acc.test.');

    $mail = mailbox()->last();
    expect($mail->getSubject())->toBe("Bill {$bill->number}")
        ->and(collect($mail->getTo())->map->getAddress()->all())->toBe(['accounts@acc.test', 'ops@acc.test'])
        ->and(collect($mail->getCc())->map->getAddress()->all())->toBe(['boss@acc.test'])
        ->and($mail->getTextBody())->toContain('Please pay.')->toContain(route('documents.public', $bill->public_token));
    app(CompanyContext::class)->set($w['company']);
    expect($bill->fresh()->status)->toBe('sent')
        ->and(DocumentDelivery::where('document_type', 'invoice')->where('document_id', $bill->id)->where('channel', 'email')->value('recipient'))->toBe('accounts@acc.test, ops@acc.test, boss@acc.test');

    // WhatsApp uses the same templates.
    $to = $this->post(route('invoices.shared', $bill), ['channel' => 'whatsapp'])->assertRedirect()->headers->get('Location');
    expect(urldecode($to))->toStartWith('https://wa.me/919820000101?text=Tax invoice ' . $bill->number . ' from Sharma Roadlines for ₹75,520.00');

    // A receipt for it by email too.
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 75520, 'mode' => 'neft', 'reference' => 'UTR1', 'narration' => 'October bill', 'allocation' => 'oldest'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $receipt = \App\Modules\Invoicing\Models\Receipt::latest('id')->first();
    $this->get(route('receipts.show', $receipt))->assertOk()->assertSee(e("Receipt {$receipt->number} from Sharma Roadlines"), false);
    $this->post(route('receipts.email', $receipt), ['to' => 'accounts@acc.test', 'subject' => 'Your receipt', 'body' => 'Thank you'])->assertSessionHas('status');
    expect(mailbox()->last()->getSubject())->toBe('Your receipt');

    // The workshop may not send bills.
    signInTo($w['company'], 'workshop');
    $this->post(route('invoices.email', $bill), ['to' => 'a@b.test', 'subject' => 'x', 'body' => 'y'])->assertForbidden();
});

it('keeps the company’s own wording, shows a preview, and goes back to the shipped words', function () {
    $w = msgBill();
    $page = $this->get(route('settings.messages'))->assertOk()->assertSeeText('Overdue reminder')->assertSeeText('{days_overdue}')->assertSeeText('INV/26-27/00042');

    $form = [];
    foreach (array_keys(\App\Modules\Invoicing\Services\Messages::PURPOSES) as $purpose) {
        foreach (['email', 'whatsapp'] as $channel) {
            $t = app(\App\Modules\Invoicing\Services\Messages::class)->shipped($purpose, $channel);
            $form["{$purpose}_{$channel}_subject"] = $t['subject'];
            $form["{$purpose}_{$channel}_body"] = $t['body'];
        }
    }
    $form['invoice_email_subject'] = 'Freight bill {number} — {company}';
    $form['invoice_whatsapp_body'] = 'Namaste {party}, bill {number}: {amount}. {link}';
    $form['reminders_enabled'] = '1';
    $form['reminder_days'] = '30, 3, 15, abc';
    $this->put(route('settings.messages.update'), $form)->assertSessionHasErrors('reminder_days');
    $form['reminder_days'] = '30, 3, 15';
    $this->put(route('settings.messages.update'), $form)->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(DB::table('message_templates')->where('company_id', $w['company']->id)->count())->toBe(2)
        ->and(app(\App\Modules\Settings\Services\CompanySettings::class)->get('reminder_days'))->toBe('3, 15, 30');

    $this->get(route('invoices.show', $w['invoice']))->assertSee(e("Freight bill {$w['invoice']->number} — Sharma Roadlines"), false);
    $to = $this->post(route('invoices.shared', $w['invoice']), ['channel' => 'whatsapp'])->headers->get('Location');
    expect(urldecode($to))->toContain('Namaste ACC Limited, bill ' . $w['invoice']->number);

    $form['invoice_email_reset'] = '1';
    $this->put(route('settings.messages.update'), $form)->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(DB::table('message_templates')->where('company_id', $w['company']->id)->pluck('purpose')->all())->toBe(['invoice'])
        ->and(DB::table('message_templates')->where('company_id', $w['company']->id)->value('channel'))->toBe('whatsapp');
});

it('reminds a client on the chosen days after the due date, once per step, and only when allowed', function () {
    $w = msgBill();
    $bill = $w['invoice'];
    $settings = fn (array $values) => app(CompanyContext::class)->runAs($w['company'], fn () => app(\App\Modules\Settings\Services\CompanySettings::class)->save($values));

    // Off by default.
    $this->travelTo($bill->due_date->copy()->addDays(3)->setTime(10, 0));
    $this->artisan('fleet:reminders')->expectsOutputToContain('0 reminder(s) sent')->assertSuccessful();

    $settings(['reminders_enabled' => true, 'reminder_days' => '3, 15, 30']);
    $this->artisan('fleet:reminders')->expectsOutputToContain('1 reminder(s) sent')->assertSuccessful();
    $this->artisan('fleet:reminders')->expectsOutputToContain('0 reminder(s) sent')->assertSuccessful();
    $mail = mailbox()->last();
    expect($mail->getSubject())->toBe("Reminder: Tax invoice {$bill->number} is 3 days overdue")
        ->and($mail->getTextBody())->toContain('₹75,520.00 is still due')->toContain(route('documents.public', $bill->public_token));

    // Day 20: the 15-day step; a client kept out gets nothing.
    $this->travelTo($bill->due_date->copy()->addDays(20)->setTime(10, 0));
    $w['client']->forceFill(['send_reminders' => false])->save();
    $this->artisan('fleet:reminders')->expectsOutputToContain('0 reminder(s) sent');
    $w['client']->forceFill(['send_reminders' => true])->save();
    $this->artisan('fleet:reminders')->expectsOutputToContain('1 reminder(s) sent');

    // Found late at day 45 (reminders were off): one reminder for the last step, not two.
    DB::table('reminder_log')->where('invoice_id', $bill->id)->delete();
    $this->travelTo($bill->due_date->copy()->addDays(45)->setTime(10, 0));
    $this->artisan('fleet:reminders')->expectsOutputToContain('1 reminder(s) sent');
    expect(DB::table('reminder_log')->where('invoice_id', $bill->id)->orderBy('step_days')->pluck('status', 'step_days')->all())->toBe([3 => 'skipped', 15 => 'skipped', 30 => 'sent'])
        ->and(mailbox()->last()->getSubject())->toContain('45 days overdue');

    // Read-only companies send nothing.
    DB::table('reminder_log')->where('invoice_id', $bill->id)->delete();
    $w['company']->forceFill(['status' => 'lapsed', 'read_only_since' => now()])->save();
    $this->artisan('fleet:reminders')->expectsOutputToContain('0 reminder(s) sent');
});
