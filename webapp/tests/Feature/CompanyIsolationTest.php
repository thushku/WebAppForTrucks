<?php

use App\Platform\Audit\AuditLog;
use App\Platform\Authorization\Models\Role;
use App\Platform\Tenancy\CompanyContext;

// One shared database, many companies: no query may read across them.

it('refuses a company-scoped query with no company in context', function () {
    app(CompanyContext::class)->forget();

    expect(fn () => Role::count())->toThrow(RuntimeException::class, 'without a company in context');
});

it('only ever reads the current company\'s rows', function () {
    $a = makeCompany(['name' => 'Alpha']);
    $b = makeCompany(['name' => 'Bravo']);

    app(CompanyContext::class)->set($a);
    $alphaRoles = Role::pluck('company_id')->unique()->all();

    app(CompanyContext::class)->set($b);
    $bravoRoles = Role::pluck('company_id')->unique()->all();

    expect($alphaRoles)->toBe([$a->id])->and($bravoRoles)->toBe([$b->id]);
});

it('stamps the current company on new rows', function () {
    $a = makeCompany();
    app(CompanyContext::class)->set($a);

    $role = Role::create(['code' => 'dispatcher', 'name' => 'Dispatcher', 'level' => 3]);

    expect($role->company_id)->toBe($a->id);
});

it('keeps another company\'s dashboard closed even with its id in the session', function () {
    $mine   = makeCompany(['name' => 'Mine Transport']);
    $theirs = makeCompany(['name' => 'Theirs Cargo']);
    $me     = signInTo($mine);

    $this->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $theirs->id])
        ->get(route('dashboard'))
        ->assertOk()->assertSee('Mine Transport')->assertDontSee('Theirs Cargo');
});

it('keeps the audit log append-only', function () {
    $company = makeCompany();
    app(CompanyContext::class)->set($company);
    app(\App\Platform\Audit\AuditRecorder::class)->record('test.event', 'Something happened');

    $row = AuditLog::first();
    expect(fn () => $row->forceFill(['summary' => 'rewritten'])->save())->toThrow(LogicException::class);
    expect(fn () => $row->delete())->toThrow(LogicException::class);
});

it('never writes secrets into the audit log', function () {
    $company = makeCompany();
    app(CompanyContext::class)->set($company);
    $account = makeAccount();
    joinCompany($account, $company);

    $recorder = app(\App\Platform\Audit\AuditRecorder::class);
    expect($recorder->isRedacted('password_hash'))->toBeTrue()
        ->and($recorder->isRedacted('bank_account_number'))->toBeTrue()
        ->and($recorder->isRedacted('pin_hash'))->toBeTrue()
        ->and($recorder->isRedacted('name'))->toBeFalse();

    $logged = \Illuminate\Support\Facades\DB::table('audit_log')->pluck('change_set')->implode(' ');
    expect($logged)->not->toContain('$2y$');
});
