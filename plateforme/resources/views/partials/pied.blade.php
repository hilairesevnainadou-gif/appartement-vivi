{{-- Pied de page du site public (visiteurs) --}}
@php
    $lienWhatsapp = \App\Support\Parametres::lienWhatsapp();
    $emailContact = \App\Support\Parametres::get('email_contact');
    $telephone = trim((string) \App\Support\Parametres::get('contact_telephone'));
    $adresse = trim((string) \App\Support\Parametres::get('contact_adresse'));
    $reseaux = \App\Support\Parametres::reseaux();
    // Villes : 5 au plus dans le pied de page (court même avec beaucoup de villes), le reste dans la recherche
    $villesPied = (array) \App\Support\Parametres::get('villes');
    $villesAffichees = array_slice($villesPied, 0, 5);
    $inscriptionPro = route('connexion', ['mode' => 'inscription', 'role' => 'proprietaire']);
    $appli = \App\Support\ApplicationMobile::affichee();
@endphp
{{--
    Disposition responsive :
      téléphone  : marque et contact, puis les listes deux par deux (Réserver | Propriétaires, Aide | Villes), application, bas de page ;
      tablette   : marque et contact, puis les quatre listes sur une ligne ;
      ordinateur : marque et contact à gauche (4/12), les quatre listes à droite (8/12).
--}}
<style>
    .vivi-footer__liens h2{white-space:nowrap}
    .vivi-footer__liens a{line-height:1.35;padding:.2rem 0}
    .vivi-footer__coord{list-style:none;padding:0;margin:1rem 0 0;display:grid;gap:.15rem}
    .vivi-footer__coord li{display:flex;gap:.55rem;align-items:flex-start}
    .vivi-footer__coord i{color:var(--vivi-orange-400);font-size:1rem;line-height:1.5;flex:none;margin-top:.45rem}
    .vivi-footer__coord a{word-break:break-word}
    .vivi-footer__reseaux{display:flex;flex-wrap:wrap;gap:8px;margin-top:1rem}
    .vivi-footer .vivi-footer__reseaux a{width:40px;height:40px;min-height:0;justify-content:center;border-radius:50%;border:1px solid rgba(255,255,255,.28);color:#fff;font-size:1.05rem;text-decoration:none;transition:background-color .15s,border-color .15s}
    .vivi-footer .vivi-footer__reseaux a:hover{background:#fff;border-color:#fff;color:var(--vivi-teal-900)}
    .vivi-footer .vivi-footer__toutes{font-weight:600;color:#fff}
    .vivi-footer__app{border-top:1px solid rgba(255,255,255,.14);margin-top:32px;padding-top:24px;display:flex;flex-direction:column;gap:14px}
    .vivi-footer__app h2{margin:0 0 .2rem}
    .vivi-footer__app p{margin:0;font-size:.9rem;color:#a9c6c4}
    .vivi-footer__app .app-detail{margin:6px 0 0}
    .vivi-footer__bottom{align-items:center}
    @media (max-width:575.98px){
        .vivi-footer__bottom{flex-direction:column;align-items:flex-start}
        .vivi-footer .pay .chip{font-size:.78rem}
    }
    @media (min-width:992px){
        .vivi-footer__app{flex-direction:row;align-items:center;justify-content:space-between;gap:24px}
        .vivi-footer__app-texte{max-width:22rem}
        .vivi-footer__app-boutons{display:flex;flex-direction:column;align-items:flex-end}
        .vivi-footer__app .app-stores{flex-wrap:nowrap}
    }
</style>
<footer class="vivi-footer">
    <div class="container">
        <div class="row g-4">
            {{-- Marque et contact --}}
            <div class="col-12 col-lg-4">
                <div class="vivi-footer__brand">
                    <img src="{{ asset('images/logo/vivi-symbole-blanc.svg') }}" alt="" width="44" height="44">
                    <span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>&amp; salles.</small></span>
                </div>
                <p class="mb-0" style="max-width:24rem">Appartements meublés, salles de fêtes et de conférence au Bénin, à l’heure, à la demi-journée ou à la journée.</p>

                @if ($adresse || $telephone || $lienWhatsapp || $emailContact)
                    <ul class="vivi-footer__coord" aria-label="Contacter VIVI">
                        @if ($adresse)
                            <li><i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($adresse) }}" target="_blank" rel="noopener">{{ $adresse }}</a></li>
                        @endif
                        @if ($telephone)
                            <li><i class="bi bi-telephone" aria-hidden="true"></i>
                                <a href="tel:{{ preg_replace('/[^+0-9]/', '', $telephone) }}">{{ $telephone }}</a></li>
                        @endif
                        @if ($lienWhatsapp)
                            <li><i class="bi bi-whatsapp" aria-hidden="true"></i>
                                <a href="{{ $lienWhatsapp }}" target="_blank" rel="noopener">Nous écrire sur WhatsApp</a></li>
                        @endif
                        @if ($emailContact)
                            <li><i class="bi bi-envelope" aria-hidden="true"></i>
                                <a href="mailto:{{ $emailContact }}">{{ $emailContact }}</a></li>
                        @endif
                    </ul>
                @endif

                @if ($reseaux)
                    <div class="vivi-footer__reseaux" aria-label="VIVI sur les réseaux sociaux">
                        @foreach ($reseaux as $r)
                            <a href="{{ $r['lien'] }}" target="_blank" rel="noopener" aria-label="VIVI sur {{ $r['nom'] }}" title="{{ $r['nom'] }}">
                                <i class="bi {{ $r['icone'] }}" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Listes de liens --}}
            <div class="col-12 col-lg-8">
                <div class="row g-4 vivi-footer__liens">
                    <div class="col-6 col-md-3">
                        <h2>Réserver</h2>
                        <ul>
                            <li><a href="{{ route('recherche', ['categorie' => 'logement']) }}">Logements</a></li>
                            <li><a href="{{ route('recherche', ['categorie' => 'salle']) }}">Salles et événements</a></li>
                            <li><a href="{{ route('accueil') }}#services">Services à la carte</a></li>
                            <li><a href="{{ route('accueil') }}#comment-ca-marche">Comment ça marche</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-3">
                        <h2>Propriétaires</h2>
                        <ul>
                            <li><a href="{{ $inscriptionPro }}">Publier un bien</a></li>
                            <li><a href="{{ route('proprietaires') }}#formules">{{ \App\Services\ModeleEconomique::formulesProposees() ? 'Formules d’abonnement' : 'Conditions propriétaires' }}</a></li>
                            <li><a href="{{ route('proprietaires') }}#gestion">Confier la gestion à VIVI</a></li>
                            <li><a href="{{ route('proprietaires') }}#agents">Agents marketing</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-3">
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
                    @if ($villesAffichees)
                        <nav class="col-6 col-md-3" aria-labelledby="pied-villes">
                            <h2 id="pied-villes">Nos villes</h2>
                            <ul>
                                @foreach ($villesAffichees as $v)
                                    <li><a href="{{ route('recherche', ['ville' => $v]) }}">{{ $v }}</a></li>
                                @endforeach
                                @if (count($villesPied) > count($villesAffichees))
                                    <li><a class="vivi-footer__toutes" href="{{ route('recherche') }}">Toutes les villes ({{ count($villesPied) }})</a></li>
                                @endif
                            </ul>
                        </nav>
                    @endif
                </div>
            </div>
        </div>

        @if ($appli)
            <div class="vivi-footer__app">
                <div class="vivi-footer__app-texte">
                    <h2>L’application VIVI</h2>
                    <p>Réservez et retrouvez votre QR Code d’entrée depuis votre téléphone.</p>
                </div>
                <div class="vivi-footer__app-boutons">
                    <x-application variante="pied" />
                </div>
            </div>
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
