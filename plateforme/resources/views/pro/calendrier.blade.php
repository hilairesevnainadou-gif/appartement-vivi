{{-- Espace propriétaire : calendrier d'un logement (maquette pro-calendrier.html), rendu par le serveur --}}
@php
    use App\Enums\Formule;
    use App\Enums\StatutReservation;
    use App\Models\Reservation;
    use App\Support\Montant;
    use App\Support\Parametres;

    $libelles = ['libre' => 'libre', 'partiel' => 'réservé en partie', 'complet' => 'complet', 'bloque' => 'bloqué'];
    $lien = fn (array $p) => route('pro.calendrier', array_merge(['logement' => $logement?->id, 'mois' => $mois->format('Y-m'), 'jour' => $jour->toDateString()], $p));
    $etatJour = $jours->first(fn ($j) => $j['date']->isSameDay($jour))['etat'] ?? ($blocage ? 'bloque' : ($duJour->isEmpty() ? 'libre' : 'partiel'));
    $pastilles = [
        StatutReservation::Confirmee->value => ['Attendu', 'chip--orange', 'bi-hourglass-split'],
        StatutReservation::EnCours->value => ['Séjour en cours', 'chip--success', 'bi-door-open'],
        StatutReservation::Terminee->value => ['Terminé', 'chip--line', 'bi-flag'],
        StatutReservation::Consommee->value => ['Client absent', 'chip--line', 'bi-person-x'],
    ];
    $delai = (int) Parametres::get('report_delai_minimum_heures');
    $peutAjouter = $logement && $logement->tarifs->where('actif', true)->isNotEmpty();
@endphp

<x-layouts.pro titre="Calendrier" page="calendrier">
    <div class="pro-head">
        <div>
            <h1>Calendrier</h1>
            <p>Réservations et jours bloqués, logement par logement.</p>
        </div>
        @if ($logements->count() > 1)
            <form method="GET" action="{{ route('pro.calendrier') }}" style="min-width:min(100%, 280px)">
                <label class="form-label" for="cal-logement">Logement</label>
                <select class="form-select" id="cal-logement" name="logement" onchange="this.form.submit()">
                    @foreach ($logements as $l)
                        <option value="{{ $l->id }}" @selected($l->id === $logement?->id)>{{ $l->titre }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        @if ($peutAjouter)
            <button class="btn btn-primary align-self-end" type="button" data-bs-toggle="modal" data-bs-target="#modal-manuelle"><i class="bi bi-calendar-plus" aria-hidden="true"></i>Ajouter une réservation</button>
        @endif
    </div>

    @error('calendrier')
        <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
    @enderror

    @if (! $logement)
        <div class="empty-state" style="background:#fff">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <h2 class="h4 mt-2">Aucun logement</h2>
            <p class="text-soft mb-0">Le calendrier s’affiche dès que vous avez un logement.</p>
        </div>
    @else
        <div class="row g-3">
            {{-- ===== Mois ===== --}}
            <div class="col-lg-5">
                <section class="panel" aria-labelledby="cal-mois">
                    <div class="cal__head">
                        <h2 id="cal-mois">{{ $mois->translatedFormat('F Y') }}</h2>
                        <div class="cal__nav">
                            <a href="{{ $lien(['mois' => $mois->subMonth()->format('Y-m')]) }}" class="{{ $mois->lte($aujourdhui->startOfMonth()) ? 'is-off' : '' }}" aria-label="Mois précédent"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                            <a href="{{ $lien(['mois' => $mois->addMonth()->format('Y-m')]) }}" aria-label="Mois suivant"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    <div class="cal__grid">
                        @foreach (['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'] as $j)
                            <div class="cal__dow" aria-hidden="true">{{ $j }}</div>
                        @endforeach
                        @for ($v = 0; $v < $mois->startOfMonth()->dayOfWeekIso - 1; $v++)<span></span>@endfor
                        @foreach ($jours as $j)
                            <a class="cal__day{{ $j['date']->isToday() ? ' is-today' : '' }}{{ $j['passe'] ? ' is-passe' : '' }}" data-etat="{{ $j['passe'] ? 'libre' : $j['etat'] }}"
                               href="{{ $lien(['jour' => $j['date']->toDateString()]) }}" @if ($j['date']->isSameDay($jour)) aria-current="date" @endif
                               aria-label="{{ $j['date']->translatedFormat('l j F') }}, {{ $libelles[$j['etat']] }}">
                                {{ $j['date']->day }}<span class="etat" aria-hidden="true"></span>
                            </a>
                        @endforeach
                    </div>
                    <ul class="cal__legend" aria-label="Légende">
                        <li><i style="background:var(--vivi-orange-400)"></i>Réservé en partie</li>
                        <li><i style="background:var(--vivi-teal-700)"></i>Complet</li>
                        <li><i style="background:var(--vivi-muted)"></i>Bloqué</li>
                    </ul>
                </section>
            </div>

            {{-- ===== Journée choisie ===== --}}
            <div class="col-lg-7">
                <section class="panel" aria-labelledby="cal-jour">
                    <div class="panel__head">
                        <h2 id="cal-jour" class="premiere-majuscule">{{ $jour->translatedFormat('l j F Y') }}</h2>
                        @php $chips = ['libre' => ['Libre', 'chip--success'], 'partiel' => ['Réservé en partie', 'chip--orange'], 'complet' => ['Complet', 'chip--teal'], 'bloque' => ['Bloqué', 'chip--line']]; @endphp
                        <span class="chip {{ $chips[$etatJour][1] }}">{{ $chips[$etatJour][0] }}</span>
                    </div>
                    <ul class="agenda">
                        @foreach ($duJour as $r)
                            @php
                                $p = $pastilles[$r->statut->value];
                                $services = $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ');
                                $premierJour = $r->debut->isSameDay($jour);
                                // Déplacement réservé au propriétaire : client absent, ou arrivée à moins de 3 h
                                $deplacable = $r->statut === StatutReservation::Consommee
                                    || ($r->statut === StatutReservation::Confirmee && ! $r->reportPossible());
                            @endphp
                            <li class="resa">
                                <div class="h">
                                    @if ($r->formule === Formule::Journee)
                                        {!! $premierJour ? e($r->debut->format('H:i')).'<small>arrivée</small>' : 'Journée<small>sur place</small>' !!}
                                    @else
                                        {{ $r->debut->format('H:i') }}<small>→ {{ $r->fin->format('H:i') }}</small>
                                    @endif
                                </div>
                                <div>
                                    <strong>{{ $r->client->name }}{{ $r->suffixePersonnes() }}</strong>
                                    @if ($r->estManuelle())
                                        <span class="d"><span class="chip chip--line" style="font-size:.75rem"><i class="bi bi-telephone" aria-hidden="true"></i>Hors plateforme</span>{{ $r->client->telephone ? ' · '.\App\Support\Telephone::lisible($r->client->telephone) : '' }}{{ $r->note_interne ? ' · '.$r->note_interne : '' }}</span>
                                        <span class="montants-manuels">
                                            <span>À payer <strong>{{ Montant::fcfa($r->montant_total) }}</strong></span>
                                            <span>Encaissé <strong>{{ Montant::fcfa((int) $r->montant_encaisse) }}</strong></span>
                                            @if ($r->resteAPayer() > 0)
                                                <span class="reste">Reste <strong>{{ Montant::fcfa($r->resteAPayer()) }}</strong></span>
                                            @else
                                                <span class="solde"><i class="bi bi-check-circle" aria-hidden="true"></i>Payé</span>
                                            @endif
                                        </span>
                                    @endif
                                    <span class="d">{{ $r->formule->getLabel() }}{{ $r->formule === Formule::Journee && $r->nombreJours() > 1 ? ', '.$r->nombreJours().' jours' : '' }} · {{ Montant::fcfa($r->montant_location) }}{{ $services ? ' · '.$services : '' }} · {{ $r->reference }}</span>
                                    @if ($r->debut_initial)<span class="d"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Déplacée (prévue au départ le {{ $r->debut_initial->translatedFormat('j M à H:i') }})</span>@endif
                                </div>
                                <div><span class="chip {{ $p[1] }}"><i class="bi {{ $p[2] }}" aria-hidden="true"></i>{{ $p[0] }}</span></div>
                                @if ($r->estManuelle() && $r->statut !== StatutReservation::Annulee)
                                    <div class="actions-manuelles">
                                        @if ($r->resteAPayer() > 0)
                                            <form method="POST" action="{{ route('pro.calendrier.encaisser', $r) }}" class="encaisser"
                                                  data-confirmer="Enregistrer {montant} FCFA reçus de {{ $r->client->name }} pour {{ $r->reference }} ?" data-confirmer-titre="Encaisser un paiement" data-confirmer-bouton="Encaisser">
                                                @csrf
                                                <label class="visually-hidden" for="enc-{{ $r->id }}">Montant reçu</label>
                                                <div class="input-group input-group-sm">
                                                    <input class="form-control" type="number" inputmode="numeric" id="enc-{{ $r->id }}" name="montant" min="1" max="{{ $r->resteAPayer() }}" step="500" value="{{ $r->resteAPayer() }}" required>
                                                    <span class="input-group-text">FCFA</span>
                                                    <button class="btn btn-primary" type="submit"><i class="bi bi-cash-coin" aria-hidden="true"></i>Encaisser</button>
                                                </div>
                                            </form>
                                        @endif
                                        @if ($r->statut === StatutReservation::Confirmee)
                                            <form method="POST" action="{{ route('pro.calendrier.retirer', $r) }}" class="retirer"
                                                  data-confirmer="La réservation de {{ $r->client->name }} ({{ $r->reference }}) sera retirée et la période redeviendra réservable en ligne." data-confirmer-titre="Retirer du calendrier ?" data-confirmer-bouton="Retirer" data-confirmer-style="danger">
                                                @csrf
                                                <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-x-circle" aria-hidden="true"></i>Retirer du calendrier</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                                @if ($deplacable)
                                    <details class="deplacer" @if ($errors->has('deplacement_'.$r->id)) open @endif>
                                        <summary class="small fw-semibold" style="cursor:pointer;color:var(--vivi-teal-700)">
                                            <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Déplacer cette réservation
                                        </summary>
                                        <form method="POST" action="{{ route('pro.calendrier.deplacer', $r) }}" class="row g-2 align-items-end mt-1"
                                              data-confirmer="La réservation {{ $r->reference }} de {{ $r->client->name }} sera déplacée (même durée). Le client est prévenu." data-confirmer-titre="Déplacer la réservation ?" data-confirmer-bouton="Déplacer">
                                            @csrf
                                            <div class="col-6 col-sm-5"><label class="form-label small" for="dd-{{ $r->id }}">Nouvelle date</label>
                                                <input class="form-control" type="date" id="dd-{{ $r->id }}" name="date" min="{{ $aujourdhui->toDateString() }}" value="{{ old('date', $r->debut->toDateString()) }}" required></div>
                                            <div class="col-6 col-sm-3"><label class="form-label small" for="dh-{{ $r->id }}">Arrivée</label>
                                                <input class="form-control" type="time" id="dh-{{ $r->id }}" name="heure" step="1800" value="{{ old('heure', $r->debut->format('H:i')) }}" required></div>
                                            <div class="col-12 col-sm-4"><button class="btn btn-primary w-100" type="submit">Déplacer</button></div>
                                            @error('deplacement_'.$r->id)<div class="col-12"><p class="small mb-0" style="color:var(--vivi-danger)">{{ $message }}</p></div>@enderror
                                            <div class="col-12"><p class="form-text mb-0">Même durée ({{ $r->dureeCourte() }}). {{ $r->statut === StatutReservation::Consommee ? 'Le client ne s’est pas présenté : seuls vous et VIVI pouvez déplacer.' : 'Arrivée à moins de '.$delai.' h : le client ne peut plus reporter lui-même.' }} Le client est prévenu.</p></div>
                                        </form>
                                    </details>
                                @endif
                            </li>
                        @endforeach

                        @if ($blocage)
                            <li class="bloque"><div class="h">Journée<small>bloquée</small></div><div><strong>Aucune réservation possible</strong><span class="d">{{ $blocage->motif }}</span></div><div></div></li>
                        @elseif ($etatJour !== 'complet' && ! $jour->lt($aujourdhui))
                            <li class="libre"><div class="h">Libre</div><div><strong>{{ $duJour->isEmpty() ? 'Toute la journée' : 'Le reste de la journée' }}</strong><span class="d">Réservable selon les créneaux de ce logement.</span></div><div></div></li>
                        @endif
                    </ul>

                    {{-- Réservation prise hors de la plateforme : formulaire dans la modale #modal-manuelle (bas de page) --}}
                    @if ($peutAjouter)
                        <button class="btn btn-outline-primary mt-3" type="button" data-bs-toggle="modal" data-bs-target="#modal-manuelle"><i class="bi bi-plus-lg" aria-hidden="true"></i>Ajouter une réservation hors plateforme</button>
                    @endif

                    <div class="mt-3">
                        @if ($jour->lt($aujourdhui))
                            <p class="form-text mb-0">Journée passée.</p>
                        @elseif ($blocage)
                            <form method="POST" action="{{ route('pro.calendrier.debloquer', [$logement, $blocage]) }}"
                                  data-confirmer="Les clients pourront de nouveau réserver {{ $blocage->date_debut->isSameDay($blocage->date_fin) ? 'cette journée' : 'cette période' }}." data-confirmer-titre="Débloquer ?" data-confirmer-bouton="Débloquer">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-unlock" aria-hidden="true"></i>Débloquer {{ $blocage->date_debut->isSameDay($blocage->date_fin) ? 'cette journée' : 'cette période' }}</button>
                            </form>
                        @elseif ($duJour->isNotEmpty())
                            <p class="form-text mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>Cette journée ne peut pas être bloquée : elle a déjà des réservations.</p>
                        @else
                            <form method="POST" action="{{ route('pro.calendrier.bloquer', $logement) }}" class="d-flex flex-wrap gap-2 align-items-end"
                                  data-confirmer="Le {{ $jour->translatedFormat('l j F') }} ne sera plus proposé aux clients de {{ $logement->titre }}." data-confirmer-titre="Bloquer cette journée ?" data-confirmer-bouton="Bloquer" data-confirmer-style="danger">
                                @csrf
                                <input type="hidden" name="jour" value="{{ $jour->toDateString() }}">
                                <div style="flex:1 1 220px"><label class="form-label small" for="motif">Motif (facultatif)</label><input class="form-control" type="text" id="motif" name="motif" maxlength="120" placeholder="Travaux, usage personnel…"></div>
                                <button class="btn btn-ghost" type="submit"><i class="bi bi-lock" aria-hidden="true"></i>Bloquer cette journée</button>
                            </form>
                            <p class="form-text mb-0 mt-2">Un jour bloqué n’apparaît plus comme disponible pour les clients.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        {{-- ===== Modale : réservation hors plateforme (téléphone, client sur place) ===== --}}
        @if ($peutAjouter)
            @php
                $formules = $logement->tarifs->where('actif', true)->sortBy(fn ($t) => array_search($t->formule, Formule::cases(), true));
                $formuleChoisie = old('formule', $formules->first()?->formule->value);
                $ouverture = substr((string) ($logement->tarif(Formule::Heure)?->heure_ouverture ?? '08:00'), 0, 5);
                $prochaine = now()->addHour()->startOfHour()->format('H:i');
                $dateProposee = $jour->lt($aujourdhui) ? $aujourdhui : $jour;
                $heureProposee = $dateProposee->isToday() && $prochaine > $ouverture ? $prochaine : $ouverture;
                // Prix du catalogue du logement, pour afficher le montant à payer pendant la saisie
                $prix = $formules->mapWithKeys(fn ($t) => [$t->formule->value => (int) $t->prix]);
                $ouvrir = $errors->has('manuelle') || $errors->hasAny(['nom', 'telephone', 'montant', 'encaisse']) || request('ajouter');
            @endphp
            <div class="modal fade" id="modal-manuelle" tabindex="-1" aria-labelledby="t-manuelle" aria-hidden="true" @if ($ouvrir) data-ouvrir @endif>
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
                    <form class="modal-content" method="POST" action="{{ route('pro.calendrier.reserver', $logement) }}" id="form-manuelle" novalidate
                          data-prix='@json($prix)'>
                        @csrf
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title h5" id="t-manuelle">Réservation hors plateforme</h2>
                                <p class="small text-soft mb-0">{{ $logement->titre }}{{ $logement->unites > 1 ? ' · résidence de '.$logement->unites.' chambres' : '' }}</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <p class="form-text mt-0">Client venu par téléphone ou sur place : la période n’est plus proposée en ligne. Vous encaissez vous-même ; aucune commission VIVI.</p>
                            @error('manuelle')<div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>@enderror

                            <h3 class="modal-section">Client</h3>
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6"><label class="form-label small" for="m-nom">Nom du client *</label>
                                    <input class="form-control @error('nom') is-invalid @enderror" id="m-nom" name="nom" value="{{ old('nom') }}" required maxlength="120" autocomplete="off">
                                    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6"><label class="form-label small" for="m-tel">Téléphone (facultatif)</label>
                                    <input class="form-control @error('telephone') is-invalid @enderror" id="m-tel" name="telephone" type="tel" inputmode="tel" placeholder="01 97 00 00 00" value="{{ old('telephone') }}">
                                    @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            </div>

                            <h3 class="modal-section">Séjour</h3>
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-sm-4"><label class="form-label small" for="m-formule">Formule</label>
                                    <select class="form-select" id="m-formule" name="formule">
                                        @foreach ($formules as $t)<option value="{{ $t->formule->value }}" @selected($formuleChoisie === $t->formule->value)>{{ $t->formule->getLabel() }}</option>@endforeach
                                    </select></div>
                                <div class="col-6 col-sm-4"><label class="form-label small" for="m-date">Date d’arrivée</label>
                                    <input class="form-control" type="date" id="m-date" name="date" min="{{ $aujourdhui->toDateString() }}" value="{{ old('date', $dateProposee->toDateString()) }}" required></div>
                                @unless ($logement->estSalle())
                                    <div class="col-6 col-sm-4"><label class="form-label small" for="m-voy">Voyageurs</label>
                                        <input class="form-control" type="number" id="m-voy" name="voyageurs" min="1" max="{{ $logement->capacite }}" value="{{ old('voyageurs', min(2, $logement->capacite)) }}" required></div>
                                @endunless
                                <div class="col-6 col-sm-4" data-formule="heure"><label class="form-label small" for="m-heure">Heure d’arrivée</label>
                                    <input class="form-control" type="time" id="m-heure" name="heure" step="1800" value="{{ old('heure', $heureProposee) }}"></div>
                                <div class="col-6 col-sm-4" data-formule="heure"><label class="form-label small" for="m-duree">Durée (heures)</label>
                                    <input class="form-control" type="number" id="m-duree" name="duree" min="1" max="24" value="{{ old('duree', max(1, (int) $logement->tarif(Formule::Heure)?->duree_minimum_heures)) }}"></div>
                                <div class="col-12 col-sm-8" data-formule="demi_journee"><label class="form-label small" for="m-creneau">Créneau</label>
                                    <select class="form-select" id="m-creneau" name="creneau_id">
                                        @foreach ($logement->creneaux->sortBy('ordre') as $c)<option value="{{ $c->id }}" @selected((int) old('creneau_id') === $c->id)>{{ $c->nom }} ({{ substr($c->heure_debut, 0, 5) }} – {{ substr($c->heure_fin, 0, 5) }})</option>@endforeach
                                    </select></div>
                                <div class="col-6 col-sm-4" data-formule="journee"><label class="form-label small" for="m-jours">Nombre de jours</label>
                                    <input class="form-control" type="number" id="m-jours" name="jours" min="1" max="30" value="{{ old('jours', 1) }}"></div>
                            </div>

                            <h3 class="modal-section">Paiement</h3>
                            <div class="row g-2">
                                <div class="col-sm-6"><label class="form-label small" for="m-montant">Montant à payer (prix du séjour)</label>
                                    <div class="input-group"><input class="form-control @error('montant') is-invalid @enderror" type="number" inputmode="numeric" id="m-montant" name="montant" min="0" step="500" value="{{ old('montant') }}"><span class="input-group-text">FCFA</span></div>
                                    <p class="form-text mb-0" id="m-tarif">Tarif du logement.</p>
                                    @error('montant')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6"><label class="form-label small" for="m-encaisse">Montant encaissé</label>
                                    <div class="input-group"><input class="form-control @error('encaisse') is-invalid @enderror" type="number" inputmode="numeric" id="m-encaisse" name="encaisse" min="0" step="500" value="{{ old('encaisse', 0) }}"><span class="input-group-text">FCFA</span></div>
                                    <div class="d-flex gap-2 mt-1"><button class="btn btn-link btn-sm p-0" type="button" data-encaisser="tout">Tout encaissé</button><button class="btn btn-link btn-sm p-0" type="button" data-encaisser="rien">Rien encore</button></div>
                                    @error('encaisse')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                                <div class="col-12">
                                    <dl class="recap-encaissement" aria-live="polite">
                                        <div><dt>À payer</dt><dd id="r-total">—</dd></div>
                                        <div><dt>Encaissé</dt><dd id="r-encaisse">—</dd></div>
                                        <div class="reste"><dt>Reste à payer sur place</dt><dd id="r-reste">—</dd></div>
                                    </dl>
                                </div>
                                <div class="col-12"><label class="form-label small" for="m-note">Note (facultatif)</label>
                                    <input class="form-control" id="m-note" name="note" maxlength="255" value="{{ old('note') }}" placeholder="Mode de paiement, heure d’arrivée prévue…"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" type="button" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-calendar-plus" aria-hidden="true"></i>Ajouter au calendrier</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
    @push('scripts')
        <script>
            // Réservation hors plateforme : champs de la formule choisie, montant à payer, encaissé et reste en direct
            (function () {
                var form = document.getElementById('form-manuelle');
                if (!form) { return; }
                var $ = function (id) { return document.getElementById(id); };
                var prix = JSON.parse(form.dataset.prix || '{}');
                var fcfa = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/\s/g, ' ') + ' FCFA'; };
                var montantSaisi = $('m-montant').value !== '';   // le propriétaire a changé le prix : on ne l'écrase plus

                function tarif() {
                    var f = $('m-formule').value, p = prix[f] || 0;
                    if (f === 'heure') { return p * Math.max(1, +$('m-duree').value || 1); }
                    if (f === 'journee') { return p * Math.max(1, +$('m-jours').value || 1); }
                    return p;
                }
                function formule() {
                    form.querySelectorAll('[data-formule]').forEach(function (bloc) {
                        var actif = bloc.dataset.formule === $('m-formule').value;
                        bloc.hidden = !actif;
                        bloc.querySelectorAll('input, select').forEach(function (c) { c.disabled = !actif; });
                    });
                }
                function recalculer() {
                    var t = tarif();
                    $('m-tarif').textContent = 'Tarif du logement : ' + fcfa(t) + '. Modifiable si vous avez convenu d’un autre prix.';
                    if (!montantSaisi) { $('m-montant').value = t; }
                    var total = Math.max(0, +$('m-montant').value || 0), encaisse = Math.max(0, +$('m-encaisse').value || 0);
                    $('r-total').textContent = fcfa(total);
                    $('r-encaisse').textContent = fcfa(encaisse);
                    $('r-reste').textContent = encaisse > total ? 'Encaissé supérieur au prix' : fcfa(total - encaisse);
                    $('m-encaisse').classList.toggle('is-invalid', encaisse > total);
                }
                $('m-montant').addEventListener('input', function () { montantSaisi = this.value !== ''; recalculer(); });
                ['m-formule', 'm-duree', 'm-jours', 'm-encaisse'].forEach(function (id) {
                    $(id).addEventListener('input', recalculer); $(id).addEventListener('change', function () { formule(); recalculer(); });
                });
                form.querySelectorAll('[data-encaisser]').forEach(function (b) {
                    b.addEventListener('click', function () { $('m-encaisse').value = b.dataset.encaisser === 'tout' ? (+$('m-montant').value || 0) : 0; recalculer(); });
                });
                formule(); recalculer();

                // Ouverture automatique après une erreur, ou depuis un lien ?ajouter=1
                var modale = $('modal-manuelle');
                if (modale.hasAttribute('data-ouvrir')) {
                    // app.js (Bootstrap) est un module chargé après ce script : on attend la fin du chargement
                    document.addEventListener('DOMContentLoaded', function () { window.bootstrap.Modal.getOrCreateInstance(modale).show(); });
                }
                modale.addEventListener('shown.bs.modal', function () { $('m-nom').focus(); });
            })();
        </script>
    @endpush
</x-layouts.pro>
