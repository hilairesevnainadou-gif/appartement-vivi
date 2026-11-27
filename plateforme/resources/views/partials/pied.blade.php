{{-- Pied de page du site public (visiteurs) --}}
@php
    $lienWhatsapp = \App\Support\Parametres::lienWhatsapp();
    $emailContact = \App\Support\Parametres::get('email_contact');
    $villesPied = (array) \App\Support\Parametres::get('villes');
    $resteVilles = max(0, count($villesPied) - 8);
    $inscriptionPro = route('connexion', ['mode' => 'inscription', 'role' => 'proprietaire']);
@endphp
<footer class="vivi-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="vivi-footer__brand">
                    <img src="{{ asset('images/logo/vivi-symbole-blanc.svg') }}" alt="" width="44" height="44">
                    <span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>&amp; salles.</small></span>
                </div>
                <p class="mb-0" style="max-width:24rem">Appartements meublés, salles de fêtes et de conférence au Bénin, à l’heure, à la demi-journée ou à la journée.</p>
                @if ($lienWhatsapp || $emailContact)
                    <div class="vivi-footer__contact">
                        @if ($lienWhatsapp)
                            <a href="{{ $lienWhatsapp }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i>Nous écrire sur WhatsApp</a>
                        @endif
                        @if ($emailContact)
                            <a href="mailto:{{ $emailContact }}"><i class="bi bi-envelope" aria-hidden="true"></i>{{ $emailContact }}</a>
                        @endif
                    </div>
                @endif
            </div>
            <div class="col-6 col-md-4 col-lg-2 offset-lg-1">
                <h2>Réserver</h2>
                <ul>
                    <li><a href="{{ route('recherche', ['categorie' => 'logement']) }}">Logements</a></li>
                    <li><a href="{{ route('recherche', ['categorie' => 'salle']) }}">Salles et événements</a></li>
                    <li><a href="{{ route('accueil') }}#services">Services à la carte</a></li>
                    <li><a href="{{ route('accueil') }}#comment-ca-marche">Comment ça marche</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <h2>Propriétaires</h2>
                <ul>
                    <li><a href="{{ $inscriptionPro }}">Publier un logement ou une salle</a></li>
                    <li><a href="{{ route('proprietaires') }}#formules">{{ \App\Services\ModeleEconomique::formulesProposees() ? 'Formules d’abonnement' : 'Conditions propriétaires' }}</a></li>
                    <li><a href="{{ route('proprietaires') }}#gestion">Confier la gestion à VIVI</a></li>
                    <li><a href="{{ route('proprietaires') }}#agents">Agents marketing</a></li>
                </ul>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <h2>Aide</h2>
                <ul>
                    <li><a href="{{ route('aide.annulation') }}">Annulation et report</a></li>
                    <li><a href="{{ route('aide.conditions') }}">Conditions générales</a></li>
                    @if (\App\Support\Parametres::get('confidentialite_publiee'))
                        <li><a href="{{ route('aide.confidentialite') }}">Confidentialité</a></li>
                    @endif
                    <li><a href="{{ route('aide.suppression') }}">Supprimer mon compte</a></li>
                    <li><a href="{{ route('connexion') }}">Connexion</a></li>
                </ul>
            </div>
        </div>

        @if ($villesPied)
            <nav class="vivi-footer__villes" aria-label="Villes">
                <strong>Nos villes</strong>
                @foreach (array_slice($villesPied, 0, 8) as $v)
                    <a href="{{ route('recherche', ['ville' => $v]) }}">{{ $v }}</a>
                @endforeach
                @if ($resteVilles)
                    <a href="{{ route('recherche') }}">Toutes les villes</a>
                @endif
            </nav>
        @endif

        <div class="vivi-footer__bottom">
            <span>© {{ now()->year }} Appartement VIVI · Nova Tech Bénin</span>
            <div class="pay" aria-label="Moyens de paiement acceptés">
                @foreach (config('vivi.paiement.moyens') as $moyen)
                    <span class="chip">{{ $moyen }}</span>
                @endforeach
            </div>
        </div>
    </div>
</footer>
