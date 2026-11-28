{{--
    Visionneuse de photos plein écran (fiche d'un logement ou d'une salle).
    Ouverte par tout élément [data-visionneuse="index"] : photo de la galerie ou bouton « Voir toutes les photos ».
    Flèches, clavier (← → Échap), glissement du doigt sur téléphone, compteur et vignettes.
--}}
@props(['photos', 'titre'])
@php
    $liste = $photos->values()->map(fn ($p, $i) => [
        'src' => \Illuminate\Support\Facades\Storage::disk('public')->url($p->chemin),
        'alt' => $p->legende ?: $titre.', photo '.($i + 1).' sur '.$photos->count(),
        'legende' => $p->legende,
    ])->all();
@endphp
@if (count($liste))
    <style>
        .visio{position:fixed;inset:0;z-index:2000;background:#061415;display:none;outline:0;flex-direction:column;color:#fff}
        .visio.est-ouverte{display:flex}
        .visio__haut{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;padding-top:max(12px, env(safe-area-inset-top))}
        .visio__titre{font-weight:600;font-size:.95rem;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .visio__compteur{font-variant-numeric:tabular-nums;color:#c5dcda;font-size:.9rem;white-space:nowrap}
        .visio__btn{width:44px;height:44px;border-radius:12px;border:1.5px solid rgba(255,255,255,.35);background:rgba(255,255,255,.08);color:#fff;
            display:inline-flex;align-items:center;justify-content:center;font-size:1.25rem;flex:none;transition:background-color .15s,border-color .15s}
        .visio__btn:hover{background:#fff;color:var(--vivi-teal-900);border-color:#fff}
        .visio__btn:focus-visible{outline:3px solid var(--vivi-orange-400);outline-offset:2px}
        .visio__btn:disabled{opacity:.3;pointer-events:none}
        .visio__scene{flex:1;min-height:0;position:relative;display:flex;align-items:center;justify-content:center;padding:0 72px;touch-action:pan-y}
        .visio__scene img{max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;user-select:none;-webkit-user-drag:none}
        .visio__prec,.visio__suiv{position:absolute;top:50%;transform:translateY(-50%)}
        .visio__prec{left:16px}.visio__suiv{right:16px}
        .visio__legende{text-align:center;color:#c5dcda;font-size:.9rem;min-height:1.4em;padding:8px 16px 0}
        .visio__vignettes{display:flex;gap:8px;overflow-x:auto;padding:12px 16px;padding-bottom:max(12px, env(safe-area-inset-bottom));scrollbar-width:none;justify-content:safe center}
        .visio__vignettes::-webkit-scrollbar{display:none}
        .visio__vignettes button{flex:0 0 72px;height:54px;padding:0;border:2px solid transparent;border-radius:8px;overflow:hidden;opacity:.55;background:none}
        .visio__vignettes button[aria-current="true"]{border-color:var(--vivi-orange-400);opacity:1}
        .visio__vignettes img{width:100%;height:100%;object-fit:cover}
        @media (max-width:767.98px){
            .visio__scene{padding:0}
            .visio__scene img{border-radius:0}
            .visio__prec,.visio__suiv{width:40px;height:40px;font-size:1.1rem;background:rgba(6,20,21,.55)}
            .visio__prec{left:8px}.visio__suiv{right:8px}
            .visio__vignettes button{flex-basis:56px;height:42px}
        }
        .gallery [data-visionneuse]{cursor:zoom-in}
        body.visio-ouverte{overflow:hidden}
    </style>

    <div class="visio" id="visionneuse" role="dialog" aria-modal="true" tabindex="-1" aria-label="Photos de {{ $titre }}" hidden>
        <div class="visio__haut">
            <span class="visio__titre">{{ $titre }}</span>
            <span class="visio__compteur" aria-live="polite"><span data-visio-n>1</span> / {{ count($liste) }}</span>
            <button class="visio__btn" type="button" data-visio-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <div class="visio__scene" data-visio-scene>
            <button class="visio__btn visio__prec" type="button" data-visio-pas="-1" aria-label="Photo précédente"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <img src="" alt="" data-visio-img>
            <button class="visio__btn visio__suiv" type="button" data-visio-pas="1" aria-label="Photo suivante"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        </div>
        <p class="visio__legende" data-visio-legende></p>
        @if (count($liste) > 1)
            <div class="visio__vignettes" role="tablist" aria-label="Toutes les photos">
                @foreach ($liste as $i => $p)
                    <button type="button" role="tab" data-visio-aller="{{ $i }}" aria-label="Photo {{ $i + 1 }}"><img src="{{ $p['src'] }}" alt="" loading="lazy"></button>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        (function () {
            var photos = @json($liste);
            var v = document.getElementById('visionneuse');
            if (!v) { return; }
            var img = v.querySelector('[data-visio-img]'), n = v.querySelector('[data-visio-n]'),
                legende = v.querySelector('[data-visio-legende]'), vignettes = v.querySelectorAll('[data-visio-aller]'),
                prec = v.querySelector('[data-visio-pas="-1"]'), suiv = v.querySelector('[data-visio-pas="1"]'),
                index = 0, retour = null;

            function montrer(i) {
                index = (i + photos.length) % photos.length;
                img.src = photos[index].src; img.alt = photos[index].alt;
                n.textContent = index + 1;
                legende.textContent = photos[index].legende || '';
                prec.hidden = suiv.hidden = photos.length < 2;
                vignettes.forEach(function (b, k) {
                    b.setAttribute('aria-current', k === index ? 'true' : 'false');
                    if (k === index) { b.scrollIntoView({block: 'nearest', inline: 'center'}); }
                });
                // Précharge les voisines
                [index + 1, index - 1].forEach(function (k) { new Image().src = photos[(k + photos.length) % photos.length].src; });
            }
            function ouvrir(i) {
                retour = document.activeElement;
                v.hidden = false; v.classList.add('est-ouverte'); document.body.classList.add('visio-ouverte');
                montrer(i);
                v.focus();
            }
            function fermer() {
                v.classList.remove('est-ouverte'); v.hidden = true; document.body.classList.remove('visio-ouverte');
                if (retour) { retour.focus(); }
            }

            document.addEventListener('click', function (e) {
                var o = e.target.closest('[data-visionneuse]');
                if (o) { e.preventDefault(); ouvrir(+o.dataset.visionneuse || 0); return; }
                if (!v.classList.contains('est-ouverte')) { return; }
                var pas = e.target.closest('[data-visio-pas]'), aller = e.target.closest('[data-visio-aller]');
                if (pas) { montrer(index + +pas.dataset.visioPas); }
                else if (aller) { montrer(+aller.dataset.visioAller); }
                else if (e.target.closest('[data-visio-fermer]') || e.target === v.querySelector('[data-visio-scene]')) { fermer(); }
            });
            document.addEventListener('keydown', function (e) {
                if (!v.classList.contains('est-ouverte')) {
                    var o = e.target.closest && e.target.closest('[data-visionneuse]');
                    if (o && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); ouvrir(+o.dataset.visionneuse || 0); }
                    return;
                }
                if (e.key === 'Escape') { fermer(); }
                else if (e.key === 'ArrowRight') { montrer(index + 1); }
                else if (e.key === 'ArrowLeft') { montrer(index - 1); }
                else if (e.key === 'Tab') {   // le focus reste dans la visionneuse
                    var f = Array.prototype.filter.call(v.querySelectorAll('button'), function (b) { return !b.hidden && b.offsetParent; });
                    if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
                    else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
                }
            });
            // Glissement du doigt : gauche = suivante, droite = précédente
            var x0 = null;
            v.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, {passive: true});
            v.addEventListener('touchend', function (e) {
                if (x0 === null) { return; }
                var dx = e.changedTouches[0].clientX - x0; x0 = null;
                if (Math.abs(dx) > 40) { montrer(index + (dx < 0 ? 1 : -1)); }
            });
        })();
    </script>
@endif
