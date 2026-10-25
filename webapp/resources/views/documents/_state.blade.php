{{-- The expiry chip of one document: $document, $compliance. --}}
@php
  $state = $compliance->state($document);
  $days  = $document->daysLeft();
@endphp
@switch($state)
  @case('expired')<span class="pill bg-red-50 text-overdue">{{ trans_choice('Expired :count day ago|Expired :count days ago', -$days) }}</span>@break
  @case('due')<span class="pill bg-amber-50 text-partial">{{ $days === 0 ? __('Expires today') : trans_choice('Expires in :count day|Expires in :count days', $days) }}</span>@break
  @case('valid')<span class="pill bg-emerald-50 text-paid">{{ __('Valid until :date', ['date' => $document->expires_on->format('d M Y')]) }}</span>@break
  @default<span class="pill bg-ink-100 text-ink-500">{{ __('Does not expire') }}</span>
@endswitch
