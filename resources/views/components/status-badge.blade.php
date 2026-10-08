@props(['status'])
{{-- Older name, kept for existing callers: the prototype-style pill. --}}
<x-status-pill :status="$status" {{ $attributes }} />
