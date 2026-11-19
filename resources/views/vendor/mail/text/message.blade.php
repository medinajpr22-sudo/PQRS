@php($brand = \App\Support\MailBranding::current())
<x-mail::layout>
    {{-- Encabezado --}}
    <x-slot:header>
        <x-mail::header :url="$brand['url']">
            {{ $brand['name'] }}
        </x-mail::header>
    </x-slot:header>

    {{-- Cuerpo --}}
    {{ $slot }}

    {{-- Texto pequeño bajo el contenido --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Pie --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ $brand['name'] }} · Sistema de atención de PQRS. Este es un mensaje automático, por favor no respondas a este correo.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
