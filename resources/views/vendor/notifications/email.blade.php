<x-mail::message>
{{-- Saludo --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# ¡Hola!
@endif

{{-- Aviso importante (p. ej. riesgo vital) --}}
@if (! empty($alert))
<x-mail::alert>
{{ $alert }}
</x-mail::alert>
@endif

{{-- Texto inicial --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Número de radicado destacado --}}
@if (! empty($highlight))
<x-mail::highlight :label="$highlight['label']" :value="$highlight['value']" />
@endif

{{-- Detalles de la solicitud --}}
@if (! empty($details))
<x-mail::details :rows="$details" />
@endif

{{-- Respuesta del funcionario --}}
@if (! empty($quote))
<x-mail::quote :text="$quote" />
@endif

@if (! empty($quoteNote))
{{ $quoteNote }}

@endif

{{-- Botón --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="in_array($level, ['success', 'error'], true) ? $level : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Texto final --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Despedida --}}
@if (! empty($salutation))
{!! nl2br(e($salutation)) !!}
@else
Atentamente,<br>
{{ config('app.name') }}
@endif

{{-- Enlace por si el botón no funciona --}}
@isset($actionText)
<x-slot:subcopy>
Si el botón «{{ $actionText }}» no funciona, copia y pega este enlace en tu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
