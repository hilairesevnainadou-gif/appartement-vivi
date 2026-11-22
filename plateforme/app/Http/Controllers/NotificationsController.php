<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace « Notifications » du site (clients, propriétaires, gestionnaires) : tout ce qui a été envoyé
 * par email ou SMS (confirmation, rappel, remboursement, bonus…) y reste consultable.
 */
class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('espace.notifications', [
            'notifications' => $user->notificationsSite()->latest()->paginate(20),
            'nonLues' => $user->notificationsSite()->whereNull('read_at')->count(),
        ]);
    }

    /** Ouvre une notification : marquée comme lue, puis direction la page concernée. */
    public function ouvrir(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notificationsSite()->findOrFail($id);
        $notification->markAsRead();
        $lien = $notification->data['lien'] ?? null;

        // Seulement vers ce site (jamais une adresse extérieure)
        return $lien && str_starts_with($lien, url('/')) ? redirect()->to($lien) : redirect()->route('notifications');
    }

    public function toutLu(Request $request): RedirectResponse
    {
        $request->user()->notificationsSite()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('statut', 'Toutes les notifications sont marquées comme lues.');
    }
}
