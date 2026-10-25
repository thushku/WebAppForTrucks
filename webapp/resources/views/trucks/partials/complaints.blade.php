@canany(['complaint.raise', 'complaint.manage'])
<form method="POST" action="{{ route('trucks.complaints.store', $truck) }}" class="card p-6" novalidate>
  @csrf
  <h3 class="font-semibold">{{ __('Raise a complaint') }}</h3>
  <div class="mt-4 grid gap-3 sm:grid-cols-4">
    <div class="sm:col-span-2"><label class="label" for="c_title">{{ __('What is wrong') }}</label><input id="c_title" name="title" class="input" value="{{ old('title') }}" maxlength="150" placeholder="{{ __('Brake noise, front left') }}" required>@error('title')<span class="error">{{ $message }}</span>@enderror</div>
    <div>
      <label class="label" for="c_cat">{{ __('Category') }}</label>
      <select id="c_cat" name="category" class="input"><option value="">—</option>@foreach ($categories as $code => $name)<option value="{{ $code }}" @selected(old('category') === $code)>{{ $name }}</option>@endforeach</select>
    </div>
    <div>
      <label class="label" for="c_pri">{{ __('Priority') }}</label>
      <select id="c_pri" name="priority" class="input">@foreach (\App\Modules\Fleet\Models\Complaint::PRIORITIES as $v => $label)<option value="{{ $v }}" @selected(old('priority', 'normal') === $v)>{{ __($label) }}</option>@endforeach</select>
    </div>
    <div class="sm:col-span-3"><label class="label" for="c_desc">{{ __('Details') }}</label><textarea id="c_desc" name="description" rows="2" class="input" maxlength="3000">{{ old('description') }}</textarea></div>
    <div><label class="label" for="c_odo">{{ __('Odometer (km)') }}</label><input id="c_odo" name="odometer_km" type="number" min="0" class="input" value="{{ old('odometer_km') }}"></div>
  </div>
  <div class="mt-4 flex justify-end"><button class="btn-primary"><x-icon name="plus" /> {{ __('Raise complaint') }}</button></div>
</form>
@endcanany

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Complaint') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Raised') }}</th></tr></thead>
      <tbody>
        @forelse ($complaints as $c)
          <tr class="{{ $c->isOpen() ? '' : 'opacity-60' }}">
            <td class="docno font-mono text-xs">{{ $c->number }}</td>
            <td><a href="{{ route('complaints.show', $c) }}" class="font-medium hover:text-brand-700">{{ $c->title }}</a></td>
            <td class="text-sm">{{ __(ucfirst($c->priority)) }}</td>
            <td><span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Fleet\Models\Complaint::STATUSES[$c->status]) }}</span></td>
            <td class="nowrap text-xs text-ink-500">{{ $c->created_at->format('d M Y') }}{{ $c->raisedBy ? ' · ' . $c->raisedBy->name : '' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ __('No complaints for this truck.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
