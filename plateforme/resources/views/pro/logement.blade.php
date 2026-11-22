{{-- Espace propriétaire : créer ou modifier un logement (maquette pro-logement.html) --}}
@php
    use App\Enums\Formule;
    use App\Enums\StatutLogement;
    use App\Enums\TypeLogement;
    use App\Support\Montant;
    use App\Support\Parametres;

    $l = $logement;
    $nouveau = ! $l->exists;
    $tarif = fn (Formule $f) => $nouveau ? null : $l->tarifs->firstWhere('formule', $f);
    // Valeur du formulaire : saisie précédente (erreur de validation), sinon valeur enregistrée, sinon défaut
    $val = fn (string $cle, $defaut = null) => old($cle, $defaut);
    $actif = fn (Formule $f) => (bool) old('tarifs.'.$f->value.'.actif', $tarif($f)?->actif ?? ($nouveau && $f !== Formule::DemiJournee));
    $hh = fn (?string $h) => $h ? substr($h, 0, 5) : null;
    $heures = fn (int $de, int $a) => collect(range($de, $a))->map(fn ($h) => sprintf('%02d:00', $h % 24))->all();

    $th = $tarif(Formule::Heure);
    $tj = $tarif(Formule::Journee);
    $creneaux = old('creneaux', $nouveau || $l->creneaux->isEmpty()
        ? [['id' => null, 'nom' => 'Matin', 'heure_debut' => '08:00', 'heure_fin' => '13:00'], ['id' => null, 'nom' => 'Après-midi', 'heure_debut' => '14:00', 'heure_fin' => '19:00']]
        : $l->creneaux->sortBy('ordre')->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom, 'heure_debut' => $hh($c->heure_debut), 'heure_fin' => $hh($c->heure_fin)])->values()->all());
    $equipementsChoisis = array_map('intval', old('equipements', $nouveau ? [] : $l->equipements->modelKeys()));
    $servicesChoisis = array_map('intval', old('services', $nouveau ? [] : $l->services->modelKeys()));
    // À qui revient le prix de chaque service (réglé par VIVI) : par défaut, sauf exception pour ce logement
    $parDefautServices = \App\Support\Parametres::get('services_beneficiaire');
    $beneficiaireService = fn ($s) => ($nouveau ? null : $l->services->firstWhere('id', $s->id)?->pivot?->beneficiaire) ?: $parDefautServices;

    $etats = [
        StatutLogement::Publie->value => ['Publié', 'chip--success', 'bi-check-circle'],
        StatutLogement::EnValidation->value => ['En attente de validation', 'chip--orange', 'bi-hourglass-split'],
        StatutLogement::Brouillon->value => ['Brouillon', 'chip--line', 'bi-pencil'],
        StatutLogement::Refuse->value => ['À corriger', 'chip--orange', 'bi-exclamation-triangle'],
        StatutLogement::Suspendu->value => ['Suspendu par VIVI', 'chip--line', 'bi-pause-circle'],
    ];
    $e = $etats[$l->statut->value];
    // Onglet à ouvrir : celui de la première erreur, sinon celui demandé
    $ongletErreur = collect(array_keys($errors->getMessages()))->map(fn ($k) => match (true) {
        str_starts_with($k, 'tarifs'), str_starts_with($k, 'creneaux'), str_starts_with($k, 'marketing'), $k === 'temps_preparation_minutes' => 'tarifs',
        str_starts_with($k, 'services') => 'services',
        str_starts_with($k, 'photos') => 'photos',
        default => 'infos',
    })->first();
    $onglet = $ongletErreur ?? $onglet;
    $onglets = ['infos' => 'Informations', 'tarifs' => 'Tarifs et créneaux', 'services' => 'Services', 'photos' => 'Photos'];
@endphp

<x-layouts.pro :titre="$nouveau ? 'Nouveau logement' : $l->titre" page="logements">
    <div style="max-width:880px">
        <a class="back-link" href="{{ route('pro.logements') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Logements et salles</a>
        <div class="pro-head mt-1">
            <div>
                <h1>{{ $nouveau ? 'Nouveau logement' : $l->titre }}</h1>
                <p>{{ $nouveau ? 'Il sera vérifié par VIVI avant d’être publié.' : $l->quartier.', '.$l->ville }}</p>
            </div>
            @unless ($nouveau)<span class="chip {{ $e[1] }}"><i class="bi {{ $e[2] }}" aria-hidden="true"></i>{{ $e[0] }}</span>@endunless
        </div>

        @if ($l->statut === StatutLogement::Refuse && $l->motif_refus)
            <div class="note-box note-box--orange mb-3" role="note"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span><strong>À corriger :</strong> {{ $l->motif_refus }}</span></div>
        @endif
        @if ($errors->any())
            <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span>Vérifiez les champs signalés.@if ($errors->has('tarifs')) {{ $errors->first('tarifs') }}@endif @if ($errors->has('creneaux')) {{ $errors->first('creneaux') }}@endif</span></div>
        @endif

        <ul class="tabs" role="tablist" aria-label="Rubriques du logement">
            @foreach ($onglets as $cle => $libelle)
                <li role="presentation">
                    <button class="{{ $onglet === $cle ? 'active' : '' }}" id="o-{{ $cle }}" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#p-{{ $cle }}"
                            aria-controls="p-{{ $cle }}" aria-selected="{{ $onglet === $cle ? 'true' : 'false' }}" data-onglet="{{ $cle }}">{{ $libelle }}</button>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ $nouveau ? route('pro.logements.store') : route('pro.logements.update', $l) }}" id="form-logement" novalidate>
            @csrf
            {{-- POST simple (pas de PUT) : les boutons des photos réutilisent ce formulaire avec leur propre adresse --}}
            <input type="hidden" name="onglet" id="onglet-courant" value="{{ $onglet }}">

            <div class="tab-content">
                {{-- ===== Informations ===== --}}
                <div class="tab-pane fade {{ $onglet === 'infos' ? 'show active' : '' }}" id="p-infos" role="tabpanel" aria-labelledby="o-infos" tabindex="0">
                    <div class="panel">
                        <h2>Présentation</h2>
                        <div class="row g-3 mt-0">
                            <div class="col-md-8">
                                <label class="form-label" for="i-titre">Nom du logement <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('titre') is-invalid @enderror" type="text" id="i-titre" name="titre" required value="{{ $val('titre', $l->titre) }}" placeholder="Ex. Studio Lagune">
                                @error('titre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="i-type">Type</label>
                                <select class="form-select" id="i-type" name="type">
                                    @foreach (['Logements' => TypeLogement::logements(), 'Salles' => TypeLogement::salles()] as $groupe => $types)
                                        <optgroup label="{{ $groupe }}">
                                            @foreach ($types as $t)
                                                <option value="{{ $t->value }}" data-salle="{{ $t->estSalle() ? 1 : 0 }}" @selected($val('type', $l->type?->value) === $t->value)>{{ $t->getLabel() }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Résidence : plusieurs chambres identiques, réservable tant qu'une chambre est libre --}}
                            @php $unitesVisibles = $val('type', $l->type?->value) === TypeLogement::Residence->value || (int) $val('unites', $l->unites ?? 1) > 1; @endphp
                            <div class="col-12" id="bloc-unites" @unless ($unitesVisibles) hidden @endunless>
                                <div class="note-box align-items-start">
                                    <i class="bi bi-buildings" aria-hidden="true"></i>
                                    <div class="flex-grow-1">
                                        <label class="form-label mb-1" for="i-unites"><strong>Nombre de chambres de ce type à louer</strong></label>
                                        <input class="form-control @error('unites') is-invalid @enderror" type="number" inputmode="numeric" min="1" max="200" id="i-unites" name="unites" style="max-width:160px"
                                               value="{{ $val('unites', $l->unites ?? 1) }}" aria-describedby="i-unites-aide" @unless ($unitesVisibles) disabled @endunless>
                                        @error('unites')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <p class="form-text mb-0 mt-1" id="i-unites-aide">Chaque chambre se réserve séparément : l’annonce reste en ligne tant qu’une chambre est libre. Si les chambres n’ont pas le même prix (simple, double, suite…), créez une annonce par type.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="i-ville">Ville</label>
                                <select class="form-select" id="i-ville" name="ville">
                                    @foreach (Parametres::get('villes') as $ville)
                                        <option @selected($val('ville', $l->ville) === $ville)>{{ $ville }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="i-quartier">Quartier <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('quartier') is-invalid @enderror" type="text" id="i-quartier" name="quartier" value="{{ $val('quartier', $l->quartier) }}">
                                @error('quartier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="i-adresse">Adresse exacte</label>
                                <input class="form-control" type="text" id="i-adresse" name="adresse" placeholder="Rue, repère, portail…" value="{{ $val('adresse', $l->adresse) }}" aria-describedby="i-adresse-aide">
                            </div>
                            <div class="col-12 mt-1"><p class="form-text mb-0" id="i-adresse-aide">L’adresse exacte n’est communiquée au client qu’après le paiement.</p></div>
                            {{-- Position du logement : sert à proposer l'adresse et aux clients qui cherchent « près de moi » --}}
                            <div class="col-12" data-carte-editeur>
                                <div class="carte-editeur">
                                    <div class="carte-editeur__barre">
                                        <div>
                                            <strong>Position sur la carte</strong>
                                            <span class="d-block form-text mt-0">Sur place, utilisez votre position ; sinon cliquez sur la carte. L’adresse vous est proposée.</span>
                                        </div>
                                        <button class="btn btn-primary btn-sm" type="button" id="geo-moi"><i class="bi bi-crosshair" aria-hidden="true"></i>Utiliser ma position</button>
                                    </div>
                                    <div class="carte" data-carte role="application" aria-label="Carte : cliquez pour placer le logement"></div>
                                    <p class="form-text mb-0 mt-2" id="geo-etat" aria-live="polite">{{ $l->latitude ? 'Déplacez le repère si besoin.' : 'Aucune position pour le moment.' }}</p>
                                </div>
                                <input type="hidden" id="i-lat" name="latitude" value="{{ $val('latitude', $l->latitude) }}">
                                <input type="hidden" id="i-lng" name="longitude" value="{{ $val('longitude', $l->longitude) }}">
                                @error('latitude')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            {{-- Salle de fêtes ou de conférence : capacité en personnes (jusqu'à 1 000), ni chambres ni lits --}}
                            @php $estSalle = (bool) TypeLogement::tryFrom((string) $val('type', $l->type?->value))?->estSalle(); @endphp
                            @foreach (['capacite' => [$estSalle ? 'Capacité (personnes)' : 'Voyageurs', 1, $estSalle ? TypeLogement::CAPACITE_MAX_SALLE : TypeLogement::CAPACITE_MAX_LOGEMENT], 'chambres' => ['Chambres', 0, 20], 'lits' => ['Lits', 1, 20], 'salles_de_bain' => [$estSalle ? 'Toilettes' : 'Salles de bain', 0, 10]] as $champ => [$libelle, $min, $max])
                                <div class="col-6 col-md-3" data-champ-capacite="{{ $champ }}" @if ($estSalle && in_array($champ, ['chambres', 'lits'], true)) hidden @endif>
                                    <label class="form-label" for="i-{{ $champ }}">{{ $libelle }}</label>
                                    <input class="form-control @error($champ) is-invalid @enderror" type="number" inputmode="numeric" min="{{ $min }}" max="{{ $max }}" id="i-{{ $champ }}" name="{{ $champ }}" value="{{ $val($champ, $l->{$champ}) }}">
                                    @error($champ)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                            <div class="col-12">
                                <label class="form-label" for="i-description">Description</label>
                                <textarea class="form-control" id="i-description" name="description" rows="4" placeholder="Ce que le client trouve en arrivant, l’ambiance, les points forts du quartier.">{{ $val('description', $l->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                    {{-- Gestion : le propriétaire accueille lui-même, ou confie l'accueil à VIVI --}}
                    @php $modeChoisi = $val('mode_gestion', $l->mode_gestion?->value ?? 'proprietaire'); @endphp
                    <div class="panel">
                        <h2 id="t-gestion">Qui accueille vos clients ?</h2>
                        <div class="role-choice mt-2" role="radiogroup" aria-labelledby="t-gestion">
                            @foreach (\App\Enums\ModeGestion::cases() as $mode)
                                <div class="slot">
                                    <input type="radio" name="mode_gestion" id="g-{{ $mode->value }}" value="{{ $mode->value }}" @checked($modeChoisi === $mode->value)>
                                    <label for="g-{{ $mode->value }}">
                                        <strong><i class="bi {{ $mode === \App\Enums\ModeGestion::Vivi ? 'bi-shield-check' : 'bi-person' }}" aria-hidden="true"></i>{{ $mode->choix() }}</strong>
                                        <span>{{ $mode->aide() }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @if ($l->estGereParVivi())
                            <p class="form-text mb-0 mt-2">
                                @if ($l->gerant_vivi_id)
                                    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Votre gérant VIVI : <strong>{{ $l->gerantVivi?->name }}</strong>.
                                @else
                                    <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Demande reçue{{ $l->gestion_vivi_demandee_le ? ' le '.$l->gestion_vivi_demandee_le->translatedFormat('j F') : '' }} : VIVI vous attribue un gérant très bientôt.
                                @endif
                                <span class="d-block"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{!! $l->libelleGestion() ? 'Rémunération de VIVI : <strong>'.e($l->libelleGestion()).'</strong>'.($l->gestion_contrat ? ' · contrat '.e($l->gestion_contrat) : '').'.' : 'La rémunération de VIVI (pourcentage ou prix fixe) sera fixée avec vous par contrat.' !!}</span>
                            </p>
                        @endif
                    </div>

                    <div class="panel">
                        <h2 id="t-equip">Équipements</h2>
                        <div class="row g-1 mt-1" role="group" aria-labelledby="t-equip">
                            @foreach ($equipements as $eq)
                                <div class="col-6 col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="eq-{{ $eq->id }}" name="equipements[]" value="{{ $eq->id }}" @checked(in_array($eq->id, $equipementsChoisis, true))>
                                        <label class="form-check-label" for="eq-{{ $eq->id }}">{{ $eq->nom }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ===== Tarifs et créneaux ===== --}}
                <div class="tab-pane fade {{ $onglet === 'tarifs' ? 'show active' : '' }}" id="p-tarifs" role="tabpanel" aria-labelledby="o-tarifs" tabindex="0">
                    <p class="text-soft">Activez les formules que vous proposez pour ce logement, puis fixez vos prix et vos horaires.</p>

                    {{-- À l'heure --}}
                    <section class="formule-card {{ $actif(Formule::Heure) ? '' : 'is-off' }}" data-formule="heure">
                        <div class="formule-card__head">
                            <div><h3 id="fh-titre">À l’heure</h3><p>Le client choisit son heure d’arrivée et sa durée.</p></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="tarifs[heure][actif]" value="1" aria-labelledby="fh-titre" @checked($actif(Formule::Heure))></div>
                        </div>
                        <div class="formule-card__body">
                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-3">
                                    <label class="form-label" for="fh-prix">Prix par heure</label>
                                    <div class="input-suffix"><input class="form-control @error('tarifs.heure.prix') is-invalid @enderror" type="number" inputmode="numeric" min="500" step="500" id="fh-prix" name="tarifs[heure][prix]" value="{{ $val('tarifs.heure.prix', $th?->prix) }}"><span class="suffix">FCFA</span>
                                        @error('tarifs.heure.prix')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                </div>
                                <div class="col-6 col-lg-3"><label class="form-label" for="fh-de">Ouverture</label>
                                    <select class="form-select" id="fh-de" name="tarifs[heure][heure_ouverture]">
                                        @foreach ($heures(5, 14) as $h)<option @selected($val('tarifs.heure.heure_ouverture', $hh($th?->heure_ouverture) ?? '08:00') === $h)>{{ $h }}</option>@endforeach
                                    </select></div>
                                <div class="col-6 col-lg-3"><label class="form-label" for="fh-a">Fermeture</label>
                                    <select class="form-select @error('tarifs.heure.heure_fermeture') is-invalid @enderror" id="fh-a" name="tarifs[heure][heure_fermeture]">
                                        @foreach ($heures(15, 24) as $h)<option value="{{ $h }}" @selected($val('tarifs.heure.heure_fermeture', $hh($th?->heure_fermeture) ?? '22:00') === $h)>{{ $h === '00:00' ? 'Minuit' : $h }}</option>@endforeach
                                    </select>
                                    @error('tarifs.heure.heure_fermeture')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6 col-lg-3"><label class="form-label" for="fh-min">Durée minimale</label>
                                    <select class="form-select" id="fh-min" name="tarifs[heure][duree_minimum_heures]">
                                        @foreach ([1, 2, 3, 4] as $n)<option value="{{ $n }}" @selected((int) $val('tarifs.heure.duree_minimum_heures', $th?->duree_minimum_heures ?? 2) === $n)>{{ $n }} heure{{ $n > 1 ? 's' : '' }}</option>@endforeach
                                    </select></div>
                            </div>
                        </div>
                    </section>

                    {{-- À la demi-journée --}}
                    <section class="formule-card {{ $actif(Formule::DemiJournee) ? '' : 'is-off' }}" data-formule="demi">
                        <div class="formule-card__head">
                            <div><h3 id="fd-titre">À la demi-journée</h3><p>Vous définissez vos propres créneaux : nom, début et fin.</p></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="tarifs[demi_journee][actif]" value="1" aria-labelledby="fd-titre" @checked($actif(Formule::DemiJournee))></div>
                        </div>
                        <div class="formule-card__body">
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6 col-lg-4">
                                    <label class="form-label" for="fd-prix">Prix du créneau</label>
                                    <div class="input-suffix"><input class="form-control @error('tarifs.demi_journee.prix') is-invalid @enderror" type="number" inputmode="numeric" min="500" step="500" id="fd-prix" name="tarifs[demi_journee][prix]" value="{{ $val('tarifs.demi_journee.prix', $tarif(Formule::DemiJournee)?->prix) }}"><span class="suffix">FCFA</span>
                                        @error('tarifs.demi_journee.prix')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                </div>
                            </div>
                            <div id="fd-liste">
                                @foreach ($creneaux as $i => $c)
                                    <div class="creneau-row">
                                        <input type="hidden" name="creneaux[{{ $i }}][id]" value="{{ $c['id'] ?? '' }}">
                                        <div class="creneau-row__champs">
                                            <div><label class="form-label" for="cn-{{ $i }}">Nom du créneau</label><input class="form-control @error('creneaux.'.$i.'.nom') is-invalid @enderror" type="text" id="cn-{{ $i }}" name="creneaux[{{ $i }}][nom]" value="{{ $c['nom'] }}"></div>
                                            <div><label class="form-label" for="cd-{{ $i }}">De</label><select class="form-select" id="cd-{{ $i }}" name="creneaux[{{ $i }}][heure_debut]">@foreach ($heures(5, 23) as $h)<option @selected($c['heure_debut'] === $h)>{{ $h }}</option>@endforeach</select></div>
                                            <div><label class="form-label" for="ca-{{ $i }}">À</label><select class="form-select @error('creneaux.'.$i.'.heure_fin') is-invalid @enderror" id="ca-{{ $i }}" name="creneaux[{{ $i }}][heure_fin]">@foreach ($heures(6, 26) as $h)<option @selected($c['heure_fin'] === $h)>{{ $h }}</option>@endforeach</select>
                                                @error('creneaux.'.$i.'.heure_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        </div>
                                        <button type="button" class="btn-icon" aria-label="Supprimer ce créneau"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-ghost btn-sm mt-2" type="button" id="fd-ajouter"><i class="bi bi-plus-lg" aria-hidden="true"></i>Ajouter un créneau</button>
                            <p class="form-text mb-0 mt-2">Un créneau peut finir après minuit (par exemple 20:00 à 01:00).</p>
                        </div>
                    </section>

                    {{-- À la journée --}}
                    <section class="formule-card {{ $actif(Formule::Journee) ? '' : 'is-off' }}" data-formule="jour">
                        <div class="formule-card__head">
                            <div><h3 id="fj-titre">À la journée</h3><p id="fj-aide" data-logement="Une ou plusieurs journées, du jour d’arrivée au lendemain." data-salle="Une ou plusieurs journées. La salle se libère le jour même ; une fin avant l’heure de début signifie après minuit.">{{ $estSalle ? 'Une ou plusieurs journées. La salle se libère le jour même ; une fin avant l’heure de début signifie après minuit.' : 'Une ou plusieurs journées, du jour d’arrivée au lendemain.' }}</p></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="tarifs[journee][actif]" value="1" aria-labelledby="fj-titre" @checked($actif(Formule::Journee))></div>
                        </div>
                        <div class="formule-card__body">
                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-4">
                                    <label class="form-label" for="fj-prix">Prix par jour</label>
                                    <div class="input-suffix"><input class="form-control @error('tarifs.journee.prix') is-invalid @enderror" type="number" inputmode="numeric" min="500" step="500" id="fj-prix" name="tarifs[journee][prix]" value="{{ $val('tarifs.journee.prix', $tj?->prix) }}"><span class="suffix">FCFA</span>
                                        @error('tarifs.journee.prix')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                </div>
                                <div class="col-6 col-lg-4"><label class="form-label" for="fj-arrivee">{{ $estSalle ? 'Début' : 'Arrivée à partir de' }}</label>
                                    <select class="form-select" id="fj-arrivee" name="tarifs[journee][heure_arrivee]">@foreach ($heures(6, 20) as $h)<option @selected($val('tarifs.journee.heure_arrivee', $hh($tj?->heure_arrivee) ?? '12:00') === $h)>{{ $h }}</option>@endforeach</select></div>
                                <div class="col-6 col-lg-4"><label class="form-label" for="fj-depart">{{ $estSalle ? 'Fin, le même jour' : 'Départ avant, le lendemain' }}</label>
                                    <select class="form-select" id="fj-depart" name="tarifs[journee][heure_depart]">@foreach ($heures(0, 23) as $h)<option @selected($val('tarifs.journee.heure_depart', $hh($tj?->heure_depart) ?? ($estSalle ? '22:00' : '11:00')) === $h)>{{ $h }}</option>@endforeach</select></div>
                            </div>
                        </div>
                    </section>

                    <div class="panel mt-3">
                        <label class="form-label" for="f-battement">Temps de préparation entre deux réservations</label>
                        <select class="form-select" id="f-battement" name="temps_preparation_minutes" style="max-width:260px" aria-describedby="f-battement-aide">
                            @foreach ([0 => 'Aucun', 30 => '30 minutes', 60 => '1 heure', 120 => '2 heures'] as $min => $libelle)
                                <option value="{{ $min }}" @selected((int) $val('temps_preparation_minutes', $l->temps_preparation_minutes ?? 60) === $min)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <p class="form-text mb-0 mt-2" id="f-battement-aide">Ce temps est bloqué après chaque départ, pour le ménage. Aucun client ne peut réserver pendant ce temps.</p>
                    </div>

                    {{-- Part de l'agent marketing : prise sur la location ; sans agent, elle revient à VIVI --}}
                    @php $mType = $val('marketing_type', $l->marketing_type?->value); @endphp
                    <div class="panel mt-3" aria-labelledby="t-marketing">
                        <h2 id="t-marketing" class="h6 mb-1">Part de l’agent marketing</h2>
                        <p class="text-soft small mb-2">Vos agents partagent le lien de ce logement. Pour chaque séjour réservé par leur lien, cette part de la location leur est versée au démarrage du séjour. Si le client réserve sans lien d’agent, elle revient à VIVI.</p>
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-6">
                                <label class="form-label" for="f-mtype">Calcul</label>
                                <select class="form-select @error('marketing_type') is-invalid @enderror" id="f-mtype" name="marketing_type">
                                    <option value="" @selected(! $mType)>Aucune part marketing</option>
                                    <option value="pourcentage" @selected($mType === 'pourcentage')>Pourcentage de la location</option>
                                    <option value="fixe" @selected($mType === 'fixe')>Montant fixe par réservation</option>
                                </select>
                                @error('marketing_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="f-mvaleur">Valeur <span class="text-soft fw-normal">(% ou FCFA)</span></label>
                                <input class="form-control @error('marketing_valeur') is-invalid @enderror" type="number" min="0" step="any" id="f-mvaleur" name="marketing_valeur"
                                       value="{{ $val('marketing_valeur', $l->marketing_type ? rtrim(rtrim(number_format((float) $l->marketing_valeur, 2, '.', ''), '0'), '.') : '') }}" placeholder="10">
                                @error('marketing_valeur')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="form-text mb-0 mt-2">Pourcentage : {{ \App\Enums\TypeMarketing::POURCENTAGE_MAX }} % au plus. Montant fixe : jamais plus que le prix de la location. Choisissez vos agents dans <a href="{{ route('pro.agents') }}">Agents marketing</a>.</p>
                    </div>
                </div>

                {{-- ===== Services ===== --}}
                <div class="tab-pane fade {{ $onglet === 'services' ? 'show active' : '' }}" id="p-services" role="tabpanel" aria-labelledby="o-services" tabindex="0">
                    <div class="panel">
                        <h2>Services à la carte</h2>
                        <p class="text-soft mb-2">Activez les services que vous pouvez assurer dans ce logement. Le client les ajoute au moment de réserver.</p>
                        @foreach ($services as $s)
                            @php $on = in_array($s->id, $servicesChoisis, true); @endphp
                            <div class="service-row{{ $on ? ' is-on' : '' }}">
                                <span class="service-tile__icon"><i class="bi {{ $s->icone ?: 'bi-bag-check' }}" aria-hidden="true"></i></span>
                                <div><h3 id="sv-{{ $s->id }}">{{ $s->nom }}</h3>@if ($s->description)<p>{{ $s->description }}</p>@endif
                                    @if ($beneficiaireService($s) === 'proprietaire')
                                        <span class="chip chip--success mt-1"><i class="bi bi-wallet2" aria-hidden="true"></i>Le prix vous revient</span>
                                    @else
                                        <span class="chip chip--line mt-1"><i class="bi bi-building" aria-hidden="true"></i>Encaissé par VIVI</span>
                                    @endif
                                </div>
                                <div class="service-row__action"><span class="fw-bold text-ink tnum">{{ Montant::fcfa($s->prix) }} <span class="fw-normal text-soft small">{{ $s->unite }}</span></span>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="services[]" value="{{ $s->id }}" aria-labelledby="sv-{{ $s->id }}" @checked($on)></div></div>
                            </div>
                        @endforeach
                        <div class="note-box mt-3">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <span>Les prix des services sont fixés par VIVI. Ils sont identiques pour tous les logements. {{ $parDefautServices === 'vivi' ? 'Le prix revient à VIVI, sauf pour les services que vous assurez vous-même : contactez VIVI pour les faire noter sur votre logement.' : 'Le prix des services vous revient avec la location, au démarrage du séjour.' }}</span>
                        </div>
                    </div>
                </div>

                {{-- ===== Photos ===== --}}
                <div class="tab-pane fade {{ $onglet === 'photos' ? 'show active' : '' }}" id="p-photos" role="tabpanel" aria-labelledby="o-photos" tabindex="0">
                    <div class="panel">
                        <h2>Photos</h2>
                        @if ($nouveau)
                            <p class="text-soft mb-0">Enregistrez d’abord le logement : vous pourrez ensuite ajouter vos photos.</p>
                        @else
                            <p class="text-soft">Des photos nettes de chaque pièce, prises de jour. La première est celle des résultats de recherche.</p>
                            @error('photos')<div class="note-box note-box--orange mb-3"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>@enderror
                            @error('photos.*')<div class="note-box note-box--orange mb-3"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>@enderror
                            <div class="photo-grid">
                                @foreach ($l->photos as $i => $p)
                                    <figure>
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($p->chemin) }}" alt="Photo {{ $i + 1 }}" width="640" height="480" loading="lazy">
                                        <figcaption>
                                            @if ($i === 0)
                                                <span class="chip chip--dark">Photo principale</span>
                                            @else
                                                <button class="chip chip--dark border-0" type="submit" formnovalidate formaction="{{ route('pro.logements.photos.principale', [$l, $p->id]) }}">Mettre en premier</button>
                                            @endif
                                            <button class="btn-icon btn-icon--photo" type="submit" formnovalidate formaction="{{ route('pro.logements.photos.supprimer', [$l, $p->id]) }}"
                                                    aria-label="Supprimer la photo {{ $i + 1 }}" data-confirmer="La photo {{ $i + 1 }} sera supprimée définitivement de l’annonce." data-confirmer-titre="Supprimer cette photo ?" data-confirmer-bouton="Supprimer" data-confirmer-style="danger"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        </figcaption>
                                    </figure>
                                @endforeach
                                <label class="ajout" for="ph-fichiers"><i class="bi bi-camera" style="font-size:1.4rem" aria-hidden="true"></i>Ajouter des photos</label>
                            </div>
                            <input class="visually-hidden" type="file" id="ph-fichiers" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                            <button class="d-none" type="submit" id="ph-envoyer" formnovalidate formenctype="multipart/form-data" formaction="{{ route('pro.logements.photos', $l) }}">Envoyer</button>
                            <p class="form-text mb-0 mt-2">JPEG, PNG ou WebP, 800 × 600 pixels au moins, 5 Mo au plus par photo.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="savebar">
                <p id="ed-etat" role="status">{{ $nouveau ? 'Remplissez les informations, les tarifs et les services, puis enregistrez.' : 'Aucune modification en attente.' }}</p>
                <div class="d-flex gap-2">
                    @if (! $nouveau && $l->estVisible())
                        <a class="btn btn-ghost d-none d-sm-inline-flex" href="{{ route('logement', $l->slug) }}">Voir l’annonce</a>
                    @elseif (! $nouveau)
                        <a class="btn btn-ghost d-none d-sm-inline-flex" href="{{ route('logement', $l->slug) }}">Aperçu</a>
                    @endif
                    <button class="btn btn-cta" type="submit">{{ $nouveau ? 'Enregistrer et continuer' : 'Enregistrer' }}</button>
                </div>
            </div>
        </form>
    </div>

    <template id="modele-creneau">
        <div class="creneau-row">
            <input type="hidden" data-champ="id" value="">
            <div class="creneau-row__champs">
                <div><label class="form-label">Nom du créneau</label><input class="form-control" type="text" data-champ="nom" value="Soirée"></div>
                <div><label class="form-label">De</label><select class="form-select" data-champ="heure_debut">@foreach ($heures(5, 23) as $h)<option @selected($h === '20:00')>{{ $h }}</option>@endforeach</select></div>
                <div><label class="form-label">À</label><select class="form-select" data-champ="heure_fin">@foreach ($heures(6, 26) as $h)<option @selected($h === '00:00')>{{ $h }}</option>@endforeach</select></div>
            </div>
            <button type="button" class="btn-icon" aria-label="Supprimer ce créneau"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </div>
    </template>

    @push('head')
        @vite('resources/js/carte.js')
    @endpush

    @push('scripts')
        <script>
            // Type « Résidence » : affiche le nombre de chambres à louer
            (function () {
                var type = document.getElementById('i-type'), bloc = document.getElementById('bloc-unites'), champ = document.getElementById('i-unites');
                if (!type || !bloc) { return; }
                type.addEventListener('change', function () {
                    var residence = type.value === 'residence';
                    var salle = type.selectedOptions[0] && type.selectedOptions[0].dataset.salle === '1';
                    if (residence || (+champ.value > 1 && !salle)) { bloc.hidden = false; champ.disabled = false; }
                    if (residence && +champ.value < 2) { champ.value = 2; }
                    if (salle || (!residence && +champ.value <= 1)) { bloc.hidden = true; champ.disabled = true; }

                    // Salle : capacité en personnes (jusqu'à {{ TypeLogement::CAPACITE_MAX_SALLE }}), ni chambres ni lits
                    var capacite = document.getElementById('i-capacite');
                    capacite.max = salle ? {{ TypeLogement::CAPACITE_MAX_SALLE }} : {{ TypeLogement::CAPACITE_MAX_LOGEMENT }};
                    document.querySelector('label[for="i-capacite"]').textContent = salle ? 'Capacité (personnes)' : 'Voyageurs';
                    document.querySelector('label[for="i-salles_de_bain"]').textContent = salle ? 'Toilettes' : 'Salles de bain';
                    // Journée : une salle se libère le jour même
                    document.querySelector('label[for="fj-arrivee"]').textContent = salle ? 'Début' : 'Arrivée à partir de';
                    document.querySelector('label[for="fj-depart"]').textContent = salle ? 'Fin, le même jour' : 'Départ avant, le lendemain';
                    var aideJour = document.getElementById('fj-aide');
                    aideJour.textContent = salle ? aideJour.dataset.salle : aideJour.dataset.logement;
                    ['chambres', 'lits'].forEach(function (c) {
                        document.querySelector('[data-champ-capacite="' + c + '"]').hidden = salle;
                    });
                });
            })();
        </script>
        <script>
            (function () {
                var $ = function (id) { return document.getElementById(id); };
                var form = $('form-logement'), liste = $('fd-liste');

                // Onglet courant, gardé après l'enregistrement
                document.querySelectorAll('[data-onglet]').forEach(function (b) {
                    b.addEventListener('shown.bs.tab', function () { $('onglet-courant').value = b.dataset.onglet; });
                });

                // Formules : la carte s'estompe quand la formule est désactivée
                form.addEventListener('change', function (e) {
                    var tete = e.target.closest('.formule-card__head');
                    if (tete) { tete.parentNode.classList.toggle('is-off', !e.target.checked); }
                    var service = e.target.closest('.service-row');
                    if (service) { service.classList.toggle('is-on', e.target.checked); }
                    $('ed-etat').textContent = 'Modifications non enregistrées.';
                });
                form.addEventListener('input', function () { $('ed-etat').textContent = 'Modifications non enregistrées.'; });

                // Créneaux : renumérotés à chaque ajout ou suppression (1 à 4 créneaux)
                function renumeroter() {
                    var lignes = liste.querySelectorAll('.creneau-row');
                    lignes.forEach(function (ligne, i) {
                        ligne.querySelectorAll('input, select').forEach(function (champ) {
                            var nom = champ.dataset.champ || (champ.name.match(/\]\[(\w+)\]$/) || [])[1];
                            if (!nom) { return; }
                            champ.dataset.champ = nom;
                            champ.name = 'creneaux[' + i + '][' + nom + ']';
                            if (champ.type !== 'hidden') { champ.id = 'c' + nom + '-' + i; champ.previousElementSibling && (champ.previousElementSibling.htmlFor = champ.id); }
                        });
                        ligne.querySelector('.btn-icon').disabled = lignes.length <= 1;
                    });
                    $('fd-ajouter').disabled = lignes.length >= 4;
                }
                $('fd-ajouter').addEventListener('click', function () {
                    liste.appendChild($('modele-creneau').content.cloneNode(true));
                    renumeroter();
                    liste.lastElementChild.querySelector('input[type=text]').focus();
                });
                liste.addEventListener('click', function (e) {
                    var b = e.target.closest('.btn-icon');
                    if (!b || b.disabled) { return; }
                    b.closest('.creneau-row').remove();
                    renumeroter();
                });
                renumeroter();

                // Photos : envoi dès qu'elles sont choisies
                var fichiers = $('ph-fichiers');
                if (fichiers) {
                    fichiers.addEventListener('change', function () {
                        if (fichiers.files.length) { $('ed-etat').textContent = 'Envoi des photos…'; $('ph-envoyer').click(); }
                    });
                }
            })();
        </script>
    @endpush
</x-layouts.pro>
