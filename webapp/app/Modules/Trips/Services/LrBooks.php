<?php

namespace App\Modules\Trips\Services;

use App\Models\Account;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\LrBook;
use App\Modules\Trips\Models\LrBookLeaf;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Pre-printed LR books (M21, blueprint 10 §3). A typed LR number is taken
 * only if it is a leaf of a book in use, not on another load, and not
 * cancelled or lost; it is kept as the book's prefix and the leaf number
 * (leading zeros dropped), so "a-01005" and "A-1005" are the same leaf.
 * Loads without a typed number keep the automatic LR series.
 */
class LrBooks
{
    public function __construct(private AuditRecorder $audit) {}

    /** @param array<string, mixed> $fields */
    public function add(array $fields, Account $by): LrBook
    {
        $prefix = strtoupper(trim((string) ($fields['prefix'] ?? '')));
        $first = (int) $fields['first_no'];
        $last = (int) $fields['last_no'];
        if ($last < $first) {
            throw ValidationException::withMessages(['last_no' => __('The last leaf comes after the first.')]);
        }
        if ($last - $first >= 5000) {
            throw ValidationException::withMessages(['last_no' => __('A book has at most 5,000 leaves; add a large stock as several books.')]);
        }
        $overlap = LrBook::where('prefix', $prefix)->where('first_no', '<=', $last)->where('last_no', '>=', $first)->first();
        if ($overlap) {
            throw ValidationException::withMessages(['first_no' => __('These leaves overlap book :book (:range).', ['book' => $overlap->book_no, 'range' => $overlap->rangeLabel()])]);
        }

        $book = LrBook::create(['prefix' => $prefix, 'first_no' => $first, 'last_no' => $last, 'status' => 'active', 'created_by' => $by->id]
            + collect($fields)->only(['book_no', 'branch_id', 'issued_to', 'issued_on', 'notes'])->all());
        $this->audit->record('lr_book.added', "LR book {$book->book_no} ({$book->rangeLabel()}) issued to " . ($book->issued_to ?: '—'), 'lr_book', (string) $book->id, $book->book_no);

        return $book;
    }

    /** The book and leaf a typed number belongs to, or null. */
    public function find(string $typed): ?array
    {
        $typed = self::normalise($typed);
        foreach (LrBook::orderByRaw('length(prefix) desc')->get() as $book) {
            $prefix = $book->prefix;
            if ($prefix !== '' && ! str_starts_with($typed, $prefix)) {
                continue;
            }
            $rest = substr($typed, strlen($prefix));
            if ($rest === '' || ! ctype_digit($rest)) {
                continue;
            }
            $leaf = (int) $rest;
            if ($leaf >= $book->first_no && $leaf <= $book->last_no) {
                return [$book, $leaf];
            }
        }

        return null;
    }

    /** Put a typed LR number on a load — only a free leaf of a book in use. */
    public function claim(Consignment $consignment, string $typed): void
    {
        $found = $this->find($typed);
        if (! $found) {
            throw ValidationException::withMessages(['lr_number' => __('LR :no is not a leaf of any LR book. Add the book under Invoices & LR → LR books, or leave the number empty for the next automatic one.', ['no' => self::normalise($typed)])]);
        }
        [$book, $leaf] = $found;
        $number = $book->prefix . $leaf;
        if ($book->status !== 'active') {
            throw ValidationException::withMessages(['lr_number' => __('LR book :book is closed.', ['book' => $book->book_no])]);
        }
        $marked = LrBookLeaf::where('lr_book_id', $book->id)->where('leaf_no', $leaf)->first();
        if ($marked) {
            throw ValidationException::withMessages(['lr_number' => __('Leaf :no was marked :status: :reason', ['no' => $number, 'status' => mb_strtolower(__(LrBookLeaf::STATUSES[$marked->status])), 'reason' => $marked->reason])]);
        }
        $used = Consignment::with('trip:id,trip_number')->where('lr_number', $number)->where('id', '!=', $consignment->id ?? 0)->first();
        if ($used) {
            throw ValidationException::withMessages(['lr_number' => __('LR :no is already used (:trip).', ['no' => $number, 'trip' => $used->trip?->label ?? __('another load')])]);
        }
        $consignment->forceFill(['lr_number' => $number, 'lr_book_id' => $book->id, 'lr_date' => $consignment->lr_date ?? now()->toDateString()])->save();
    }

    /** A leaf that will carry no load: spoilt (cancelled) or lost — with why. */
    public function mark(LrBook $book, int $leaf, string $status, string $reason, Account $by): LrBookLeaf
    {
        if ($leaf < $book->first_no || $leaf > $book->last_no) {
            throw ValidationException::withMessages(['leaf_no' => __('Leaf :no is not in this book (:range).', ['no' => $leaf, 'range' => $book->rangeLabel()])]);
        }
        if (Consignment::where('lr_book_id', $book->id)->where('lr_number', $book->prefix . $leaf)->exists()) {
            throw ValidationException::withMessages(['leaf_no' => __('Leaf :no carries a load; cancel that LR instead.', ['no' => $book->prefix . $leaf])]);
        }
        if (LrBookLeaf::where('lr_book_id', $book->id)->where('leaf_no', $leaf)->exists()) {
            throw ValidationException::withMessages(['leaf_no' => __('Leaf :no is marked already.', ['no' => $book->prefix . $leaf])]);
        }
        $row = LrBookLeaf::create(['lr_book_id' => $book->id, 'leaf_no' => $leaf, 'status' => $status, 'reason' => mb_substr($reason, 0, 255), 'marked_by' => $by->id]);
        $this->audit->record("lr_book.leaf_{$status}", "LR leaf {$book->prefix}{$leaf} of book {$book->book_no} marked {$status}", 'lr_book', (string) $book->id, $book->book_no, reason: $reason, severity: 'notable');

        return $row;
    }

    /**
     * Every leaf of a book and what became of it — used (with the load),
     * cancelled, lost or free — and the gaps: free leaves below the highest
     * used one, where an LR may have been written and never entered.
     *
     * @return array{leaves: Collection, used: int, cancelled: int, lost: int, free: int, gaps: Collection}
     */
    public function report(LrBook $book): array
    {
        $used = Consignment::with('trip:id,trip_number')->where('lr_book_id', $book->id)->get(['id', 'trip_id', 'lr_number', 'lr_date', 'status'])
            ->keyBy(fn ($c) => (int) substr($c->lr_number, strlen($book->prefix)));
        $marked = $book->leaves()->get()->keyBy('leaf_no');
        $top = $used->keys()->max() ?? 0;
        $leaves = collect(range($book->first_no, $book->last_no))->map(fn (int $n) => (object) [
            'no' => $n, 'label' => $book->prefix . $n,
            'state' => isset($used[$n]) ? 'used' : ($marked[$n]->status ?? ($n < $top ? 'gap' : 'free')),
            'load' => $used[$n] ?? null, 'mark' => $marked[$n] ?? null,
        ]);

        return [
            'leaves' => $leaves, 'used' => $used->count(), 'cancelled' => $marked->where('status', 'cancelled')->count(), 'lost' => $marked->where('status', 'lost')->count(),
            'free' => $leaves->whereIn('state', ['free', 'gap'])->count(), 'gaps' => $leaves->where('state', 'gap')->pluck('label')->values(),
        ];
    }

    public static function normalise(string $typed): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($typed)));
    }
}
