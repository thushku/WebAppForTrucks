<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Services\Registers;
use App\Modules\Accounts\Services\TallyExport;
use App\Modules\Accounts\Services\TaxReturns;
use App\Modules\Tax\Models\TaxRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The accountant's working lists and the files for others: ageing of what
 * clients owe and what we owe truck suppliers, the GST return tables
 * (GSTR-1, a GSTR-3B working) or the UAE VAT 201 boxes, the TDS registers,
 * and the Tally import file. Everything also downloads as CSV.
 */
class ReportsController extends AccountsController
{
    public function ageing(Request $request, Registers $registers): View|StreamedResponse
    {
        $f = $request->validate(['tab' => ['nullable', Rule::in(['receivables', 'payables'])], 'basis' => ['nullable', Rule::in(['due', 'bill'])], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $tab = $f['tab'] ?? 'receivables';
        $basis = $f['basis'] ?? 'due';
        $asOf = isset($f['to']) ? CarbonImmutable::parse($f['to']) : $this->today();
        $rows = $tab === 'payables' ? $registers->payables($asOf) : $registers->receivables($asOf, $basis);

        if ($request->query('format') === 'csv') {
            $out = [];
            foreach ($rows as $r) {
                $out[] = array_merge([$r['party']->name], array_map(fn ($k) => $r[$k], array_keys(Registers::SLABS)), [$r['total']]);
            }

            return $this->csv('Ageing-' . $tab . '-' . $asOf->format('Ymd'), array_merge([__('Name')], array_map('__', Registers::SLABS), [__('Total')]), $out);
        }

        return view('accounts.ageing', ['tab' => $tab, 'basis' => $basis, 'asOf' => $asOf, 'rows' => $rows]);
    }

    /** India: GSTR-1 tables and the GSTR-3B working for a GSTIN and month; UAE: the VAT 201 boxes. */
    public function tax(Request $request, TaxReturns $returns): View|StreamedResponse
    {
        $registrations = TaxRegistration::usable()->orderByDesc('is_default')->get();
        $f = $request->validate([
            'registration' => ['nullable', 'integer', Rule::in($registrations->pluck('id')->all())],
            'month' => ['nullable', 'date_format:Y-m'],
            'section' => ['nullable', Rule::in(['b2b', 'b2cl', 'b2cs', 'cdnr', 'cdnur', 'exemp', 'hsn', 'docs'])],
        ]);
        $registration = isset($f['registration']) ? $registrations->firstWhere('id', $f['registration']) : $registrations->first();
        $month = isset($f['month']) ? CarbonImmutable::parse($f['month'] . '-01') : $this->today()->startOfMonth();
        $india = $this->context->company()->country === 'IN';

        if (! $registration) {
            return view('accounts.tax', ['registration' => null, 'registrations' => $registrations, 'month' => $month, 'india' => $india]);
        }
        if (! $india) {
            return view('accounts.tax', ['registration' => $registration, 'registrations' => $registrations, 'month' => $month, 'india' => false,
                'vat' => $returns->vat201($registration, $month->startOfMonth(), $month->endOfMonth())]);
        }

        $gstr1 = $returns->gstr1($registration, $month);
        if ($section = $f['section'] ?? null) {
            [$header, $keys] = self::GSTR1_SHEETS[$section];

            return $this->csv('GSTR1-' . $registration->tax_id . '-' . $month->format('Y-m') . '-' . $section, $header,
                array_map(fn ($row) => array_map(fn ($k) => $row[$k] ?? '', $keys), $gstr1[$section]));
        }

        return view('accounts.tax', ['registration' => $registration, 'registrations' => $registrations, 'month' => $month, 'india' => true, 'gstr1' => $gstr1]);
    }

    public function tds(Request $request, Registers $registers): View|StreamedResponse
    {
        [$from, $to, $fy] = $this->period($request);
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['receivable', 'payable'])]])['tab'] ?? 'receivable';
        $rows = $tab === 'payable' ? $registers->tdsPayable($from, $to) : $registers->tdsReceivable($from, $to);

        if ($request->query('format') === 'csv') {
            return $tab === 'payable'
                ? $this->csv('TDS-deducted-' . $from->format('Ymd') . '-' . $to->format('Ymd'), [__('Quarter'), __('Supplier'), 'PAN', __('Challan'), __('Date'), __('Hire'), __('Section'), __('Rate %'), __('TDS'), __('No TDS because')],
                    $rows->map(fn ($r) => [$r->quarter, $r->party, $r->pan, $r->challan, $r->date->format('d-m-Y'), $r->hire, $r->section, $r->rate, $r->tds, $r->nil_reason]))
                : $this->csv('TDS-by-clients-' . $from->format('Ymd') . '-' . $to->format('Ymd'), [__('Quarter'), __('Client'), 'PAN', 'TAN', __('Bill'), __('Bill date'), __('Bill total'), __('TDS expected'), __('TDS deducted'), __('Section'), __('Receipt'), __('Receipt date')],
                    $rows->map(fn ($r) => [$r->quarter, $r->party, $r->pan, $r->tan, $r->invoice, CarbonImmutable::parse($r->issue_date)->format('d-m-Y'), (float) $r->bill_total, (float) $r->tds_expected, (float) $r->tds, $r->section, $r->receipt, CarbonImmutable::parse($r->receipt_date)->format('d-m-Y')]));
        }

        return view('accounts.tds', ['tab' => $tab, 'from' => $from, 'to' => $to, 'fy' => $fy, 'rows' => $rows]);
    }

    public function tally(Request $request, TallyExport $tally): Response
    {
        [$from, $to] = $this->period($request);
        $company = $this->context->company();
        app(\App\Platform\Audit\AuditRecorder::class)->record('data.exported', "Exported the books to Tally ({$from->toDateString()} to {$to->toDateString()})", 'company', (string) $company->id);

        return response($tally->xml($company->legal_name ?: $company->name, $from, $to), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Tally-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xml"',
        ]);
    }

    /** GSTR-1 sheet => [the offline tool's column headings, our keys]. */
    public const GSTR1_SHEETS = [
        'b2b' => [['GSTIN/UIN of Recipient', 'Receiver Name', 'Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply', 'Reverse Charge', 'Applicable % of Tax Rate', 'Invoice Type', 'E-Commerce GSTIN', 'Rate', 'Taxable Value', 'Cess Amount'],
            ['gstin', 'name', 'number', 'date', 'value', 'pos', 'rcm', 'applicable', 'type', 'ecom', 'rate', 'taxable', 'cess']],
        'b2cl' => [['Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount', 'E-Commerce GSTIN'],
            ['number', 'date', 'value', 'pos', 'applicable', 'rate', 'taxable', 'cess', 'ecom']],
        'b2cs' => [['Type', 'Place Of Supply', 'Rate', 'Applicable % of Tax Rate', 'Taxable Value', 'Cess Amount', 'E-Commerce GSTIN'],
            ['type', 'pos', 'rate', 'applicable', 'taxable', 'cess', 'ecom']],
        'cdnr' => [['GSTIN/UIN of Recipient', 'Receiver Name', 'Note Number', 'Note Date', 'Note Type', 'Place Of Supply', 'Reverse Charge', 'Note Supply Type', 'Note Value', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount'],
            ['gstin', 'name', 'number', 'date', 'type', 'pos', 'rcm', 'supply_type', 'value', 'applicable', 'rate', 'taxable', 'cess']],
        'cdnur' => [['UR Type', 'Note Number', 'Note Date', 'Note Type', 'Place Of Supply', 'Note Value', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount'],
            ['type', 'number', 'date', 'note_type', 'pos', 'value', 'applicable', 'rate', 'taxable', 'cess']],
        'exemp' => [['Description', 'Nil Rated Supplies', 'Exempted(other than nil rated/non GST supply)', 'Non-GST Supplies'], ['description', 'nil', 'exempt', 'non_gst']],
        'hsn' => [['HSN', 'Description', 'UQC', 'Total Quantity', 'Total Value', 'Rate', 'Taxable Value', 'Integrated Tax Amount', 'Central Tax Amount', 'State/UT Tax Amount', 'Cess Amount'],
            ['hsn', 'description', 'uqc', 'qty', 'value', 'rate', 'taxable', 'igst', 'cgst', 'sgst', 'cess']],
        'docs' => [['Nature of Document', 'Sr. No. From', 'Sr. No. To', 'Total Number', 'Cancelled'], ['nature', 'from', 'to', 'total', 'cancelled']],
    ];
}
