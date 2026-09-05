<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TermsAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ConfirmationController extends Controller
{
    public function index(): View
    {
        if (! Schema::hasColumn('users', 'accepted_at')) {
            $pending = new LengthAwarePaginator([], 0, 20, request('page', 1), [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        } else {
            $pending = User::query()
                ->whereNull('accepted_at')
                ->orderBy('created_at')
                ->paginate(20);
        }

        return view('configuration.confirmations', [
            'pending' => $pending,
        ]);
    }

    public function resend(User $user, TermsAcceptanceService $service): RedirectResponse
    {
        $service->send($user);

        return redirect()->route('confirmations.pending')->with('ok', 'Correo reenviado');
    }
}
