{{-- Styles communs ajoutés sans recompiler les CSS (chargés par les mises en page public et pro). --}}
<style>
    /* Deux boutons côte à côte : sur téléphone, ils restent sur la même ligne et se partagent la largeur. */
    .btn-duo{display:flex;flex-wrap:wrap;gap:.5rem}
    @media (max-width:575.98px){
        .btn-duo{flex-wrap:nowrap}
        .btn-duo > .btn{flex:1 1 0;min-width:0;padding-left:.6rem;padding-right:.6rem;white-space:normal;line-height:1.2;
            display:inline-flex;align-items:center;justify-content:center;text-align:center}
        .btn-duo > .btn-lg{font-size:1rem;min-height:52px}
    }
    /* Boutons − / + des compteurs : même forme que les boutons du site (coins arrondis, vert VIVI) */
    .stepper button{border-radius:12px;border:1.5px solid var(--vivi-teal-700);color:var(--vivi-teal-700);font-weight:600}
    .stepper button:hover:not(:disabled){background:var(--vivi-teal-700);border-color:var(--vivi-teal-700);color:#fff}
    .stepper button:focus-visible{outline:3px solid var(--vivi-orange-400);outline-offset:2px}
    .stepper button:disabled{border-color:var(--vivi-line-strong, #c9d3d2);color:var(--vivi-muted)}

    /* Zoom de la carte : boutons VIVI au lieu des carrés gris par défaut */
    .leaflet-bar{border:0 !important;box-shadow:none !important;display:grid;gap:6px}
    .leaflet-bar a,.leaflet-bar a:first-child,.leaflet-bar a:last-child{width:38px !important;height:38px !important;line-height:36px !important;
        border-radius:10px !important;border:1.5px solid var(--vivi-teal-700) !important;background:#fff;color:var(--vivi-teal-700) !important;font-weight:600;box-shadow:var(--vivi-shadow)}
    .leaflet-bar a:hover{background:var(--vivi-teal-700) !important;color:#fff !important}
    .leaflet-bar a.leaflet-disabled{border-color:var(--vivi-line-strong, #c9d3d2) !important;color:var(--vivi-muted) !important;background:#fff !important}

    @media (max-width:379.98px){
        .btn-duo{gap:.4rem}
        .btn-duo > .btn,.btn-duo > .btn-lg{font-size:.875rem;padding-left:.4rem;padding-right:.4rem}
    }
</style>
