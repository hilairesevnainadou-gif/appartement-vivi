<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Vues et clics d'une publicité pour un jour. */
class PubliciteJour extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['publicite_id', 'jour', 'vues', 'clics'];

    protected function casts(): array
    {
        return ['jour' => 'date', 'vues' => 'integer', 'clics' => 'integer'];
    }
}
