<?php

namespace App\Services;

use App\Mail\TermsAcceptanceMail;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Swift_TransportException;

class TermsAcceptanceService
{
    public function send(User $user, bool $forceNewToken = true, ?string $plainPassword = null): void
    {
        $terms = $this->currentTerms();
        $token = $forceNewToken || empty($user->confirmation_token)
            ? $user->newConfirmationToken()
            : $user->confirmation_token;

        // Cada envío deja al usuario pendiente hasta que acepte nuevamente.
        $user->markPending($token);

        try {
            Mail::to($user)->send(new TermsAcceptanceMail($user, $terms, $token, $plainPassword));
        } catch (Swift_TransportException|\Throwable $e) {
            // En entornos locales sin SMTP disponible, evitamos que falle el alta del usuario.
            Log::warning('No se pudo enviar email de aceptación de términos: '.$e->getMessage());
        }
    }

    protected function currentTerms(): Term
    {
        if (!Schema::hasTable('terms')) {
            $term = new Term();
            $term->slug = 'terms-of-use';
            $term->content = 'Acepto los términos y condiciones estándar.';
            return $term;
        }

        return Term::firstOrCreate(
            ['slug' => 'terms-of-use'],
            ['content' => 'Aquí puedes redactar los términos y condiciones...']
        );
    }
}
