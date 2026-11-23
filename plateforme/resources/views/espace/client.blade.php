{{-- Espace client : mes réservations (maquette mes-reservations.html) --}}
@php
    use App\Enums\StatutAvis;
    use App\Enums\StatutReservation;
    use App\Models\Reservation;
    use App\Support\Montant;
    use Illuminate\Support\Facades\Storage;

    $onglets = [
        'avenir' => ['À venir', 'Aucune réservation à venir', 'Trouvez un logement ou une salle pour une heure, une demi-journée ou la journée.'],
        'passees' => ['Passées', 'Aucune réservation passée', 'Vos séjours et vos événements terminés apparaîtront ici.'],
        'annulees' => ['Annulées', 'Aucune réservation annulée', 'Les réservations que vous annulez apparaîtront ici.'],
    ];
    $pastilles = [
        StatutReservation::EnAttentePaiement->value => ['Paiement à finaliser', 'chip--orange', 'bi-hourglass-split'],
        StatutReservation::Confirmee->value => ['Confirmée', 'chip--teal', 'bi-check-circle'],
        StatutReservation::EnCours->value => ['Séjour en cours', 'chip--success', 'bi-door-open'],
        StatutReservation::Terminee->value => ['Terminée', 'chip--line', 'bi-flag'],
        StatutReservation::Consommee->value => ['Consommée', 'chip--line', 'bi-person-x'],
        StatutReservation::Annulee->value => ['Annulée', 'chip--line', 'bi-x-circle'],
    ];
    $delai = \App\Support\Parametres::get('report_delai_minimum_heures');
@endphp

<x-layouts.public page="reservations" titre="Mes réservations" classe-corps="bg-sand">
    <div class="container py-4 py-lg-5" style="max-width:980px">
        <h1 class="page-title mb-3">Mes réservations</h1>

        <div class="segmented mb-4" role="radiogroup" aria-label="Filtrer les réservations" style="max-width:520px">
            @foreach ($onglets as $cle => $o)
                <input type="radio" name="onglet" id="o-{{ $cle }}" value="{{ $cle }}" @checked($onglet === $cle)>
                <label for="o-{{ $cle }}">{{ $o[0] }} <span class="ms-1 text-soft">({{ $groupes[$cle]->count() }})</span></label>
            @endforeach
        </div>

        @foreach ($onglets as $cle => $o)
            <div class="resa-list" data-onglet="{{ $cle }}" @if($onglet !== $cle) hidden @endif>
                @forelse ($groupes[$cle] as $r)
                    @php
                        $l = $r->logement;
                        $photo = $l->photos->first();
                        $p = $pastilles[$r->statut->value];
                    @endphp
                    <article class="resa-card">
                        @if ($photo)
                            <img class="resa-card__photo" src="{{ Storage::url($photo->chemin) }}" alt="" width="640" height="480" loading="lazy">
                        @else
                            <span class="resa-card__photo d-flex align-items-center justify-content-center bg-sand" aria-hidden="true"><i class="bi bi-house-door fs-3 text-soft"></i></span>
                        @endif
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="chip {{ $p[1] }}"><i class="bi {{ $p[2] }}" aria-hidden="true"></i>{{ $p[0] }}</span>
                                <span class="small text-soft tnum">{{ $r->reference }}</span>
                            </div>
                            <h2><a href="{{ route('reservation.voir', $r->reference) }}">{{ $l->titre }}</a></h2>
                            <ul class="recap">
                                <li><i class="bi bi-calendar-event" aria-hidden="true"></i><span>{{ $r->debut->translatedFormat('l j F Y') }}</span></li>
                                <li><i class="bi bi-clock" aria-hidden="true"></i><span>{{ $r->creneauEnClair() }}</span></li>
                                <li><i class="bi bi-wallet2" aria-hidden="true"></i><span>{{ Montant::fcfa($r->montant_total) }}{{ $r->suffixePersonnes() }}
                                    @if ($r->montant_commission > 0)<small class="d-block text-soft">Réservation {{ Montant::fcfa($r->montant_location) }}@if ($r->montant_services > 0) · services {{ Montant::fcfa($r->montant_services) }}@endif · commission VIVI {{ Montant::fcfa($r->montant_commission) }}, sur la réservation</small>@endif
                                </span></li>
                            </ul>

                            @if ($cle === 'avenir')
                                @if ($r->debut_initial)
                                    <div class="note-box"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>Reportée : la date prévue au départ était le {{ $r->debut_initial->translatedFormat('D j M') }}.</span></div>
                                @endif
                                @if ($r->statut === StatutReservation::Confirmee && ! $r->reportPossible())
                                    <p class="form-text mb-0 mt-2"><i class="bi bi-lock me-1" aria-hidden="true"></i>Report impossible à moins de {{ $delai }} h de l’arrivée. Seuls le propriétaire ou VIVI peuvent encore déplacer cette réservation.</p>
                                @endif
                            @elseif ($cle === 'passees')
                                @if ($r->statut === StatutReservation::Consommee)
                                    <p class="form-text mb-0 mt-2"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Vous ne vous êtes pas présenté : la réservation est considérée comme consommée.</p>
                                @elseif ($r->avis)
                                    <p class="mb-0 mt-2 small">
                                        <span class="stars" aria-label="Votre note : {{ $r->avis->note }} sur 5">
                                            @for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $r->avis->note ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>@endfor
                                        </span>
                                        <span class="text-soft">{{ $r->avis->statut === StatutAvis::Publie ? 'Votre avis est publié.' : 'Votre avis est en cours de relecture.' }}</span>
                                    </p>
                                @endif
                            @else
                                <div class="note-box"><i class="bi bi-cash-coin" aria-hidden="true"></i><span>
                                    @if ($r->montant_rembourse > 0)
                                        Remboursement de <strong>{{ Montant::fcfa($r->montant_rembourse) }}</strong> sur votre moyen de paiement.
                                    @else
                                        Annulation sans remboursement.
                                    @endif
                                </span></div>
                            @endif
                        </div>
                        <div class="resa-card__actions">
                            @if ($cle === 'avenir')
                                @if ($r->statut === StatutReservation::EnAttentePaiement)
                                    <a class="btn btn-cta" href="{{ route('reservation.paiement', $r->reference) }}"><i class="bi bi-lock-fill" aria-hidden="true"></i>Finaliser le paiement</a>
                                @else
                                    <a class="btn btn-primary" href="{{ route('reservation.voir', $r->reference) }}"><i class="bi bi-qr-code" aria-hidden="true"></i>QR Code</a>
                                @endif
                                @if ($r->reportPossible())
                                    <a class="btn btn-ghost" href="{{ route('reservation.reporter', $r->reference) }}">Reporter</a>
                                @endif
                            @else
                                @if ($r->statut === StatutReservation::Terminee && ! $r->avis)
                                    <a class="btn btn-primary" href="{{ route('reservation.voir', $r->reference) }}#avis"><i class="bi bi-star" aria-hidden="true"></i>Donner mon avis</a>
                                @endif
                                <a class="btn btn-ghost" href="{{ route('logement', $l->slug) }}">Réserver à nouveau</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-state" style="background:#fff">
                        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                        <h2 class="h4 mt-2">{{ $o[1] }}</h2>
                        <p class="text-soft">{{ $o[2] }}</p>
                        <a class="btn btn-primary" href="{{ route('recherche', ['categorie' => 'logement']) }}">Chercher un logement</a>
                        <a class="btn btn-ghost" href="{{ route('recherche', ['categorie' => 'salle']) }}">Chercher une salle</a>
                    </div>
                @endforelse
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            // Onglets : les trois listes sont déjà dans la page, on affiche la bonne
            document.querySelectorAll('[name="onglet"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    document.querySelectorAll('[data-onglet]').forEach(function (liste) { liste.hidden = liste.dataset.onglet !== radio.value; });
                    history.replaceState(null, '', '?onglet=' + radio.value);
                });
            });
        </script>
    @endpush
</x-layouts.public>
