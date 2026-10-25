<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\BookLock;
use App\Modules\Accounts\Services\Books;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Locking the books (blueprint 07 §1): nothing dated on or before the lock
 * date changes any more — late entries and reversals land on the first open
 * day. Unlocking needs a reason; both are kept and audited.
 */
class LockController extends AccountsController
{
    public function edit(Books $books): View
    {
        return view('accounts.lock', ['lockedUntil' => $books->lockedUntil(), 'history' => BookLock::with('author:id,name')->orderByDesc('id')->limit(20)->get(), 'today' => $this->today()]);
    }

    public function update(Request $request, Books $books): RedirectResponse
    {
        $data = $request->validate([
            'locked_until' => ['nullable', 'date_format:Y-m-d', 'before:' . $this->today()->addDay()->toDateString()],
            'reason' => ['nullable', 'string', 'max:255', 'required_without:locked_until'],
        ]);
        $until = isset($data['locked_until']) ? CarbonImmutable::parse($data['locked_until']) : null;
        $current = $books->lockedUntil();
        if ($current && (! $until || $until->lessThan($current)) && mb_strlen(trim((string) ($data['reason'] ?? ''))) < 5) {
            return back()->withErrors(['reason' => __('Opening locked months again needs a reason.')]);
        }
        $books->lock($until, $data['reason'] ?? null, $request->user()->id);

        return back()->with('status', $until ? __('The books are locked up to :date.', ['date' => $until->format('d M Y')]) : __('The books are unlocked.'));
    }
}
