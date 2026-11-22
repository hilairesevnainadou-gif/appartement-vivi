<?php

namespace App\Http\Resources\Api;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compte connecté. « espace » indique à l'application quels écrans ouvrir :
 * « pro » (propriétaire ou gestionnaire) ou « client ».
 *
 * @mixin User
 */
class UtilisateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->getRoleNames()->values()->all();

        return [
            'id' => $this->id,
            'nom' => $this->name,
            'prenom' => $this->prenom(),
            'initiales' => $this->initiales(),
            'email' => $this->email,
            'telephone' => $this->telephone,
            // Dernier numéro WhatsApp donné à une réservation (tout pays, +indicatif) : à préremplir au paiement
            'whatsapp' => $this->whatsapp,
            'email_verifie' => $this->email_verified_at !== null,
            'telephone_verifie' => $this->telephone_verified_at !== null,
            'roles' => $roles,
            'libelle_role' => $this->libelleRole(),
            'est_proprietaire' => in_array(Role::Proprietaire->value, $roles, true),
            'est_gestionnaire' => in_array(Role::Gestionnaire->value, $roles, true),
            'est_agent' => in_array(Role::AgentMarketing->value, $roles, true),
            // pro (propriétaire ou gestionnaire), agent (agent marketing, aussi client), sinon client
            'espace' => match (true) {
                $this->estProprietaireOuGestionnaire() => 'pro',
                in_array(Role::AgentMarketing->value, $roles, true) => 'agent',
                default => 'client',
            },
            'recevoir_offres' => (bool) ($this->recevoir_offres ?? true),
            'membre_depuis' => Format::date($this->created_at),
        ];
    }
}
