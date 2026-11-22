{{-- Carte des logements et des salles inscrits, et progression sur le territoire (resources/js/carte-admin.js) --}}
@php
    $c = $chiffres;
    $tendance = $c['ceMois'] - $c['moisPrecedent'];
    $maxMois = max(1, collect($mois)->max('nouveaux'));
@endphp
<x-filament-panels::page>
    @vite('resources/js/carte-admin.js')

    {{-- ===== Tout, logements ou salles ===== --}}
    <x-filament::tabs label="Catégorie affichée">
        @foreach ($categories as $cle => [$libelle])
            <x-filament::tabs.item tag="a" :href="\App\Filament\Pages\CarteLogements::getUrl(['categorie' => $cle])" :active="$this->categorie === $cle">{{ $libelle }}</x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{-- ===== Chiffres ===== --}}
    <div class="vivi-carte-chiffres">
        <div><span>{{ ucfirst($mot) }} {{ $inscrits }}</span><strong>{{ $c['total'] }}</strong><small>{{ $c['proprietaires'] }} propriétaire{{ $c['proprietaires'] > 1 ? 's' : '' }}</small></div>
        <div><span>{{ $salles ? 'Publiées' : 'Publiés' }}</span><strong style="color:#1e7d4f">{{ $c['publies'] }}</strong><small>{{ $salles ? number_format($c['places'], 0, ',', ' ').' places · ' : '' }}{{ $c['enVerification'] }} en vérification · {{ $c['geresParVivi'] }} géré{{ $c['geresParVivi'] > 1 ? 's' : '' }} par VIVI</small></div>
        <div><span>Nouveaux ce mois</span><strong>{{ $c['ceMois'] }}</strong>
            <small @class(['hausse' => $tendance > 0, 'baisse' => $tendance < 0])>{{ $tendance > 0 ? '+'.$tendance : $tendance }} par rapport au mois dernier</small></div>
        <div><span>Villes couvertes</span><strong>{{ $c['villesCouvertes'] }}</strong><small>{{ $villes->sum('quartiers') }} quartiers</small></div>
    </div>

    <div class="vivi-carte-grille">
        {{-- ===== Carte ===== --}}
        <x-filament::section>
            <x-slot name="heading">Où sont les {{ $mot }}</x-slot>
            <x-slot name="description">Cliquez sur un point pour ouvrir la fiche. {{ $c['sansPosition'] ? $c['sansPosition'].' fiche(s) sans position ne sont pas sur la carte.' : '' }}</x-slot>

            <div class="vivi-carte-filtres" role="group" aria-label="Statuts affichés">
                @foreach ($statuts as $valeur => [$libelle, $couleur])
                    <label><input type="checkbox" value="{{ $valeur }}" data-filtre-statut checked>
                        <i style="background:{{ $couleur }}"></i>{{ $libelle }}
                        <span class="nb">{{ $points->where('statut', $valeur)->count() }}</span></label>
                @endforeach
                @if ($this->categorie !== 'logement')
                    <span class="vivi-carte-legende"><i></i>Bord orange : salle</span>
                @endif
            </div>

            <div id="vivi-carte-admin" class="vivi-carte" data-points='@json($points)' data-mois='@json($mois)' data-mot="{{ $mot }}" role="application"
                 data-inscrits="{{ $inscrits }}" aria-label="Carte des {{ $mot }} {{ $inscrits }} sur VIVI"></div>

            {{-- Progression : curseur des mois et lecture animée --}}
            <div class="vivi-progression">
                <button type="button" class="vivi-progression__lecture" id="progression-lecture" aria-label="Lire la progression">
                    <x-filament::icon icon="heroicon-s-play" class="h-5 w-5" data-icone-lecture />
                    <x-filament::icon icon="heroicon-s-pause" class="h-5 w-5" data-icone-pause style="display:none" />
                </button>
                <div class="vivi-progression__curseur">
                    <label for="progression-mois" class="sr-only">Mois affiché</label>
                    <input type="range" id="progression-mois" min="0" max="{{ count($mois) - 1 }}" value="{{ count($mois) - 1 }}" step="1">
                    <div class="vivi-progression__bornes"><span>{{ $mois[0]['libelle'] ?? '' }}</span><span>{{ end($mois)['libelle'] ?? '' }}</span></div>
                </div>
                <div class="vivi-progression__etat" aria-live="polite"><strong id="progression-libelle">{{ end($mois)['libelle'] ?? '' }}</strong><span id="progression-total">{{ $c['total'] }} {{ $mot }}</span></div>
            </div>
        </x-filament::section>

        <div class="vivi-carte-cote">
            {{-- ===== Couverture par ville ===== --}}
            <x-filament::section>
                <x-slot name="heading">Couverture du territoire</x-slot>
                <ul class="vivi-villes">
                    @foreach ($villes as $v)
                        @php $part = $c['total'] ? round($v['total'] / $c['total'] * 100) : 0; @endphp
                        <li>
                            <div class="vivi-villes__tete"><strong>{{ $v['ville'] }}</strong><span>{{ $v['total'] }} fiche{{ $v['total'] > 1 ? 's' : '' }}</span></div>
                            <div class="vivi-villes__barre" aria-hidden="true"><i style="width:{{ max($part, $v['total'] ? 4 : 0) }}%"></i></div>
                            <small>{{ $v['publies'] }} publiée{{ $v['publies'] > 1 ? 's' : '' }} · {{ $v['quartiers'] }} quartier{{ $v['quartiers'] > 1 ? 's' : '' }}{{ $v['chambres'] ? ' · '.$v['chambres'].' unité'.($v['chambres'] > 1 ? 's' : '').' en ligne' : '' }}{{ $v['total'] === 0 ? ' · rien encore : à développer' : '' }}</small>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>

            {{-- ===== Nouveaux logements par mois ===== --}}
            <x-filament::section>
                <x-slot name="heading">Nouvelles {{ $salles ? 'salles' : 'fiches' }} par mois</x-slot>
                <div class="vivi-mois" role="img" aria-label="Nombre de nouvelles fiches ({{ $mot }}) inscrites chaque mois">
                    @foreach (array_slice($mois, -12) as $m)
                        <div class="vivi-mois__col" title="{{ $m['libelle'] }} : {{ $m['nouveaux'] }} nouveau(x), {{ $m['total'] }} au total">
                            <span class="vivi-mois__nb">{{ $m['nouveaux'] ?: '' }}</span>
                            <i style="height:{{ max(3, $m['nouveaux'] / $maxMois * 100) }}%"></i>
                            <span class="vivi-mois__lib">{{ \Illuminate\Support\Str::before($m['libelle'], ' ') }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
