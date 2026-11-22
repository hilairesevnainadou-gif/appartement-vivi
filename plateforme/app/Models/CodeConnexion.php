<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Code de connexion envoyé par email ou SMS. Voir App\Services\CodesConnexion. */
class CodeConnexion extends Model
{
    protected $table = 'codes_connexion';

    /** @var list<string> */
    protected $fillable = ['identifiant', 'canal', 'empreinte', 'expire_le', 'tentatives', 'utilise_le', 'ip'];

    /** @var list<string> */
    protected $hidden = ['empreinte'];

    protected function casts(): array
    {
        return [
            'expire_le' => 'datetime',
            'utilise_le' => 'datetime',
            'tentatives' => 'integer',
        ];
    }
}
