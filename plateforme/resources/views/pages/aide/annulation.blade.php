{{-- Conditions d'annulation et de report (CDC 6.1, décision du 12/11/2026 : commission jamais remboursée) --}}
@php
    $taux = rtrim(rtrim(number_format($commission, 1, ',', ''), '0'), ',');
@endphp

<x-layouts.public page="aide" titre="Annulation et report" description="Conditions d’annulation, de remboursement et de report d’une réservation VIVI." classe-corps="bg-sand">
    <div class="container py-3 py-lg-5 aide" style="max-width:860px">
        <p class="eyebrow mb-2">Aide</p>
        <h1 class="page-title mb-2">Annulation et report</h1>
        <p class="text-soft mb-4">Ce que vous récupérez si vous annulez, et comment changer de date. Le montant exact est toujours affiché avant de confirmer une annulation.</p>

        @include('pages.aide._entete')

        <div class="note-box mb-4" role="note">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>La <strong>commission VIVI</strong> ({{ $taux }} % pour un logement, selon le montant pour une salle) n’est <strong>jamais remboursée</strong>. La location et les services à la carte le sont selon les règles ci-dessous.</span>
        </div>

        <section class="panel" aria-labelledby="t-heure">
            <h2 id="t-heure"><i class="bi bi-clock me-2" aria-hidden="true"></i>À l’heure et à la demi-journée</h2>
            <p class="text-soft">Politique « Flexible ».</p>
            <div class="table-responsive">
                <table class="table aide-bareme mb-0">
                    <thead><tr><th scope="col">Vous annulez</th><th scope="col">Location</th><th scope="col">Services</th><th scope="col">Commission</th></tr></thead>
                    <tbody>
                        <tr><th scope="row">24 h ou plus avant l’arrivée</th><td class="ok">Remboursée</td><td class="ok">Remboursés</td><td class="non">Conservée</td></tr>
                        <tr><th scope="row">Moins de 24 h avant l’arrivée</th><td class="non">Due</td><td class="ok">Remboursés</td><td class="non">Conservée</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="t-journee">
            <h2 id="t-journee"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i>À la journée</h2>
            <p class="text-soft">Politique « Modérée ».</p>
            <div class="table-responsive">
                <table class="table aide-bareme mb-0">
                    <thead><tr><th scope="col">Vous annulez</th><th scope="col">Location</th><th scope="col">Services</th><th scope="col">Commission</th></tr></thead>
                    <tbody>
                        <tr><th scope="row">5 jours ou plus avant l’arrivée</th><td class="ok">Remboursée</td><td class="ok">Remboursés</td><td class="non">Conservée</td></tr>
                        <tr><th scope="row">Moins de 5 jours avant l’arrivée</th><td class="partiel">1<sup>re</sup> journée et moitié des suivantes dues</td><td class="ok">Remboursés</td><td class="non">Conservée</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="form-text mt-3 mb-0">Exemple : 3 journées à 25 000 FCFA annulées 2 jours avant. Restent dus 25 000 + 12 500 + 12 500 = 50 000 FCFA ; 25 000 FCFA de location vous sont rendus, plus les services.</p>
        </section>

        <section class="panel" aria-labelledby="t-cas">
            <h2 id="t-cas"><i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>Cas particuliers</h2>
            <ul class="aide-liste">
                <li><strong>Heure d’arrivée passée</strong> ou client absent : la réservation est consommée et n’est plus remboursable.</li>
                <li><strong>Réservation déjà reportée</strong> : elle n’est plus remboursable, quel que soit le délai.</li>
                <li><strong>Versement</strong> : sur le compte Mobile Money ou la carte utilisés pour payer, sous 24 h à 7 jours. Vous êtes prévenu par SMS ou email.</li>
            </ul>
        </section>

        <section class="panel" aria-labelledby="t-report">
            <h2 id="t-report"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Reporter plutôt qu’annuler</h2>
            <ul class="aide-liste">
                <li>Gratuit, <strong>une seule fois</strong>, jusqu’à <strong>{{ $delaiReport }} h avant l’arrivée</strong>.</li>
                <li>La nouvelle date doit tomber dans les <strong>{{ $fenetreReport }} jours</strong> qui suivent la date prévue, dans le même logement ou la même salle, et pour la même durée, selon ses disponibilités.</li>
                <li>Un nouveau QR Code et un nouveau code de secours vous sont envoyés ; l’ancien ne fonctionne plus.</li>
                <li>Moins de {{ $delaiReport }} h avant l’arrivée, seuls le propriétaire ou VIVI peuvent encore déplacer la réservation.</li>
            </ul>
            @auth
                <a class="btn btn-ghost mt-2" href="{{ route('espace.client') }}"><i class="bi bi-ticket-perforated" aria-hidden="true"></i>Mes réservations</a>
            @endauth
        </section>

        @include('pages.aide._contact')
    </div>
</x-layouts.public>
