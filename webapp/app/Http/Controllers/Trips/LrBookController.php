<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\Branch;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\LrBook;
use App\Modules\Trips\Models\LrBookLeaf;
use App\Modules\Trips\Services\LrBooks;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Invoices & LR → LR books (M21): the register of pre-printed books, each
 * book's leaves (used, cancelled, lost, free) and the gaps, marking a spoilt
 * or lost leaf, closing a finished book.
 */
class LrBookController extends Controller
{
    public function __construct(private CompanyContext $context, private LrBooks $books) {}

    public function index(): View
    {
        $books = LrBook::withCount(['consignments', 'leaves'])->orderByRaw("status = 'active' desc")->orderByDesc('issued_on')->orderByDesc('id')->get();

        return view('lr-books.index', ['books' => $books, 'branches' => Branch::where('active', true)->orderBy('name')->pluck('name', 'id'),
            'today' => now($this->context->company()->timezone ?: 'UTC')->toDateString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'book_no' => ['required', 'string', 'max:30', Rule::unique('lr_books', 'book_no')->where('company_id', $companyId)],
            'prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\/\-]*$/'],
            'first_no' => ['required', 'integer', 'min:1', 'max:999999999'],
            'last_no' => ['required', 'integer', 'min:1', 'max:999999999'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'issued_to' => ['nullable', 'string', 'max:120'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $book = $this->books->add($data, $request->user());

        return redirect()->route('lr-books.show', $book)->with('status', __('LR book :book added: :n leaves (:range).', ['book' => $book->book_no, 'n' => $book->size(), 'range' => $book->rangeLabel()]));
    }

    public function show(LrBook $book): View
    {
        return view('lr-books.show', ['book' => $book, 'report' => $this->books->report($book)]);
    }

    public function mark(Request $request, LrBook $book): RedirectResponse
    {
        $data = $request->validate(['leaf_no' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(array_keys(LrBookLeaf::STATUSES))], 'reason' => ['required', 'string', 'min:3', 'max:255']]);
        $this->books->mark($book, (int) $data['leaf_no'], $data['status'], $data['reason'], $request->user());

        return back()->with('status', __('Leaf :no marked :status.', ['no' => $book->prefix . $data['leaf_no'], 'status' => mb_strtolower(__(LrBookLeaf::STATUSES[$data['status']]))]));
    }

    public function unmark(LrBook $book, LrBookLeaf $leaf): RedirectResponse
    {
        abort_unless($leaf->lr_book_id === $book->id, 404);
        $leaf->delete();
        app(\App\Platform\Audit\AuditRecorder::class)->record('lr_book.leaf_restored', "LR leaf {$book->prefix}{$leaf->leaf_no} of book {$book->book_no} free again", 'lr_book', (string) $book->id, $book->book_no, severity: 'notable');

        return back()->with('status', __('Leaf :no is free again.', ['no' => $book->prefix . $leaf->leaf_no]));
    }

    public function status(Request $request, LrBook $book): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(LrBook::STATUSES))]]);
        $book->forceFill(['status' => $data['status']])->save();

        return back()->with('status', $data['status'] === 'closed' ? __('Book :book closed: no more LRs from it.', ['book' => $book->book_no]) : __('Book :book is in use again.', ['book' => $book->book_no]));
    }
}
