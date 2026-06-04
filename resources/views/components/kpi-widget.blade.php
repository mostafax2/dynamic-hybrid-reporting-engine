{{--
  x-kpi-widget
  Variables: $report, $kpis, $cols, $color, $variant, $theme, $isRtl, $widgetId, $resolvedTitle, $showTitle

  Variants (pass as variant="…"):
    flat (default)  — white card, colored top border
    gradient        — soft tinted background from accent color
    dark            — near-black card, glowing accent number
    glass           — frosted glass on gradient backdrop
    minimal         — no card box, oversized typography
    bold            — solid accent fill, white text
--}}
@php
    $dir    = $isRtl ? 'rtl' : 'ltr';
    $color  = $color ?? '#0077A8';
    $safe   = in_array($variant, ['flat','gradient','dark','glass','minimal','bold'])
              ? $variant : 'flat';
@endphp

{{-- ── Scoped styles ─────────────────────────────────────────────── --}}
<style>
/* ── Layout ──────────────────────────────────────────────────────── */
#{{ $widgetId }} {
    --kpi-color: {{ $color }};
    font-family: inherit;
}
#{{ $widgetId }} .kpi-title {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .12em;
    margin-bottom: .85rem;
    color: #6b7280;
    transition: color .25s;
}
#{{ $widgetId }} .kpi-grid {
    display: grid;
    grid-template-columns: repeat({{ $cols }}, 1fr);
    gap: 1rem;
}
@media (max-width: 640px) {
    #{{ $widgetId }} .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 380px) {
    #{{ $widgetId }} .kpi-grid { grid-template-columns: 1fr; }
}
#{{ $widgetId }} .kpi-card {
    padding: 1.25rem 1.4rem;
    transition: background .3s ease, border-color .3s ease,
                box-shadow .3s ease, color .3s ease, transform .2s ease;
}
#{{ $widgetId }} .kpi-card:hover { transform: translateY(-2px); }
#{{ $widgetId }} .kpi-label {
    display: block;
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .1em;
    margin-bottom: .3rem;
    transition: color .25s;
}
#{{ $widgetId }} .kpi-value {
    display: block;
    font-size: 2rem;
    font-weight: 900;
    font-family: 'JetBrains Mono', ui-monospace, 'Courier New', monospace;
    line-height: 1.1;
    transition: color .25s;
}
#{{ $widgetId }} .kpi-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 2rem 0;
    color: #9ca3af;
    font-size: .9rem;
}

/* ── Variant: flat (default) ─────────────────────────────────────── */
#{{ $widgetId }}[data-variant="flat"] .kpi-card {
    background: #fff;
    border-top: 3px solid var(--kpi-color);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 0 0 1px rgba(0,0,0,.04);
}
#{{ $widgetId }}[data-variant="flat"] .kpi-label { color: #6b7280; }
#{{ $widgetId }}[data-variant="flat"] .kpi-value  { color: var(--kpi-color); }

/* ── Variant: gradient ───────────────────────────────────────────── */
#{{ $widgetId }}[data-variant="gradient"] .kpi-card {
    background: color-mix(in srgb, var(--kpi-color) 9%, #fff);
    border: 1px solid color-mix(in srgb, var(--kpi-color) 22%, #fff);
    border-radius: 12px;
}
#{{ $widgetId }}[data-variant="gradient"] .kpi-label {
    color: color-mix(in srgb, var(--kpi-color) 65%, #444);
}
#{{ $widgetId }}[data-variant="gradient"] .kpi-value { color: var(--kpi-color); }

/* ── Variant: dark ───────────────────────────────────────────────── */
#{{ $widgetId }}[data-variant="dark"] .kpi-card {
    background: #111827;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,.35);
}
#{{ $widgetId }}[data-variant="dark"] .kpi-title { color: #9ca3af; }
#{{ $widgetId }}[data-variant="dark"] .kpi-label { color: #9ca3af; }
#{{ $widgetId }}[data-variant="dark"] .kpi-value {
    color: var(--kpi-color);
    filter: brightness(1.25);
}

/* ── Variant: glass ──────────────────────────────────────────────── */
#{{ $widgetId }}[data-variant="glass"] {
    background: linear-gradient(
        135deg,
        var(--kpi-color) 0%,
        color-mix(in srgb, var(--kpi-color) 55%, #06090f) 100%
    );
    border-radius: 16px;
    padding: 1.4rem;
}
#{{ $widgetId }}[data-variant="glass"] .kpi-title { color: rgba(255,255,255,.65); }
#{{ $widgetId }}[data-variant="glass"] .kpi-card {
    background: rgba(255,255,255,.14);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255,255,255,.22);
    border-radius: 10px;
}
#{{ $widgetId }}[data-variant="glass"] .kpi-label { color: rgba(255,255,255,.72); }
#{{ $widgetId }}[data-variant="glass"] .kpi-value  { color: #fff; }

/* ── Variant: minimal ────────────────────────────────────────────── */
#{{ $widgetId }}[data-variant="minimal"] .kpi-card {
    background: transparent;
    border-bottom: 2px solid color-mix(in srgb, var(--kpi-color) 28%, transparent);
    border-radius: 0;
    padding-left: .25rem;
    padding-right: .25rem;
}
#{{ $widgetId }}[data-variant="minimal"] .kpi-label { color: #9ca3af; }
#{{ $widgetId }}[data-variant="minimal"] .kpi-value {
    color: #111827;
    font-size: 2.4rem;
}

/* ── Variant: bold ───────────────────────────────────────────────── */
#{{ $widgetId }}[data-variant="bold"] .kpi-card {
    background: var(--kpi-color);
    border-radius: 12px;
    box-shadow: 0 6px 20px color-mix(in srgb, var(--kpi-color) 42%, transparent);
}
#{{ $widgetId }}[data-variant="bold"] .kpi-label { color: rgba(255,255,255,.78); }
#{{ $widgetId }}[data-variant="bold"] .kpi-value  { color: #fff; }
</style>

{{-- ── Widget markup ────────────────────────────────────────────── --}}
<div id="{{ $widgetId }}"
     class="dhr-widget dhr-kpi-widget"
     data-variant="{{ $safe }}"
     dir="{{ $dir }}">

    @if($showTitle && $resolvedTitle)
        <p class="kpi-title">{{ $resolvedTitle }}</p>
    @endif

    <div class="kpi-grid">
        @forelse($kpis as $kpi)
            <div class="kpi-card">
                <span class="kpi-label">{{ $kpi['label'] }}</span>
                <span class="kpi-value">
                    {{ is_numeric($kpi['value'])
                        ? number_format((float) $kpi['value'], 2)
                        : $kpi['value'] }}
                </span>
            </div>
        @empty
            <div class="kpi-empty">
                @include('reporting-engine::partials._empty')
            </div>
        @endforelse
    </div>
</div>
