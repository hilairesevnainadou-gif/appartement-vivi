{{-- Politique de confidentialité : projet fondé sur ce que fait la plateforme, à faire relire par un juriste avant publication (Administration › Paramètres) --}}
<x-layouts.public page="aide" titre="Politique de confidentialité" description="Données personnelles utilisées par le site et l’application Appartement VIVI, et vos droits." classe-corps="bg-sand">
    <div class="container py-3 py-lg-5 aide" style="max-width:860px">
        <p class="eyebrow mb-2">Aide</p>
        <h1 class="page-title mb-2">Politique de confidentialité</h1>
        <p class="text-soft mb-4">Données personnelles utilisées par le site et l’application Appartement VIVI, édités par Nova Tech Bénin.</p>

        @unless ($publiee)
            <div class="alert alert-warning" role="status"><strong>Projet, visible de l’équipe VIVI seulement.</strong> Publiez cette page dans Administration › Paramètres après relecture.</div>
        @endunless

        @include('pages.aide._entete')

        <div class="panel aide-texte">
            <nav class="aide-sommaire" aria-label="Sommaire">
                <ol>
                    <li><a href="#responsable">Responsable</a></li>
                    <li><a href="#donnees">Données utilisées</a></li>
                    <li><a href="#usages">Pourquoi</a></li>
                    <li><a href="#partage">Avec qui</a></li>
                    <li><a href="#publicite">Publicité</a></li>
                    <li><a href="#telephone">Autorisations du téléphone</a></li>
                    <li><a href="#cookies">Cookies</a></li>
                    <li><a href="#duree">Durée</a></li>
                    <li><a href="#securite">Sécurité</a></li>
                    <li><a href="#droits">Vos droits</a></li>
                </ol>
            </nav>

            <h2 id="responsable">1. Responsable</h2>
            <p>Nova Tech Bénin, éditeur d’Appartement VIVI, décide de l’utilisation de vos données.
                @if ($emailContact) Contact : <a href="mailto:{{ $emailContact }}">{{ $emailContact }}</a>.@else Contact : le service client de VIVI.@endif</p>

            <h2 id="donnees">2. Données utilisées</h2>
            <ul>
                <li><strong>Compte</strong> : nom, numéro de téléphone, email. Avec Google : votre nom, votre email et l’identifiant de votre compte Google.</li>
                <li><strong>Réservation</strong> : lieu, dates, services, numéro WhatsApp, montants, QR Code, avis.</li>
                <li><strong>Paiement</strong> : montant, moyen, référence de la transaction. Vos données de carte et vos codes Mobile Money sont saisis chez FedaPay et ne nous parviennent pas.</li>
                <li><strong>Propriétaires et agents</strong> : annonces, photos, adresse et position des lieux, abonnement, revenus, compte de retrait (moyen, numéro, titulaire).</li>
                <li><strong>Annonceurs</strong> : nom, contact et règlements.</li>
                <li><strong>Appareil</strong> : identifiant de notification de l’application, adresse IP, tentatives de connexion et journaux de sécurité.</li>
                <li><strong>Position</strong> : seulement si vous l’autorisez, pour trouver les lieux proches ou placer votre logement sur la carte.</li>
            </ul>

            <h2 id="usages">3. Pourquoi</h2>
            <ul>
                <li>Fournir le service demandé : compte, réservation, paiement, entrée par QR Code, remboursement, retraits.</li>
                <li>Vous prévenir : codes de connexion, confirmations, rappels, par SMS, email ou notification.</li>
                <li>Vous envoyer des offres de VIVI, si vous les acceptez. Vous pouvez les refuser dans votre profil.</li>
                <li>Protéger la plateforme contre la fraude et respecter nos obligations comptables et légales.</li>
            </ul>

            <h2 id="partage">4. Avec qui</h2>
            <p>Vos données ne sont pas vendues. Elles sont transmises seulement à ceux qui en ont besoin :</p>
            <ul>
                <li><strong>la personne qui vous accueille</strong> (propriétaire, gestionnaire ou gérant VIVI) : votre nom, votre numéro, votre WhatsApp et votre réservation. Le client reçoit l’adresse exacte du lieu après le paiement ;</li>
                <li><strong>FedaPay</strong> pour les paiements, et nos prestataires d’envoi de SMS et d’emails ;</li>
                <li><strong>Google</strong> : connexion avec Google si vous la choisissez, notifications de l’application (Firebase){{ $admob ? ', publicités (AdMob)' : '' }} ;</li>
                <li><strong>OpenStreetMap</strong> : l’affichage d’une carte lui transmet votre adresse IP ;</li>
                <li><strong>les autorités</strong>, quand la loi l’exige.</li>
            </ul>
            <p>Certains de ces prestataires traitent des données hors du Bénin.</p>

            <h2 id="publicite">5. Publicité</h2>
            @if ($admob)
                <p>L’application affiche des publicités de VIVI, de ses annonceurs et de Google AdMob. VIVI compte les vues et les clics de ses propres publicités sans les relier à votre compte. Google AdMob peut utiliser l’identifiant publicitaire de votre téléphone, votre adresse IP et des données d’utilisation. Vous pouvez supprimer cet identifiant dans les réglages Android (Confidentialité › Annonces). Là où la loi le demande, votre consentement est demandé avant. Voir <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">comment Google utilise ces données</a>.</p>
            @else
                <p>L’application peut afficher des publicités de VIVI et de ses annonceurs. VIVI compte leurs vues et leurs clics sans les relier à votre compte, et ne transmet pas vos données aux annonceurs.</p>
            @endif

            <h2 id="telephone">6. Autorisations du téléphone</h2>
            <p>L’application demande la <strong>position approximative</strong> (lieux proches), l’<strong>appareil photo</strong> (scanner du QR Code) et les <strong>notifications</strong>. Vous pouvez les refuser ou les retirer dans les réglages du téléphone. Elle ne lit que les photos que vous choisissez d’envoyer.</p>

            <h2 id="cookies">7. Cookies</h2>
            <p>Le site utilise seulement les cookies nécessaires à son fonctionnement : session, sécurité des formulaires, maintien de la connexion et lien d’un agent marketing. Il n’utilise pas de cookie publicitaire ni de mesure d’audience.</p>

            <h2 id="duree">8. Durée</h2>
            <p>Vos données sont gardées tant que votre compte est ouvert. Quand vous le supprimez, elles sont effacées ; seules restent celles que la loi nous oblige à conserver, comme les pièces comptables, et vos avis sans votre nom (<a href="{{ route('aide.suppression') }}">détail</a>). Le journal d’activité de l’équipe est effacé après {{ $joursJournal }} jours.</p>

            <h2 id="securite">9. Sécurité</h2>
            <p>La connexion se fait par un code à usage unique, jamais enregistré en clair. L’accès de l’équipe VIVI dépend du rôle de chacun et ses modifications sont journalisées. Les paiements passent par la page sécurisée de FedaPay.</p>

            <h2 id="droits">10. Vos droits</h2>
            <p>Vous pouvez <a href="{{ route('aide.suppression') }}">supprimer votre compte</a> vous-même. Vous pouvez aussi demander au service client à consulter ou corriger vos données, ou vous opposer à leur utilisation. Nous vérifions votre identité avant de répondre. Vous pouvez aussi saisir l’Autorité de protection des données à caractère personnel (APDP) du Bénin, selon le Code du numérique (loi n° 2017-20).</p>
            <p class="mb-0">Cette page peut évoluer ; un changement important vous sera signalé.</p>
        </div>

        <p class="form-text mt-3">Dernière mise à jour : {{ \Illuminate\Support\Carbon::parse('2026-11-22')->translatedFormat('j F Y') }}.</p>

        @include('pages.aide._contact')
    </div>
</x-layouts.public>
