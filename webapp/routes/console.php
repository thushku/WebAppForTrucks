<?php

use Illuminate\Support\Facades\Schedule;

// Old single-use links and spent codes are removed daily (they hold only hashes,
// but there is no reason to keep them).
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('password_tokens')->where('expires_at', '<', now()->subDays(7))->delete();
    \Illuminate\Support\Facades\DB::table('otp_challenges')->where('expires_at', '<', now()->subDays(7))->delete();
    \Illuminate\Support\Facades\DB::table('trusted_browsers')->where('expires_at', '<', now())->delete();
    app(\App\Platform\Auth\WebLoginQr::class)->prune();
})->daily()->name('prune-auth-tokens');

// Sanctum tokens past their expiry are removed daily.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
