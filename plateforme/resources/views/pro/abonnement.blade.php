{{-- Espace propriétaire : abonnement (maquette pro-abonnement.html) --}}
@php
    use App\Enums\StatutPaiement;
    use App\Support\Montant;

    $joursRestants = $actif ? (int) today()->diffInDays($actif->fin) : 0;
@endphp

<x-layouts.pro titre="Abonnement" page="abonnement">
    <div class="pro-head">
        <div>
            <h1>{{ $commission ? 'Conditions VIVI' : 'Abonnement' }}</h1>
            <p>{{ $commission ? 'Vous travaillez sur commission : aucun abonnement à payer.' : 'Un abonnement actif est nécessaire pour publier vos logements.' }}</p>
        </div>
    </div>

    @if (session('statut'))
        <div class="note-box mb-3" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('statut') }}</span></div>
    @endif
    @error('abonnement')
        <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
    @enderror

    @if ($commission)
        {{-- ===== Commission sur retrait (pas de formule) ===== --}}
        @php
            $taux = \App\Services\Caisse::taux($commission['taux']);
            $illimite = fn (int $n, int $plafond) => $n >= $plafond ? 'illimité' : $n;
            $exemple = 100000;
        @endphp
        <section class="panel" aria-labelledby="t-commission">
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h2 id="t-commission" class="mb-0">Commission sur retrait : {{ $taux }}</h2>
                        <span class="chip chip--success"><i class="bi bi-check-circle" aria-hidden="true"></i>Actif</span>
                    </div>
                    <p class="mb-1">Vos logements sont publiés sans abonnement. À chaque retrait, VIVI garde <strong class="text-ink">{{ $taux }}</strong> du montant retiré de votre solde.</p>
                    <p class="mb-0 text-soft">Exemple : pour {{ Montant::fcfa($exemple) }} retirés, commission VIVI {{ Montant::fcfa(round($exemple * $commission['taux'] / 100)) }}, vous recevez {{ Montant::fcfa($exemple - round($exemple * $commission['taux'] / 100)) }}.</p>
                </div>
                <div class="col-md-5">
                    <div class="quota">
                        <div><strong class="text-ink">{{ $utilises }} sur {{ $illimite($commission['logements'], 999) }}</strong><div class="small text-soft">logements publiés ou en vérification</div></div>
                        <div class="meter" aria-hidden="true"><i style="width:{{ $commission['logements'] >= 999 ? 0 : min(100, $utilises / max(1, $commission['logements']) * 100) }}%"></i></div>
                    </div>
                    <div class="quota mt-2">
                        <div><strong class="text-ink">{{ $sallesUtilisees }} sur {{ $illimite($commission['salles'], 999) }}</strong><div class="small text-soft">salles publiées ou en vérification</div></div>
                        <div class="meter" aria-hidden="true"><i style="width:{{ $commission['salles'] >= 999 ? 0 : min(100, $sallesUtilisees / max(1, $commission['salles']) * 100) }}%"></i></div>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn btn-primary btn-sm" href="{{ route('pro.revenus') }}"><i class="bi bi-cash-coin" aria-hidden="true"></i>Mon solde et mes retraits</a>
            </div>
            <div class="note-box mt-3">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>Vous préférez un abonnement mensuel, sans commission sur vos retraits ? Contactez l’équipe VIVI : elle peut changer vos conditions.</span>
            </div>
        </section>
    @else

    {{-- ===== Formule en cours ===== --}}
    <section class="panel" aria-labelledby="t-actuel">
        @if ($actif)
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h2 id="t-actuel" class="mb-0">Formule {{ $actif->formule->nom }}</h2>
                        @if ($actif->essai)
                            <span class="chip chip--orange"><i class="bi bi-gift" aria-hidden="true"></i>Essai gratuit</span>
                        @else
                            <span class="chip chip--success"><i class="bi bi-check-circle" aria-hidden="true"></i>Actif</span>
                        @endif
                    </div>
                    @if ($actif->essai)
                        <p class="mb-1">Gratuit pendant l’essai. Pour continuer ensuite : <strong class="text-ink">{{ Montant::fcfa($tarifs[$actif->formule_abonnement_id]['net'] ?? $actif->formule->prix_mensuel) }}</strong> par mois.</p>
                    @else
                        <p class="mb-1"><strong class="text-ink">{{ Montant::fcfa($actif->prix_paye) }}</strong> par mois{{ $actif->libelle_promo ? ' ('.$actif->libelle_promo.')' : '' }}.</p>
                    @endif
                    <p class="mb-0 text-soft">
                        Valable jusqu’au {{ $actif->fin->translatedFormat('l j F Y') }}{{ $joursRestants > 0 ? ', encore '.$joursRestants.' jour'.($joursRestants > 1 ? 's' : '') : ', dernier jour' }}.
                        @if ($suivant) Déjà renouvelé jusqu’au {{ $suivant->fin->translatedFormat('j F Y') }}. @endif
                    </p>
                </div>
                <div class="col-md-5">
                    {{-- Quotas séparés : logements, puis salles de fête ou de conférence --}}
                    <div class="quota">
                        <div><strong class="text-ink">{{ $utilises }} sur {{ $actif->formule->logements_max }}</strong><div class="small text-soft">logements publiés ou en vérification</div></div>
                        <div class="meter" aria-hidden="true"><i style="width:{{ min(100, $utilises / max(1, $actif->formule->logements_max) * 100) }}%"></i></div>
                    </div>
                    <div class="quota mt-2">
                        <div><strong class="text-ink">{{ $sallesUtilisees }} sur {{ $actif->formule->salles_max }}</strong><div class="small text-soft">salles publiées ou en vérification</div></div>
                        <div class="meter" aria-hidden="true"><i style="width:{{ min(100, $sallesUtilisees / max(1, $actif->formule->salles_max) * 100) }}%"></i></div>
                    </div>
                </div>
            </div>
            @unless ($suivant)
                <form method="POST" action="{{ route('pro.abonnement.souscrire', $actif->formule) }}" class="mt-3 d-flex flex-wrap align-items-center gap-2"
                      data-confirmer="Formule {{ $actif->formule->nom }} pour un mois : {{ Montant::fcfa($tarifs[$actif->formule_abonnement_id]['net'] ?? $actif->formule->prix_mensuel) }}. Le nouveau mois commence à la fin de {{ $actif->essai ? 'l’essai' : 'la période en cours' }}. Vous allez être redirigé vers la page de paiement sécurisée." data-confirmer-titre="{{ $actif->essai ? 'Continuer après l’essai ?' : 'Renouveler l’abonnement ?' }}" data-confirmer-bouton="Payer">
                    @csrf
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>{{ $actif->essai ? 'Continuer après l’essai' : 'Renouveler pour un mois' }} · {{ Montant::fcfa($tarifs[$actif->formule_abonnement_id]['net'] ?? $actif->formule->prix_mensuel) }}</button>
                    <span class="form-text m-0">Le nouveau mois commence à la fin de {{ $actif->essai ? 'l’essai' : 'la période en cours' }}.</span>
                </form>
            @endunless
        @else
            <h2 id="t-actuel" class="mb-1">Aucun abonnement actif</h2>
            <p class="mb-0 text-soft">Choisissez une formule ci-dessous : vos logements pourront être vérifiés puis publiés dès le paiement reçu.</p>
        @endif
    </section>

    {{-- ===== Formules ===== --}}
    <section class="mt-4" aria-labelledby="t-formules">
        <h2 id="t-formules" class="mb-3" style="font-size:1.35rem">{{ $actif ? 'Changer de formule' : 'Choisir une formule' }}</h2>
        @if ($essai)
            <div class="promo-bandeau mb-3"><i class="bi bi-gift" aria-hidden="true"></i>
                <span><strong>Essai gratuit de {{ $essaiJours }} jours</strong>{{ $essaiFormule ? ' sur la formule '.($formules->firstWhere('id', $essaiFormule)?->nom ?? '') : ' sur la formule de votre choix' }}, sans paiement. Une seule fois par compte.</span>
            </div>
        @endif
        @if ($promotion && $tarifs->contains(fn ($t) => $t['remise'] > 0))
            <div class="promo-bandeau mb-3"><i class="bi bi-percent" aria-hidden="true"></i>
                <span><strong>{{ $promotion['libelle'] }} : −{{ $promotion['pourcentage'] }} %</strong> sur votre abonnement{{ $promotion['nouveaux'] ? ' (premier mois payé)' : '' }}{{ $promotion['fin'] ? ', jusqu’au '.\Illuminate\Support\Carbon::parse($promotion['fin'])->translatedFormat('j F Y') : '' }}.</span>
            </div>
        @endif
        <div class="plans">
            @foreach ($formules as $f)
                @php
                    $courante = $actif && $actif->formule_abonnement_id === $f->id;
                    $tropPetite = $utilises > $f->logements_max || $sallesUtilisees > $f->salles_max;
                    $t = $tarifs[$f->id];
                    $essaiIci = $essai && ($essaiFormule === 0 || $essaiFormule === $f->id) && ! $tropPetite;
                @endphp
                <article class="plan{{ $courante ? ' is-current' : '' }}">
                    @if ($courante)<span class="chip chip--teal">Votre formule</span>@endif
                    <h3>{{ $f->nom }}</h3>
                    @if ($t['remise'] > 0)<span class="plan__barre">{{ Montant::fcfa($t['prix']) }}</span>@endif
                    <p class="plan__prix mb-0">{{ Montant::fcfa($t['net']) }} <small>par mois</small></p>
                    <ul>
                        <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>{{ $f->logements_max }} logement{{ $f->logements_max > 1 ? 's' : '' }} publié{{ $f->logements_max > 1 ? 's' : '' }}</span></li>
                        <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>{{ $f->salles_max }} salle{{ $f->salles_max > 1 ? 's' : '' }} de fête ou de conférence</span></li>
                        @foreach ((array) $f->avantages as $avantage)
                            <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>{{ $avantage }}</span></li>
                        @endforeach
                    </ul>
                    @if ($courante)
                        <button class="btn btn-ghost" type="button" disabled>Formule actuelle</button>
                    @elseif ($tropPetite)
                        <button class="btn btn-ghost" type="button" disabled>Trop de logements publiés</button>
                    @else
                        <div class="mt-auto d-grid gap-2">
                            @if ($essaiIci)
                                <form method="POST" action="{{ route('pro.abonnement.essai', $f) }}"
                                      data-confirmer="La formule {{ $f->nom }} démarre aujourd’hui, gratuitement, pendant {{ $essaiJours }} jours. L’essai n’est proposé qu’une fois par compte." data-confirmer-titre="Commencer l’essai gratuit ?" data-confirmer-bouton="Commencer l’essai">
                                    @csrf
                                    <button class="btn btn-cta w-100" type="submit"><i class="bi bi-gift" aria-hidden="true"></i>Essayer {{ $essaiJours }} jours gratuits</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('pro.abonnement.souscrire', $f) }}"
                                  data-confirmer="{{ Montant::fcfa($t['net']) }} par mois{{ $t['remise'] > 0 ? ' ('.$t['libelle'].')' : '' }}.{{ $actif ? ' La nouvelle formule commence dès le paiement et remplace la formule '.$actif->formule->nom.', sans remboursement des jours restants.' : '' }} Vous allez être redirigé vers la page de paiement sécurisée." data-confirmer-titre="Passer à la formule {{ $f->nom }} ?" data-confirmer-bouton="Payer">
                                @csrf
                                <button class="btn btn-outline-primary w-100" type="submit">Choisir {{ $f->nom }}{{ $t['remise'] > 0 ? ' · '.Montant::fcfa($t['net']) : '' }}</button>
                            </form>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="note-box mt-3">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>Paiement par Mobile Money ou carte, sur la page sécurisée de FedaPay. Une nouvelle formule commence dès le paiement et remplace la précédente, sans remboursement des jours restants.</span>
        </div>
    </section>
    @endif

    {{-- ===== Paiements ===== --}}
    <section class="panel mt-4" aria-labelledby="t-paiements">
        <h2 id="t-paiements" class="mb-2">Paiements</h2>
        @if ($paiements->isEmpty())
            <p class="text-soft mb-0">Aucun paiement pour le moment.</p>
        @else
            <div class="table-responsive">
                <table class="table-simple">
                    <thead><tr><th scope="col">Date</th><th scope="col">Formule</th><th scope="col">État</th><th scope="col" class="num">Montant</th></tr></thead>
                    <tbody>
                        @foreach ($paiements as $p)
                            @php
                                [$texte, $classe, $icone] = match ($p->statut) {
                                    StatutPaiement::Reussi => ['Payé'.($p->moyen ? ' · '.$p->moyen : ''), 'chip--success', 'bi-check-circle'],
                                    StatutPaiement::Echoue => ['Non abouti', 'chip--line', 'bi-x-circle'],
                                    StatutPaiement::Rembourse => ['Remboursé', 'chip--line', 'bi-arrow-counterclockwise'],
                                    default => ['En attente', 'chip--orange', 'bi-hourglass-split'],
                                };
                            @endphp
                            <tr>
                                <td>{{ ($p->paye_le ?? $p->created_at)->translatedFormat('j F Y') }}</td>
                                <td>{{ $p->payable?->formule?->nom ?? '—' }}</td>
                                <td><span class="chip {{ $classe }}"><i class="bi {{ $icone }}" aria-hidden="true"></i>{{ $texte }}</span></td>
                                <td class="num">{{ Montant::fcfa($p->montant) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.pro>
