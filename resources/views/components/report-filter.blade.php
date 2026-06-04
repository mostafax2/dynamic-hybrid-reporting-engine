{{--
  x-report-filter
  Variables: $report, $fields, $inline, $action, $theme, $isRtl, $widgetId
--}}
@php $dir = $isRtl ? 'rtl' : 'ltr'; @endphp

<div id="{{ $widgetId }}" class="dhr-widget dhr-filter-widget" dir="{{ $dir }}">

    @unless($report)
        @include('reporting-engine::partials._error', ['message' => 'Report not found.'])
        @php return; @endphp
    @endunless

    @if(!empty($fields))
        @if(!$inline)
            @if($theme === 'tailwind')
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-600 mb-3">{{ __('Filters') }}</h3>
                @include('reporting-engine::partials._filter-form', compact('fields','theme','isRtl','action'))
            </div>
            @else
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-light border-bottom py-2 px-3">
                    <span class="fw-semibold small">{{ __('Filters') }}</span>
                </div>
                <div class="card-body py-2 px-3">
                    @include('reporting-engine::partials._filter-form', compact('fields','theme','isRtl','action'))
                </div>
            </div>
            @endif
        @else
            @include('reporting-engine::partials._filter-form', compact('fields','theme','isRtl','action'))
        @endif
    @endif
</div>
