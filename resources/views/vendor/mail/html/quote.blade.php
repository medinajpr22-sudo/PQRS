@props(['text'])
@php($brand = \App\Support\MailBranding::current())
<table class="quote" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="quote-cell" style="border-left-color: {{ $brand['color'] }};">{!! str_replace(["\r\n", "\r", "\n"], '<br>', e($text)) !!}</td>
</tr>
</table>
