@props(['rows' => []])
@foreach ($rows as $label => $value)
@continue(blank($value))
{{ $label }}: {{ $value }}
@endforeach
