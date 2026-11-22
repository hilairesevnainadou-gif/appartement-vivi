{{-- Suppression du compte : page publique, demandée par Google Play (lien web de la fiche de l'application) --}}
<x-layouts.public page="aide" titre="Supprimer mon compte" description="Comment supprimer votre compte Appartement VIVI et vos données personnelles." classe-corps="bg-sand">
    <div class="container py-3 py-lg-5 aide" style="max-width:860px">
        <p class="eyebrow mb-2">Aide</p>
        <h1 class="page-title mb-2">Supprimer mon compte</h1>
        <p class="text-soft mb-4">Pour le site et l’application Appartement VIVI, édités par Nova Tech Bénin.</p>

        <section class="panel">
            <h2>Comment faire</h2>
            <ol class="aide-liste mb-3">
                <li>Connectez-vous, sur le site ou dans l’application.</li>
                <li>Ouvrez « Mon profil », puis « Supprimer mon compte ».</li>
                <li>Saisissez le code reçu par email ou par SMS. La suppression est immédiate et définitive.</li>
            </ol>
            @auth
                <a class="btn btn-primary" href="{{ route('profil.suppression') }}">Supprimer mon compte</a>
            @else
                <a class="btn btn-primary" href="{{ route('connexion', ['suite' => '/mon-profil/suppression']) }}">Me connecter</a>
            @endauth
        </section>

        <section class="panel">
            <h2>Avant de supprimer</h2>
            <p class="mb-0">La suppression attend la fin de vos séjours, paiements, remboursements et retraits en cours. Un propriétaire ou un agent retire d’abord son solde.</p>
        </section>

        <section class="panel">
            <h2>Ce qui est effacé</h2>
            <p>Nom, téléphone, email, numéro WhatsApp, compte Google relié, compte de retrait, notifications, téléphones enregistrés, bonus et code de parrainage. Pour un propriétaire : adresse, position, description et photos de ses logements et salles, retirés du site.</p>
            <h2>Ce qui est gardé</h2>
            <p class="mb-0">Réservations, paiements et retraits, le temps demandé par la loi pour la comptabilité. Votre compte n’y porte plus de nom. Un retrait déjà payé garde le compte qui l’a reçu. Vos avis restent publiés, sans votre nom.</p>
        </section>

        @include('pages.aide._contact')
    </div>
</x-layouts.public>
