{{-- Espace propriétaire : tableau de bord (maquette pro-tableau-de-bord.html) --}}
@php
    use App\Enums\StatutReservation;
    use App\Enums\StatutRetrait;
    use App\Models\Reservation;
    use App\Services\Caisse;
    use App\Support\Montant;

    $nombre = fn (int $n) => number_format($n, 0, ',', "\u{00A0}");
    $commission ??= null;
    // Sur commission : part de VIVI si tout le solde est retiré
    $commRetrait = $commission && $solde > 0 ? (int) round($solde * $commission['taux'] / 100) : 0;
    $prenom = \Illuminate\Support\Str::before($utilisateur->name, ' ') ?: $utilisateur->name;

    // Écart avec le mois précédent, comme dans la maquette (en % pour les revenus, en points pour l'occupation)
    $ecart = function (int $valeur, int $avant, bool $points = false) {
        if (! $points && $avant === 0) {
            return null;
        }
        $haut = $valeur >= $avant;
        $texte = $points ? abs($valeur - $avant).' pts' : str_replace('.', ',', (string) round(abs($valeur - $avant) / $avant * 100, 1)).' %';

        return '<b class="'.($haut ? 'up' : 'down').'"><i class="bi '.($haut ? 'bi-arrow-up-short' : 'bi-arrow-down-short').'" aria-hidden="true"></i>'
            .'<span class="visually-hidden">'.($haut ? 'en hausse de ' : 'en baisse de ').'</span>'.$texte.'</b> par rapport au mois dernier';
    };

    $etats = [
        StatutReservation::Confirmee->value => ['Attendu', 'chip--orange', 'bi-hourglass-split'],
        StatutReservation::EnCours->value => ['Séjour en cours', 'chip--success', 'bi-door-open'],
        StatutReservation::Terminee->value => ['Terminé', 'chip--line', 'bi-flag'],
        StatutReservation::Consommee->value => ['Client absent', 'chip--line', 'bi-person-x'],
    ];
    $surPlace = $arrivees->where('statut', StatutReservation::EnCours)->count();
    $nomCourt = function (Reservation $r) {
        $mots = preg_split('/\s+/', trim($r->client?->name ?? 'Client VIVI'));

        return $mots[0].(isset($mots[1]) ? ' '.mb_substr($mots[1], 0, 1).'.' : '');
    };
    $aPreparer = fn (Reservation $r) => $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ');

    // Graphique : graduation « ronde » (1, 2 ou 5 × 10^n) pour trois ou quatre lignes
    $max = max(15000, $revenus7j->max('montant'));   // échelle minimale : 0 à 15 000 FCFA
    $brut = $max / 3;
    $puissance = 10 ** floor(log10($brut));
    $pas = (int) (collect([1, 2, 5, 10])->first(fn ($k) => $k * $puissance >= $brut) * $puissance);
    $plafond = (int) (ceil($max / $pas) * $pas);
    $maxReel = $revenus7j->max('montant');
@endphp

<x-layouts.pro titre="Tableau de bord" page="tableau">
    <div class="pro-head">
        <div>
            <span class="eyebrow">{{ today()->translatedFormat('l j F Y') }}</span>
            <h1>Bonjour {{ $prenom }}</h1>
        </div>
        <a class="btn btn-cta" href="{{ route('pro.scanner') }}"><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Scanner un QR Code</a>
    </div>

    @error('retrait')
        <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
    @enderror

    {{-- ===== Indicateurs ===== --}}
    <section class="kpi-row" aria-label="Indicateurs">
        <div class="kpi">
            <p class="kpi__label">Arrivées aujourd’hui</p>
            <p class="kpi__value">{{ $arrivees->count() }}</p>
            <p class="kpi__delta">{{ $surPlace ? 'dont '.$surPlace.' déjà sur place' : 'aucune sur place pour l’instant' }}</p>
        </div>
        <div class="kpi">
            <p class="kpi__label">Réservations à venir</p>
            <p class="kpi__value">{{ $prochaines->count() }}</p>
            <p class="kpi__delta">sur les 7 prochains jours</p>
        </div>
        <div class="kpi">
            <p class="kpi__label">Revenus du mois</p>
            <p class="kpi__value">{{ $nombre($revenusMois) }} <small>FCFA</small></p>
            <p class="kpi__delta">{!! $ecart($revenusMois, $revenusMoisPrecedent) ?? 'premier mois de location' !!}</p>
        </div>
        <div class="kpi">
            <p class="kpi__label">Taux d’occupation</p>
            <p class="kpi__value">{{ $occupation }} <small>%</small></p>
            <p class="kpi__delta">{!! $ecart($occupation, $occupationPrecedente, true) !!}</p>
        </div>
    </section>

    <div class="row g-3 mt-1">
        {{-- ===== Arrivées du jour ===== --}}
        <div class="col-xl-7">
            <section class="panel h-100" aria-labelledby="t-arrivees">
                <div class="panel__head">
                    <h2 id="t-arrivees">Arrivées d’aujourd’hui</h2>
                    <a href="{{ route('pro.calendrier') }}">Voir le calendrier</a>
                </div>
                <ul class="rows">
                    @forelse ($arrivees as $r)
                        @php $etat = $etats[$r->statut->value] ?? $etats['confirmee']; @endphp
                        <li>
                            <div class="quand">{{ $r->debut->format('H:i') }}<small>{{ $r->dureeCourte() }}</small></div>
                            <div class="qui">
                                <strong>{{ $nomCourt($r) }}{{ $r->suffixePersonnes() }}</strong>
                                <span>{{ $r->logement->titre }} · {{ $r->formuleCourte() }}</span>
                                @if ($r->services->isNotEmpty())
                                    <span><i class="bi bi-bag-check me-1" aria-hidden="true"></i>À préparer : {{ $aPreparer($r) }}</span>
                                @endif
                                <span class="chip {{ $etat[1] }}"><i class="bi {{ $etat[2] }}" aria-hidden="true"></i>{{ $etat[0] }}</span>
                            </div>
                            <div class="action">
                                <span class="montant">{{ Montant::fcfa($r->montant_location) }}</span>
                                @if ($r->statut === StatutReservation::Confirmee)
                                    <a class="btn btn-primary btn-sm" href="{{ route('pro.scanner') }}"><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Scanner</a>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li style="display:block" class="text-soft">Aucune arrivée prévue aujourd’hui.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        {{-- ===== Revenus ===== --}}
        <div class="col-xl-5">
            <section class="panel h-100" aria-labelledby="t-revenus">
                <div class="panel__head">
                    <h2 id="t-revenus">Revenus de location, 7 derniers jours</h2>
                </div>
                <p class="text-soft mb-3">Total : <strong class="text-ink">{{ Montant::fcfa($revenus7j->sum('montant')) }}</strong>, hors services à la carte.</p>
                <figure class="chart" aria-labelledby="t-revenus">
                    <div class="chart__plot">
                        <div class="chart__y" aria-hidden="true">
                            @for ($g = 0; $g <= $plafond; $g += $pas)
                                <span style="bottom:{{ $g / $plafond * 100 }}%">{{ $g ? ($g >= 1000 ? $g / 1000 .' k' : $g) : '0' }}</span>
                            @endfor
                        </div>
                        <div class="chart__zone">
                            <div class="chart__grid" aria-hidden="true">
                                @for ($g = 0; $g <= $plafond; $g += $pas)
                                    <i style="bottom:{{ $g / $plafond * 100 }}%"></i>
                                @endfor
                            </div>
                            <div class="chart__cols" id="ch-colonnes">
                                @foreach ($revenus7j as $i => $j)
                                    @php $h = $j['montant'] / $plafond * 100; @endphp
                                    <button type="button" class="chart__col" data-h="{{ $h }}" data-jour="{{ ucfirst($j['jour']->translatedFormat('D j M')) }}" data-montant="{{ Montant::fcfa($j['montant']) }}"
                                            aria-label="{{ $j['jour']->translatedFormat('l j F') }} : {{ Montant::fcfa($j['montant']) }}">
                                        @if ($maxReel > 0 && $j['montant'] === $maxReel)
                                            <span class="chart__val" style="top:{{ 100 - $h }}%">{{ $nombre($j['montant']) }}</span>
                                        @endif
                                        <span class="chart__bar" style="height:{{ $h }}%"></span>
                                    </button>
                                @endforeach
                            </div>
                            <div class="chart__tip" id="ch-bulle" role="status" hidden></div>
                        </div>
                        <div class="chart__x" aria-hidden="true">
                            @foreach ($revenus7j as $j)
                                @if ($loop->last)<b>auj.</b>@else<span>{{ $j['jour']->translatedFormat('D') }}</span>@endif
                            @endforeach
                        </div>
                    </div>
                    <details>
                        <summary>Voir les chiffres</summary>
                        <table>
                            <caption class="visually-hidden">Revenus de location par jour, en FCFA</caption>
                            <tbody>
                                @foreach ($revenus7j as $j)
                                    <tr><th scope="row">{{ ucfirst($j['jour']->translatedFormat('D j M')) }}</th><td>{{ Montant::fcfa($j['montant']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </figure>
            </section>
        </div>
    </div>

    <div class="row g-3 mt-0">
        {{-- ===== Prochaines réservations ===== --}}
        <div class="{{ $estProprietaire ? 'col-xl-7' : 'col-12' }}">
            <section class="panel h-100" aria-labelledby="t-prochaines">
                <div class="panel__head">
                    <h2 id="t-prochaines">Prochaines réservations</h2>
                    <a href="{{ route('pro.reservations') }}">Tout voir</a>
                </div>
                <ul class="rows">
                    @forelse ($prochaines as $r)
                        <li>
                            <div class="quand">{{ $r->debut->translatedFormat('j M') }}<small>{{ $r->debut->format('H:i') }}</small></div>
                            <div class="qui">
                                <strong>{{ $nomCourt($r) }}{{ $r->suffixePersonnes() }}</strong>
                                <span>{{ $r->logement->titre }} · {{ $r->formuleEtDuree() }}</span>
                                @if ($r->services->isNotEmpty())
                                    <span><i class="bi bi-bag-check me-1" aria-hidden="true"></i>À préparer : {{ $aPreparer($r) }}</span>
                                @endif
                            </div>
                            <div class="action"><span class="montant">{{ Montant::fcfa($r->montant_location) }}</span></div>
                        </li>
                    @empty
                        <li style="display:block" class="text-soft">Aucune réservation sur les 7 prochains jours.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        {{-- ===== Solde et retraits (propriétaire seulement) ===== --}}
        @if ($estProprietaire)
            <div class="col-xl-5">
                <section class="panel h-100" aria-labelledby="t-retraits">
                    <div class="panel__head">
                        <h2 id="t-retraits">Solde et retraits</h2>
                        <a href="{{ route('pro.revenus') }}">Historique</a>
                    </div>
                    <p class="kpi__label">Solde disponible</p>
                    <p class="kpi__value mb-3">{{ $nombre($solde) }} <small>FCFA</small></p>
                    <form method="POST" action="{{ route('pro.retraits.demander') }}"
                          data-confirmer="{{ $commission && $commission['taux'] > 0
                              ? 'Tout votre solde ('.$nombre($solde).' FCFA) sera retenu jusqu’à la validation par la caisse VIVI. Commission VIVI '.Caisse::taux($commission['taux']).' : '.Montant::fcfa($commRetrait).'. Vous recevrez '.Montant::fcfa($solde - $commRetrait).'.'
                              : 'Tout votre solde ('.$nombre($solde).' FCFA) sera retenu jusqu’à la validation par la caisse VIVI, qui vous verse le montant.' }}"
                          data-confirmer-titre="Demander un retrait ?" data-confirmer-bouton="Demander le retrait">
                        @csrf
                        @if ($commission && $commission['taux'] > 0 && $solde > 0)
                            <p class="small text-soft mb-2">Commission VIVI {{ Caisse::taux($commission['taux']) }} : <strong>− {{ Montant::fcfa($commRetrait) }}</strong> · vous recevez <strong class="text-ink">{{ Montant::fcfa($solde - $commRetrait) }}</strong></p>
                        @endif
                        <button class="btn btn-primary w-100" type="submit" @disabled($solde <= 0)><i class="bi bi-cash-coin" aria-hidden="true"></i>Demander un retrait</button>
                    </form>
                    @if ($retraits->isNotEmpty())
                        <ul class="rows mt-3">
                            @foreach ($retraits as $v)
                                @php
                                    $jour = $v->created_at;
                                    $quand = $jour->isToday() ? 'Auj.' : ($jour->isYesterday() ? 'Hier' : $jour->translatedFormat('j M'));
                                    [$classe, $icone, $texte] = match ($v->statut) {
                                        StatutRetrait::Paye => ['chip--success', 'bi-check-circle', 'Payé · '.$v->mode_reglement],
                                        StatutRetrait::Refuse => ['chip--line', 'bi-x-circle', 'Refusé, montant recrédité'],
                                        default => ['chip--orange', 'bi-hourglass-split', 'En attente de validation'],
                                    };
                                @endphp
                                <li>
                                    <div class="quand">{{ $quand }}</div>
                                    <div class="qui">
                                        <strong class="tnum">{{ $v->reference }}</strong>
                                        <span class="chip {{ $classe }}"><i class="bi {{ $icone }}" aria-hidden="true"></i>{{ $texte }}</span>
                                    </div>
                                    <div class="action"><span class="montant">{{ Montant::fcfa($v->statut === StatutRetrait::Refuse ? (int) $v->montant : $v->aVerser()) }}</span></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="note-box mt-3">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>Chaque séjour démarré (QR Code scanné) s’ajoute à votre solde. Vos retraits sont validés par la caissière VIVI, qui indique le mode de règlement.</span>
                    </div>
                </section>
            </div>
        @endif
    </div>

    {{-- ===== Abonnement ===== --}}
    @if ($estProprietaire)
        <section class="panel mt-3" aria-labelledby="t-abonnement">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    @if ($commission)
                        @php $illimite = fn (int $n) => $n >= 999 ? 'illimité' : $n; @endphp
                        <h2 id="t-abonnement" class="mb-1" style="font-size:1.2rem">Commission sur retrait : {{ Caisse::taux($commission['taux']) }}</h2>
                        <p class="mb-0 text-soft">Pas d’abonnement à payer : VIVI garde {{ Caisse::taux($commission['taux']) }} de chaque retrait · {{ $nombrePublies }} logement{{ $nombrePublies > 1 ? 's' : '' }} sur {{ $illimite($commission['logements']) }} et {{ $sallesPubliees }} salle{{ $sallesPubliees > 1 ? 's' : '' }} sur {{ $illimite($commission['salles']) }} publiés.</p>
                    @elseif ($abonnement)
                        <h2 id="t-abonnement" class="mb-1" style="font-size:1.2rem">Abonnement {{ $abonnement->formule->nom }}</h2>
                        <p class="mb-0 text-soft">Prochain renouvellement le {{ $abonnement->fin->translatedFormat('l j F Y') }} · {{ $nombrePublies }} logement{{ $nombrePublies > 1 ? 's' : '' }} sur {{ $abonnement->formule->logements_max }} et {{ $sallesPubliees }} salle{{ $sallesPubliees > 1 ? 's' : '' }} sur {{ $abonnement->formule->salles_max }} publiés.</p>
                    @else
                        <h2 id="t-abonnement" class="mb-1" style="font-size:1.2rem">Aucun abonnement actif</h2>
                        <p class="mb-0 text-soft">Un abonnement est nécessaire pour publier vos logements sur VIVI.</p>
                    @endif
                </div>
                <a class="btn btn-ghost" href="{{ route('pro.abonnement') }}">{{ $commission ? 'Voir les conditions' : ($abonnement ? 'Gérer mon abonnement' : 'Choisir un abonnement') }}</a>
            </div>
        </section>
    @endif

    @push('scripts')
        <script>
            // Infobulle du graphique au survol et au clavier ; les chiffres restent lisibles dans le tableau
            (function () {
                var bulle = document.getElementById('ch-bulle');
                function montrer(col) {
                    bulle.innerHTML = '';
                    var jour = document.createElement('span'), montant = document.createElement('strong');
                    jour.textContent = col.dataset.jour; montant.textContent = col.dataset.montant;
                    bulle.append(jour, montant);
                    bulle.style.left = (col.offsetLeft + col.offsetWidth / 2) + 'px';
                    bulle.style.top = (100 - parseFloat(col.dataset.h)) + '%';
                    bulle.hidden = false;
                }
                function cacher() { bulle.hidden = true; }
                document.querySelectorAll('#ch-colonnes .chart__col').forEach(function (col) {
                    ['mouseenter', 'focus', 'click'].forEach(function (e) { col.addEventListener(e, function () { montrer(col); }); });
                    ['mouseleave', 'blur'].forEach(function (e) { col.addEventListener(e, cacher); });
                });
            })();
        </script>
    @endpush
</x-layouts.pro>
