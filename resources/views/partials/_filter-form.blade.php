{{--
  Shared filter form partial.
  Variables: $filterFields (array), $theme, $isRtl, $action (optional, defaults to current URL)
--}}
@php
    $action = $action ?? request()->url();
    $dir    = $isRtl ? 'rtl' : 'ltr';
@endphp

@if(!empty($filterFields))
<form method="GET" action="{{ $action }}" dir="{{ $dir }}"
      class="{{ $theme === 'tailwind' ? 'flex flex-wrap gap-2 items-end' : 'd-flex flex-wrap gap-2 align-items-end' }}">

    {{-- Preserve non-dhr query params --}}
    @foreach(request()->except(array_column($filterFields, 'key')) as $qk => $qv)
        @unless(str_starts_with((string)$qk, 'dhr_'))
            <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
        @endunless
    @endforeach

    @foreach($filterFields as $field)
    <div class="{{ $theme === 'tailwind' ? 'flex flex-col gap-1' : 'flex-fill' }}" style="min-width:140px;max-width:220px">
        <label for="{{ $field['key'] }}"
               class="{{ $theme === 'tailwind' ? 'text-xs font-medium text-gray-600' : 'form-label small fw-medium mb-1' }}">
            {{ $field['label'] }}
        </label>
        <input
            type="{{ $field['type'] }}"
            id="{{ $field['key'] }}"
            name="{{ $field['key'] }}"
            value="{{ $field['current'] ?? '' }}"
            placeholder="{{ $field['label'] }}"
            class="{{ $theme === 'tailwind'
                ? 'border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
                : 'form-control form-control-sm' }}"
        >
    </div>
    @endforeach

    <div class="{{ $theme === 'tailwind' ? 'flex gap-2 mt-auto' : 'd-flex gap-1 mt-auto' }}">
        <button type="submit"
                class="{{ $theme === 'tailwind'
                    ? 'px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700'
                    : 'btn btn-primary btn-sm' }}">
            {{ __('Filter') }}
        </button>
        <a href="{{ request()->url() }}"
           class="{{ $theme === 'tailwind'
               ? 'px-3 py-1.5 border border-gray-300 text-sm rounded-md hover:bg-gray-50 text-gray-600'
               : 'btn btn-outline-secondary btn-sm' }}">
            {{ __('Reset') }}
        </a>
    </div>
</form>
@endif
