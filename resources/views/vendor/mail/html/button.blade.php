@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php($brand = \App\Support\MailBranding::current())
@php($background = match ($color) {
    'error' => '#c62833',
    'success' => '#15803d',
    default => $brand['color'],
})
@php($text = $color === 'primary' ? $brand['on_color'] : '#ffffff')
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="button-cell" style="background-color: {{ $background }};">
<a href="{{ $url }}" class="button" target="_blank" rel="noopener" style="background-color: {{ $background }}; border-color: {{ $background }}; color: {{ $text }};">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
