{{-- A payslip to print or save as PDF from the browser's print dialog. --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $lines = collect($slip->lines ?? []);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Payslip') }} — {{ $slip->person->name }} — {{ $run->periodLabel() }}</title>
@vite(['resources/css/app.css'])
<style>@media print { .no-print { display: none !important; } body { background: white; } .sheet { box-shadow: none; border: 0; margin: 0; } }</style>
</head>
<body class="bg-ink-50 py-8">
<div class="sheet mx-auto max-w-2xl rounded-2xl border border-ink-100 bg-white p-8 shadow-sm">
  <div class="flex items-start justify-between gap-4 border-b border-ink-100 pb-4">
    <div>
      <div class="font-display text-xl font-semibold">{{ $company->legal_name ?: $company->name }}</div>
      <div class="text-sm text-ink-500">{{ collect([$company->address_line1, $company->city])->filter()->join(', ') }}</div>
    </div>
    <div class="text-end">
      <div class="font-display text-lg font-semibold">{{ __('Payslip') }}</div>
      <div class="text-sm text-ink-500">{{ $run->periodLabel() }}</div>
    </div>
  </div>

  <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
    <dt class="text-ink-500">{{ __('Name') }}</dt><dd class="font-medium">{{ $slip->person->name }}</dd>
    <dt class="text-ink-500">{{ __('Work') }}</dt><dd>{{ __(\App\Modules\People\Models\Person::TYPES[$slip->person->type] ?? $slip->person->type) }}</dd>
    @if ($slip->person->mobile)<dt class="text-ink-500">{{ __('Mobile') }}</dt><dd>{{ $slip->person->mobile }}</dd>@endif
    <dt class="text-ink-500">{{ __('Pay basis') }}</dt><dd>{{ $slip->basisLabel($m) }}</dd>
  </dl>

  <table class="mt-6 w-full text-sm">
    <tbody>
      <tr class="border-b border-ink-100"><td class="py-2">{{ __('Base pay') }}</td><td class="money py-2 text-end">{{ $m($slip->base_pay) }}</td></tr>
      @foreach ($lines->where('kind', 'addition') as $line)<tr class="border-b border-ink-100"><td class="py-2">{{ $line['label'] }}</td><td class="money py-2 text-end">{{ $m($line['amount']) }}</td></tr>@endforeach
      <tr class="border-b border-ink-100 font-semibold"><td class="py-2">{{ __('Gross pay') }}</td><td class="money py-2 text-end">{{ $m((float) $slip->base_pay + (float) $slip->additions) }}</td></tr>
      @foreach ($lines->where('kind', 'deduction') as $line)<tr class="border-b border-ink-100"><td class="py-2">{{ __('Less') }}: {{ $line['label'] }}</td><td class="money py-2 text-end">− {{ $m($line['amount']) }}</td></tr>@endforeach
      <tr class="text-base font-semibold"><td class="py-3">{{ __('Net pay') }}</td><td class="money py-3 text-end">{{ $m($slip->net) }}</td></tr>
    </tbody>
  </table>

  <p class="mt-4 text-sm text-ink-600">
    @if ($slip->status === 'paid')
      {{ __('Paid :amount on :date by :mode', ['amount' => $m($slip->paid_amount), 'date' => $slip->paid_at->format('d M Y'), 'mode' => __(\App\Modules\Payroll\Models\Payslip::PAY_MODES[$slip->paid_mode] ?? $slip->paid_mode)]) }}@if ($slip->paid_reference) ({{ $slip->paid_reference }})@endif.
      @if ((float) $slip->paid_amount > (float) $slip->net){{ __('This includes :amount of bata and other dues from trips.', ['amount' => $m((float) $slip->paid_amount - (float) $slip->net)]) }}@endif
    @elseif ($slip->status === 'cancelled')
      {{ __('This pay run was cancelled.') }}
    @else
      {{ $run->status === 'draft' ? __('Draft — not approved yet.') : __('Not paid yet.') }}
    @endif
  </p>

  <div class="mt-12 grid grid-cols-2 gap-8 text-sm text-ink-500">
    <div class="border-t border-ink-200 pt-2">{{ __('Received by') }}</div>
    <div class="border-t border-ink-200 pt-2 text-end">{{ __('For :company', ['company' => $company->name]) }}</div>
  </div>
</div>
<p class="no-print mt-6 text-center"><button type="button" class="btn-primary" data-print>{{ __('Print') }}</button></p>
@vite(['resources/js/app.js'])
</body>
</html>
