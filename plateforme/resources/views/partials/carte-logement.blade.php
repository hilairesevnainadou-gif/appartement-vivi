{{-- Carte d'un logement (maquette : V.carteLogement).
     $l : Logement chargé avec tarifs, photos, equipements (et note via avecNote)
     $ligne : présentation en ligne (page de résultats) ; $niveau : niveau du titre (2 ou 3)
     $formule : formule choisie dans la recherche, ou null ; $suite : paramètres repris dans le lien ; $dispo : texte de disponibilité --}}
@php
    $ligne = $ligne ?? false;
    $niveau = $niveau ?? 3;
    $formuleChoisie = $formule ?? null;
    $affichee = $formuleChoisie && $l->tarif($formuleChoisie) ? $formuleChoisie : $l->formuleParDefaut();
    $tarif = $affichee ? $l->tarif($affichee) : null;
    $lien = route('logement', ['slug' => $l->slug] + array_filter($suite ?? []));
    $photo = $l->urlPhoto();
@endphp
<article class="listing-card{{ $ligne ? ' listing-card--row' : '' }}">
    <div class="listing-card__media">
        @if ($photo)
            <img src="{{ $photo }}" alt="{{ $l->titre }}, {{ $l->quartier }}" width="640" height="480" loading="lazy" decoding="async">
        @endif
        <div class="listing-card__badges">
            <span class="chip">{{ \Illuminate\Support\Str::before($l->type->getLabel(), ' (') }}</span>
            <span class="chip"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Vérifié</span>
        </div>
    </div>
    <div class="listing-card__body">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <p class="listing-card__place"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $l->quartier }}, {{ $l->ville }}@if ($l->getAttribute('distance_km') !== null)<span class="listing-card__distance"> · à {{ \App\Support\Geo::distanceLisible($l->distance_km) }}</span>@endif</p>
            @if ($l->noteAffichee())
                <span class="rating" aria-label="Note {{ $l->noteAffichee() }} sur 5, {{ $l->nombre_avis }} avis"><i class="bi bi-star-fill" aria-hidden="true"></i>{{ $l->noteAffichee() }} <small>({{ $l->nombre_avis }})</small></span>
            @else
                <span class="rating rating--nouveau"><small>Nouveau</small></span>
            @endif
        </div>
        <h{{ $niveau }} class="listing-card__title"><a class="stretched-link" href="{{ $lien }}">{{ $l->titre }}</a></h{{ $niveau }}>
        <p class="listing-card__meta">{{ $l->resume() }}</p>
        @if ($ligne)
            <ul class="amenity-inline">
                @foreach ($l->equipements->sortBy('ordre')->take(4) as $e)
                    <li><i class="bi {{ $e->icone ?: 'bi-check2' }}" aria-hidden="true"></i>{{ $e->nom }}</li>
                @endforeach
            </ul>
            @isset($dispo)
                <p class="mb-0 mt-2"><span class="chip chip--success"><i class="bi bi-calendar-check" aria-hidden="true"></i>{{ $dispo }}</span></p>
            @endisset
        @endif
        <div class="listing-card__foot">
            @if ($tarif)
                <div class="price">
                    <small>{{ $formuleChoisie ? 'Formule '.mb_strtolower($affichee->getLabel()) : 'À partir de' }}</small>
                    <strong>{{ \App\Support\Montant::fcfa($tarif->prix) }}</strong> <span>{{ $affichee->unite() }}</span>
                </div>
            @endif
            <div class="formules" aria-label="Formules proposées">
                @foreach ($l->formulesProposees() as $f)
                    <span class="chip {{ $f === $affichee ? 'chip--teal' : 'chip--line' }}">{{ $f->court() }}</span>
                @endforeach
            </div>
        </div>
    </div>
</article>
