{{--
  x-report-widget
  Variables: $report, $result, $filterFields, $theme, $isRtl, $widgetId,
             $resolvedTitle, $showTitle, $showExport, $showFilters
--}}
@php
    $columns = (!empty($result?->data)) ? array_keys($result->data[0]) : [];
    $dir     = $isRtl ? 'rtl' : 'ltr';
@endphp

<div id="{{ $widgetId }}" class="dhr-widget dhr-report-widget" dir="{{ $dir }}"
     data-report="{{ $report?->id }}" data-theme="{{ $theme }}">

    {{-- Error: report not found --}}
    @unless($report)
        @include('reporting-engine::partials._error', ['message' => "Report '{$report}' not found."])
        @php return; @endphp
    @endunless

    {{-- Card wrapper --}}
    @if($theme === 'tailwind')
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

        @if($showTitle && $resolvedTitle)
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">{{ $resolvedTitle }}</h3>
            @if($showExport)
                <x-reporting-engine::report-export :report="$report->id" theme="tailwind" :formats="['csv','xlsx']" />
            @endif
        </div>
        @endif

        @if($showFilters && !empty($filterFields))
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
            @include('reporting-engine::partials._filter-form', compact('filterFields','theme','isRtl'))
        </div>
        @endif

        <div class="p-0">
            @if($result && !empty($result->data))
                @include('reporting-engine::partials._table', [
                    'data'    => $result->data,
                    'columns' => $columns,
                    'theme'   => $theme,
                    'isRtl'   => $isRtl,
                ])
            @else
                <div class="py-10 text-center">
                    @include('reporting-engine::partials._empty')
                </div>
            @endif
        </div>

        @if($result && $result->total > $result->perPage)
        <div class="px-5 border-t border-gray-100">
            @include('reporting-engine::partials._pagination', [
                'page'     => $result->page,
                'perPage'  => $result->perPage,
                'total'    => $result->total,
                'lastPage' => $result->lastPage,
                'theme'    => $theme,
                'isRtl'    => $isRtl,
                'livewire' => false,
            ])
        </div>
        @endif
    </div>

    @else
    {{-- Bootstrap --}}
    <div class="card shadow-sm border-0 rounded-3">

        @if($showTitle && $resolvedTitle)
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-semibold">{{ $resolvedTitle }}</h6>
            @if($showExport)
                <x-reporting-engine::report-export :report="$report->id" theme="bootstrap" :formats="['csv','xlsx']" />
            @endif
        </div>
        @endif

        @if($showFilters && !empty($filterFields))
        <div class="card-body border-bottom bg-light py-2 px-3">
            @include('reporting-engine::partials._filter-form', compact('filterFields','theme','isRtl'))
        </div>
        @endif

        <div class="card-body p-0">
            @if($result && !empty($result->data))
                @include('reporting-engine::partials._table', [
                    'data'    => $result->data,
                    'columns' => $columns,
                    'theme'   => $theme,
                    'isRtl'   => $isRtl,
                ])
            @else
                <div class="text-center text-muted py-5">
                    @include('reporting-engine::partials._empty')
                </div>
            @endif
        </div>

        @if($result && $result->total > $result->perPage)
        <div class="card-footer bg-white border-top px-3 py-2">
            @include('reporting-engine::partials._pagination', [
                'page'     => $result->page,
                'perPage'  => $result->perPage,
                'total'    => $result->total,
                'lastPage' => $result->lastPage,
                'theme'    => $theme,
                'isRtl'    => $isRtl,
                'livewire' => false,
            ])
        </div>
        @endif
    </div>
    @endif

    {{-- Execution metadata (debug only) --}}
    @if(config('app.debug') && $result?->metadata)
    <div style="font-size:.72rem;color:#888;padding:.4rem 0;text-align:{{ $isRtl ? 'right' : 'left' }}">
        {{ $result->metadata['execution_time_ms'] ?? 0 }}ms ·
        {{ $result->total }} rows ·
        {{ $result->metadata['cache_hit'] ? '⚡ cache' : '🗄 db' }}
    </div>
    @endif
</div>
