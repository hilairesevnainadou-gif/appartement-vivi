{{-- Gabarit des notifications par email (MailMessage), en français, aux couleurs de VIVI --}}
<x-mail::message>
{{-- Salutation --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# {{ $level === 'error' ? 'Un problème est survenu' : 'Bonjour,' }}
@endif

{{-- Texte --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Bouton d'action --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="match ($level) { 'success', 'error' => $level, default => 'primary' }">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Suite --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Formule de fin --}}
@if (! empty($salutation))
{{ $salutation }}
@else
À bientôt,<br>
L’équipe VIVI
@endif

{{-- Lien de secours --}}
@isset($actionText)
<x-slot:subcopy>
Si le bouton « {{ $actionText }} » ne fonctionne pas, copiez ce lien dans votre navigateur :
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
