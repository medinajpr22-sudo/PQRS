@props(['url'])
@php($brand = \App\Support\MailBranding::current())
<tr>
<td class="header-cell" style="background-color: {{ $brand['color'] }}; color: {{ $brand['on_color'] }};">
<a href="{{ $url }}" style="color: {{ $brand['on_color'] }}; text-decoration: none;">
@if ($brand['logo'])
<span class="logo-box"><img src="{{ $brand['logo'] }}" class="logo" alt="{{ $brand['name'] }}"></span>
@else
<span class="brand-name" style="color: {{ $brand['on_color'] }};">{{ $brand['name'] }}</span>
@endif
<span class="brand-tag" style="color: {{ $brand['on_color'] }};">Atención de PQRS</span>
</a>
</td>
</tr>
