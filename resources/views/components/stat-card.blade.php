@props([
    'label',
    'value',
    'icon' => 'bi-graph-up',
    'tone' => 'primary',
    'hint' => null,
])

<div class="stat-card">
    <div class="stat-top">
        <div>
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
        </div>
        <span class="stat-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
    </div>
    @if ($hint)
        <p class="stat-hint">{{ $hint }}</p>
    @endif
</div>
