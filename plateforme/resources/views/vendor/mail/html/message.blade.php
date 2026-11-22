{{-- Gabarit de tous les emails VIVI (thème : themes/vivi.css) --}}
<x-mail::layout>
{{-- En-tête --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
VIVI
</x-mail::header>
</x-slot:header>

{{-- Corps --}}
{!! $slot !!}

{{-- Lien de secours --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Pied de page --}}
<x-slot:footer>
<x-mail::footer>
**VIVI · Appartements meublés et salles** à l’heure, à la demi-journée ou à la journée, au Bénin.<br>
[{{ preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')) }}]({{ config('app.url') }})@if ($whatsapp = \App\Support\Parametres::lienWhatsapp()) · [Nous écrire sur WhatsApp]({{ $whatsapp }})@endif

<br>© {{ date('Y') }} Appartement VIVI · Nova Tech Bénin. Ce message vous est envoyé à la suite d’une action sur votre compte VIVI.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
