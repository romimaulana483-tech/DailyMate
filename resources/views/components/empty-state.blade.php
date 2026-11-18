@props([
    'icon' => 'bi-inbox',
    'title' => 'Nothing here yet',
    'message' => null,
])

<div class="empty-state">
    <div class="empty-icon"><i class="bi {{ $icon }}"></i></div>
    <h6>{{ $title }}</h6>
    @if ($message)
        <p>{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
