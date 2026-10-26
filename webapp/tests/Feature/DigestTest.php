<?php

use App\Models\CompanyUser;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Reports\Models\DigestDelivery;
use App\Platform\Notifications\NotificationSettings;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// The morning summary (M32): at 07:00 company time, yesterday's trips and
// money and what waits, by email and to the inbox, once; owners get it
// daily unless they choose otherwise; weekly on Mondays.

/** The emails sent so far (array mailer). */
function dgMails(): \Illuminate\Support\Collection
{
    return collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage());
}

it('emails the owner yesterday’s trips, money and what waits, at 7 in the morning, once', function () {
    config(['mail.default' => 'array']);
    $w = invWorld(loads: 2);
    [$one] = $w['loads'];
    // Yesterday (25 Oct): two trips ended; one billed and part paid; the other bill overdue since August.
    $this->post(route('invoices.store'), ['party_id' => $w['client']->id, 'loads' => [$one->id]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $bill = Invoice::latest('id')->first();
    $this->post(route('invoices.finalise', $bill))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 50000, 'mode' => 'upi', 'reference' => 'UPI 1',
        'narration' => 'Part payment', 'allocation' => 'manual', 'apply' => [$bill->id => 50000]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $bill->fresh()->forceFill(['due_date' => '2026-08-31'])->saveQuietly();
    $manager = makeAccount(['name' => 'Office Manager', 'email' => 'manager@fleet.test']);
    joinCompany($manager, $w['company'], 'manager');

    // 06:10 in Kochi: not yet. 07:10: the owner's daily summary (the manager has not chosen one).
    $this->travelTo(CarbonImmutable::parse('2026-10-26 06:10', 'Asia/Kolkata'));
    $this->artisan('fleet:digest')->assertSuccessful();
    expect(dgMails())->toHaveCount(0);
    $this->travelTo(CarbonImmutable::parse('2026-10-26 07:10', 'Asia/Kolkata'));
    $this->artisan('fleet:digest')->expectsOutput('1 summary email(s) sent.')->assertSuccessful();

    $mails = dgMails();
    expect($mails)->toHaveCount(1);
    $mail = $mails->first();
    $html = $mail->getHtmlBody();
    expect($mail->getTo()[0]->getAddress())->toBe($w['owner']->email)
        ->and($mail->getSubject())->toBe('Sharma Roadlines — Sunday 25 Oct: 2 trips ended · ₹50,000.00 received · ₹25,520.00 overdue')
        ->and($html)->toContain('Good morning, ' . $w['owner']->name)->and($html)->toContain('₹75,520.00')->and($html)->toContain('ACC Limited')
        ->and($html)->toContain('2,800 km')->and($html)->toContain(route('notifications.settings'));
    app(CompanyContext::class)->set($w['company']);
    expect(UserNotification::for($w['owner'])->where('kind', 'digest.ready')->value('title'))->toBe('Sunday 25 Oct: 2 trips ended · ₹50,000.00 received · ₹25,520.00 overdue')
        ->and(DigestDelivery::count())->toBe(1);

    // The hourly run again: nothing twice.
    $this->artisan('fleet:digest', ['--now' => true])->expectsOutput('0 summary email(s) sent.');
    expect(dgMails())->toHaveCount(1);
});

it('lets each person choose daily, weekly or none, on the web and in the apps', function () {
    config(['mail.default' => 'array']);
    $w = invWorld();
    $manager = makeAccount(['name' => 'Office Manager', 'email' => 'manager@fleet.test']);
    joinCompany($manager, $w['company'], 'manager');

    // The owner switches it off on the web; the manager asks for the weekly one from the phone.
    $this->get(route('notifications.settings'))->assertOk()->assertSeeText('Morning summary')->assertSee('name="digest" value="daily" class="size-4" checked', false);
    $groups = array_keys(NotificationSettings::GROUPS);
    $this->put(route('notifications.settings.update'), ['push' => $groups, 'digest' => 'off'])->assertSessionHasNoErrors();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $manager->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/notifications/settings')->assertOk()->assertJsonPath('digest', 'off')->assertJsonPath('digests.weekly', 'Monday mornings (last week)');
    $this->putJson('/api/v1/notifications/settings', ['push' => $groups, 'digest' => 'weekly'])->assertOk()->assertJsonPath('digest', 'weekly');
    $this->putJson('/api/v1/notifications/settings', ['push' => $groups, 'digest' => 'hourly'])->assertStatus(422);

    // Sunday 7 am: no one. Monday 7 am: the manager's weekly summary.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 07:05', 'Asia/Kolkata'));
    $this->artisan('fleet:digest')->expectsOutput('0 summary email(s) sent.');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:05', 'Asia/Kolkata'));
    $this->artisan('fleet:digest')->expectsOutput('1 summary email(s) sent.');
    $mail = dgMails()->first();
    expect($mail->getTo()[0]->getAddress())->toBe('manager@fleet.test')->and($mail->getSubject())->toStartWith('Sharma Roadlines — Last week:')
        ->and($mail->getHtmlBody())->toContain('26 Oct – 1 Nov 2026');
    app(CompanyContext::class)->set($w['company']);
    expect(CompanyUser::where('account_id', $manager->id)->first()->notification_settings['digest'])->toBe('weekly');
});
