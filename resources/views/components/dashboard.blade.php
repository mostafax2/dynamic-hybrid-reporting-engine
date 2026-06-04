{{--
  x-dashboard
  Variables: $dashboard, $widgets (array), $cols, $theme, $isRtl
--}}
@php $dir = $isRtl ? 'rtl' : 'ltr'; @endphp

@unless($dashboard)
    @include('reporting-engine::partials._error', ['message' => 'Dashboard not found.'])
    @php return; @endphp
@endunless

<div class="dhr-dashboard" dir="{{ $dir }}" data-dashboard="{{ $dashboard->id }}">

    {{-- Dashboard header --}}
    @if($theme === 'tailwind')
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-900">{{ $dashboard->name }}</h2>
        @if($dashboard->description)
            <p class="text-sm text-gray-500 mt-1">{{ $dashboard->description }}</p>
        @endif
    </div>
    <div class="grid grid-cols-1 md:grid-cols-{{ min($cols, 3) }} gap-5">

    @else
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">{{ $dashboard->name }}</h4>
            @if($dashboard->description)
                <small class="text-muted">{{ $dashboard->description }}</small>
            @endif
        </div>
    </div>
    <div class="row g-4">
    @endif

    @forelse($widgets as $widget)
        @php
            $widgetType = $widget['type'] ?? 'table';
            $reportId   = $widget['report_id'];
            $title      = $widget['title'];
            $config     = $widget['config'] ?? [];
        @endphp

        @if($theme === 'tailwind')
        <div class="{{ in_array($widgetType, ['bar_chart','line_chart','pie_chart','area_chart']) && $cols > 1 ? 'col-span-2' : '' }}">
        @else
        <div class="col-12 col-md-{{ $cols > 1 ? '6' : '12' }}">
        @endif

            @if(in_array($widgetType, ['bar_chart','line_chart','pie_chart','area_chart','heatmap']))
                <x-reporting-engine::chart-widget
                    :report="$reportId"
                    :title="$title"
                    :theme="$theme"
                    :chart-type="str_replace(['_chart',''], ['',''], $widgetType)"
                    :height="$config['height'] ?? 280"
                />

            @elseif($widgetType === 'kpi_card')
                <x-reporting-engine::kpi-widget
                    :report="$reportId"
                    :title="$title"
                    :theme="$theme"
                    :cols="$config['cols'] ?? 3"
                    :color="$config['color'] ?? null"
                />

            @else
                <x-reporting-engine::report-widget
                    :report="$reportId"
                    :title="$title"
                    :theme="$theme"
                    :per-page="$config['per_page'] ?? 10"
                    :show-export="$config['show_export'] ?? false"
                />
            @endif

        </div>
    @empty
        @if($theme === 'tailwind')
        <div class="col-span-{{ $cols }} text-center py-12 text-gray-400">
        @else
        <div class="col-12 text-center py-5 text-muted">
        @endif
            @include('reporting-engine::partials._empty')
        </div>
    @endforelse

    </div>
</div>
