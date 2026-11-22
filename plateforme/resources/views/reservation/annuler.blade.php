{{-- Annulation d'une réservation par le client (maquette annuler.html) : montant remboursé calculé par le serveur --}}
@php
    use App\Support\Montant;

    $r = $reservation;
@endphp

<x-layouts.public page="reservations" titre="Annuler {{ $r->reference }}" classe-corps="bg-sand">
    <div class="container py-3 py-lg-4" style="max-width:760px">
        <a class="back-link" href="{{ route('reservation.voir', $r->reference) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Ma réservation</a>

        <h1 class="page-title mt-2 mb-1">Annuler ma réservation</h1>
        <p class="text-soft">{{ $r->logement->titre }} · {{ $r->debut->translatedFormat('l j F') }} · {{ lcfirst($r->creneauEnClair()) }}</p>

        <div class="panel">
            <h2>Ce que vous récupérez</h2>
            <p>{{ $calcul['regle'] }}</p>
            <dl class="price-lines">
                <div><dt>Total de la réservation{{ $calcul['bonus'] > 0 ? ' (dont '.Montant::fcfa($calcul['bonus']).' de bonus)' : '' }}</dt><dd>{{ Montant::fcfa($calcul['paye']) }}</dd></div>
                <div><dt>Commission VIVI (non remboursable)</dt><dd>− {{ Montant::fcfa($calcul['commission']) }}</dd></div>
                @if ($calcul['retenue'] - $calcul['commission'] > 0)
                    <div><dt>Location retenue</dt><dd>− {{ Montant::fcfa($calcul['retenue'] - $calcul['commission']) }}</dd></div>
                @endif
                @if ($calcul['bonus_rendu'] > 0)
                    <div><dt>Rendu sur votre bonus</dt><dd>− {{ Montant::fcfa($calcul['bonus_rendu']) }}</dd></div>
                @endif
                <div class="total"><dt>Remboursement</dt><dd>{{ Montant::fcfa($calcul['rembourse']) }}</dd></div>
            </dl>
            @if ($calcul['bonus_rendu'] > 0)
                <p class="form-text mt-3 mb-0"><i class="bi bi-gift me-1" aria-hidden="true"></i>{{ Montant::fcfa($calcul['bonus_rendu']) }} reviennent sur votre bonus, utilisables jusqu’à leur date d’expiration.</p>
            @endif
            <p class="form-text mt-3 mb-1">
                {{ $calcul['rembourse'] > 0 ? 'Remboursement sur votre compte '.($moyen ?: 'de paiement').', sous 24 h à 7 jours.' : 'Aucun remboursement ne sera effectué. Le report, lui, reste possible.' }}
            </p>
            <p class="form-text mb-0">Politique d’annulation « {{ $calcul['politique'] }} ». <a href="{{ route('aide.annulation') }}">Lire les conditions</a></p>
        </div>

        @if ($r->reportPossible())
            <div class="panel">
                <h2>Plutôt reporter ?</h2>
                <p>Le report est gratuit jusqu’à {{ \App\Support\Parametres::get('report_delai_minimum_heures') }} h avant l’arrivée : vous gardez votre réservation et choisissez une autre date, jusqu’au <strong>{{ $limite->translatedFormat('l j F') }}</strong>.</p>
                <a class="btn btn-outline-primary" href="{{ route('reservation.reporter', $r->reference) }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Reporter ma réservation</a>
            </div>
        @endif

        <form method="POST" action="{{ route('reservation.annuler.confirmer', $r->reference) }}" class="panel" id="form-annuler">
            @csrf
            <label class="form-label" for="an-motif">Motif de l’annulation (facultatif)</label>
            <select class="form-select" id="an-motif" name="motif">
                <option value="">Choisir un motif</option>
                <option>Changement de programme</option>
                <option>J’ai trouvé {{ $r->logement->mot() === 'salle' ? 'une autre salle' : 'un autre '.$r->logement->mot() }}</option>
                <option>Problème de transport</option>
                <option>Autre raison</option>
            </select>
            <button class="btn btn-danger btn-lg w-100 mt-4" type="button" data-bs-toggle="modal" data-bs-target="#confirmer">Annuler la réservation</button>
            <a class="btn btn-ghost w-100 mt-2" href="{{ route('reservation.voir', $r->reference) }}">Garder ma réservation</a>

            <div class="modal fade" id="confirmer" tabindex="-1" aria-labelledby="t-confirmer" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="t-confirmer">Confirmer l’annulation ?</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Cette action est définitive : le créneau sera remis en ligne pour d’autres clients.
                                {{ $calcul['rembourse'] > 0 ? 'Vous serez remboursé de '.Montant::fcfa($calcul['rembourse']).'.' : 'Vous ne serez pas remboursé.' }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Non, garder</button>
                            <button type="submit" class="btn btn-danger">Oui, annuler</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-layouts.public>
