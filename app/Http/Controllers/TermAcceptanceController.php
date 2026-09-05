<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Models\User;
use App\Models\UserConfirmation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TermAcceptanceController extends Controller
{
    public function accept(string $token, Request $request): View
    {
        $user = User::where('confirmation_token', $token)->firstOrFail();

        if (! $user->needsAcceptance()) {
            return view('terms.accepted', [
                'message' => 'Ya habias aceptado los terminos anteriormente.',
                'showLoginLink' => true,
            ]);
        }

        return view('terms.accept', [
            'token' => $token,
            'user' => $user,
            'term' => $this->currentTerms(),
        ]);
    }

    public function store(string $token, Request $request): View
    {
        $user = User::where('confirmation_token', $token)->firstOrFail();

        $request->validate([
            'accept_terms' => ['accepted'],
        ]);

        if (! $user->needsAcceptance()) {
            return view('terms.accepted', [
                'message' => 'Ya habias aceptado los terminos anteriormente.',
                'showLoginLink' => true,
            ]);
        }

        $user->markAccepted([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        UserConfirmation::create([
            'user_id' => $user->id,
            'token' => $token,
            'confirmed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);

        return view('terms.accepted', [
            'message' => 'Gracias! Ahora podes ingresar normalmente.',
            'showLoginLink' => true,
        ]);
    }

    private function currentTerms(): Term
    {
        return Term::firstOrCreate(
            ['slug' => 'terms-of-use'],
            ['content' => 'Aqui puedes redactar los terminos y condiciones...']
        );
    }
}
