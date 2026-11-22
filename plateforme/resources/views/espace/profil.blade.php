{{-- « Mon profil » : identité, email et téléphone (changement confirmé par un code), raccourcis, données personnelles --}}
@php
    use App\Support\Telephone;

    $whatsapp = \App\Support\Parametres::lienWhatsapp('Bonjour VIVI, j’ai une question sur mon compte.');
    // Bloc « changer » : ouvert si une erreur le concerne, ou si un code est attendu pour ce champ
    $ouvert = fn (string $champ) => $errors->has('valeur_'.$champ) || ($enAttente['champ'] ?? null) === $champ;
@endphp

<x-layouts.espace titre="Mon profil" page="profil">
    <div class="container py-3 py-lg-4" style="max-width:880px">
        {{-- ===== En-tête ===== --}}
        <section class="profil-tete">
            <span class="avatar avatar--lg" aria-hidden="true">{{ $user->initiales() }}</span>
            <div class="flex-grow-1">
                <h1 class="page-title mb-1">{{ $user->name }}</h1>
                <p class="text-soft mb-0">{{ $user->libelleRole() }} · membre depuis {{ $stats['membreDepuis']->translatedFormat('F Y') }}
                    @if ($stats['reservations']) · {{ \App\Models\Reservation::pluriel($stats['reservations'], 'réservation') }}@endif</p>
            </div>
        </section>

        @if (session('code_dev'))
            <div class="note-box note-box--orange mb-3" role="note"><i class="bi bi-tools" aria-hidden="true"></i>
                <span>Développement : le code est <strong class="tnum">{{ session('code_dev') }}</strong>. Ce cadre n’apparaît jamais en production.</span></div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7 d-grid gap-3 align-content-start">
                {{-- ===== Identité ===== --}}
                <section class="panel m-0" aria-labelledby="t-identite">
                    <h2 id="t-identite" class="profil-titre">Identité</h2>
                    <form method="POST" action="{{ route('profil.nom') }}" class="d-flex flex-wrap gap-2 align-items-end" novalidate>
                        @csrf
                        <div class="flex-grow-1" style="min-width:220px">
                            <label class="form-label" for="pr-nom">Nom et prénom</label>
                            <input class="form-control @error('nom') is-invalid @enderror" id="pr-nom" name="nom" value="{{ old('nom', $user->name) }}" autocomplete="name" required maxlength="120">
                            @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                    </form>
                    <p class="form-text mb-0 mt-2">Il figure sur vos réservations et vos reçus.</p>
                </section>

                {{-- ===== Email et téléphone ===== --}}
                @foreach ([
                    'email' => ['Adresse email', 'bi-envelope', $user->email, $user->email_verified_at, 'vous@exemple.com', 'email', $emailActif, 'Pour recevoir confirmations, adresse du logement, itinéraire et reçus par email, et vous connecter avec.'],
                    'telephone' => ['Téléphone', 'bi-phone', $user->telephone ? Telephone::lisible($user->telephone) : null, $user->telephone_verified_at, '01 97 00 00 00', 'tel', $smsActif, 'Pour recevoir votre QR Code et vos rappels par SMS, et vous connecter avec.'],
                ] as $champ => [$libelle, $icone, $actuel, $verifie, $exemple, $type, $actif, $usage])
                    <section class="panel m-0" aria-labelledby="t-{{ $champ }}">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <h2 id="t-{{ $champ }}" class="profil-titre mb-1"><i class="bi {{ $icone }} me-2" aria-hidden="true"></i>{{ $libelle }}</h2>
                                @if ($actuel)
                                    <p class="mb-0"><strong class="text-ink">{{ $actuel }}</strong>
                                        @if ($verifie)<span class="chip chip--success ms-1"><i class="bi bi-patch-check" aria-hidden="true"></i>Vérifié</span>@endif</p>
                                @else
                                    <p class="mb-0 text-soft">Non renseigné.</p>
                                @endif
                            </div>
                        </div>
                        <p class="form-text mt-2 mb-0">{{ $usage }}</p>

                        @if (($enAttente['champ'] ?? null) === $champ)
                            {{-- Étape 2 : code reçu --}}
                            <form method="POST" action="{{ route('profil.confirmer') }}" class="profil-code mt-3" novalidate>
                                @csrf
                                <p class="mb-2">Saisissez le code envoyé {{ $champ === 'telephone' ? 'par SMS au '.Telephone::lisible($enAttente['valeur']) : 'à '.$enAttente['valeur'] }}.</p>
                                <div class="d-flex flex-wrap gap-2 align-items-start">
                                    <div style="flex:0 1 200px">
                                        <label class="visually-hidden" for="pr-code">Code reçu</label>
                                        <input class="form-control otp-input @error('code') is-invalid @enderror" id="pr-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
                                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <button class="btn btn-primary" type="submit" data-chargement="Vérification…">Confirmer</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('profil.annuler') }}" class="mt-2">
                                @csrf
                                <button class="btn btn-link btn-sm p-0" type="submit">Annuler le changement</button>
                            </form>
                        @elseif ($actif)
                            {{-- Étape 1 : nouvelle valeur --}}
                            <details class="profil-changer mt-3" @if ($ouvert($champ)) open @endif>
                                <summary class="btn btn-ghost btn-sm">{{ $actuel ? 'Changer' : 'Ajouter' }} {{ $champ === 'email' ? 'mon email' : 'mon numéro' }}</summary>
                                <form method="POST" action="{{ route('profil.demander') }}" class="d-flex flex-wrap gap-2 align-items-start mt-2" novalidate>
                                    @csrf
                                    <input type="hidden" name="champ" value="{{ $champ }}">
                                    <div class="flex-grow-1" style="min-width:220px">
                                        <label class="visually-hidden" for="pr-{{ $champ }}">Nouveau {{ mb_strtolower($libelle) }}</label>
                                        <input class="form-control @error('valeur_'.$champ) is-invalid @enderror" id="pr-{{ $champ }}" name="valeur" type="{{ $type }}"
                                               placeholder="{{ $exemple }}" value="{{ $ouvert($champ) ? old('valeur') : '' }}" required>
                                        @error('valeur_'.$champ)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <button class="btn btn-primary" type="submit" data-chargement="Envoi du code…">Recevoir un code</button>
                                </form>
                                <p class="form-text mb-0 mt-2">Un code vous est envoyé pour vérifier que {{ $champ === 'email' ? 'l’adresse' : 'le numéro' }} est bien à vous.</p>
                            </details>
                        @else
                            <p class="form-text mt-2 mb-0">L’envoi {{ $champ === 'email' ? 'd’emails' : 'de SMS' }} est désactivé pour le moment : modification impossible.</p>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="col-lg-5 d-grid gap-3 align-content-start">
                {{-- ===== Raccourcis ===== --}}
                <nav class="panel m-0" aria-label="Mon compte">
                    <h2 class="profil-titre">Mon compte</h2>
                    <ul class="profil-liens">
                        @if ($user->estProprietaireOuGestionnaire())
                            <li><a href="{{ route('espace.proprietaire') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i>Tableau de bord propriétaire</a></li>
                        @endif
                        <li><a href="{{ route('espace.client') }}"><i class="bi bi-ticket-perforated" aria-hidden="true"></i>Mes réservations</a></li>
                        <li><a href="{{ route('notifications') }}"><i class="bi bi-bell" aria-hidden="true"></i>Notifications</a></li>
                        <li><a href="{{ route('parrainage') }}"><i class="bi bi-gift" aria-hidden="true"></i>Parrainage et bonus</a></li>
                        <li><a href="{{ route('aide.annulation') }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Annulation et report</a></li>
                    </ul>
                </nav>

                {{-- ===== Notifications ===== --}}
                <section class="panel m-0" aria-labelledby="t-notifications">
                    <h2 id="t-notifications" class="profil-titre">Notifications</h2>
                    <form method="POST" action="{{ route('profil.preferences') }}">
                        @csrf
                        <div class="form-check form-switch">
                            <input type="hidden" name="recevoir_offres" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="pr-offres" name="recevoir_offres" value="1"
                                   @checked($user->recevoir_offres) onchange="this.form.requestSubmit()">
                            <label class="form-check-label" for="pr-offres">Recevoir les offres et les nouveautés de VIVI</label>
                        </div>
                        <noscript><button class="btn btn-ghost btn-sm mt-2" type="submit">Enregistrer</button></noscript>
                    </form>
                    <p class="form-text mb-0 mt-2">Vos réservations, paiements et rappels vous sont toujours notifiés.</p>
                </section>

                {{-- ===== Données personnelles ===== --}}
                <section class="panel m-0" aria-labelledby="t-donnees">
                    <h2 id="t-donnees" class="profil-titre">Mes données</h2>
                    <p class="small text-soft">VIVI utilise votre nom, votre téléphone et votre email seulement pour vos réservations, vos paiements et le service client. Ils ne sont ni vendus ni cédés (<a href="{{ route('aide.conditions') }}#donnees">conditions générales</a>).</p>
                    <p class="small text-soft mb-0">Pour consulter ou corriger vos données, contactez le service client{{ $whatsapp ? '' : '.' }}
                        @if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener">sur WhatsApp</a>.@endif</p>
                    <p class="mb-0 mt-3"><a class="text-danger" href="{{ route('profil.suppression') }}"><i class="bi bi-trash me-1" aria-hidden="true"></i>Supprimer mon compte</a></p>
                </section>

                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button class="btn btn-ghost w-100" type="submit"><i class="bi bi-power" aria-hidden="true"></i>Me déconnecter</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.espace>
