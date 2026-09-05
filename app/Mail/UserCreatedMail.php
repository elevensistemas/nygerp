<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class UserCreatedMail extends Mailable
{
    protected User $user;
    protected string $plainPassword;

    public function __construct(User $user, string $plainPassword)
    {
        $this->user = $user;
        $this->plainPassword = $plainPassword;
    }

    public function build()
    {
        return $this->subject('Tu cuenta de usuario ha sido creada')
            ->view('emails.user.created')
            ->with([
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
            ]);
    }
}
