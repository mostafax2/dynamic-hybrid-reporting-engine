{{--
  livewire:report-widget
  Variables: $reportEntity, $result, $columns, $error, $theme, $isRtl, $resolvedTitle
  Livewire properties: $search, $filters, $sortCol, $sortDir, $page, $perPage, $showExport, $showFilters
--}}
@php $dir = $isRtl ? 'rtl' : 'ltr'; @endphp

<div class="dhr-widget dhr-livewire-widget" dir="{{ $dir }}" wire:loading.class="opacity-60">

    @if($theme === 'tailwind')
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 gap-3 flex-wrap">
            <h3 class="text-sm font-semibold text-gray-800">{{ $resolvedTitle }}</h3>

            <div class="flex items-center gap-2 flex-wrap">
                {{-- Search --}}
                @if($showFilters)
                <div class="relative">
                    <input type="search"
                           wire:model.live.debounce.400ms="search"
                           placeholder="{{ __('Search...') }}"
                           class="pl-3 pr-8 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-44">
                    <span class="absolute right-2 top-1.5 text-gray-400 text-xs">🔍</span>
                </div>
                @endif

                {{-- Export --}}
                @if($showExport && $reportEntity)
                    <x-reporting-engine::report-export :report="$reportEntity->id" theme="tailwind" :formats="['csv','xlsx']" />
                @endif

                {{-- Loading indicator --}}
                <span wire:loading class="text-xs text-gray-400">{{ __('Loading...') }}</span>
            </div>
        </div>

        {{-- Active filters --}}
        @if($showFilters && !empty($filters))
        <div class="px-5 py-2 bg-amber-50 border-b border-amber-100 flex flex-wrap gap-2 items-center">
            <span class="text-xs text-amber-700 font-medium">{{ __('Active filters:') }}</span>
            @foreach(array_filter($filters) as $field => $value)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full">
                {{ ucwords(str_replace('_',' ',$field)) }}: {{ $value }}
                <button wire:click="$set('filters.{{ $field }}', '')" class="ml-0.5 hover:text-red-600">×</button>
            </span>
            @endforeach
            <button wire:click="resetFilters" class="text-xs text-amber-700 underline ml-2">{{ __('Clear all') }}</button>
        </div>
        @endif

        {{-- Table --}}
        <div class="p-0">
            @if($error)
                @include('reporting-engine::partials._error', ['message' => $error])
            @elseif($result && !empty($result->data))
                @include('reporting-engine::partials._table', [
                    'data'    => $result->data,
                    'columns' => $columns,
                    'theme'   => $theme,
                    'isRtl'   => $isRtl,
                    'sortCol' => $sortCol,
                    'sortDir' => $sortDir,
                    'livewire'=> true,
                ])
            @else
                <div class="py-10 text-center">@include('reporting-engine::partials._empty')</div>
            @endif
        </div>

        {{-- Pagination --}}
        @if($result && $result->total > $result->perPage)
        <div class="px-5 border-t border-gray-100">
            @include('reporting-engine::partials._pagination', [
                'page'     => $result->page,
                'perPage'  => $result->perPage,
                'total'    => $result->total,
                'lastPage' => $result->lastPage,
                'theme'    => $theme,
                'isRtl'    => $isRtl,
                'livewire' => true,
            ])
        </div>
        @endif
    </div>

    @else
    {{-- Bootstrap --}}
    <div class="card shadow-sm border-0 rounded-3">

        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3 gap-2 flex-wrap">
            <h6 class="mb-0 fw-semibold">{{ $resolvedTitle }}</h6>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                @if($showFilters)
                <input type="search"
                       wire:model.live.debounce.400ms="search"
                       placeholder="{{ __('Search...') }}"
                       class="form-control form-control-sm"
                       style="width:180px">
                @endif
                @if($showExport && $reportEntity)
                    <x-reporting-engine::report-export :report="$reportEntity->id" theme="bootstrap" :formats="['csv','xlsx']" />
                @endif
                <span wire:loading class="text-muted small">{{ __('Loading...') }}</span>
            </div>
        </div>

        @if($showFilters && !empty(array_filter($filters ?? [])))
        <div class="card-body border-bottom bg-warning bg-opacity-10 py-2 px-3 d-flex flex-wrap gap-2 align-items-center">
            <small class="fw-medium text-warning-emphasis">{{ __('Active filters:') }}</small>
            @foreach(array_filter($filters) as $field => $value)
            <span class="badge bg-warning text-dark">
                {{ ucwords(str_replace('_',' ',$field)) }}: {{ $value }}
                <button wire:click="$set('filters.{{ $field }}', '')" class="btn-close btn-close-sm ms-1" style="font-size:.6rem"></button>
            </span>
            @endforeach
            <button wire:click="resetFilters" class="btn btn-link btn-sm text-warning-emphasis py-0">{{ __('Clear all') }}</button>
        </div>
        @endif

        <div class="card-body p-0">
            @if($error)
                @include('reporting-engine::partials._error', ['message' => $error])
            @elseif($result && !empty($result->data))
                @include('reporting-engine::partials._table', [
                    'data'    => $result->data,
                    'columns' => $columns,
                    'theme'   => $theme,
                    'isRtl'   => $isRtl,
                    'sortCol' => $sortCol,
                    'sortDir' => $sortDir,
                    'livewire'=> true,
                ])
            @else
                <div class="text-center text-muted py-5">@include('reporting-engine::partials._empty')</div>
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
                'livewire' => true,
            ])
        </div>
        @endif
    </div>
    @endif
</div>
