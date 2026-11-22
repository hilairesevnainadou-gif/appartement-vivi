{{-- Page de paiement simulée (local uniquement, VIVI_PAIEMENT_PILOTE=simulation) : remplace la page FedaPay --}}
@php use App\Support\Montant; @endphp
<x-layouts.public page="reservation" titre="Paiement simulé" :nav-basse="false" entete="tunnel" classe-corps="bg-sand">
    <div class="container py-5" style="max-width:560px">
        <div class="note-box note-box--orange mb-3" role="note">
            <i class="bi bi-tools" aria-hidden="true"></i>
            <span>Paiement simulé : cette page remplace FedaPay tant que les clés ne sont pas configurées. Rien n’est débité.</span>
        </div>
        <div class="panel">
            <h1 class="h3">Payer {{ Montant::fcfa($paiement->montant) }}</h1>
            <p class="text-soft">
                @if ($reservation instanceof \App\Models\Reservation)
                    Réservation {{ $reservation->reference }} · {{ $reservation->logement->titre }}
                @else
                    Abonnement VIVI {{ $reservation->formule->nom }} · un mois
                @endif
            </p>
            <form method="POST" action="{{ route('paiement.simuler', $paiement) }}">
                @csrf
                <div class="pay-options mb-3" role="radiogroup" aria-label="Moyen de paiement">
                    @foreach (config('vivi.paiement.moyens') as $i => $moyen)
                        <div class="pay-option">
                            <input type="radio" name="moyen" id="m-{{ $i }}" value="{{ $moyen }}" @checked($i === 0)>
                            <label for="m-{{ $i }}">
                                <span class="pay-option__icon"><i class="bi {{ $moyen === 'Carte bancaire' ? 'bi-credit-card' : 'bi-phone' }}" aria-hidden="true"></i></span>
                                <span><strong>{{ $moyen }}</strong></span><span class="radio" aria-hidden="true"></span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-cta btn-lg" type="submit" name="choix" value="reussi"><i class="bi bi-check-circle" aria-hidden="true"></i>Simuler un paiement réussi</button>
                    <button class="btn btn-ghost" type="submit" name="choix" value="refuse">Simuler un paiement refusé</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.public>
