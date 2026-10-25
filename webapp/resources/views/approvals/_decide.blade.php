{{-- Approve, ask or reject one entry (the inbox and the trip timeline). The note opens with "Ask / reject". --}}
<div class="flex flex-wrap items-start justify-end gap-1.5">
  @if (in_array('approve', $actions, true))
    <form method="POST" action="{{ route('approvals.decide', $event) }}">
      @csrf
      <input type="hidden" name="decision" value="approve">
      <button class="btn-secondary px-2.5 py-1.5 text-xs text-paid"><x-icon name="check" class="size-3.5" /> {{ $event->status === 'waiting_2nd' ? __('Approve (2nd + check)') : __('Approve') }}</button>
    </form>
  @endif
  <details class="text-start">
    <summary class="btn-ghost cursor-pointer list-none px-2.5 py-1.5 text-xs">{{ in_array('query', $actions, true) ? __('Ask / reject') : __('Reject') }}</summary>
    <form method="POST" action="{{ route('approvals.decide', $event) }}" class="mt-2 grid w-64 gap-2">
      @csrf
      <input name="note" class="input py-1.5 text-sm" maxlength="255" required placeholder="{{ __('Question or reason (the crew see it)') }}" aria-label="{{ __('Question or reason') }}">
      <div class="flex justify-end gap-1.5">
        @if (in_array('query', $actions, true))<button name="decision" value="query" class="btn-secondary px-2.5 py-1.5 text-xs">{{ __('Ask') }}</button>@endif
        <button name="decision" value="reject" class="btn-danger px-2.5 py-1.5 text-xs">{{ __('Reject') }}</button>
      </div>
    </form>
  </details>
</div>
