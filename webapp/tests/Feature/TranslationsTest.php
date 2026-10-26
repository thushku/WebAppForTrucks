<?php

use App\Models\Account;
use App\Platform\Notifications\Notices;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;

// The screens in Hindi (M36). Every string the screens show through __() or
// trans_choice() has its Hindi in lang/hi.json, with the same placeholders
// and plural parts — a new string without its Hindi fails here.

/** Every __('…') and trans_choice('…') string in app/ and resources/views. */
function trStrings(): array
{
    $found = [];
    foreach ([app_path(), resource_path('views')] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/s", $src, $single);
            foreach ($single[1] as $s) {
                $found[stripcslashes(str_replace("\\'", "'", $s))] = true;
            }
            preg_match_all('/(?:__|trans_choice)\(\s*"((?:[^"\\\\]|\\\\.)*)"/s', $src, $double);
            foreach ($double[1] as $s) {
                if (! str_contains($s, '$')) {
                    $found[stripcslashes($s)] = true;
                }
            }
        }
    }

    return array_keys($found);
}

it('has every screen string in Hindi, with its placeholders and plural parts', function () {
    $hi = json_decode(file_get_contents(lang_path('hi.json')), true, flags: JSON_THROW_ON_ERROR);
    $placeholders = function (string $s): array {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $s, $m);
        sort($m[0]);

        return $m[0];
    };
    $missing = [];
    $broken = [];
    foreach (trStrings() as $en) {
        if (! isset($hi[$en]) || trim($hi[$en]) === '') {
            $missing[] = $en;
            continue;
        }
        if ($placeholders($en) !== $placeholders($hi[$en]) || substr_count($en, '|') !== substr_count($hi[$en], '|')) {
            $broken[] = $en;
        }
    }

    expect($missing)->toBe([])->and($broken)->toBe([]);
});

it('shows the screens in Hindi to someone who chose it, and tells each reader in their own language', function () {
    $w = phoneWorld();
    signInTo($w['company'], 'owner', ['locale' => 'hi']);
    $this->get(route('trips.index'))->assertOk()->assertSee('lang="hi"', false)->assertSeeText(__('Reset filters', [], 'hi'))->assertDontSeeText('Reset filters');

    // A trip sent: Ravi (Hindi) and Suresh (English) each read it in their own language.
    app(CompanyContext::class)->set($w['company']);
    $ravi = phoneActivate($w['driver']->mobile, $w['codes']['driver']);
    $suresh = phoneActivate($w['co']->mobile, $w['codes']['co'], 'android-0000-0002');
    app(CompanyContext::class)->set($w['company']);
    Account::where('mobile', $w['driver']->mobile)->update(['locale' => 'hi']);
    app(Notices::class)->tripSent($w['trip']->fresh());
    $titles = UserNotification::where('kind', 'trip.sent')->get()->mapWithKeys(fn ($n) => [Account::find($n->account_id)->mobile => $n->title]);
    expect($titles[$w['driver']->mobile])->toBe(__('New trip :number', ['number' => $w['trip']->trip_number], 'hi'))
        ->and($titles[$w['co']->mobile])->toBe('New trip ' . $w['trip']->trip_number)
        ->and(__('New trip :number', [], 'hi'))->not->toBe('New trip :number');

    // The phone apps get their answers in the person's language too.
    phoneReset();
    $owner = makeAccount(['name' => 'Anil Sharma', 'locale' => 'hi']);
    joinCompany($owner, $w['company'], 'owner');
    $this->withHeaders(['Authorization' => 'Bearer ' . $owner->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/trucks')->assertOk()->assertJsonPath('trucks.0.status_label', __('Available', [], 'hi'));
});
