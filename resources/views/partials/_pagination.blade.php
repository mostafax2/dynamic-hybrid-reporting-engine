{{--
  Variables: $page, $perPage, $total, $lastPage, $theme, $isRtl, $livewire (bool)
--}}
@php
    $livewire = $livewire ?? false;
    $dir      = $isRtl ? 'rtl' : 'ltr';
    $from     = (($page - 1) * $perPage) + 1;
    $to       = min($page * $perPage, $total);
@endphp

@if($lastPage <= 1)
    @php return; @endphp
@endif

@if($theme === 'tailwind')
<div class="flex items-center justify-between px-1 py-3 text-sm text-gray-600" dir="{{ $dir }}">
    <span>
        {{ __('Showing') }} {{ $from }}–{{ $to }} {{ __('of') }} {{ $total }}
    </span>
    <div class="flex gap-1">
        @if($page > 1)
            @if($livewire)
                <button wire:click="goToPage({{ $page - 1 }})" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">‹</button>
            @else
                <a href="{{ request()->fullUrlWithQuery(['dhr_page' => $page - 1]) }}" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">‹</a>
            @endif
        @endif

        @foreach(range(max(1, $page - 2), min($lastPage, $page + 2)) as $p)
            @if($livewire)
                <button wire:click="goToPage({{ $p }})"
                        class="px-3 py-1 rounded border {{ $p === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50' }}">
                    {{ $p }}
                </button>
            @else
                <a href="{{ request()->fullUrlWithQuery(['dhr_page' => $p]) }}"
                   class="px-3 py-1 rounded border {{ $p === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50' }}">
                    {{ $p }}
                </a>
            @endif
        @endforeach

        @if($page < $lastPage)
            @if($livewire)
                <button wire:click="goToPage({{ $page + 1 }})" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">›</button>
            @else
                <a href="{{ request()->fullUrlWithQuery(['dhr_page' => $page + 1]) }}" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">›</a>
            @endif
        @endif
    </div>
</div>

@else
{{-- Bootstrap --}}
<div class="d-flex justify-content-between align-items-center mt-2 small text-muted" dir="{{ $dir }}">
    <span>{{ __('Showing') }} {{ $from }}–{{ $to }} {{ __('of') }} {{ $total }}</span>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                @if($livewire)
                    <button wire:click="goToPage({{ $page - 1 }})" class="page-link">‹</button>
                @else
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['dhr_page' => $page - 1]) }}">‹</a>
                @endif
            </li>

            @foreach(range(max(1, $page - 2), min($lastPage, $page + 2)) as $p)
            <li class="page-item {{ $p === $page ? 'active' : '' }}">
                @if($livewire)
                    <button wire:click="goToPage({{ $p }})" class="page-link">{{ $p }}</button>
                @else
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['dhr_page' => $p]) }}">{{ $p }}</a>
                @endif
            </li>
            @endforeach

            <li class="page-item {{ $page >= $lastPage ? 'disabled' : '' }}">
                @if($livewire)
                    <button wire:click="goToPage({{ $page + 1 }})" class="page-link">›</button>
                @else
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['dhr_page' => $page + 1]) }}">›</a>
                @endif
            </li>
        </ul>
    </nav>
</div>
@endif
