<?php

use App\Models\CompanyUser;
use App\Platform\Devices\Device;
use App\Platform\Notifications\NotificationSettings;
use App\Platform\Notifications\Notifier;
use App\Platform\Notifications\Push\PushGateway;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// Notification priorities and each person's choices (M26, 13-parity #15,
// #29): high notices first and loudest; a group switched off reaches only
// the inbox; quiet hours let only high notices ring the phone.

/** Pushes kept instead of sent. */
function nsPushes(): object
{
    $fake = new class implements PushGateway
    {
        public array $sent = [];

        public function send(string $token, array $message): string
        {
            $this->sent[] = ['token' => $token] + $message;

            return self::SENT;
        }
    };
    app()->instance(PushGateway::class, $fake);

    return $fake;
}

it('orders the inbox by priority and pushes high notices loudest', function () {
    $push = nsPushes();
    $w = invWorld();
    app(CompanyContext::class)->set($w['company']);
    Device::create(['account_id' => $w['owner']->id, 'device_id' => 'owner-phone', 'platform' => 'android', 'app_kind' => 'owner', 'push_token' => 'fcm-owner', 'activated_at' => now()]);
    $notifier = app(Notifier::class);
    $notifier->tell([$w['owner']->id], 'pay.paid', 'Pay run paid');
    $notifier->tell([$w['owner']->id], 'approvals.waiting', '2 entries wait for your approval');
    $notifier->tell([$w['owner']->id], 'road.incident', 'Incident on 261025-ACC-COK-BOM-01');

    expect(UserNotification::for($w['owner'])->orderBy('id')->pluck('priority')->all())->toBe(['low', 'medium', 'high'])
        ->and(collect($push->sent)->pluck('priority')->all())->toBe(['low', 'medium', 'high']);
    $this->get(route('notifications.index'))->assertOk()
        ->assertSeeInOrder(['Incident on 261025-ACC-COK-BOM-01', '2 entries wait for your approval', 'Pay run paid'])->assertSeeText('Urgent');
});

it('keeps a person’s choices: groups off for the phone, quiet hours for all but high notices', function () {
    $push = nsPushes();
    $w = invWorld();
    app(CompanyContext::class)->set($w['company']);
    Device::create(['account_id' => $w['owner']->id, 'device_id' => 'owner-phone', 'platform' => 'android', 'app_kind' => 'owner', 'push_token' => 'fcm-owner', 'activated_at' => now()]);

    $this->get(route('notifications.settings'))->assertOk()->assertSeeText('Entries to approve')->assertSeeText('Quiet hours')->assertSee('value="papers" class="size-5 rounded border-ink-300" checked', false);
    $groups = array_keys(NotificationSettings::GROUPS);
    $this->put(route('notifications.settings.update'), ['push' => array_values(array_diff($groups, ['papers'])), 'quiet_from' => '22:00'])->assertSessionHasErrors('quiet_to');
    $this->put(route('notifications.settings.update'), ['push' => array_values(array_diff($groups, ['papers'])), 'quiet_from' => '22:00', 'quiet_to' => '06:00'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(CompanyUser::where('account_id', $w['owner']->id)->first()->notification_settings)->toBe(['muted' => ['papers'], 'quiet_from' => '22:00', 'quiet_to' => '06:00']);

    $notifier = app(Notifier::class);
    // Papers are off for the phone: the inbox has it, nothing rings.
    $notifier->tell([$w['owner']->id], 'papers.due', '2 papers need attention');
    // 11 pm: a medium notice waits in the inbox; a high one rings.
    $this->travelTo(CarbonImmutable::parse('2026-10-25 23:00', 'Asia/Kolkata'));
    app(CompanyContext::class)->set($w['company']);
    $notifier->tell([$w['owner']->id], 'approvals.waiting', '1 entry waits for your approval');
    $notifier->tell([$w['owner']->id], 'road.incident', 'Incident on the road');
    // 9 am: medium rings again.
    $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00', 'Asia/Kolkata'));
    app(CompanyContext::class)->set($w['company']);
    $notifier->tell([$w['owner']->id], 'quotation.accepted', 'ACC Limited accepted quotation QT/26-27/0001');

    expect(UserNotification::for($w['owner'])->orderBy('id')->pluck('push_result', 'kind')->all())
        ->toBe(['papers.due' => 'off', 'approvals.waiting' => 'quiet', 'road.incident' => 'sent', 'quotation.accepted' => 'sent'])
        ->and(collect($push->sent)->pluck('title')->all())->toBe(['Incident on the road', 'ACC Limited accepted quotation QT/26-27/0001']);

    // The choices are per company: in another company the same person keeps the defaults.
    $other = makeCompany(['name' => 'Second Roadlines']);
    joinCompany($w['owner'], $other, 'owner');
    app(CompanyContext::class)->set($other);
    app(Notifier::class)->tell([$w['owner']->id], 'papers.due', '1 paper needs attention');
    expect(UserNotification::for($w['owner'])->value('push_result'))->toBe('sent');
});

it('gives the phone apps the settings and each notice’s priority', function () {
    $w = invWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $settings = $this->getJson('/api/v1/notifications/settings')->assertOk()->json();
    expect(collect($settings['groups'])->pluck('push')->unique()->values()->all())->toBe([true])->and($settings['quiet_from'])->toBeNull();
    $this->putJson('/api/v1/notifications/settings', ['push' => ['trips', 'incidents'], 'quiet_from' => '21:30', 'quiet_to' => '21:30'])->assertStatus(422)->assertJsonValidationErrors('quiet_to');
    $saved = $this->putJson('/api/v1/notifications/settings', ['push' => ['trips', 'incidents'], 'quiet_from' => '13:00', 'quiet_to' => '14:00'])->assertOk()->json();
    expect(collect($saved['groups'])->where('push', true)->pluck('key')->all())->toBe(['trips', 'incidents'])->and($saved['quiet_to'])->toBe('14:00');

    app(CompanyContext::class)->set($w['company']);
    app(Notifier::class)->tell([$w['owner']->id], 'road.incident', 'Incident');
    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('items.0.priority', 'high');

    // Quiet hours that cross midnight, and ones that do not.
    expect(NotificationSettings::within('23:30', '22:00', '06:00'))->toBeTrue()->and(NotificationSettings::within('05:59', '22:00', '06:00'))->toBeTrue()
        ->and(NotificationSettings::within('06:00', '22:00', '06:00'))->toBeFalse()->and(NotificationSettings::within('13:30', '13:00', '14:00'))->toBeTrue()
        ->and(NotificationSettings::within('14:00', '13:00', '14:00'))->toBeFalse();
});
