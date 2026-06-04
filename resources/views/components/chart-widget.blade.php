{{--
  x-chart-widget
  Variables: $report, $result, $chartConfig (JSON string|null), $height, $theme, $isRtl, $widgetId, $resolvedTitle, $showTitle
--}}
@php
    $dir     = $isRtl ? 'rtl' : 'ltr';
    $columns = (!empty($result?->data)) ? array_keys($result->data[0]) : [];
@endphp

<div id="{{ $widgetId }}" class="dhr-widget dhr-chart-widget" dir="{{ $dir }}">

    @if($theme === 'tailwind')
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

        @if($showTitle && $resolvedTitle)
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">{{ $resolvedTitle }}</h3>
        </div>
        @endif

        <div class="p-5">
            {{-- SSR Table fallback — always present (SEO / no-JS) --}}
            <div data-dhr-chart-fallback="{{ $widgetId }}">
                @if($result && !empty($result->data))
                    @include('reporting-engine::partials._table', ['data' => $result->data, 'columns' => $columns, 'theme' => $theme, 'isRtl' => $isRtl])
                @else
                    <div class="py-8 text-center">@include('reporting-engine::partials._empty')</div>
                @endif
            </div>

            {{-- Chart canvas — hidden until Chart.js activates it --}}
            @if($chartConfig)
            <canvas id="canvas-{{ $widgetId }}"
                    data-dhr-chart="{{ $widgetId }}"
                    data-config="{{ $chartConfig }}"
                    height="{{ $height }}"
                    style="display:none;max-height:{{ $height }}px">
            </canvas>
            @endif
        </div>
    </div>

    @else
    <div class="card shadow-sm border-0 rounded-3">

        @if($showTitle && $resolvedTitle)
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-semibold">{{ $resolvedTitle }}</h6>
        </div>
        @endif

        <div class="card-body">
            <div data-dhr-chart-fallback="{{ $widgetId }}">
                @if($result && !empty($result->data))
                    @include('reporting-engine::partials._table', ['data' => $result->data, 'columns' => $columns, 'theme' => $theme, 'isRtl' => $isRtl])
                @else
                    <div class="text-center text-muted py-4">@include('reporting-engine::partials._empty')</div>
                @endif
            </div>

            @if($chartConfig)
            <canvas id="canvas-{{ $widgetId }}"
                    data-dhr-chart="{{ $widgetId }}"
                    data-config="{{ $chartConfig }}"
                    height="{{ $height }}"
                    style="display:none;max-height:{{ $height }}px">
            </canvas>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- Progressive enhancement: activate Chart.js if available --}}
@if($chartConfig)
@once
<script>
(function () {
    function activateCharts() {
        if (typeof Chart === 'undefined') return;
        document.querySelectorAll('[data-dhr-chart]').forEach(function (canvas) {
            var id       = canvas.dataset.dhrChart;
            var fallback = document.querySelector('[data-dhr-chart-fallback="' + id + '"]');
            try {
                var config = JSON.parse(canvas.dataset.config);
                if (fallback) fallback.style.display = 'none';
                canvas.style.display = 'block';
                new Chart(canvas, config);
            } catch (e) { /* config parse error — leave fallback visible */ }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activateCharts);
    } else {
        activateCharts();
    }
    // Also re-run after Livewire/Alpine updates
    document.addEventListener('livewire:update', activateCharts);
})();
</script>
@endonce
@endif
