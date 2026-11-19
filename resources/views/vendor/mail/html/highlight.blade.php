@props(['label', 'value'])
@php($brand = \App\Support\MailBranding::current())
<table class="highlight" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="highlight-cell" style="background-color: {{ $brand['soft'] }}; border: 1px solid {{ $brand['border'] }};">
<span class="highlight-label" style="color: {{ $brand['ink'] }};">{{ $label }}</span>
<span class="highlight-value">{{ $value }}</span>
</td>
</tr>
</table>
