{{--
    Boutons de téléchargement de l'application VIVI (APK, Google Play, App Store), réglés dans les Paramètres.
    Lien vide : bouton grisé « Bientôt disponible ». variante="section" (accueil) ou "pied" (pied de page, fond foncé).
--}}
@props(['variante' => 'section'])
@php
    $boutons = \App\Support\ApplicationMobile::boutons();
@endphp
@if (\App\Support\ApplicationMobile::affichee())
    @once
            <style>
                /* Téléphone : APK sur toute la largeur, Google Play et App Store côte à côte. Dès 576 px : une seule ligne. */
                .app-stores{display:grid;grid-template-columns:1fr 1fr;gap:10px}
                .app-stores > :first-child{grid-column:1/-1}
                .app-stores .app-store{width:100%;min-width:0}
                @media (min-width:576px){.app-stores{display:flex;flex-wrap:wrap}.app-stores .app-store{width:auto}}
                .app-store small,.app-store b{white-space:nowrap}
                @media (max-width:575.98px){.app-store{gap:8px;padding:8px 10px}.app-store i{font-size:1.35rem}.app-store b{font-size:.95rem}}
                .app-store{display:inline-flex;align-items:center;gap:10px;min-height:52px;padding:8px 16px 8px 12px;border-radius:12px;
                    background:var(--vivi-ink);color:#fff;border:1px solid var(--vivi-ink);text-decoration:none;line-height:1.15;transition:background-color .15s,border-color .15s}
                a.app-store:hover{background:var(--vivi-teal-800);border-color:var(--vivi-teal-800);color:#fff}
                a.app-store:focus-visible{outline:3px solid var(--vivi-orange-400);outline-offset:2px}
                .app-store i{font-size:1.6rem;line-height:1;flex:none}
                .app-store span{display:flex;flex-direction:column;min-width:0}
                .app-store small{font-size:.72rem;opacity:.85;font-weight:500}
                .app-store b{font-family:var(--vivi-font-title);font-size:1.05rem;font-weight:600}
                .app-store--bientot{background:transparent;color:var(--vivi-muted);border:1px dashed var(--vivi-line-strong, #c9d3d2);cursor:default}
                .app-stores--pied .app-store{min-height:46px;padding:6px 14px 6px 10px}
                .app-stores--pied .app-store:not(.app-store--bientot){background:#fff;color:var(--vivi-ink);border-color:#fff}
                .app-stores--pied a.app-store:hover{background:var(--vivi-teal-50);border-color:var(--vivi-teal-50);color:var(--vivi-ink);text-decoration:none}
                .app-stores--pied .app-store--bientot{color:#c5dcda;border-color:rgba(255,255,255,.35)}
                .app-stores--pied .app-store i{font-size:1.35rem}
                .app-stores--pied .app-store b{font-size:.95rem}
                .app-detail{font-size:.875rem;color:var(--vivi-muted);margin:.6rem 0 0}
                .vivi-footer .app-detail{color:#a9c6c4}
                .app-band{border:1px solid var(--vivi-line);border-radius:var(--vivi-radius);background:var(--vivi-teal-50);padding:24px 20px;display:grid;gap:20px}
                .app-band h2{margin-bottom:.4rem}
                .app-band p.lead-app{color:var(--vivi-body);margin:0}
                .app-band__points{list-style:none;padding:0;margin:.9rem 0 0;display:grid;gap:.35rem;color:var(--vivi-body)}
                .app-band__points i{color:var(--vivi-teal-700);margin-right:.4rem}
                @media (min-width:768px){.app-band{padding:32px}}
                @media (min-width:992px){.app-band{padding:40px 48px;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:40px}.app-band__boutons{width:26rem}.app-band__boutons .app-stores{display:grid}.app-band__boutons .app-store{width:100%}}
            </style>
    @endonce

    @php $apk = collect($boutons)->firstWhere('cle', 'apk'); @endphp
    <div {{ $attributes->class(['app-stores', 'app-stores--pied' => $variante === 'pied']) }}>
        @foreach ($boutons as $b)
            @if ($b['lien'])
                <a class="app-store" href="{{ $b['lien'] }}"
                   @if ($b['cle'] === 'apk' && ! $b['externe']) download @endif
                   @if ($b['externe']) target="_blank" rel="noopener" @endif
                   aria-label="{{ $b['haut'] }} {{ $b['nom'] }}">
                    <i class="bi {{ $b['icone'] }}" aria-hidden="true"></i>
                    <span><small>{{ $b['haut'] }}</small><b>{{ $b['nom'] }}</b></span>
                </a>
            @else
                <span class="app-store app-store--bientot" aria-label="{{ $b['nom'] }} : bientôt disponible">
                    <i class="bi {{ $b['icone'] }}" aria-hidden="true"></i>
                    <span><small>{{ $b['haut'] }}</small><b>{{ $b['nom'] }}</b></span>
                </span>
            @endif
        @endforeach
    </div>
    @php $magasinsAbsents = collect($boutons)->where('cle', '!=', 'apk')->whereNull('lien')->isNotEmpty(); @endphp
    @if ($apk['lien'] && $magasinsAbsents)
        <p class="app-detail"><i class="bi bi-info-circle" aria-hidden="true"></i> Pour le moment, l’application Android s’installe avec l’APK {{ $apk['detail'] }}.</p>
    @elseif ($apk['lien'] && $apk['detail'])
        <p class="app-detail">APK {{ $apk['detail'] }}.</p>
    @endif
@endif
