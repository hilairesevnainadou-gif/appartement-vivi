{{-- Numéro WhatsApp donné par le client à la réservation (tout pays) : un toucher ouvre la conversation --}}
@php($numeroWhatsapp = $r->whatsapp ?: $r->client?->whatsapp)
@if ($numeroWhatsapp)
    <a class="lien-whatsapp" href="{{ \App\Support\Telephone::lienWhatsapp($numeroWhatsapp, 'Bonjour '.$r->client->prenom().', c’est votre hôte VIVI pour la réservation '.$r->reference.'.') }}"
       target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i>{{ \App\Support\Telephone::lisibleInternational($numeroWhatsapp) }}</a>
@endif
