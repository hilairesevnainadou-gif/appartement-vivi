<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;

/** Réglage de la plateforme modifiable par l'administrateur. Lire via App\Support\Parametres. */
class Parametre extends Model
{
    use Journalise;

    protected $table = 'parametres';

    /** @var list<string> */
    protected $fillable = ['cle', 'valeur'];

    protected function casts(): array
    {
        return ['valeur' => 'json'];
    }
}
