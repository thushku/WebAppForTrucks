{{-- The morning summary by email (M32). Inline styles only: mail apps drop style sheets. --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $muted = 'color:#64748b;font-size:13px;';
  $cell = 'padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;';
  $num = $cell . 'text-align:right;white-space:nowrap;font-weight:600;';
  $when = $period === 'weekly' ? __(':from – :to', ['from' => $d['from']->translatedFormat('j M'), 'to' => $d['to']->translatedFormat('j M Y')]) : $d['from']->translatedFormat('l j F Y');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $headline }}</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:16px 8px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
      <tr><td style="background:#1e40af;color:#ffffff;padding:20px 24px;">
        <div style="font-size:13px;opacity:.85;">{{ $company->name }}</div>
        <div style="font-size:20px;font-weight:bold;margin-top:4px;">{{ $period === 'weekly' ? __('Your week') : __('Your morning summary') }}</div>
        <div style="font-size:14px;margin-top:4px;opacity:.9;">{{ $when }}</div>
      </td></tr>
      <tr><td style="padding:20px 24px 4px;">
        <p style="margin:0 0 12px;font-size:15px;">{{ __('Good morning, :name.', ['name' => $name]) }} {{ $period === 'weekly' ? __('Here is how last week went.') : __('Here is how yesterday went.') }}</p>
      </td></tr>

      <tr><td style="padding:0 24px;">
        <h2 style="font-size:15px;margin:12px 0 4px;">{{ __('Trips') }}</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr><td style="{{ $cell }}">{{ __('Started') }}</td><td style="{{ $num }}">{{ $d['trips']['started'] }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Ended') }}</td><td style="{{ $num }}">{{ $d['trips']['ended'] }}@if ($d['trips']['km']) <span style="{{ $muted }}font-weight:normal;">· {{ number_format($d['trips']['km']) }} km</span>@endif</td></tr>
          <tr><td style="{{ $cell }}">{{ __('On the road now') }}</td><td style="{{ $num }}">{{ $d['trips']['running'] }}@if ($d['trips']['planned']) <span style="{{ $muted }}font-weight:normal;">· {{ __(':n planned', ['n' => $d['trips']['planned']]) }}</span>@endif</td></tr>
        </table>

        <h2 style="font-size:15px;margin:20px 0 4px;">{{ __('Money') }}</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr><td style="{{ $cell }}">{{ __('Billed') }} <span style="{{ $muted }}">({{ trans_choice(':count bill|:count bills', $d['money']['bills']) }})</span></td><td style="{{ $num }}">{{ $m($d['money']['billed']) }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Received') }} <span style="{{ $muted }}">({{ trans_choice(':count receipt|:count receipts', $d['money']['receipts']) }})</span></td><td style="{{ $num }}color:#047857;">{{ $m($d['money']['received']) }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Paid out: advances, pay, vendors') }}</td><td style="{{ $num }}">{{ $m($d['money']['paid']) }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Spent on the road') }}@if ($d['money']['spent_by'])<div style="{{ $muted }}">{{ collect($d['money']['spent_by'])->map(fn ($v, $k) => $k . ' ' . $m($v))->join(' · ') }}</div>@endif</td><td style="{{ $num }}">{{ $m($d['money']['spent']) }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Profit this month so far') }}</td><td style="{{ $num }}{{ $d['profit']['profit'] < 0 ? 'color:#b91c1c;' : '' }}">{{ $m($d['profit']['profit']) }}</td></tr>
        </table>

        <h2 style="font-size:15px;margin:20px 0 4px;">{{ __('Waiting for you') }}</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr><td style="{{ $cell }}">{{ __('Crew entries to approve') }}</td><td style="{{ $num }}">{{ $d['waiting']['approvals'] }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Trips ended, crew cash not settled') }}</td><td style="{{ $num }}">{{ $d['waiting']['to_settle'] }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('LRs delivered, POD not received') }}</td><td style="{{ $num }}">{{ $d['waiting']['pod_pending'] }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('LRs ready to bill') }}</td><td style="{{ $num }}">{{ $d['waiting']['ready_to_bill'] }}</td></tr>
          <tr><td style="{{ $cell }}">{{ __('Bills overdue') }} <span style="{{ $muted }}">({{ $d['waiting']['overdue'] }})</span></td><td style="{{ $num }}{{ $d['waiting']['overdue_amount'] > 0 ? 'color:#b91c1c;' : '' }}">{{ $m($d['waiting']['overdue_amount']) }}</td></tr>
          @if ($d['papers']['expired'] || $d['papers']['week'] || $d['papers']['missing'])
            <tr><td style="{{ $cell }}">{{ __('Papers') }}</td><td style="{{ $cell }}text-align:right;font-weight:600;color:#b45309;">{{ collect([$d['papers']['expired'] ? __(':n expired', ['n' => $d['papers']['expired']]) : null, $d['papers']['week'] ? __(':n due this week', ['n' => $d['papers']['week']]) : null, $d['papers']['missing'] ? __(':n missing', ['n' => $d['papers']['missing']]) : null])->filter()->join(' · ') }}</td></tr>
          @endif
        </table>

        @if ($d['overdue_clients'])
          <h2 style="font-size:15px;margin:20px 0 4px;">{{ __('Who owes the most, overdue') }}</h2>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            @foreach ($d['overdue_clients'] as $c)
              <tr><td style="{{ $cell }}">{{ $c['party'] }} <span style="{{ $muted }}">· {{ trans_choice(':count bill|:count bills', $c['bills']) }} · {{ trans_choice('oldest :count day late|oldest :count days late', $c['days']) }}</span></td><td style="{{ $num }}">{{ $m($c['amount']) }}</td></tr>
            @endforeach
          </table>
        @endif
      </td></tr>

      <tr><td style="padding:24px;" align="center">
        <a href="{{ route('dashboard') }}" style="display:inline-block;background:#1e40af;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;font-size:14px;">{{ __('Open the dashboard') }}</a>
        <p style="{{ $muted }}margin:16px 0 0;">{{ __('You get this because of My notifications → Morning summary. Change it there.') }} <a href="{{ route('notifications.settings') }}" style="color:#1e40af;">{{ __('My notifications') }}</a></p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
