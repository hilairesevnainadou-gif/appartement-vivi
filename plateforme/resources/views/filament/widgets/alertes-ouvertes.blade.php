{{-- Bandeau des alertes de surveillance (tableau de bord) --}}
<x-filament-widgets::widget>
    @if ($total > 0)
        <div @class(['vivi-alertes', 'is-critique' => $critiques > 0]) role="alert" wire:poll.30s>
            <div class="vivi-alertes__icone" aria-hidden="true">
                <x-filament::icon :icon="$critiques > 0 ? 'heroicon-o-shield-exclamation' : 'heroicon-o-exclamation-triangle'" class="h-7 w-7" />
            </div>
            <div class="vivi-alertes__texte">
                <strong>{{ $critiques > 0 ? $critiques.' alerte'.($critiques > 1 ? 's' : '').' critique'.($critiques > 1 ? 's' : '') : $total.' alerte'.($total > 1 ? 's' : '').' à examiner' }}</strong>
                <ul>
                    @foreach ($ouvertes as $a)
                        <li>{{ $a->titre }}{{ $a->source ? ' · '.$a->source : '' }} · {{ $a->derniere_le?->diffForHumans() }}{{ $a->occurrences > 1 ? ' · '.$a->occurrences.' fois' : '' }}</li>
                    @endforeach
                </ul>
            </div>
            <x-filament::button tag="a" :href="$url" :color="$critiques > 0 ? 'danger' : 'warning'" icon="heroicon-m-arrow-right" icon-position="after">
                Voir les alertes
            </x-filament::button>
        </div>
    @else
        <div class="vivi-alertes is-ok">
            <div class="vivi-alertes__icone" aria-hidden="true"><x-filament::icon icon="heroicon-o-shield-check" class="h-6 w-6" /></div>
            <div class="vivi-alertes__texte"><strong>Surveillance : tout est normal.</strong> Aucune panne ni tentative d’attaque à traiter.</div>
        </div>
    @endif
</x-filament-widgets::widget>
