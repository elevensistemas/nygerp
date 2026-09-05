<?php

namespace App\Mail;

use App\Models\Term;
use App\Models\User;
use Illuminate\Mail\Mailable;

class TermsAcceptanceMail extends Mailable
{
    protected User $user;
    protected Term $terms;
    protected string $token;
    protected ?string $plainPassword;

    public function __construct(User $user, Term $terms, string $token, ?string $plainPassword = null)
    {
        $this->user = $user;
        $this->terms = $terms;
        $this->token = $token;
        $this->plainPassword = $plainPassword;
    }

    public function build()
    {
        return $this->subject('Confirma tus terminos y condiciones')
            ->view('emails.terms.acceptance')
            ->with([
                'user' => $this->user,
                'terms' => $this->terms,
                'acceptLink' => route('terms.accept', $this->token),
                'plainPassword' => $this->plainPassword,
            ]);
    }
}
