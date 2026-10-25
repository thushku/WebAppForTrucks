<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the book screens share: the period asked for (this financial year to
 * today unless chosen), and CSV downloads that Excel opens as they are.
 */
abstract class AccountsController extends Controller
{
    public function __construct(protected CompanyContext $context) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: FiscalYear} */
    protected function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $company = $this->context->company();
        $today = $this->today();
        $fy = FiscalYear::containing($today, (int) ($company->fy_start_month ?: 4));

        return [
            isset($data['from']) ? CarbonImmutable::parse($data['from']) : $fy->start,
            isset($data['to']) ? CarbonImmutable::parse($data['to']) : $today,
            $fy,
        ];
    }

    /** Today in the company's time zone. */
    protected function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }

    /** @param iterable<array<int, mixed>> $rows */
    protected function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        abort_unless(auth()->user()->can('statement.export') || auth()->user()->can('accounts.view'), 403);

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads ₹ and names in any script
            fputcsv($out, $header);
            foreach ($rows as $row) {
                // A cell starting with = + - @ is a formula to a spreadsheet: quote it as text.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row));
            }
            fclose($out);
        }, str_replace(['/', '\\', '"'], '-', $filename) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
