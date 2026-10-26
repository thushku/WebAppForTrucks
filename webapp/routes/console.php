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

// Document expiry reminders, early morning in India (07:00 IST) and the UAE (05:30 GST).
Schedule::command('documents:remind')->dailyAt('01:30')->name('documents-remind')->withoutOverlapping();

// Ended trials and plans turn read-only (data kept) — just after midnight, India time.
Schedule::command('fleet:subscriptions')->dailyAt('00:20')->timezone('Asia/Kolkata')->name('subscriptions-sweep')->withoutOverlapping();
// Overdue reminders by email (M18), each morning in India.
Schedule::command('fleet:reminders')->dailyAt('09:05')->timezone('Asia/Kolkata')->name('overdue-reminders')->withoutOverlapping();
// Companies whose 72-hour deletion countdown has ended (M17), every hour.
Schedule::command('fleet:deletions')->hourlyAt(35)->name('company-deletions')->withoutOverlapping();
// The morning summary (M32): every hour, for the companies where it is 07:00 now.
Schedule::command('fleet:digest')->hourlyAt(10)->name('morning-summary')->withoutOverlapping();
