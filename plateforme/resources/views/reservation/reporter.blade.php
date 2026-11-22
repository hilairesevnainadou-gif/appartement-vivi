{{-- Report d'une réservation par le client (maquette reporter.html). Disponibilités calculées par le serveur. --}}
@php
    use App\Enums\Formule;

    $r = $reservation;
    $dateChoisie = $choisi ? $choisi['date']->toDateString() : null;
@endphp

<x-layouts.public page="reservations" titre="Reporter {{ $r->reference }}" classe-corps="bg-sand">
    <div class="container py-3 py-lg-4" style="max-width:760px">
        <a class="back-link" href="{{ route('reservation.voir', $r->reference) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Ma réservation</a>

        @if (! $possible)
            <section class="mt-3">
                <div class="panel done">
                    <div class="check-badge check-badge--neutre"><i class="bi bi-hourglass-bottom" aria-hidden="true"></i></div>
                    <h1>Le report n’est plus possible</h1>
                    <p class="text-soft">
                        @if ($r->reportPossible())
                            Aucun créneau n’est libre dans {{ $r->logement->mot('ce') }} d’ici le {{ $limite->translatedFormat('l j F') }}, pour la même durée.
                        @else
                            Un report doit être demandé au plus tard {{ $delai }} heures avant l’arrivée. Passé ce délai, ou si vous ne vous êtes pas présenté,
                            la réservation est considérée comme consommée : seuls le propriétaire ou VIVI peuvent encore la déplacer.
                        @endif
                    </p>
                    <a class="btn btn-primary" href="{{ route('espace.client') }}">Retour à mes réservations</a>
                </div>
            </section>
        @else
            <h1 class="page-title mt-2 mb-1">Reporter ma réservation</h1>
            <p class="text-soft">{{ $r->logement->titre }} · prévu le {{ $r->debut->translatedFormat('l j F') }} · {{ lcfirst($r->creneauEnClair()) }}</p>
            <div class="note-box mb-3">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>Le report est gratuit. Vous pouvez choisir une date jusqu’au <strong>{{ $limite->translatedFormat('l j F') }}</strong>, selon les disponibilités {{ $r->logement->mot('du') }}.</span>
            </div>
            @error('report')
                <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
            @enderror

            {{-- 1. Nouvelle date : choisir une date recharge la page avec ses créneaux libres --}}
            <form method="GET" action="{{ route('reservation.reporter', $r->reference) }}">
                <div class="panel">
                    <h2 id="t-date">1. Nouvelle date</h2>
                    <div class="day-grid mt-3" role="radiogroup" aria-labelledby="t-date">
                        @foreach ($jours as $i => $j)
                            @php $complet = $j['options'] === []; @endphp
                            <div class="slot">
                                <input type="radio" name="date" id="j-{{ $i }}" value="{{ $j['date']->toDateString() }}" @disabled($complet)
                                       @checked($dateChoisie === $j['date']->toDateString()) onchange="this.form.submit()">
                                <label for="j-{{ $i }}"><strong>{{ $j['date']->isToday() ? 'auj.' : $j['date']->translatedFormat('D') }}</strong><span>{{ $complet ? 'Complet' : $j['date']->translatedFormat('j M') }}</span></label>
                            </div>
                        @endforeach
                    </div>
                    <noscript><button class="btn btn-ghost mt-3" type="submit">Voir les créneaux</button></noscript>
                </div>
            </form>

            {{-- 2. Créneau, puis confirmation --}}
            <form method="POST" action="{{ route('reservation.reporter.confirmer', $r->reference) }}" novalidate
                  data-confirmer="Le report n’est possible qu’une fois et la réservation ne sera plus remboursable. Un nouveau QR Code et un nouveau code de secours vous seront envoyés." data-confirmer-titre="Confirmer le report ?" data-confirmer-bouton="Reporter">
                @csrf
                <input type="hidden" name="date" value="{{ $dateChoisie }}">
                <div class="panel">
                    <h2 id="t-creneau">2. Créneau</h2>
                    @if (! $choisi)
                        <p class="text-soft mb-0 mt-2">Choisissez d’abord une date.</p>
                    @elseif ($r->formule === Formule::Journee)
                        <input type="hidden" name="option" value="journee">
                        <p class="mb-0 mt-2">{{ rtrim($choisi['options'][0]['libelle'], '.') }}. Durée inchangée : {{ $r->dureeCourte() }}.</p>
                    @else
                        <div class="slot-grid mt-3" role="radiogroup" aria-labelledby="t-creneau">
                            @foreach ($choisi['options'] as $k => $o)
                                <div class="slot">
                                    <input type="radio" name="option" id="o-{{ $k }}" value="{{ $o['valeur'] }}" @checked(old('option') === $o['valeur'])>
                                    <label for="o-{{ $k }}"><strong>{{ \Illuminate\Support\Str::before($o['libelle'], ' · ') ?: $o['libelle'] }}</strong>
                                        <span>{{ \Illuminate\Support\Str::contains($o['libelle'], ' · ') ? \Illuminate\Support\Str::after($o['libelle'], ' · ') : 'Durée inchangée : '.$r->dureeCourte() }}</span></label>
                                </div>
                            @endforeach
                        </div>
                        @error('option')<p class="small mt-2 mb-0" style="color:var(--vivi-danger)">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div class="panel">
                    <h2>Récapitulatif</h2>
                    <dl class="detail-list mt-3">
                        <div><dt>Nouvelle date</dt><dd>{{ $choisi ? ucfirst($choisi['date']->translatedFormat('l j F Y')) : 'À choisir' }}</dd></div>
                        <div><dt>Durée</dt><dd>{{ $r->dureeCourte() }}, comme prévu</dd></div>
                        <div><dt>À payer</dt><dd>Rien, le report est gratuit</dd></div>
                    </dl>
                    <div class="form-check align-items-start mt-3">
                        <input class="form-check-input mt-1 @error('accord') is-invalid @enderror" type="checkbox" id="rp-accord" name="accord" value="1">
                        <label class="form-check-label" for="rp-accord">J’ai compris qu’une réservation reportée n’est plus remboursable : le montant payé reste acquis pour ce logement.</label>
                        @error('accord')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-cta btn-lg w-100 mt-3" type="submit" @disabled(! $choisi)>Confirmer le report</button>
                    <p class="form-text text-center mt-2 mb-0">Un nouveau QR Code est créé : l’ancien ne sera plus valable.</p>
                </div>
            </form>
        @endif
    </div>
</x-layouts.public>
