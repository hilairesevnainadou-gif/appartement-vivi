{{--
    Espace publicitaire du site web : publicités de VIVI (Administration › Publicités), pour tous les visiteurs,
    connectés ou non. Une publicité tirée au hasard parmi celles de l'espace ; rien n'est affiché s'il n'y en a pas.
    Usage : <x-pub emplacement="accueil_haut" :ville="$ville" />  ; conteneur : entre deux sections, aligné sur la grille du site
--}}
@props(['emplacement', 'ville' => null, 'conteneur' => false])
@php
    $espace = \App\Enums\EmplacementPub::from($emplacement);
    $pub = \App\Support\Publicites::pourSite($espace, $ville)->shuffle()->first();
@endphp
@if ($pub)
    @php
        $image = \App\Http\Resources\Api\Format::photo($pub->image);
        $interne = $pub->lien !== null && ($pub->lien === url('/') || str_starts_with($pub->lien, url('/').'/'));
        $balise = $pub->lien ? 'a' : 'div';
    @endphp
    @if ($conteneur)<div class="container pub-zone">@endif
    <aside {{ $attributes->merge(['class' => 'pub pub--'.$espace->format()]) }} aria-label="Publicité" data-pub="{{ $pub->id }}">
        <{{ $balise }} class="pub__corps"
            @if ($pub->lien) href="{{ $pub->lien }}" @unless ($interne) target="_blank" rel="sponsored noopener" @endunless @endif>
            @if ($image)
                <span class="pub__media"><img class="pub__image" src="{{ $image }}" alt="{{ $pub->titre }}" loading="lazy" decoding="async"></span>
            @endif
            <span class="pub__texte">
                <span class="pub__mention">Publicité{{ $pub->annonceur ? ' · '.$pub->annonceur : '' }}</span>
                <strong class="pub__titre">{{ $pub->titre }}</strong>
                @if ($pub->texte)<span class="pub__description">{{ $pub->texte }}</span>@endif
                @if ($pub->lien)<span class="pub__bouton btn btn-primary btn-sm">{{ $pub->bouton ?: 'En savoir plus' }}<i class="bi bi-arrow-right" aria-hidden="true"></i></span>@endif
            </span>
        </{{ $balise }}>
    </aside>
    @if ($conteneur)</div>@endif

    @once
        @push('head')
            <style>
                /* Même langage que les cartes de la maquette : bord fin, rayon 16 px, ombre au survol, titres Outfit */
                .pub-zone { padding-top: 32px; }
                .pub__corps { display: flex; align-items: stretch; overflow: hidden; border-radius: var(--vivi-radius, 16px); background: #fff;
                    border: 1px solid var(--vivi-line, #e2e8e7); color: inherit; text-decoration: none; transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease; }
                a.pub__corps:hover { box-shadow: var(--vivi-shadow, 0 8px 24px rgba(20, 40, 42, .10)); transform: translateY(-2px); border-color: transparent; color: inherit; }
                a.pub__corps:focus-visible { outline: 3px solid var(--vivi-orange-400, #f4ad4c); outline-offset: 2px; }
                /* Image dans ses proportions d'origine : jamais recadrée, le texte d'une affiche reste lisible */
                .pub__media { flex: 0 0 40%; max-width: 480px; display: flex; align-items: center; justify-content: center; background: var(--vivi-teal-50, #eff8f7); }
                .pub__image { display: block; width: 100%; height: auto; }
                .pub__texte { flex: 1 1 auto; display: flex; flex-direction: column; justify-content: center; gap: 4px; padding: 16px 20px; min-width: 0; }
                .pub__mention { font-size: .72rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--vivi-muted, #5f7072); }
                .pub__titre { font-family: var(--vivi-font-title, inherit); font-size: 1.1rem; font-weight: 600; line-height: 1.3; color: var(--vivi-ink, #14282a); }
                .pub__description { font-size: .92rem; color: var(--vivi-body, #3b4d4f); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
                .pub__bouton { align-self: flex-start; margin-top: 8px; }
                a.pub__corps:hover .pub__bouton { background-color: var(--vivi-teal-800); border-color: var(--vivi-teal-800); }
                @media (max-width: 767.98px) {
                    .pub-zone { padding-top: 24px; }
                    .pub__corps { flex-direction: column; }
                    .pub__media { flex-basis: auto; max-width: none; }
                    .pub__texte { padding: 12px 16px 16px; }
                }
            </style>
        @endpush
        @push('scripts')
            <script>
                // Vues (publicité vue à moitié à l'écran) et clics, comptés une fois par page pour l'annonceur
                (function () {
                    var base = @json(url('/api/v1/publicites'));
                    function compter(id, quoi) {
                        try { fetch(base + '/' + id + '/' + quoi + '?support=site', { method: 'POST', keepalive: true, headers: { 'Accept': 'application/json' } }); } catch (e) {}
                    }
                    var pubs = document.querySelectorAll('[data-pub]');
                    pubs.forEach(function (el) {
                        var lien = el.querySelector('a');
                        if (lien) { lien.addEventListener('click', function () { compter(el.dataset.pub, 'clic'); }, { once: true }); }
                    });
                    if (!('IntersectionObserver' in window)) { pubs.forEach(function (el) { compter(el.dataset.pub, 'vue'); }); return; }
                    var vues = new IntersectionObserver(function (entrees) {
                        entrees.forEach(function (e) {
                            if (e.isIntersecting) { compter(e.target.dataset.pub, 'vue'); vues.unobserve(e.target); }
                        });
                    }, { threshold: .5 });
                    pubs.forEach(function (el) { vues.observe(el); });
                })();
            </script>
        @endpush
    @endonce
@endif
