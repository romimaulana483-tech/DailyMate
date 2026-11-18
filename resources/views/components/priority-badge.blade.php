@props(['priority'])

@php
    $labels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
    $label = $labels[$priority] ?? ucfirst($priority);
@endphp

<span class="badge badge-priority-{{ $priority }}">{{ $label }}</span>
