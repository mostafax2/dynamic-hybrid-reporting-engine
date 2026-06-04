{{--
  Shared table partial used by report-widget, chart-widget (fallback), and livewire.
  Variables: $data (array), $columns (array|null), $theme, $isRtl, $sortCol (optional), $sortDir (optional), $livewire (bool)
--}}
@php
    $columns  = $columns ?? (!empty($data) ? array_keys($data[0]) : []);
    $sortCol  = $sortCol  ?? '';
    $sortDir  = $sortDir  ?? 'asc';
    $livewire = $livewire ?? false;
    $dir      = $isRtl ? 'rtl' : 'ltr';
@endphp

@if($theme === 'tailwind')
<div class="overflow-x-auto rounded-lg border border-gray-200" dir="{{ $dir }}">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                @foreach($columns as $col)
                <th scope="col"
                    class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wide text-xs whitespace-nowrap {{ $isRtl ? 'text-right' : 'text-left' }} {{ $livewire ? 'cursor-pointer select-none hover:bg-gray-100' : '' }}"
                    @if($livewire) wire:click="sort('{{ $col }}')" @endif>
                    {{ ucwords(str_replace('_', ' ', $col)) }}
                    @if($livewire && $sortCol === $col)
                        <span class="ml-1">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                    @endif
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            @forelse($data as $row)
            <tr class="hover:bg-gray-50 transition-colors">
                @foreach($columns as $col)
                <td class="px-4 py-3 text-gray-700 {{ $isRtl ? 'text-right' : 'text-left' }}">
                    {{ $row[$col] ?? '—' }}
                </td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($columns) }}" class="px-4 py-8 text-center text-gray-400">
                    @include('reporting-engine::partials._empty')
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@else
{{-- Bootstrap (default) --}}
<div class="table-responsive" dir="{{ $dir }}">
    <table class="table table-hover table-bordered table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                @foreach($columns as $col)
                <th scope="col"
                    class="fw-semibold text-uppercase small {{ $livewire ? 'cursor-pointer user-select-none' : '' }}"
                    @if($livewire) wire:click="sort('{{ $col }}')" @endif
                    style="{{ $livewire ? 'cursor:pointer' : '' }}">
                    {{ ucwords(str_replace('_', ' ', $col)) }}
                    @if($livewire && $sortCol === $col)
                        <span>{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                    @endif
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
            <tr>
                @foreach($columns as $col)
                <td>{{ $row[$col] ?? '—' }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($columns) }}" class="text-center text-muted py-4">
                    @include('reporting-engine::partials._empty')
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif
