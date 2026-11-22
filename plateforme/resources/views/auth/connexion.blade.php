<x-layouts.public page="connexion" titre="Connexion" description="Connectez-vous à VIVI avec un code reçu par email ou par SMS.">
    <div class="auth-wrap">
        <div class="container">
            <div class="auth-split">
            <div class="auth-card">
                <form method="POST" action="{{ route('connexion.envoyer') }}" novalidate>
                    @csrf

                    <div class="segmented mb-4" role="radiogroup" aria-label="Connexion ou création de compte">
                        <input type="radio" name="mode" id="mode-connexion" value="connexion" @checked(old('mode', $mode) === 'connexion')>
                        <label for="mode-connexion">Se connecter</label>
                        <input type="radio" name="mode" id="mode-inscription" value="inscription" @checked(old('mode', $mode) === 'inscription')>
                        <label for="mode-inscription">Créer un compte</label>
                    </div>

                    <h1 class="h3" data-titre-connexion="Bon retour" data-titre-inscription="Créer votre compte">Bon retour</h1>

                    {{-- Google : clients seulement. Masqué quand on crée un compte propriétaire. --}}
                    <div id="bloc-google" class="auth-google">
                        <a class="btn btn-google btn-lg w-100" href="{{ route('connexion.google') }}" data-chargement="Ouverture de Google…"
                           data-libelle-connexion="Se connecter avec Google" data-libelle-inscription="S’inscrire avec Google">
                            <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                            <span data-libelle-google>Se connecter avec Google</span>
                        </a>
                        <p class="form-text text-center mt-2 mb-0" data-aide-connexion="Pour les clients. Les propriétaires et les gestionnaires se connectent avec le code."
                           data-aide-inscription="Compte client créé en un clic avec votre adresse Gmail.">Pour les clients. Les propriétaires et les gestionnaires se connectent avec le code.</p>
                        <div class="auth-ou" role="separator"><span>ou avec un code</span></div>
                    </div>

                    <div id="bloc-inscription" @if(old('mode', $mode) !== 'inscription') hidden @endif>
                        <div class="field">
                            <label class="form-label" for="nom">Nom et prénom <span aria-hidden="true">*</span></label>
                            <input class="form-control @error('nom') is-invalid @enderror" type="text" id="nom" name="nom"
                                   value="{{ old('nom') }}" autocomplete="name" @error('nom') aria-describedby="nom-erreur" @enderror>
                            @error('nom')<div class="invalid-feedback" id="nom-erreur">{{ $message }}</div>@enderror
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Vous êtes</legend>
                            <div class="role-choice">
                                <div class="slot">
                                    <input type="radio" name="role" id="role-client" value="client" @checked(old('role', $role) === 'client')>
                                    <label for="role-client"><strong><i class="bi bi-person" aria-hidden="true"></i>Client</strong><span>Je cherche un logement</span></label>
                                </div>
                                <div class="slot">
                                    <input type="radio" name="role" id="role-proprietaire" value="proprietaire" @checked(old('role', $role) === 'proprietaire')>
                                    <label for="role-proprietaire"><strong><i class="bi bi-building" aria-hidden="true"></i>Propriétaire</strong><span>Je publie mes logements</span></label>
                                </div>
                            </div>
                        </fieldset>
                        @if ($parrainageActif)
                            <div class="field">
                                <label class="form-label" for="invitation">Code d’invitation <span class="text-soft fw-normal">(facultatif)</span></label>
                                <input class="form-control text-uppercase @error('invitation') is-invalid @enderror" type="text" id="invitation" name="invitation"
                                       value="{{ old('invitation', $invitation) }}" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                       placeholder="Ex. GERA-7K3P" aria-describedby="invitation-aide @error('invitation') invitation-erreur @enderror">
                                @error('invitation')<div class="invalid-feedback" id="invitation-erreur">{{ $message }}</div>@enderror
                                <p class="form-text mb-0" id="invitation-aide">Un ami vous a invité ? Saisissez son code{{ $bonusBienvenue > 0 ? ' : vous recevez '.\App\Support\Montant::fcfa($bonusBienvenue).' de bonus sur votre première réservation' : '' }}.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Au Bénin : numéro béninois (code par SMS) ou email. International : email seulement. --}}
                    @php
                        $paysChoisi = $emailActif ? old('pays', 'benin') : 'benin';
                        $beninNumero = $smsActif;          // le numéro béninois est possible
                        $beninEmail = $emailActif;         // l'email aussi
                        $libelleBenin = $beninNumero && $beninEmail ? 'Numéro béninois ou email' : ($beninNumero ? 'Numéro béninois' : 'Adresse email');
                    @endphp
                    <fieldset class="field">
                        <legend class="form-label">Où êtes-vous ?</legend>
                        @if ($emailActif)
                            <div class="segmented segmented--pays mb-3" role="radiogroup" aria-label="Où êtes-vous ?">
                                <input type="radio" name="pays" id="pays-benin" value="benin" @checked($paysChoisi === 'benin')>
                                <label for="pays-benin"><i class="bi bi-geo-alt" aria-hidden="true"></i>Au Bénin</label>
                                <input type="radio" name="pays" id="pays-international" value="international" @checked($paysChoisi === 'international')>
                                <label for="pays-international"><i class="bi bi-globe2" aria-hidden="true"></i>International</label>
                            </div>
                        @else
                            <input type="hidden" name="pays" value="benin">
                        @endif

                        <div id="saisie-benin" @if ($paysChoisi !== 'benin') hidden @endif>
                            <label class="form-label" for="identifiant-benin">{{ $libelleBenin }} <span aria-hidden="true">*</span></label>
                            <input class="form-control @error('identifiant') is-invalid @enderror" type="{{ $beninNumero ? 'text' : 'email' }}" id="identifiant-benin" name="identifiant"
                                   value="{{ $paysChoisi === 'benin' ? old('identifiant') : '' }}" autocomplete="username" autocapitalize="none" spellcheck="false"
                                   placeholder="{{ $beninNumero ? '01 97 00 00 00' : '' }}{{ $beninNumero && $beninEmail ? ' ou ' : '' }}{{ $beninEmail ? 'vous@exemple.com' : '' }}"
                                   aria-describedby="benin-aide @error('identifiant') identifiant-erreur @enderror"
                                   @if ($paysChoisi !== 'benin') disabled @endif required>
                            @if ($paysChoisi === 'benin') @error('identifiant')<div class="invalid-feedback" id="identifiant-erreur">{{ $message }}</div>@enderror @endif
                            <p class="form-text mb-0" id="benin-aide">
                                @if ($beninNumero && $beninEmail)
                                    Avec votre numéro (10 chiffres, commençant par 01), le code arrive par SMS ; avec votre email, par email.
                                @elseif ($beninNumero)
                                    Le code vous est envoyé par SMS. 10 chiffres, commençant par 01.
                                @else
                                    Le code vous est envoyé par email.
                                @endif
                            </p>
                        </div>

                        @if ($emailActif)
                            <div id="saisie-international" @if ($paysChoisi !== 'international') hidden @endif>
                                <label class="form-label" for="identifiant-international">Adresse email <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('identifiant') is-invalid @enderror" type="email" id="identifiant-international" name="identifiant"
                                       value="{{ $paysChoisi === 'international' ? old('identifiant') : '' }}" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false"
                                       placeholder="vous@exemple.com" aria-describedby="international-aide @error('identifiant') identifiant-erreur @enderror"
                                       @if ($paysChoisi !== 'international') disabled @endif required>
                                @if ($paysChoisi === 'international') @error('identifiant')<div class="invalid-feedback" id="identifiant-erreur">{{ $message }}</div>@enderror @endif
                                <p class="form-text mb-0" id="international-aide">Hors du Bénin, le code vous est envoyé par email.</p>
                            </div>
                        @endif
                    </fieldset>

                    <button class="btn btn-cta btn-lg w-100" type="submit" data-chargement="Un instant…"
                            data-libelle-connexion="Se connecter" data-libelle-inscription="Créer mon compte">{{ old('mode', $mode) === 'inscription' ? 'Créer mon compte' : 'Se connecter' }}</button>
                    <p class="form-text text-center mt-2 mb-0">Un code à 6 chiffres vous est envoyé pour confirmer : pas de mot de passe à retenir.</p>
                </form>
            </div>
            @include('partials.auth-visuel')
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Affiche les champs d'inscription seulement en mode « Créer un compte »
            (function () {
                var bloc = document.getElementById('bloc-inscription');
                var titre = document.querySelector('[data-titre-connexion]');
                var google = document.getElementById('bloc-google');
                var lienGoogle = google.querySelector('a');
                var aideGoogle = google.querySelector('[data-aide-connexion]');
                function regler() {
                    var inscription = document.getElementById('mode-inscription').checked;
                    var proprietaire = document.getElementById('role-proprietaire').checked;
                    bloc.hidden = !inscription;
                    titre.textContent = inscription ? titre.dataset.titreInscription : titre.dataset.titreConnexion;
                    // Google : clients seulement, donc absent pour une inscription propriétaire
                    google.hidden = inscription && proprietaire;
                    google.querySelector('[data-libelle-google]').textContent = inscription ? lienGoogle.dataset.libelleInscription : lienGoogle.dataset.libelleConnexion;
                    aideGoogle.textContent = inscription ? aideGoogle.dataset.aideInscription : aideGoogle.dataset.aideConnexion;
                    bouton.textContent = inscription ? bouton.dataset.libelleInscription : bouton.dataset.libelleConnexion;
                }
                var bouton = document.querySelector('button[data-libelle-connexion]');
                document.querySelectorAll('[name="mode"], [name="role"]').forEach(function (r) { r.addEventListener('change', regler); });
                regler();
            })();

            // « Au Bénin » : numéro ou email ; « International » : email. Un seul champ est envoyé.
            (function () {
                var choix = document.querySelectorAll('input[type="radio"][name="pays"]');
                if (!choix.length) { return; }
                function basculer(focus) {
                    var benin = document.getElementById('pays-benin').checked;
                    [['saisie-benin', benin], ['saisie-international', !benin]].forEach(function (p) {
                        var bloc = document.getElementById(p[0]);
                        var champ = bloc.querySelector('input');
                        bloc.hidden = !p[1];
                        champ.disabled = !p[1];
                        if (p[1] && focus) { champ.focus(); }
                    });
                }
                choix.forEach(function (r) { r.addEventListener('change', function () { basculer(true); }); });
                basculer(false);
            })();
        </script>
    @endpush
</x-layouts.public>
