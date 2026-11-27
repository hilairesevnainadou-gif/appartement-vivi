{{-- Conditions générales d'utilisation et de vente : texte de travail fondé sur le cahier des charges, à faire relire par un juriste avant la mise en ligne --}}
@php
    $taux = rtrim(rtrim(number_format($commission, 1, ',', ''), '0'), ',');
@endphp

<x-layouts.public page="aide" titre="Conditions générales" description="Conditions générales d’utilisation et de vente de la plateforme Appartement VIVI." classe-corps="bg-sand">
    <div class="container py-3 py-lg-5 aide" style="max-width:860px">
        <p class="eyebrow mb-2">Aide</p>
        <h1 class="page-title mb-2">Conditions générales</h1>
        <p class="text-soft mb-4">Conditions d’utilisation et de vente de la plateforme Appartement VIVI, éditée par Nova Tech Bénin.</p>

        @include('pages.aide._entete')

        <div class="panel aide-texte">
            <nav class="aide-sommaire" aria-label="Sommaire">
                <ol>
                    <li><a href="#objet">Objet</a></li>
                    <li><a href="#compte">Compte et connexion</a></li>
                    <li><a href="#reservation">Réservation et prix</a></li>
                    <li><a href="#paiement">Paiement</a></li>
                    <li><a href="#sejour">Arrivée et séjour</a></li>
                    <li><a href="#annulation">Annulation et report</a></li>
                    <li><a href="#proprietaires">Propriétaires</a></li>
                    <li><a href="#donnees">Données personnelles</a></li>
                    <li><a href="#litiges">Responsabilité et litiges</a></li>
                </ol>
            </nav>

            <h2 id="objet">1. Objet</h2>
            <p>Appartement VIVI met en relation des clients et des propriétaires d’appartements meublés, de salles de fêtes, de conférence ou de réunion et d’espaces événementiels au Bénin, pour des séjours ou des événements à l’heure, à la demi-journée ou à la journée, avec des services à la carte. Utiliser la plateforme vaut acceptation des présentes conditions.</p>

            <h2 id="compte">2. Compte et connexion</h2>
            <p>La connexion se fait sans mot de passe, par un code à 6 chiffres envoyé par SMS ou par email. Ce code est personnel : ne le communiquez à personne, VIVI ne vous le demandera jamais. Vous êtes responsable de l’exactitude du numéro et de l’adresse renseignés.</p>

            <h2 id="reservation">3. Réservation et prix</h2>
            <p>Le prix affiché comprend la location et les services choisis. Une <strong>commission VIVI</strong> s’y ajoute : {{ $taux }} % pour un logement ; pour une salle, selon le montant ({{ \App\Support\Parametres::tranchesEnClair() }}). Elle est indiquée avant le paiement. Pendant le paiement, le créneau vous est réservé {{ $blocage }} minutes ; passé ce délai, il redevient disponible.</p>

            <h2 id="paiement">4. Paiement</h2>
            <p>Le paiement se fait en ligne par Mobile Money (MTN, Moov, Celtiis) ou par carte bancaire, sur la page sécurisée de notre prestataire FedaPay. VIVI ne conserve aucune donnée de carte. La réservation est confirmée dès la réception du paiement ; un reçu est disponible dans votre espace.</p>

            <h2 id="sejour">5. Arrivée et séjour</h2>
            <p>À la confirmation, vous recevez un QR Code et un code de secours à 6 chiffres. Présentez-les à l’arrivée au propriétaire, à son gestionnaire ou au gérant VIVI. Respectez les horaires réservés, le nombre de voyageurs (logement) ou la capacité maximale (salle) et les règles du lieu.</p>

            <h2 id="annulation">6. Annulation et report</h2>
            <p>Les règles de remboursement et de report sont détaillées sur la page <a href="{{ route('aide.annulation') }}">Annulation et report</a>. La commission VIVI n’est jamais remboursée. Le report est possible une fois, jusqu’à {{ $delaiReport }} h avant l’arrivée.</p>

            <h2 id="proprietaires">7. Propriétaires</h2>
            @php $tauxRetraitCgu = \App\Services\Caisse::taux((float) \App\Support\Parametres::get('commission_retrait_taux')); @endphp
            <p>VIVI est rémunérée par le propriétaire de l’une des deux façons suivantes, précisées dans son espace (rubrique « Abonnement » ou « Conditions VIVI ») :
                @if (\App\Services\ModeleEconomique::formulesProposees())
                    en règle générale, un <strong>abonnement mensuel</strong> : ses logements et ses salles ne sont visibles que tant que l’abonnement est en cours ; ou, sur accord avec VIVI, une <strong>commission prélevée sur chaque retrait</strong> de son solde, sans abonnement.
                @else
                    en règle générale, une <strong>commission de {{ $tauxRetraitCgu }} prélevée sur chaque retrait</strong> de son solde, sans abonnement ; ou, sur accord avec VIVI, un <strong>abonnement mensuel</strong> ou un taux de commission particulier.
                @endif
                La commission sur retrait est calculée au moment de la demande, indiquée avant confirmation, et n’est pas prélevée sur un retrait refusé.
                Le propriétaire garantit l’exactitude des annonces, la propreté et la sécurité des lieux. Il les gère lui-même ou en confie la gestion à VIVI, qui perçoit alors un pourcentage de la location ou un prix fixe par réservation, selon le contrat signé avec lui. Chaque réservation démarrée (QR Code scanné) crédite son solde ; ses retraits sont validés par la caisse VIVI.</p>

            <h2 id="donnees">8. Données personnelles</h2>
            <p>VIVI collecte le nom, le téléphone, l’email et l’historique des réservations, uniquement pour gérer les réservations, les paiements et le service client. Ces données ne sont ni vendues ni cédées. Vous pouvez demander leur consultation ou leur rectification au service client, et <a href="{{ route('aide.suppression') }}">supprimer votre compte</a>.@if (\App\Support\Parametres::get('confidentialite_publiee')) Voir la <a href="{{ route('aide.confidentialite') }}">politique de confidentialité</a>.@endif</p>

            <h2 id="litiges">9. Responsabilité et litiges</h2>
            <p class="mb-0">VIVI agit comme intermédiaire : le propriétaire reste responsable de son logement. En cas de difficulté, contactez d’abord le service client, qui recherche une solution amiable. Les présentes conditions sont soumises au droit béninois.</p>
        </div>

        <p class="form-text mt-3">Dernière mise à jour : {{ \Illuminate\Support\Carbon::parse('2026-11-12')->translatedFormat('j F Y') }}.</p>

        @include('pages.aide._contact')
    </div>
</x-layouts.public>
