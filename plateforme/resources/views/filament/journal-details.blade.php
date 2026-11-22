{{-- Détail d'une entrée du journal d'activité : champs modifiés (avant → après) ou informations jointes --}}
@php
    use App\Filament\Resources\Journal\JournalResource;

    $proprietes = $activite->properties?->toArray() ?? [];
    $apres = $proprietes['attributes'] ?? [];
    $avant = $proprietes['old'] ?? [];
    $autres = collect($proprietes)->except(['attributes', 'old']);
    $texte = function ($v) {
        if (is_bool($v)) { return $v ? 'Oui' : 'Non'; }
        if (is_array($v)) { return $v === [] ? '—' : implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)); }
        if ($v === null || $v === '') { return '—'; }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $v)) { return \Illuminate\Support\Carbon::parse($v)->timezone(config('app.timezone'))->format('d/m/Y H:i'); }

        return (string) $v;
    };
@endphp

<div class="journal-details">
    @if ($apres)
        <table class="journal-details__table">
            <thead><tr><th>Champ</th>@if ($avant)<th>Avant</th>@endif<th>{{ $avant ? 'Après' : 'Valeur' }}</th></tr></thead>
            <tbody>
                @foreach ($apres as $cle => $valeur)
                    <tr>
                        <td>{{ JournalResource::champ($cle) }}</td>
                        @if ($avant)<td class="avant">{{ $texte($avant[$cle] ?? null) }}</td>@endif
                        <td>{{ $texte($valeur) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($autres->isNotEmpty())
        <dl class="journal-details__infos">
            @foreach ($autres as $cle => $valeur)
                <div><dt>{{ JournalResource::champ((string) $cle) }}</dt><dd>{{ $texte($valeur) }}</dd></div>
            @endforeach
        </dl>
    @endif

    @if (! $apres && $autres->isEmpty())
        <p class="text-gray-500">Aucun détail supplémentaire.</p>
    @endif
</div>
