{{-- Espace propriétaire : toutes les réservations, par onglet et par logement --}}
@php
    use App\Enums\StatutReservation;
    use App\Models\Reservation;
    use App\Support\Montant;

    $onglets = ['avenir' => 'À venir', 'passees' => 'Passées', 'annulees' => 'Annulées'];
    $pastilles = [
        StatutReservation::Confirmee->value => ['Attendu', 'chip--orange', 'bi-hourglass-split'],
        StatutReservation::EnCours->value => ['Séjour en cours', 'chip--success', 'bi-door-open'],
        StatutReservation::Terminee->value => ['Terminé', 'chip--line', 'bi-flag'],
        StatutReservation::Consommee->value => ['Client absent', 'chip--line', 'bi-person-x'],
        StatutReservation::Annulee->value => ['Annulée', 'chip--line', 'bi-x-circle'],
    ];
@endphp

<x-layouts.pro titre="Réservations" page="reservations">
    <div class="pro-head">
        <div>
            <h1>Réservations</h1>
            <p>Toutes les réservations de vos logements.</p>
        </div>
        @if ($logements->count() > 1)
            <form method="GET" style="min-width:min(100%, 260px)">
                <input type="hidden" name="onglet" value="{{ $onglet }}">
                <label class="form-label" for="filtre-logement">Logement</label>
                <select class="form-select" id="filtre-logement" name="logement" onchange="this.form.submit()">
                    <option value="">Tous les logements</option>
                    @foreach ($logements as $l)<option value="{{ $l->id }}" @selected($logementChoisi === $l->id)>{{ $l->titre }}</option>@endforeach
                </select>
            </form>
        @endif
    </div>

    <nav class="segmented mb-3" style="max-width:520px" aria-label="Filtrer les réservations">
        @foreach ($onglets as $cle => $libelle)
            <a href="{{ route('pro.reservations', array_filter(['onglet' => $cle, 'logement' => $logementChoisi])) }}"
               class="segmented__lien{{ $onglet === $cle ? ' is-actif' : '' }}" @if ($onglet === $cle) aria-current="page" @endif>
                {{ $libelle }} <span class="ms-1 text-soft">({{ $compteurs[$cle] }})</span>
            </a>
        @endforeach
    </nav>

    <section class="panel">
        <ul class="rows">
            @forelse ($reservations as $r)
                @php
                    $p = $pastilles[$r->statut->value] ?? ['', 'chip--line', 'bi-dot'];
                    $services = $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ');
                @endphp
                <li>
                    <div class="quand">{{ $r->debut->translatedFormat('j M') }}<small>{{ $r->debut->format('H:i') }}</small></div>
                    <div class="qui">
                        <strong>{{ $r->client->name }}{{ $r->suffixePersonnes() }}</strong>
                        <span>{{ $r->logement->titre }} · {{ $r->formuleEtDuree() }} · {{ $r->reference }}{{ $r->estManuelle() ? " · hors plateforme" : "" }}</span>
                        @if ($services)<span><i class="bi bi-bag-check me-1" aria-hidden="true"></i>{{ $services }}</span>@endif
                        @include('pro.partials.whatsapp-client', ['r' => $r])
                        <span class="chip {{ $p[1] }}"><i class="bi {{ $p[2] }}" aria-hidden="true"></i>{{ $p[0] }}</span>
                    </div>
                    <div class="action">
                        <span class="montant">{{ Montant::fcfa($r->montant_location) }}</span>
                        @if (in_array($r->statut, [StatutReservation::Confirmee, StatutReservation::Consommee], true))
                            <a class="btn btn-ghost btn-sm" href="{{ route('pro.calendrier', ['logement' => $r->logement_id, 'jour' => $r->debut->toDateString()]) }}"><i class="bi bi-calendar3" aria-hidden="true"></i>Calendrier</a>
                        @endif
                    </div>
                </li>
            @empty
                <li style="display:block" class="text-soft">Aucune réservation dans cet onglet.</li>
            @endforelse
        </ul>
        @if ($reservations->hasPages())<div class="mt-3">{{ $reservations->links() }}</div>@endif
    </section>
</x-layouts.pro>
