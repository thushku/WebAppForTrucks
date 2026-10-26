{{-- The letterhead and the document's title block. $seller from DocumentData::letterhead(); $docTitle; $docLines (lines under the title). --}}
<div class="head">
  <div>
    @if (! empty($seller['logo']))<img src="{{ $seller['logo'] }}" alt="" style="max-height:48px; display:block; margin-bottom:6px">@endif
    <h1>{{ $seller['legal_name'] ?: $seller['name'] }}</h1>
    <div class="muted">{{ implode(', ', $seller['address']) }}</div>
    @if ($seller['tax_id'])<div class="muted">{{ $seller['tax_label'] }}: {{ $seller['tax_id'] }}</div>@endif
    @if ($seller['phone'] || $seller['email'])<div class="muted">{{ collect([$seller['phone'], $seller['email']])->filter()->join(' · ') }}</div>@endif
  </div>
  <div class="title">
    <h2>{{ $docTitle }}</h2>
    @foreach ($docLines as $line)<div class="muted">{{ $line }}</div>@endforeach
  </div>
</div>
