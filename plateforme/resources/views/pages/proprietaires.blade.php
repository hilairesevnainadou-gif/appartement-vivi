{{-- Page publique « Propriétaires » : avantages, formules d'abonnement (administration › Formules d'abonnement), étapes, questions --}}
@php
    use App\Support\Montant;

    $taux = rtrim(rtrim(number_format($commission, 1, ',', ''), '0'), ',');
    $inscription = route('connexion', ['mode' => 'inscription', 'role' => 'proprietaire', 'suite' => '/pro/abonnement']);
    $moinsCher = $tarifs->min('net');
    $tauxRetraitTexte = \App\Services\Caisse::taux($tauxRetrait);
@endphp

<x-layouts.public page="proprietaires"
    :titre="$surCommission ? 'Propriétaires : publiez sans abonnement' : 'Propriétaires : formules d’abonnement'"
    :description="$surCommission
        ? 'Publiez votre appartement meublé ou votre salle sur VIVI sans abonnement : VIVI garde '.$tauxRetraitTexte.' de chaque retrait de votre solde.'
        : 'Publiez votre appartement meublé ou votre salle sur VIVI : formules d’abonnement mensuelles, sans commission pour le propriétaire.'">

    {{-- ===== Présentation ===== --}}
    <section class="section pb-0" aria-labelledby="titre-proprietaires">
        <div class="container">
            <div class="owner-band">
                <div class="row g-0 align-items-stretch">
                    <div class="col-lg-7">
                        <div class="owner-band__body">
                            <span class="eyebrow" style="color:var(--vivi-orange-400)">Propriétaires</span>
                            <h1 id="titre-proprietaires" class="h2">Publiez votre appartement ou votre salle sur VIVI</h1>
                            @if ($surCommission)
                                <p class="mb-0">Aucun abonnement à payer : vous louez à l’heure, à la demi-journée ou à la journée, et VIVI garde {{ \App\Services\Caisse::taux($tauxRetrait) }} de chaque retrait.</p>
                            @else
                                <p class="mb-0">Un abonnement mensuel, et vous louez à l’heure, à la demi-journée ou à la journée, sans commission sur vos revenus.</p>
                            @endif
                            <ul>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Vous fixez vos prix, vos créneaux et vos jours fermés.</span></li>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>La commission VIVI ({{ $taux }} % sur les logements ; pour les salles, selon le montant : {{ \App\Support\Parametres::tranchesEnClair() }}) est payée par le client, pas par vous.</span></li>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Vous gérez vous-même, ou vous <a class="text-reset" href="#gestion">confiez la gestion à VIVI</a>.</span></li>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Chaque réservation démarrée crédite votre solde ; vous demandez un retrait quand vous voulez.</span></li>
                            </ul>
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <a class="btn btn-cta btn-lg" href="#formules">{{ $surCommission ? 'Voir les conditions' : 'Voir les formules' }}</a>
                                @if ($moinsCher && ! $surCommission)
                                    <span class="owner-band__prix">À partir de <strong>{{ Montant::fcfa($moinsCher) }}</strong> par mois</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <img class="owner-band__img" src="{{ asset('images/proprietaires.webp') }}" alt="" width="1280" height="960" decoding="async">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Formules ===== --}}
    <section class="section" id="formules" aria-labelledby="titre-formules">
        <div class="container">
            @if ($surCommission)
                {{-- Commission sur retrait : pas de formule d'abonnement --}}
                @php $exemple = 100000; $part = (int) round($exemple * $tauxRetrait / 100); @endphp
                <div class="section-head">
                    <span class="eyebrow">Sans abonnement</span>
                    <h2 id="titre-formules">VIVI se rémunère seulement quand vous encaissez</h2>
                    <p>Publiez gratuitement. À chaque retrait de votre solde, VIVI garde {{ \App\Services\Caisse::taux($tauxRetrait) }} et vous verse le reste par Mobile Money ou virement.</p>
                </div>
                <div class="plans plans--public">
                    <article class="plan is-current">
                        <h3>Commission sur retrait</h3>
                        <p class="plan__prix mb-0">{{ \App\Services\Caisse::taux($tauxRetrait) }} <small>par retrait</small></p>
                        <ul>
                            <li><i class="bi bi-check-lg" aria-hidden="true"></i><span><strong>0 FCFA</strong> d’abonnement</span></li>
                            <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Exemple : {{ Montant::fcfa($exemple) }} retirés → {{ Montant::fcfa($exemple - $part) }} reçus, {{ Montant::fcfa($part) }} pour VIVI</span></li>
                            <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Retrait refusé : recrédité en entier, sans commission</span></li>
                        </ul>
                        @if ($estProprietaire)
                            <a class="btn btn-ghost w-100 mt-auto" href="{{ route('pro.abonnement') }}">Voir mes conditions</a>
                        @elseif (! auth()->check())
                            <a class="btn btn-outline-primary w-100 mt-auto" href="{{ $inscription }}">Créer mon compte propriétaire</a>
                        @endif
                    </article>
                </div>
            @else
            <div class="section-head">
                <span class="eyebrow">Formules d’abonnement</span>
                <h2 id="titre-formules">Choisissez selon le nombre de logements et de salles</h2>
                <p>Paiement mensuel par Mobile Money ou carte bancaire. Vous pouvez changer de formule à tout moment depuis votre espace.</p>
            </div>

            @if ($essai && $formules->isNotEmpty())
                <div class="promo-bandeau mb-3"><i class="bi bi-gift" aria-hidden="true"></i>
                    <span><strong>Essai gratuit de {{ $essaiJours }} jours</strong>{{ $essaiFormule ? ' sur la formule '.($formules->firstWhere('id', $essaiFormule)?->nom ?? '') : ' sur la formule de votre choix' }} : publiez sans payer, puis décidez. Une seule fois par compte.</span>
                </div>
            @endif
            @if ($promotion && $tarifs->contains(fn ($t) => $t['remise'] > 0))
                <div class="promo-bandeau mb-3"><i class="bi bi-percent" aria-hidden="true"></i>
                    <span><strong>{{ $promotion['libelle'] }} : −{{ $promotion['pourcentage'] }} %</strong>{{ $promotion['nouveaux'] ? ' sur votre premier mois payé' : ' sur votre abonnement' }}{{ $promotion['fin'] ? ', jusqu’au '.\Illuminate\Support\Carbon::parse($promotion['fin'])->translatedFormat('j F Y') : '' }}.</span>
                </div>
            @endif

            @if ($formules->isEmpty())
                <div class="note-box"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Les formules seront bientôt publiées. Créez votre compte propriétaire pour être prévenu.</span></div>
            @else
                <div class="plans plans--public">
                    @foreach ($formules as $f)
                        @php $courante = $abonnementActif && $abonnementActif->formule_abonnement_id === $f->id; @endphp
                        <article @class(['plan', 'is-current' => $courante])>
                            @if ($courante)<span class="chip chip--teal plan__badge">Votre formule</span>@endif
                            <h3>{{ $f->nom }}</h3>
                            @if ($tarifs[$f->id]['remise'] > 0)<span class="plan__barre">{{ Montant::fcfa($tarifs[$f->id]['prix']) }}</span>@endif
                            <p class="plan__prix mb-0">{{ Montant::fcfa($tarifs[$f->id]['net']) }} <small>par mois</small></p>
                            <ul>
                                <li><i class="bi bi-check-lg" aria-hidden="true"></i><span><strong>{{ $f->logements_max }} logement{{ $f->logements_max > 1 ? 's' : '' }}</strong> publié{{ $f->logements_max > 1 ? 's' : '' }}</span></li>
                                <li><i class="bi bi-check-lg" aria-hidden="true"></i><span><strong>{{ $f->salles_max }} salle{{ $f->salles_max > 1 ? 's' : '' }}</strong> de fête ou de conférence</span></li>
                                @foreach ((array) $f->avantages as $avantage)
                                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>{{ $avantage }}</span></li>
                                @endforeach
                            </ul>
                            @if ($estProprietaire)
                                <a class="btn {{ $courante ? 'btn-ghost' : 'btn-outline-primary' }} w-100 mt-auto" href="{{ route('pro.abonnement') }}">{{ $courante ? 'Gérer mon abonnement' : 'Choisir '.$f->nom }}</a>
                            @elseif (! auth()->check())
                                <a class="btn btn-outline-primary w-100 mt-auto" href="{{ $inscription }}">Choisir {{ $f->nom }}</a>
                            @endif
                        </article>
                    @endforeach
                </div>
                @auth
                    @unless ($estProprietaire)
                        <p class="form-text mt-3 mb-0">Votre compte est un compte client. Pour publier un logement ou une salle, créez un compte propriétaire avec un autre numéro ou une autre adresse email.</p>
                    @endunless
                @endauth
            @endif
            @endif

            <div class="inclus mt-4">
                <h3>{{ $surCommission ? 'Inclus' : 'Inclus dans toutes les formules' }}</h3>
                <ul>
                    <li><i class="bi bi-calendar-check" aria-hidden="true"></i>Calendrier, créneaux et jours bloqués</li>
                    <li><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Scanner de QR Code à l’arrivée</li>
                    <li><i class="bi bi-phone" aria-hidden="true"></i>Paiements Mobile Money et carte encaissés par VIVI</li>
                    <li><i class="bi bi-wallet2" aria-hidden="true"></i>Solde, historique et retraits</li>
                    <li><i class="bi bi-bell" aria-hidden="true"></i>Alerte à chaque nouvelle réservation</li>
                    <li><i class="bi bi-shield-check" aria-hidden="true"></i>Annonce vérifiée par VIVI</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- ===== Gestion et agents marketing ===== --}}
    <section class="section pt-0" id="gestion" aria-labelledby="titre-gestion">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Gestion</span>
                <h2 id="titre-gestion">Vous gérez, ou VIVI gère pour vous</h2>
                <p>Le choix se fait dans la fiche de chaque logement ou salle. Vous pouvez en changer.</p>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <div class="service-tile">
                        <span class="service-tile__icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                        <div><h3>Je gère moi-même</h3><p>Vous accueillez vos clients et scannez leur QR Code, seul ou avec un gestionnaire de votre choix{{ $surCommission ? '' : ' selon votre formule' }}.</p></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="service-tile">
                        <span class="service-tile__icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                        <div><h3>Je confie la gestion à VIVI</h3><p>VIVI désigne un gérant : il suit le calendrier, accueille les clients et scanne les QR Codes. Sa rémunération, un pourcentage ou un prix fixe, est prévue dans le contrat signé avec vous. Vous gardez vos tarifs et votre solde.</p></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4" id="agents">
                    <div class="service-tile">
                        <span class="service-tile__icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                        <div><h3>Des agents marketing</h3><p>Vous choisissez un agent : il partage son lien personnel vers vos annonces et touche une commission sur les réservations qu’il apporte.</p></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Étapes ===== --}}
    <section class="section bg-sand" aria-labelledby="titre-etapes">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Comment ça marche</span>
                <h2 id="titre-etapes">En ligne en quatre étapes</h2>
            </div>
            <ol class="steps-how">
                <li><h3>Créez votre compte</h3><p>Avec votre numéro ou votre email : un code de connexion suffit, pas de mot de passe.</p></li>
                @if ($surCommission)
                    <li><h3>Indiquez votre compte de retrait</h3><p>Le numéro Mobile Money ou le compte sur lequel VIVI vous versera vos gains. Aucun abonnement à payer.</p></li>
                @else
                    <li><h3>Choisissez une formule</h3><p>Payez le premier mois par Mobile Money ou carte. L’abonnement démarre aussitôt.</p></li>
                @endif
                <li><h3>Publiez vos logements et vos salles</h3><p>Photos, tarifs, créneaux et services. VIVI vérifie l’annonce avant la mise en ligne.</p></li>
                <li><h3>Accueillez vos clients</h3><p>Vous, votre gestionnaire ou le gérant VIVI scanne leur QR Code à l’arrivée : la réservation est créditée sur votre solde.</p></li>
            </ol>
        </div>
    </section>

    {{-- ===== Questions ===== --}}
    <section class="section" aria-labelledby="titre-questions">
        <div class="container" style="max-width:860px">
            <div class="section-head">
                <span class="eyebrow">Questions fréquentes</span>
                <h2 id="titre-questions">Bon à savoir</h2>
            </div>
            <div class="faq">
                <details open>
                    <summary>Est-ce que VIVI prend une commission sur mes revenus ?</summary>
                    @if ($surCommission)
                        <p>Oui, seulement quand vous retirez votre argent : VIVI garde {{ $tauxRetraitTexte }} de chaque retrait, et il n’y a pas d’abonnement à payer. Exemple : pour 100&nbsp;000 FCFA retirés, vous recevez {{ Montant::fcfa(100000 - round(100000 * $tauxRetrait / 100)) }}. Le client paie en plus de votre prix une commission de réservation ({{ $taux }} % pour un logement, un taux selon le montant pour une salle) : elle n’est pas retirée de votre solde.</p>
                    @else
                        <p>Non. Vous payez seulement l’abonnement. La commission VIVI ({{ $taux }} % pour un logement, un taux selon le montant pour une salle) est ajoutée au prix payé par le client ; le prix de location que vous fixez vous revient, moins la part d’un agent marketing ou la rémunération de gestion si vous les avez choisies.</p>
                    @endif
                </details>
                <details>
                    <summary>Comment je reçois mon argent ?</summary>
                    <p>Chaque réservation démarrée (QR Code scanné) s’ajoute à votre solde. Vous demandez un retrait du montant de votre choix ; la caisse VIVI le valide et vous le verse{{ $surCommission ? ', commission de '.$tauxRetraitTexte.' déduite' : '' }}, en indiquant le mode de règlement utilisé. Avant de confirmer, vous voyez exactement ce que vous allez recevoir.</p>
                </details>
                @unless ($surCommission)
                    <details>
                        <summary>Que se passe-t-il à la fin du mois ?</summary>
                        <p>Vous renouvelez depuis votre espace. Sans renouvellement, vos logements et vos salles ne sont plus visibles dans la recherche ; les réservations déjà payées restent valables.</p>
                    </details>
                    <details>
                        <summary>Puis-je changer de formule ?</summary>
                        <p>Oui, à tout moment. La nouvelle formule commence dès le paiement et remplace la précédente, sans remboursement des jours restants.</p>
                    </details>
                @endunless
                <details>
                    <summary>Puis-je avoir des conditions particulières ?</summary>
                    <p>Oui. Selon votre activité, VIVI peut vous proposer un abonnement mensuel sans commission sur vos retraits, ou un taux de commission adapté. Contactez l’équipe VIVI.</p>
                </details>
                <details>
                    <summary>Combien coûte la gestion confiée à VIVI ?</summary>
                    <p>Cela dépend du contrat signé avec vous : un pourcentage de la location, un prix fixe par réservation (un jour ou une période), ou un prix fixe par jour réservé. Le montant est retiré de ce que chaque réservation vous rapporte. Sans réservation, vous ne payez rien pour la gestion.</p>
                </details>
                <details>
                    <summary>Je ne peux pas être présent à chaque arrivée.</summary>
                    <p>{{ $surCommission ? 'Vous' : 'Selon votre formule, vous' }} confiez l’accueil à un gestionnaire : il voit les arrivées et le calendrier des logements et des salles confiés et scanne les QR Codes, sans accès à votre solde ni à vos tarifs. Vous pouvez aussi <a href="#gestion">confier la gestion à VIVI</a>.</p>
                </details>
            </div>

            @include('pages.aide._contact')

            @guest
                <div class="text-center mt-4">
                    <a class="btn btn-cta btn-lg" href="{{ $inscription }}">Créer mon compte propriétaire</a>
                </div>
            @endguest
        </div>
    </section>
</x-layouts.public>
