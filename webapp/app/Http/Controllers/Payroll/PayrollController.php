<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayRuns;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Payroll (M16, 13-parity #24): the pay runs, one run with its payslips,
 * and a printable payslip. See PayRuns for the rules.
 */
class PayrollController extends Controller
{
    public function __construct(private CompanyContext $context, private PayRuns $runs) {}

    public function index(): View
    {
        $today = CarbonImmutable::now($this->context->company()->timezone ?: 'UTC');
        $last = $today->subMonthNoOverflow();

        return view('payroll.index', [
            'runs' => PayRun::orderByDesc('period_to')->orderByDesc('id')->paginate(30),
            'suggest' => ['from' => $last->startOfMonth()->toDateString(), 'to' => $last->endOfMonth()->toDateString()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_from' => ['required', 'date_format:Y-m-d'], 'period_to' => ['required', 'date_format:Y-m-d'],
            'scope' => ['required', Rule::in(array_keys(PayRun::SCOPES))], 'notes' => ['nullable', 'string', 'max:255'],
        ]);
        ['run' => $run, 'skipped' => $skipped] = $this->runs->create(CarbonImmutable::parse($data['period_from']), CarbonImmutable::parse($data['period_to']), $data['scope'], $request->user(), $data['notes'] ?? null);

        return redirect()->route('payroll.show', $run)->with('status', trans_choice(':count payslip made. Check them, add bonuses or recoveries, then approve.|:count payslips made. Check them, add bonuses or recoveries, then approve.', $run->people))
            ->with('skipped', $skipped);
    }

    public function show(PayRun $run): View
    {
        $slips = $run->payslips()->with('person:id,name,type,mobile,pay_basis')->get();

        return view('payroll.show', [
            'run' => $run, 'slips' => $slips, 'withoutPay' => $run->status === 'draft' ? $this->runs->withoutPay($run->scope) : collect(),
            'owed' => $run->status === 'approved' ? $slips->where('status', 'pending')->mapWithKeys(fn ($s) => [$s->id => $this->runs->owed($s->person)]) : collect(),
            'today' => CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString(),
        ]);
    }

    public function recalculate(PayRun $run): RedirectResponse
    {
        $this->runs->recalculate($run);

        return back()->with('status', __('Worked out again from the latest trips and rates.'));
    }

    public function addLine(Request $request, PayRun $run, Payslip $slip): RedirectResponse
    {
        abort_unless($slip->pay_run_id === $run->id, 404);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['addition', 'deduction'])],
            'type' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
        ]);
        $this->runs->addLine($slip, $data['kind'], $data['type'], (string) ($data['label'] ?? ''), (float) $data['amount']);

        return back()->with('status', __('Line added.'));
    }

    public function removeLine(PayRun $run, Payslip $slip, int $index): RedirectResponse
    {
        abort_unless($slip->pay_run_id === $run->id, 404);
        $this->runs->removeLine($slip, $index);

        return back()->with('status', __('Line removed.'));
    }

    public function removeSlip(PayRun $run, Payslip $slip): RedirectResponse
    {
        abort_unless($slip->pay_run_id === $run->id, 404);
        $this->runs->removeSlip($slip);

        return back()->with('status', __('Left out of this pay run.'));
    }

    public function approve(Request $request, PayRun $run): RedirectResponse
    {
        $this->runs->approve($run, $request->user());

        return back()->with('status', __('Approved. The pay is now owed to each person; mark each payslip paid when you pay.'));
    }

    public function pay(Request $request, PayRun $run, Payslip $slip): RedirectResponse
    {
        abort_unless($slip->pay_run_id === $run->id, 404);
        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(Payslip::PAY_MODES))], 'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:tomorrow'],
            'reference' => ['nullable', 'string', 'max:60'], 'with_dues' => ['nullable', 'boolean'],
        ]);
        $slip = $this->runs->pay($slip, $data['mode'], CarbonImmutable::parse($data['paid_on'], $this->context->company()->timezone ?: 'UTC'), $data['reference'] ?? null, $request->boolean('with_dues'), $request->user());

        return back()->with('status', __(':name paid :amount.', ['name' => $slip->person->name, 'amount' => \App\Support\Money::company($slip->paid_amount)]));
    }

    public function cancel(Request $request, PayRun $run): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $this->runs->cancel($run, $data['reason']);

        return redirect()->route('payroll.index')->with('status', __('Pay run cancelled.'));
    }

    /** A payslip to print or save as PDF from the browser. */
    public function slip(PayRun $run, Payslip $slip): View
    {
        abort_unless($slip->pay_run_id === $run->id, 404);
        $slip->load('person');

        return view('payroll.slip', ['run' => $run, 'slip' => $slip, 'company' => $this->context->company()]);
    }
}
