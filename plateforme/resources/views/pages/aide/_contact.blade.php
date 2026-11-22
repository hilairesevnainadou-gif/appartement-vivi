{{-- Bloc « Une question ? » : WhatsApp et email du service client (Paramètres de l'administration). Masqué si aucun n'est réglé. --}}
@if ($whatsapp || $emailContact)
    <div class="panel aide-contact">
        <div>
            <h2 class="mb-1">Une question ?</h2>
            <p class="mb-0 text-soft">Le service client VIVI vous répond{{ $whatsapp ? ' sur WhatsApp' : '' }}{{ $whatsapp && $emailContact ? ' ou' : '' }}{{ $emailContact ? ' par email' : '' }}.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($whatsapp)
                <a class="btn btn-whatsapp" href="{{ $whatsapp }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i>Nous écrire sur WhatsApp</a>
            @endif
            @if ($emailContact)
                <a class="btn btn-ghost" href="mailto:{{ $emailContact }}"><i class="bi bi-envelope" aria-hidden="true"></i>{{ $emailContact }}</a>
            @endif
        </div>
    </div>
@endif
