{{-- Retour de la page de paiement alors que le prestataire n'a pas encore confirmé : on revérifie toutes les 5 secondes.
     $reservation est null pour un paiement d'abonnement. --}}
<x-layouts.public page="reservation" titre="Paiement en cours de vérification" :nav-basse="false" entete="tunnel" classe-corps="bg-sand">
    @push('head')<meta http-equiv="refresh" content="5">@endpush
    <div class="container py-5" style="max-width:640px">
        <div class="panel waiting" role="status" aria-live="polite">
            <div class="spinner-border" aria-hidden="true"></div>
            <h1 class="h3 mt-3">Confirmez le paiement sur votre téléphone</h1>
            <p class="text-soft mb-1">Si vous payez par Mobile Money, validez la demande reçue sur votre téléphone avec votre code secret.</p>
            @if ($reservation)
                <p class="text-soft mb-3">Cette page se met à jour toute seule dès que le paiement de la réservation <strong class="text-ink">{{ $reservation->reference }}</strong> est reçu.</p>
                <a class="btn btn-ghost" href="{{ route('reservation.voir', $reservation->reference) }}">Voir ma réservation</a>
            @else
                <p class="text-soft mb-3">Cette page se met à jour toute seule dès que le paiement de votre abonnement est reçu.</p>
                <a class="btn btn-ghost" href="{{ route('pro.abonnement') }}">Mon abonnement</a>
            @endif
        </div>
    </div>
</x-layouts.public>
