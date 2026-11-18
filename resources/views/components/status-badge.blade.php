@props(['status'])

@php
    $labels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
    $label = $labels[$status] ?? ucfirst($status);
@endphp

<span class="badge badge-status-{{ $status }}">{{ $label }}</span>
