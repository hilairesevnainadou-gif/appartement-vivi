{{-- Exports comptables : choix de la période, puis un bouton par fichier --}}
<x-filament-panels::page>
    <x-filament::section heading="Période" description="Par défaut, le mois en cours.">
        <form method="GET" id="periode" class="flex flex-wrap items-end gap-4" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end">
            <label style="display:grid;gap:6px">
                <span class="fi-fo-field-label-content">Du</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" name="du" value="{{ request('du', today()->startOfMonth()->toDateString()) }}" required />
                </x-filament::input.wrapper>
            </label>
            <label style="display:grid;gap:6px">
                <span class="fi-fo-field-label-content">Au</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" name="au" value="{{ request('au', today()->toDateString()) }}" required />
                </x-filament::input.wrapper>
            </label>
        </form>
    </x-filament::section>

    <x-filament::section heading="Fichiers">
        <div style="display:grid;gap:10px">
            @foreach ($types as $type => $libelle)
                <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid var(--vivi-line, #e2e8e7)">
                    <span style="font-weight:600">{{ $libelle }}</span>
                    <x-filament::button type="submit" form="periode" icon="heroicon-o-arrow-down-tray" size="sm"
                        formaction="{{ route('admin.exports', $type) }}" formtarget="_blank">
                        Télécharger
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
