{{-- Billet d'une réservation (maquettes ma-reservation.html et, juste après le paiement, confirmation.html) --}}
@php
    use App\Enums\StatutReservation;
    use App\Models\Reservation;
    use App\Support\Montant;
    use App\Support\Parametres;
    use App\Support\QrCode;

    $r = $reservation;
    $l = $r->logement;
    $actif = in_array($r->statut, [StatutReservation::Confirmee, StatutReservation::EnCours], true);
    $pastilles = [
        StatutReservation::EnAttentePaiement->value => ['Paiement à finaliser', 'chip--orange', 'bi-hourglass-split'],
        StatutReservation::Confirmee->value => ['Confirmée', 'chip--teal', 'bi-check-circle'],
        StatutReservation::EnCours->value => [$l->estSalle() ? 'Événement en cours' : 'Séjour en cours', 'chip--success', 'bi-door-open'],
        StatutReservation::Terminee->value => ['Terminée', 'chip--line', 'bi-flag'],
        StatutReservation::Consommee->value => ['Consommée', 'chip--line', 'bi-person-x'],
        StatutReservation::Annulee->value => ['Annulée', 'chip--line', 'bi-x-circle'],
    ];
    $p = $pastilles[$r->statut->value];
    $services = $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ') ?: 'Aucun';
    $delai = Parametres::get('report_delai_minimum_heures');
@endphp

<x-layouts.public page="reservations" titre="Réservation {{ $r->reference }}" classe-corps="bg-sand">

    @if ($confirmee && $actif)
        <section class="confirm-hero" aria-labelledby="t-confirmation">
            <div class="container">
                <div class="check"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
                <h1 id="t-confirmation">Réservation confirmée</h1>
                <p class="mb-0">Référence <strong class="text-ink">{{ $r->reference }}</strong> · Votre confirmation vous a été envoyée par {{ $r->client?->email ? 'email' : 'SMS' }}.</p>
            </div>
        </section>
    @endif

    <div class="container py-3 py-lg-4" style="max-width:940px">
        @unless ($confirmee && $actif)
            <a class="back-link" href="{{ route('espace.client') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Mes réservations</a>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-3">
                <h1 class="page-title mb-0">Ma réservation</h1>
                <span class="chip {{ $p[1] }}"><i class="bi {{ $p[2] }}" aria-hidden="true"></i>{{ $p[0] }}</span>
            </div>
        @endunless

        @if ($r->statut === StatutReservation::EnAttentePaiement)
            <div class="note-box note-box--orange mb-3" role="status">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <span>Le paiement n’est pas encore finalisé.
                    @if ($r->bloque_jusqua?->isFuture())
                        <a href="{{ route('reservation.paiement', $r->reference) }}">Finaliser le paiement</a> avant {{ $r->bloque_jusqua->format('H:i') }}.
                    @else
                        Le délai est écoulé : <a href="{{ route('reservation.paiement', $r->reference) }}">réessayer</a> si le créneau est toujours libre.
                    @endif
                </span>
            </div>
        @elseif ($r->statut === StatutReservation::Annulee)
            <div class="note-box mb-3" role="status">
                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                <span>
                    {{ $r->motif_annulation ?: 'Réservation annulée.' }}
                    @if ($r->montant_rembourse > 0) Remboursement de <strong>{{ Montant::fcfa($r->montant_rembourse) }}</strong> sur votre moyen de paiement. @endif
                </span>
            </div>
        @endif

        {{-- ===== Billet ===== --}}
        <article class="ticket {{ $confirmee && $actif ? '' : 'ticket--plat' }}" aria-label="Billet de la réservation">
            <div class="ticket__qr">
                @if ($actif)
                    <div class="qr" role="img" aria-label="QR Code de votre réservation">{!! QrCode::svg($r->jetonQr()) !!}</div>
                    <p class="fw-semibold text-ink mb-1">Votre QR Code d’accès</p>
                    <p class="small text-soft mb-1">Si le scan ne passe pas, donnez ce code :</p>
                    <div class="code-secours" aria-label="Code de secours {{ $r->code_secours }}">@foreach (str_split((string) $r->code_secours) as $chiffre)<span aria-hidden="true">{{ $chiffre }}</span>@endforeach</div>
                @else
                    <div class="qr qr--inactif" aria-hidden="true"><i class="bi bi-qr-code"></i></div>
                    <p class="small text-soft mb-0">
                        {{ $r->statut === StatutReservation::EnAttentePaiement ? 'Le QR Code apparaît dès que le paiement est reçu.' : 'Ce QR Code n’est plus valable.' }}
                    </p>
                @endif
            </div>
            <div class="ticket__cut" aria-hidden="true"></div>
            <div class="ticket__body">
                <h2 class="h4 mb-1">{{ $l->titre }}</h2>
                <p class="text-soft mb-3">
                    <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $actif && $l->adresse ? $l->adresse.', ' : '' }}{{ $l->quartier }}, {{ $l->ville }}
                    · Référence <strong class="text-ink tnum">{{ $r->reference }}</strong>
                </p>
                @if ($actif)
                    <a class="btn btn-ghost btn-sm mb-3" href="{{ $l->lienItineraire() }}" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right" aria-hidden="true"></i>Itinéraire jusqu’{{ match ($l->mot()) { 'salle' => 'à la salle', 'espace' => 'à l’espace', default => 'au logement' } }}</a>
                @endif
                <dl class="detail-list">
                    <div><dt>Date</dt><dd>{{ ucfirst($r->debut->translatedFormat('l j F Y')) }}</dd></div>
                    <div><dt>Créneau</dt><dd>{{ $r->creneauEnClair() }}</dd></div>
                    @if ($r->personnes())<div><dt>Voyageurs</dt><dd>{{ $r->personnes() }}</dd></div>@endif
                    <div><dt>Services</dt><dd>{{ $services }}</dd></div>
                    @if ($paiement)
                        <div><dt>Total payé</dt><dd>{{ Montant::fcfa($r->montantPaye()) }}{{ $r->montant_bonus > 0 ? ' + '.Montant::fcfa($r->montant_bonus).' de bonus' : '' }}</dd></div>
                        <div><dt>Paiement</dt><dd>{{ $paiement->moyen ?: 'FedaPay' }} · {{ $paiement->paye_le?->translatedFormat('j M Y à H:i') }}</dd></div>
                    @endif
                </dl>
                <div class="d-flex flex-wrap gap-2 mt-4 actions-billet">
                    @if ($actif)
                        <a class="btn btn-primary" href="#" id="enregistrer"><i class="bi bi-download" aria-hidden="true"></i>Enregistrer le QR Code</a>
                    @endif
                    @if ($paiement)
                        <a class="btn btn-ghost" href="{{ route('reservation.recu', $r->reference) }}"><i class="bi bi-receipt" aria-hidden="true"></i>Reçu</a>
                    @endif
                    @if ($confirmee && $actif)
                        <a class="btn btn-ghost" href="{{ route('espace.client') }}">Voir mes réservations</a>
                    @else
                        <a class="btn btn-ghost" href="{{ route('logement', $l->slug) }}">Voir l’annonce {{ $l->mot('du') }}</a>
                    @endif
                </div>
            </div>
        </article>

        <div class="row g-3 g-lg-4 mt-1">
            <div class="col-lg-7">
                @if ($confirmee && $actif)
                    <section class="panel h-100" aria-labelledby="t-suite">
                        <h2 id="t-suite" class="mb-3">Et maintenant ?</h2>
                        <ol class="timeline">
                            <li><span class="ico"><i class="bi bi-chat-dots" aria-hidden="true"></i></span><h3>Vous avez l’adresse</h3><p>L’adresse exacte {{ $l->mot('du') }} figure sur ce billet et dans votre message de confirmation.</p></li>
                            <li><span class="ico"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span><h3>Votre QR Code est scanné à l’arrivée</h3><p>Le propriétaire ou son gestionnaire le scanne : votre séjour commence à ce moment-là.</p></li>
                            <li><span class="ico"><i class="bi bi-key" aria-hidden="true"></i></span><h3>Vous profitez {{ $l->mot('du') }}</h3><p>Les services que vous avez ajoutés sont prêts à votre arrivée.</p></li>
                            <li><span class="ico"><i class="bi bi-star" aria-hidden="true"></i></span><h3>Vous donnez votre avis</h3><p>Après votre départ, notez {{ $l->mot('le') }} en quelques secondes.</p></li>
                        </ol>
                    </section>
                @else
                    <section class="panel h-100" aria-labelledby="t-paiement">
                        <h2 id="t-paiement" class="mb-3">Détail du paiement</h2>
                        <dl class="price-lines">
                            <div><dt>Location</dt><dd>{{ Montant::fcfa($r->montant_location) }}</dd></div>
                            @foreach ($r->services as $s)
                                <div><dt>{{ $s->nom }}{{ $s->quantite > 1 ? ' × '.$s->quantite : '' }}</dt><dd>{{ Montant::fcfa($s->montant) }}</dd></div>
                            @endforeach
                            <div><dt>Commission VIVI</dt><dd>{{ Montant::fcfa($r->montant_commission) }}</dd></div>
                            @if ($r->remise_agent > 0)
                                <div class="bonus"><dt>Remise agent (votre part marketing)</dt><dd>− {{ Montant::fcfa($r->remise_agent) }}</dd></div>
                            @endif
                            <div class="total"><dt>Total</dt><dd>{{ Montant::fcfa($r->montant_total) }}</dd></div>
                            @if ($r->montant_bonus > 0)
                                <div><dt>Payé avec votre bonus</dt><dd>− {{ Montant::fcfa($r->montant_bonus) }}</dd></div>
                                <div class="total"><dt>Payé</dt><dd>{{ Montant::fcfa($r->montantPaye()) }}</dd></div>
                            @endif
                        </dl>
                        @if ($paiement)
                            <p class="form-text mt-3 mb-0">Payé par {{ $paiement->moyen ?: 'FedaPay' }} le {{ $paiement->paye_le?->translatedFormat('j F Y à H:i') }}.</p>
                        @endif
                    </section>
                @endif
            </div>
            <div class="col-lg-5">
                <section class="panel h-100" aria-labelledby="t-gerer">
                    <h2 id="t-gerer">{{ $confirmee && $actif ? 'Un empêchement ?' : 'Gérer ma réservation' }}</h2>
                    @error('annulation')
                        <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
                    @enderror
                    @if ($r->statut === StatutReservation::Confirmee)
                        @php $annulable = (int) $r->nombre_reports === 0 && $r->debut->isFuture(); @endphp
                        @if ($r->reportPossible())
                            <p>Vous pouvez reporter votre réservation jusqu’à {{ $delai }} h avant l’arrivée, selon les disponibilités {{ $l->mot('du') }}{{ $annulable ? ', ou l’annuler dans les conditions prévues' : '' }}.</p>
                        @else
                            <p><i class="bi bi-lock me-1" aria-hidden="true"></i>Report impossible à moins de {{ $delai }} h de l’arrivée. Seuls le propriétaire ou VIVI peuvent encore déplacer cette réservation.</p>
                        @endif
                        @if ((int) $r->nombre_reports > 0)
                            <p class="form-text"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Réservation reportée : elle n’est plus remboursable.</p>
                        @endif
                        <div class="d-grid gap-2">
                            @if ($r->reportPossible())
                                <a class="btn btn-outline-primary" href="{{ route('reservation.reporter', $r->reference) }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Reporter ma réservation</a>
                            @endif
                            @if ($annulable)
                                <a class="btn btn-ghost" href="{{ route('reservation.annuler', $r->reference) }}">Annuler ma réservation</a>
                            @endif
                        </div>
                    @elseif ($r->statut === StatutReservation::EnCours)
                        <p class="mb-0">{{ $l->estSalle() ? 'Votre événement est en cours. Bon événement !' : 'Votre séjour est en cours. Bon séjour !' }}</p>
                    @else
                        <p class="mb-0 text-soft">Aucune action possible sur cette réservation.</p>
                    @endif
                    <p class="form-text mt-3 mb-0"><a href="{{ route('aide.annulation') }}">Lire les conditions d’annulation et de report</a></p>
                </section>
            </div>
        </div>

        {{-- ===== Avis (séjour terminé) ===== --}}
        @if ($r->statut === StatutReservation::Terminee)
            <section class="panel mt-3" id="avis" aria-labelledby="t-avis">
                <h2 id="t-avis">Votre avis sur {{ $l->estSalle() ? $l->mot('ce') : 'ce séjour' }}</h2>
                @if ($r->avis)
                    <p class="mb-1">
                        <span class="stars" aria-label="Votre note : {{ $r->avis->note }} sur 5">@for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $r->avis->note ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>@endfor</span>
                    </p>
                    @if ($r->avis->commentaire)<p class="mb-1">« {{ $r->avis->commentaire }} »</p>@endif
                    <p class="mb-0 text-soft"><i class="bi bi-check-circle-fill me-1" style="color:var(--vivi-success)" aria-hidden="true"></i>
                        {{ $r->avis->statut === \App\Enums\StatutAvis::Publie ? 'Votre avis est publié. Merci !' : 'Merci, votre avis est enregistré. Il sera publié après vérification par VIVI.' }}</p>
                @else
                    <form method="POST" action="{{ route('reservation.avis', $r->reference) }}" novalidate>
                        @csrf
                        <p class="text-soft">Votre note aide les prochains clients et le propriétaire.</p>
                        <fieldset class="mb-3">
                            <legend class="form-label">Note <span aria-hidden="true">*</span></legend>
                            <div class="stars-input">
                                @for ($n = 5; $n >= 1; $n--)
                                    <input type="radio" name="note" id="n{{ $n }}" value="{{ $n }}" @checked((int) old('note') === $n)>
                                    <label for="n{{ $n }}" aria-label="{{ $n }} étoile{{ $n > 1 ? 's' : '' }}"><i class="bi bi-star-fill" aria-hidden="true"></i></label>
                                @endfor
                            </div>
                            @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </fieldset>
                        <div class="mb-3">
                            <label class="form-label" for="commentaire">Commentaire (facultatif)</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" maxlength="1000" placeholder="Propreté, accueil, conformité aux photos…">{{ old('commentaire') }}</textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Publier mon avis</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($confirmee && $actif)
            <p class="text-center mt-4 mb-0"><a class="btn btn-ghost" href="{{ route('accueil') }}"><i class="bi bi-house-door" aria-hidden="true"></i>Retour à l’accueil</a></p>
        @endif
    </div>

    @if ($actif)
        @push('scripts')
            <script>
                // « Enregistrer le QR Code » : téléchargement de l'image SVG
                (function () {
                    var lien = document.getElementById('enregistrer'), svg = document.querySelector('.qr svg');
                    if (!lien || !svg) { return; }
                    lien.href = URL.createObjectURL(new Blob([svg.outerHTML], { type: 'image/svg+xml' }));
                    lien.download = 'VIVI-{{ $r->reference }}-QR.svg';
                })();
            </script>
        @endpush
    @endif
</x-layouts.public>
