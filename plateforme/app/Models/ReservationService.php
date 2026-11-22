<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Service ajouté à une réservation ; nom et prix copiés au moment de l'achat. */
class ReservationService extends Model
{
    protected $table = 'reservation_services';

    /** @var list<string> */
    protected $fillable = [
        'reservation_id',
        'service_id',
        'nom',
        'prix_unitaire',
        'quantite',
        'montant',
        'beneficiaire',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
