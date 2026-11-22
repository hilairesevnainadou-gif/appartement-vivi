<?php

namespace App\Sms;

use Illuminate\Support\Facades\Log;

/** Pilote gratuit pour le développement : le SMS est écrit dans storage/logs/laravel.log, rien n'est envoyé. */
class JournalSms implements Sms
{
    public function envoyer(string $numero, string $message): void
    {
        Log::info('SMS (non envoyé, pilote « log ») à '.$numero.' : '.$message);
    }
}
