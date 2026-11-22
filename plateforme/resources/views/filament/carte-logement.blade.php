{{-- Administration › fiche logement : carte de positionnement (resources/js/carte.js › viviCarteChamp).
     « Utiliser ma position » ou clic sur la carte : latitude et longitude remplies, adresse, quartier et ville proposés. --}}
@vite('resources/js/carte.js')
<div wire:ignore class="vivi-carte-champ"
     x-data="{ villes: @js($villes) }"
     x-init="
        const lancer = () => window.viviCarteChamp($refs.carte, {
            lat: $wire.get('data.latitude'), lng: $wire.get('data.longitude'), ville: $wire.get('data.ville'),
            bouton: $refs.bouton, etat: $refs.etat,
            surPosition: (la, ln, adr, depuisGps) => {
                $wire.set('data.latitude', la.toFixed(7));
                $wire.set('data.longitude', ln.toFixed(7));
                if (! adr) return;
                if (adr.rue && (depuisGps || ! $wire.get('data.adresse'))) $wire.set('data.adresse', adr.rue);
                if (adr.quartier && (depuisGps || ! $wire.get('data.quartier'))) $wire.set('data.quartier', adr.quartier);
                const ville = villes.find((v) => v.toLowerCase() === (adr.ville || '').toLowerCase());
                if (ville) $wire.set('data.ville', ville);
            },
        });
        window.viviCarteChamp ? lancer() : document.addEventListener('vivi:carte-prete', lancer, { once: true });
     ">
    <div class="vivi-carte-champ__barre">
        <span>Sur place, utilisez la position du téléphone ; sinon cliquez sur la carte ou faites glisser le repère.</span>
        <button type="button" x-ref="bouton" class="fi-btn fi-size-sm fi-color-primary fi-btn-color-primary vivi-carte-champ__bouton">
            <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4" />Utiliser ma position
        </button>
    </div>
    <div x-ref="carte" class="vivi-carte-champ__carte" role="application" aria-label="Carte : cliquez pour placer le logement"></div>
    <p x-ref="etat" class="vivi-carte-champ__etat" aria-live="polite"></p>
</div>
