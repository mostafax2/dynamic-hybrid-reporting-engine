{{--
  x-kpi-widget
  Variables: $report, $kpis, $cols, $color, $theme, $isRtl, $widgetId, $resolvedTitle, $showTitle
--}}
@php
    $dir        = $isRtl ? 'rtl' : 'ltr';
    $accentColor = $color ?? '#0077A8';
    $gridClass  = match((int)$cols) {
        1 => $theme === 'tailwind' ? 'grid-cols-1' : 'row-cols-1',
        2 => $theme === 'tailwind' ? 'grid-cols-2' : 'row-cols-2',
        3 => $theme === 'tailwind' ? 'grid-cols-3' : 'row-cols-3',
        default => $theme === 'tailwind' ? 'grid-cols-4' : 'row-cols-4',
    };
@endphp

<div id="{{ $widgetId }}" class="dhr-widget dhr-kpi-widget" dir="{{ $dir }}">

    @if($showTitle && $resolvedTitle)
        @if($theme === 'tailwind')
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">{{ $resolvedTitle }}</h3>
        @else
            <h6 class="text-muted text-uppercase small fw-semibold mb-3">{{ $resolvedTitle }}</h6>
        @endif
    @endif

    @if($theme === 'tailwind')
    <div class="grid {{ $gridClass }} gap-4">
        @forelse($kpis as $kpi)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col gap-1"
             style="border-top: 3px solid {{ $accentColor }}">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $kpi['label'] }}</span>
            <span class="text-3xl font-black text-gray-900" style="font-family: 'JetBrains Mono', monospace; color: {{ $accentColor }}">
                {{ is_numeric($kpi['value']) ? number_format((float)$kpi['value'], 2) : $kpi['value'] }}
            </span>
        </div>
        @empty
        <div class="col-span-{{ $cols }} text-center text-gray-400 py-6">
            @include('reporting-engine::partials._empty')
        </div>
        @endforelse
    </div>

    @else
    {{-- Bootstrap --}}
    <div class="row {{ $gridClass }} g-3">
        @forelse($kpis as $kpi)
        <div class="col">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid {{ $accentColor }} !important">
                <div class="card-body">
                    <div class="text-uppercase text-muted small fw-semibold mb-1">{{ $kpi['label'] }}</div>
                    <div class="fw-black" style="font-size:2rem;font-family:monospace;color:{{ $accentColor }}">
                        {{ is_numeric($kpi['value']) ? number_format((float)$kpi['value'], 2) : $kpi['value'] }}
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center text-muted py-4">
            @include('reporting-engine::partials._empty')
        </div>
        @endforelse
    </div>
    @endif
</div>
