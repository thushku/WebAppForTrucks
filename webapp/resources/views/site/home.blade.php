@extends('layouts.site')
@section('title', __('Truck fleet software with a crew app'))
@section('content')
{{-- Hero: the promise, the trial, and the product drawn as an office screen and a crew phone. --}}
<section class="site-hero">
  <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pb-28 pt-14 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:pb-36 lg:pt-20">
    <div>
      <span class="site-kicker"><x-icon name="sparkle" class="size-3.5" /> {{ __('For truck owners and transport companies') }}</span>
      <h1 class="mt-5 font-display text-4xl font-semibold leading-[1.08] sm:text-5xl lg:text-6xl">{{ __('Every trip, every rupee — proved from the road.') }}</h1>
      <p class="mt-5 max-w-xl text-lg text-white/80">{{ __('Plan the trip; your driver records fuel, tolls and payments on the phone with GPS, time and a photo — even without network. You approve, bill the client and the books post themselves.') }}</p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('signup') }}" class="btn-lg btn rounded-xl bg-road-400 font-semibold text-ink-900 hover:bg-road-500">{{ __('Start :days-day free trial', ['days' => $trialDays]) }} <x-icon name="arrow-right" /></a>
        <a href="{{ route('site.pricing') }}" class="btn-lg btn rounded-xl border border-white/25 text-white hover:bg-white/10">{{ __('See pricing') }}</a>
      </div>
      <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/75">
        @foreach ([__('No card needed'), __('Works offline on the road'), __('GST and UAE VAT ready'), __('Export your data any time')] as $point)
          <li class="flex items-center gap-1.5"><x-icon name="check" class="size-4 text-road-400" /> {{ $point }}</li>
        @endforeach
      </ul>
    </div>

    <div class="relative mx-auto h-[500px] w-full max-w-[580px]" aria-hidden="true">
      {{-- The office screen. --}}
      <div class="mock-window absolute end-0 top-0 w-[86%]">
        <div class="flex items-center gap-1.5 border-b border-ink-100 px-4 py-2.5"><span class="size-2.5 rounded-full bg-red-300"></span><span class="size-2.5 rounded-full bg-amber-300"></span><span class="size-2.5 rounded-full bg-emerald-300"></span><span class="ms-3 text-[11px] text-ink-400">{{ __('Dashboard') }}</span></div>
        <div class="grid grid-cols-3 gap-2 p-4">
          @foreach ([[__('Trips running'), '12'], [__('Entries to check'), '7'], [__('Profit this month'), '₹4.8 L']] as [$label, $value])
            <div class="rounded-xl border border-ink-100 p-2.5"><div class="text-[10px] text-ink-500">{{ $label }}</div><div class="mt-1 font-display text-lg font-semibold">{{ $value }}</div></div>
          @endforeach
        </div>
        <div class="mx-4 mb-4 overflow-hidden rounded-xl bg-brand-50">
          <svg viewBox="0 0 400 150" class="block h-36 w-full">
            <path d="M20 120 C 90 100, 110 40, 180 60 S 300 120, 380 30" fill="none" stroke="#2B6EDF" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 6" />
            <circle cx="20" cy="120" r="6" fill="#1D57C2" /><circle cx="380" cy="30" r="6" fill="#F59E0B" />
            <circle cx="245" cy="92" r="9" fill="white" stroke="#2B6EDF" stroke-width="3" />
            <text x="30" y="140" font-size="11" fill="#47546A">Kochi</text><text x="330" y="22" font-size="11" fill="#47546A">Mumbai</text>
          </svg>
        </div>
        <div class="space-y-2 px-4 pb-4 text-[11px]">
          @foreach ([['KL-07-AB-1234', 'Kochi → Mumbai', '₹38,200'], ['TN-38-CQ-4410', 'Chennai → Pune', '₹21,650']] as [$truck, $route, $profit])
            <div class="flex items-center justify-between rounded-lg bg-ink-50 px-3 py-2"><span class="font-semibold">{{ $truck }}</span><span class="text-ink-500">{{ $route }}</span><span class="font-semibold text-emerald-700">{{ $profit }}</span></div>
          @endforeach
        </div>
      </div>
      {{-- The crew phone. --}}
      <div class="mock-phone mock-float absolute -bottom-10 start-0 sm:-start-4">
        <div class="mock-phone-screen">
          <div class="bg-brand-700 px-4 pb-4 pt-5 text-white"><div class="text-[10px] opacity-80">{{ __('Trip') }} T-26-0141</div><div class="font-display text-base font-semibold">{{ __('Add fuel') }}</div></div>
          <div class="space-y-3 p-4 text-[11px]">
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-ink-500">{{ __('Amount') }}</div><div class="font-display text-xl font-semibold">₹9,500</div><div class="text-ink-500">100 L × ₹95 · HP Aluva</div></div>
            <div class="flex flex-wrap gap-1.5">
              <span class="rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">GPS ✓</span>
              <span class="rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">{{ __('Photo') }} ✓</span>
              <span class="rounded-full bg-amber-50 px-2 py-1 font-semibold text-amber-800">{{ __('Offline — saved') }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div class="grid h-16 place-items-center rounded-xl bg-ink-100 text-ink-500">{{ __('Bill') }}</div>
              <div class="grid h-16 place-items-center rounded-xl bg-ink-100 text-ink-500">{{ __('Meter') }}</div>
            </div>
            <div class="rounded-xl bg-brand-600 py-2.5 text-center font-semibold text-white">{{ __('Save entry') }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- The problems and how they go away. --}}
<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
  <div class="max-w-2xl site-reveal">
    <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ __('Where the money goes missing today') }}</h2>
    <p class="mt-3 text-ink-600">{{ __('Paper slips, phone calls and Excel sheets leave gaps. Each gap costs money every trip.') }}</p>
  </div>
  <div class="mt-10 grid gap-5 md:grid-cols-3">
    @foreach ([
      ['wallet', __('Cash to the driver, no proof back'), __('Advances become "expenses" nobody can check.'), __('Each entry comes with GPS, the phone’s time and a photo. Odd ones are flagged before you approve.')],
      ['receipt', __('Bills go out late, PODs get lost'), __('Money waits weeks because the paperwork is not ready.'), __('The LR, the POD photo and the bill are linked. A queue shows what is ready to bill and what is overdue.')],
      ['ledger', __('Accounts typed twice'), __('Trips in one place, books in Tally, nothing agrees.'), __('Every trip, bill and payment posts to the books once. Trial balance, P&L, GST data and a Tally export are ready.')],
    ] as [$icon, $problem, $pain, $fix])
      <article class="site-card site-reveal">
        <span class="grid size-10 place-items-center rounded-xl bg-red-50 text-overdue"><x-icon :name="$icon" class="size-5" /></span>
        <h3 class="mt-4 text-lg font-semibold">{{ $problem }}</h3>
        <p class="mt-1 text-sm text-ink-500">{{ $pain }}</p>
        <p class="mt-4 flex gap-2 border-t border-ink-100 pt-4 text-sm text-ink-800"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ $fix }}</p>
      </article>
    @endforeach
  </div>
</section>

{{-- How it works. --}}
<section class="site-slant bg-brand-50/70 py-24">
  <div class="mx-auto max-w-7xl px-4 sm:px-6">
    <h2 class="font-display text-3xl font-semibold sm:text-4xl site-reveal">{{ __('One trip, from plan to profit') }}</h2>
    <ol class="mt-10 grid gap-5 md:grid-cols-4">
      @foreach ([
        [__('Plan'), __('Truck, crew, loads and stops — full or part load, your truck or a hired one. Papers are checked before it leaves.')],
        [__('On the road'), __('The crew app records fuel, tolls, loading and delivery with GPS and photos. It works without network and sends later.')],
        [__('Check and settle'), __('You approve entries (two people for big amounts if you like), settle the crew’s cash and pay bata.')],
        [__('Bill and book'), __('LR and invoice with GST or VAT, receipts with TDS, and the profit of every trip and truck from the books.')],
      ] as $i => [$title, $text])
        <li class="site-card site-reveal relative">
          <span class="font-display text-5xl font-semibold text-brand-200">{{ $i + 1 }}</span>
          <h3 class="mt-2 text-lg font-semibold">{{ $title }}</h3>
          <p class="mt-1 text-sm text-ink-600">{{ $text }}</p>
        </li>
      @endforeach
    </ol>
  </div>
</section>

{{-- Features. --}}
<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <h2 class="max-w-xl font-display text-3xl font-semibold sm:text-4xl site-reveal">{{ __('Everything a transport office runs on') }}</h2>
    <a href="{{ route('site.features') }}" class="font-semibold text-brand-700 hover:underline">{{ __('All features') }} →</a>
  </div>
  <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($features as [$icon, $title, $text])
      <a href="{{ route('site.features') }}#{{ \Illuminate\Support\Str::slug($title) }}" class="site-card site-reveal block transition hover:-translate-y-0.5 hover:border-brand-200">
        <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon :name="$icon" class="size-5" /></span>
        <h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3>
        <p class="mt-1 text-sm text-ink-600">{{ $text }}</p>
      </a>
    @endforeach
  </div>
</section>

{{-- Built for India and the UAE. --}}
<section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6">
  <div class="grid gap-5 lg:grid-cols-[1.2fr_1fr]">
    <div class="site-card site-reveal bg-ink-900 text-white">
      <h2 class="font-display text-2xl font-semibold">{{ __('Made for how Indian and UAE fleets really work') }}</h2>
      <ul class="mt-5 grid gap-3 text-sm text-white/85 sm:grid-cols-2">
        @foreach ([__('GST for transport: forward charge or RCM, by state'), __('TDS on freight and hire (194C) with the right rate'), __('UAE VAT with TRN and emirates'), __('Lorry receipts with copies, in your own design'), __('FASTag, fuel cards and pump credit kept apart'), __('Hindi and English; more languages coming'), __('Drivers in several companies, one app'), __('A Tally export for your accountant')] as $point)
          <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-road-400" /> {{ $point }}</li>
        @endforeach
      </ul>
    </div>
    <div class="site-card site-reveal flex flex-col justify-between">
      <div>
        <h2 class="font-display text-2xl font-semibold">{{ __('Your data stays yours') }}</h2>
        <p class="mt-2 text-sm text-ink-600">{{ __('Every change is kept in an audit log, finalised bills are never edited, and you can download all your data. If your plan ends, nothing is deleted — it turns read-only until you renew.') }}</p>
      </div>
      <a href="{{ route('site.faq') }}" class="mt-6 font-semibold text-brand-700 hover:underline">{{ __('Questions? Read the FAQ') }} →</a>
    </div>
  </div>
</section>

{{-- The call to action. --}}
<section class="px-4 pb-24 sm:px-6">
  <div class="site-reveal mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-brand-700 px-6 py-14 text-center text-white sm:px-12" style="clip-path: polygon(0 6%, 100% 0, 100% 100%, 0 94%)">
    <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ __('Try it on your next trip') }}</h2>
    <p class="mx-auto mt-3 max-w-xl text-white/80">{{ __(':days days free, every feature, no card. Add a truck and a driver and send the first trip in ten minutes.', ['days' => $trialDays]) }}</p>
    <a href="{{ route('signup') }}" class="btn-lg btn mt-8 rounded-xl bg-road-400 font-semibold text-ink-900 hover:bg-road-500">{{ __('Start free trial') }} <x-icon name="arrow-right" /></a>
  </div>
</section>
@endsection
