<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Accounts\AccountsController;
use App\Modules\Reports\Services\Reports;
use App\Modules\Road\Services\EventTypes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports (blueprint 04 §2): one page, a tab per report, the same period
 * for all (this financial year to today unless chosen), each one as CSV.
 */
class ReportController extends AccountsController
{
    public const TABS = [
        'trips' => 'Trip profit',
        'spending' => 'Road spending',
        'no_receipt' => 'Payments without receipt',
        'fuel' => 'Fuel and mileage',
        'drivers' => 'Drivers',
    ];

    public function index(Request $request, Reports $reports): View|StreamedResponse
    {
        [$from, $to, $fy] = $this->period($request);
        $tab = $request->validate(['tab' => ['nullable', Rule::in(array_keys(self::TABS))]])['tab'] ?? 'trips';
        $data = match ($tab) {
            'spending' => $reports->roadSpending($from, $to),
            'no_receipt' => $reports->withoutReceipt($from, $to),
            'fuel' => $reports->fuel($from, $to),
            'drivers' => $reports->drivers($from, $to),
            default => $reports->tripProfit($from, $to),
        };

        if ($request->query('format') === 'csv') {
            abort_unless($request->user()->canAny(['report.export', 'statement.export']), 403);

            return $this->export($tab, $data, $from->format('Ymd') . '-' . $to->format('Ymd'));
        }

        return view('reports.index', ['tab' => $tab, 'from' => $from, 'to' => $to, 'fy' => $fy, 'data' => $data]);
    }

    private function export(string $tab, $data, string $span): StreamedResponse
    {
        return match ($tab) {
            'spending' => $this->csv('Road-spending-' . $span, array_merge([__('Category'), __('Entries')], array_map(fn ($m) => __(EventTypes::PAYMENT_MODES[$m][0]), $data['modes']), [__('Total')]),
                $data['rows']->map(fn ($r) => array_merge([$r['category'], $r['count']], array_map(fn ($m) => $r[$m], $data['modes']), [$r['total']]))),
            'no_receipt' => $this->csv('Payments-without-receipt-' . $span, [__('Date'), __('Person'), __('Trip'), __('Authority'), __('Reason'), __('Place'), __('Amount'), __('Status')],
                $data['events']->map(fn ($e) => [$e->event_at?->format('d-m-Y H:i'), $e->person?->name, $e->trip?->trip_number, $e->data['authority'] ?? '', $e->data['reason'] ?? '', $e->data['place'] ?? '', (float) $e->amount, $e->status])),
            'fuel' => $this->csv('Fuel-and-mileage-' . $span, [__('Truck'), __('Fills'), __('Litres'), __('Fuel cost'), __('Trips'), __('Km'), __('Km per litre'), __('Standard'), __('Fuel per km')],
                $data->map(fn ($r) => [$r->truck->reg_no, $r->fills, $r->litres, $r->cost, $r->trips, $r->km, $r->kmpl, $r->standard, $r->per_km])),
            'drivers' => $this->csv('Drivers-' . $span, [__('Driver'), __('Trips'), __('Km'), __('Hours at the wheel'), __('Money entries'), __('Rejected'), __('Without receipt')],
                $data->map(fn ($r) => [$r->person?->name, $r->trips, $r->km, round($r->minutes / 60, 1), $r->entries, $r->rejected, $r->without_receipt])),
            default => $this->csv('Trip-profit-' . $span, [__('Trip'), __('Vehicle'), __('Route'), __('Ended'), __('Income'), __('Cost'), __('Profit'), __('Km'), __('Profit per km'), __('Margin %')],
                $data->map(fn ($r) => [$r->trip->trip_number, $r->trip->vehicleLabel(), $r->route, $r->trip->ended_at?->format('d-m-Y'), $r->income, $r->cost, $r->profit, $r->km, $r->per_km, $r->margin])),
        };
    }
}
