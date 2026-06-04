{{--
  x-report-export
  Variables: $report, $links, $icons, $size, $theme, $isRtl
--}}
@if($report && !empty($links))
@php $dir = $isRtl ? 'rtl' : 'ltr'; @endphp

<div class="dhr-export-bar d-inline-flex gap-1" dir="{{ $dir }}">
    @foreach($links as $format => $url)
    <a href="{{ $url }}"
       target="_blank"
       rel="noopener noreferrer"
       title="{{ __('Export as :format', ['format' => strtoupper($format)]) }}"
       @if($theme === 'tailwind')
       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
       @else
       class="btn btn-outline-secondary btn-{{ $size }}"
       @endif>
        <span aria-hidden="true">{{ $icons[$format] ?? '📥' }}</span>
        <span>{{ strtoupper($format) }}</span>
    </a>
    @endforeach
</div>
@endif
