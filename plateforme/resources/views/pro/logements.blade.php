{{-- Espace propriétaire : mes logements (maquette pro-logements.html) --}}
@php
    use App\Enums\StatutLogement;
    use App\Models\Reservation;
    use App\Support\Montant;

    $etats = [
        StatutLogement::Publie->value => ['Publié', 'chip--success', 'bi-check-circle'],
        StatutLogement::EnValidation->value => ['En attente de validation', 'chip--orange', 'bi-hourglass-split'],
        StatutLogement::Brouillon->value => ['Brouillon', 'chip--line', 'bi-pencil'],
        StatutLogement::Refuse->value => ['À corriger', 'chip--orange', 'bi-exclamation-triangle'],
        StatutLogement::Suspendu->value => ['Suspendu par VIVI', 'chip--line', 'bi-pause-circle'],
    ];
@endphp

<x-layouts.pro titre="Logements et salles" page="logements">
    <div class="pro-head">
        <div>
            <h1>Logements et salles</h1>
            <p>Chaque logement a ses propres formules, tarifs et créneaux.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-ghost" href="{{ route('pro.logements.create', ['type' => 'salle']) }}"><i class="bi bi-balloon" aria-hidden="true"></i>Ajouter une salle</a>
            <a class="btn btn-ghost" href="{{ route('pro.logements.create', ['type' => 'residence']) }}"><i class="bi bi-buildings" aria-hidden="true"></i>Ajouter une résidence</a>
            <a class="btn btn-primary" href="{{ route('pro.logements.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i>Ajouter un logement</a>
        </div>
    </div>

    <section class="panel mb-3" aria-label="Logements inclus dans l’abonnement">
        <div class="quota">
            <div style="min-width:0">
                @if ($quota['max'] !== null)
                    <strong class="text-ink">{{ $quota['utilises'] }} {{ $quota['utilises'] > 1 ? 'logements publiés ou en vérification' : 'logement publié ou en vérification' }} sur {{ $quota['max'] >= 999 ? 'illimité' : $quota['max'] }}</strong>
                    <div class="small text-soft">{{ ($quota['commission'] ?? false) ? 'Sans abonnement : vous êtes sur commission sur retrait.' : 'Inclus dans votre formule '.$quota['formule'].'.' }}</div>
                @else
                    <strong class="text-ink">Aucun abonnement actif</strong>
                    <div class="small text-soft">Un abonnement est nécessaire pour publier vos logements.</div>
                @endif
            </div>
            <div class="meter" aria-hidden="true"><i style="width:{{ $quota['max'] && $quota['max'] < 999 ? min(100, $quota['utilises'] / $quota['max'] * 100) : 0 }}%"></i></div>
            <a class="btn btn-ghost btn-sm" href="{{ route('pro.abonnement') }}">{{ ($quota['commission'] ?? false) ? 'Conditions' : 'Abonnement' }}</a>
        </div>
        {{-- Quota séparé pour les salles (fêtes, conférences, plein air, espaces complets) --}}
        @if ($quota['salles']['max'] !== null)
            <div class="quota mt-2">
                <div style="min-width:0">
                    <strong class="text-ink">{{ $quota['salles']['utilises'] }} salle{{ $quota['salles']['utilises'] > 1 ? 's' : '' }} sur {{ $quota['salles']['max'] >= 999 ? 'illimité' : $quota['salles']['max'] }}</strong>
                    <div class="small text-soft">Salles de fête, de conférence ou espaces événementiels.</div>
                </div>
                <div class="meter" aria-hidden="true"><i style="width:{{ min(100, $quota['salles']['utilises'] / max(1, $quota['salles']['max']) * 100) }}%"></i></div>
            </div>
        @endif
    </section>

    <div class="d-grid gap-3">
        @forelse ($logements as $l)
            @php
                $e = $etats[$l->statut->value];
                $publie = $l->statut === StatutLogement::Publie;
                $photo = $l->urlPhoto();
            @endphp
            <article class="pro-listing">
                @if ($photo)
                    <img src="{{ $photo }}" alt="" width="640" height="480" loading="lazy">
                @else
                    <a class="pro-listing__sans-photo" href="{{ route('pro.logements.edit', ['logement' => $l, 'onglet' => 'photos']) }}"><i class="bi bi-camera" aria-hidden="true"></i>Ajouter des photos</a>
                @endif
                <div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="chip {{ $e[1] }}"><i class="bi {{ $e[2] }}" aria-hidden="true"></i>{{ $e[0] }}</span>
                        <span class="chip chip--line">{{ \Illuminate\Support\Str::before($l->type->getLabel(), ' (') }}</span>
                    </div>
                    <h2>{{ $l->titre }}</h2>
                    <p class="mb-0 text-soft small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $l->quartier }}, {{ $l->ville }} · {{ $l->personnes() }}</p>
                    <ul class="pro-listing__prix">
                        @foreach ($l->formulesProposees() as $f)
                            <li>{{ $f->court() }} <b>{{ Montant::fcfa($l->tarif($f)->prix) }}</b></li>
                        @endforeach
                    </ul>
                    @if ($l->statut === StatutLogement::EnValidation)
                        <div class="note-box note-box--orange mt-2" style="font-size:.9rem;padding:10px 12px"><i class="bi bi-info-circle" aria-hidden="true"></i><span>VIVI vérifie les informations et les photos avant la mise en ligne.</span></div>
                    @elseif ($l->statut === StatutLogement::Refuse)
                        <div class="note-box note-box--orange mt-2" style="font-size:.9rem;padding:10px 12px"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>À corriger : {{ $l->motif_refus ?: 'voir avec VIVI' }}. Enregistrez vos corrections pour le renvoyer.</span></div>
                    @elseif ($l->statut === StatutLogement::Brouillon)
                        <div class="note-box mt-2" style="font-size:.9rem;padding:10px 12px"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Brouillon : choisissez un abonnement pour l’envoyer à la vérification.</span></div>
                    @endif
                </div>
                <div class="pro-listing__actions">
                    <form method="POST" action="{{ route('pro.logements.en-ligne', $l) }}" class="form-check form-switch" data-interrupteur-ligne
                          data-titre-logement="{{ $l->titre }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="en_ligne" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="ligne-{{ $l->id }}" name="en_ligne" value="1"
                               @checked($l->en_ligne && $publie) @disabled(! $publie)>
                        <label class="form-check-label" for="ligne-{{ $l->id }}">{{ $publie ? 'Visible dans la recherche' : 'Pas encore visible' }}</label>
                    </form>
                    <a class="btn btn-primary btn-sm" href="{{ route('pro.logements.edit', $l) }}"><i class="bi bi-pencil" aria-hidden="true"></i>Modifier</a>
                    <a class="btn btn-ghost btn-sm" href="{{ route('pro.calendrier', ['logement' => $l->id]) }}"><i class="bi bi-calendar3" aria-hidden="true"></i>Calendrier</a>
                    @if ($publie)
                        <a class="btn btn-ghost btn-sm" href="{{ route('logement', $l->slug) }}"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Voir l’annonce</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state" style="background:#fff">
                <i class="bi bi-houses" aria-hidden="true"></i>
                <h2 class="h4 mt-2">Vous n’avez pas encore de logement</h2>
                <p class="text-soft">Ajoutez votre premier logement : VIVI le vérifie avant de le mettre en ligne.</p>
                <a class="btn btn-primary" href="{{ route('pro.logements.create') }}">Ajouter un logement</a>
            </div>
        @endforelse
    </div>
    @push('scripts')
        <script>
            // « Visible dans la recherche » : masquer un logement demande confirmation ; le remettre en ligne, non
            document.querySelectorAll('[data-interrupteur-ligne]').forEach(function (form) {
                var interrupteur = form.querySelector('input[type=checkbox]');
                interrupteur.addEventListener('change', function () {
                    if (interrupteur.checked) {
                        delete form.dataset.confirmer;
                    } else {
                        form.dataset.confirmer = '« ' + form.dataset.titreLogement + ' » ne sera plus proposé aux clients. Les réservations déjà payées restent valables ; vous pourrez le remettre en ligne à tout moment.';
                        form.dataset.confirmerTitre = 'Masquer ce logement ?';
                        form.dataset.confirmerBouton = 'Masquer';
                        form.dataset.confirmerStyle = 'danger';
                    }
                    form.requestSubmit();
                });
                // Confirmation annulée : l'interrupteur revient en position « visible »
                form.addEventListener('vivi:confirmation-annulee', function () { interrupteur.checked = true; });
            });
        </script>
    @endpush
</x-layouts.pro>
