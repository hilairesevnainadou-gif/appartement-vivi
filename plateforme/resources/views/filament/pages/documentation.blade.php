{{-- Documentation technique : un onglet par document, rendu du Markdown du projet --}}
<x-filament-panels::page>
    <x-filament::tabs label="Documents">
        @foreach ($documents as $cle => [$libelle])
            <x-filament::tabs.item :active="$onglet === $cle" tag="a" :href="request()->url().'?doc='.$cle"
                :icon="$cle === 'api' ? 'heroicon-o-device-phone-mobile' : 'heroicon-o-server-stack'">
                {{ $libelle }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <x-filament::section>
        @if ($misAJour)
            <p class="doc-vivi__maj">Fichier mis à jour le {{ $misAJour->translatedFormat('j F Y à H:i') }}</p>
        @endif
        <article class="doc-vivi">{!! $html !!}</article>
    </x-filament::section>

    <style>
        .doc-vivi { line-height: 1.65; font-size: .95rem; max-width: 980px; overflow-wrap: anywhere; }
        .doc-vivi__maj { font-size: .8rem; opacity: .65; margin: 0 0 1rem; }
        .doc-vivi h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 1rem; }
        .doc-vivi h2 { font-size: 1.25rem; font-weight: 700; margin: 2rem 0 .75rem; padding-top: 1rem; border-top: 1px solid rgba(127, 127, 127, .25); }
        .doc-vivi h3 { font-size: 1.05rem; font-weight: 700; margin: 1.5rem 0 .5rem; }
        .doc-vivi p, .doc-vivi ul, .doc-vivi ol, .doc-vivi pre, .doc-vivi table { margin: 0 0 1rem; }
        .doc-vivi ul { list-style: disc; padding-left: 1.4rem; }
        .doc-vivi ol { list-style: decimal; padding-left: 1.4rem; }
        .doc-vivi li { margin: .25rem 0; }
        .doc-vivi a { color: #136f6e; text-decoration: underline; }
        .doc-vivi strong { font-weight: 700; }
        .doc-vivi code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85em; background: rgba(19, 111, 110, .1); padding: .1em .35em; border-radius: 4px; }
        .doc-vivi pre { background: #0a3d3e; color: #e8f3f2; padding: 1rem; border-radius: 8px; overflow-x: auto; }
        .doc-vivi pre code { background: none; padding: 0; color: inherit; }
        .doc-vivi table { display: block; overflow-x: auto; border-collapse: collapse; width: 100%; font-size: .88rem; }
        .doc-vivi th, .doc-vivi td { border: 1px solid rgba(127, 127, 127, .3); padding: .45rem .6rem; text-align: left; vertical-align: top; }
        .doc-vivi th { background: rgba(19, 111, 110, .08); font-weight: 700; }
        .dark .doc-vivi a { color: #5fc7c5; }
    </style>
</x-filament-panels::page>
