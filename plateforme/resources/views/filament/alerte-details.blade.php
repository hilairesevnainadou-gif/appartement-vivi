{{-- Détail d'une alerte de surveillance --}}
<div class="journal-details">
    <p style="margin:0 0 12px">{{ $alerte->message }}</p>
    <dl class="journal-details__infos">
        <div><dt>Type</dt><dd>{{ \App\Services\Surveillance::TYPES[$alerte->type] ?? $alerte->type }}</dd></div>
        <div><dt>Source</dt><dd>{{ $alerte->source ?: '—' }}</dd></div>
        <div><dt>Première fois</dt><dd>{{ $alerte->created_at->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Dernière fois</dt><dd>{{ $alerte->derniere_le?->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Occurrences</dt><dd>{{ $alerte->occurrences }}</dd></div>
        <div><dt>Équipe prévenue</dt><dd>{{ $alerte->notifiee_le?->format('d/m/Y H:i') ?? 'Non (information)' }}</dd></div>
        @foreach ((array) $alerte->details as $cle => $valeur)
            <div><dt>{{ \Illuminate\Support\Str::of($cle)->replace('_', ' ')->ucfirst() }}</dt><dd>{{ is_scalar($valeur) ? $valeur : json_encode($valeur, JSON_UNESCAPED_UNICODE) }}</dd></div>
        @endforeach
    </dl>
    <p style="margin:14px 0 0;font-size:.85rem;color:#5f7072">
        @switch($alerte->type)
            @case('connexion_admin') @case('codes_faux') Conseil : si l’adresse n’est pas celle d’un membre de l’équipe, bloquez-la chez l’hébergeur (pare-feu). @break
            @case('exploration') @case('chemin_attaque') Conseil : robots d’attaque courants ; aucune donnée n’a été exposée si l’adresse n’apparaît pas ailleurs. Bloquez l’adresse si cela se répète. @break
            @case('webhook') Conseil : vérifiez la clé FEDAPAY_WEBHOOK_SECRET et les derniers paiements dans « Paiements reçus ». @break
            @case('erreurs_serveur') Conseil : consultez storage/logs/laravel.log sur le serveur pour la cause exacte. @break
            @case('planificateur') Conseil : vérifiez que la tâche cron « php artisan schedule:run » tourne chaque minute. @break
            @default Conseil : vérifiez l’état du serveur et des services externes (FedaPay, SMS, email).
        @endswitch
    </p>
</div>
