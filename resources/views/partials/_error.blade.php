{{-- Variables: $message (string) --}}
@if(config('app.debug'))
<div role="alert" style="padding:.75rem 1rem;border-radius:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:.85rem;font-family:monospace">
    ⚠ DHR: {{ $message ?? 'An error occurred.' }}
</div>
@endif
