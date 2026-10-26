<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\TruckEmi;
use App\Modules\Fleet\Services\TruckLoans;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trucks → EMIs (M35): every truck with a loan — its EMI, the next one due,
 * any overdue, what is still owed — and "EMI paid" as a payment voucher.
 */
class EmiController extends Controller
{
    public function __construct(private CompanyContext $context, private TruckLoans $loans) {}

    public function index(): View
    {
        $today = $this->today();
        $trucks = Truck::inBranch()->where('status', '!=', 'sold')->where('emi_amount', '>', 0)->whereNotNull('emi_day')->orderBy('reg_no')->get();

        return view('trucks.emis', [
            'today' => $today,
            'rows' => $trucks->map(fn (Truck $t) => ['truck' => $t] + $this->loans->status($t, $today)),
            'history' => TruckEmi::with(['truck:id,reg_no', 'voucher:id,number'])->whereIn('truck_id', $trucks->pluck('id'))->orderByDesc('paid_on')->orderByDesc('id')->limit(24)->get(),
            'banks' => CompanyBankAccount::where('active', true)->orderByDesc('is_default')->get(),
            'without' => Truck::inBranch()->where('status', '!=', 'sold')->whereNotNull('financier')->where(fn ($q) => $q->whereNull('emi_amount')->orWhereNull('emi_day'))->count(),
        ]);
    }

    public function pay(Request $request, Truck $truck): RedirectResponse
    {
        $emi = $this->loans->pay($truck, $request->validate($this->loans->rules(), ['reference.required_if' => __('Give the UTR or cheque number.')]), $request->user());

        return redirect()->route('trucks.emis')->with('status', __('EMI of :truck for :month recorded (:number).', ['truck' => $truck->reg_no, 'month' => $emi->due_on->translatedFormat('F Y'), 'number' => $emi->voucher?->number]));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
