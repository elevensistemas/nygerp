<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Transportista;
use App\Models\User;
use App\Services\TermsAcceptanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $currentUser = $request->user();

        $query = User::query()->orderBy('name');
        if ($currentUser && ! $currentUser->isAdminOrSuper()) {
            $query->where('id', $currentUser->id);
        }

        if ($currentUser && $currentUser->isAdminOrSuper()) {
            $query->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $users = $query
            ->paginate(20)
            ->withQueryString();

        return view('configuration.users.index', [
            'users' => $users,
            'search' => $search,
            'roles' => User::roleLabels(),
            'canManageUsers' => $currentUser && $currentUser->isAdminOrSuper(),
            'currentUser' => $currentUser,
            'transportistas' => Transportista::orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, TermsAcceptanceService $termsService): RedirectResponse
    {
        $requestUser = $request->user();
        abort_unless($requestUser && $requestUser->isAdminOrSuper(), 403);

        $data = $request->validate($this->rules());
        $plainPassword = $data['password'];
        $data['password'] = Hash::make($plainPassword);
        $data['role'] = $request->input('role', User::ROLE_USER);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($data);
        $this->syncTransportistaProfile($user, $request);
        $termsService->send($user, true, $plainPassword);

        return redirect()->route('users.index')->with('ok', 'Usuario creado');
    }

    public function edit(User $user): JsonResponse
    {
        $currentUser = auth()->user();
        abort_if($user->id === 1, 403, 'El usuario admin no se puede editar.');
        abort_unless(
            ($currentUser && $currentUser->isAdminOrSuper()) || ($currentUser && $currentUser->id === $user->id),
            403,
            'No tienes permisos'
        );

        return new JsonResponse($user->load('transportistaProfile'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        abort_if($user->id === 1, 403, 'El usuario admin no se puede editar.');
        abort_unless(
            ($currentUser && $currentUser->isAdminOrSuper()) || ($currentUser && $currentUser->id === $user->id),
            403,
            'No tienes permisos'
        );

        $canManageUsers = $currentUser && $currentUser->isAdminOrSuper();
        $data = $request->validate($this->rules($user, (bool) $canManageUsers));

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (! $canManageUsers) {
            unset($data['role']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        $this->syncTransportistaProfile($user, $request);

        return redirect()->route('users.index')->with('ok', 'Usuario actualizado');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === 1, 403, 'El usuario admin no se puede eliminar.');
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->isAdminOrSuper(), 403, 'No tienes permisos');

        $user->delete();

        return redirect()->route('users.index')->with('ok', 'Usuario eliminado');
    }

    public function validateUser(User $user): JsonResponse
    {
        $currentUser = auth()->user();
        abort_unless($currentUser && $currentUser->isAdminOrSuper(), 403, 'No tienes permisos');

        if (! $user->needsAcceptance()) {
            return new JsonResponse(['message' => 'El usuario ya se encuentra validado.'], 422);
        }

        $user->markAccepted();

        return new JsonResponse(['message' => 'Usuario validado manualmente.']);
    }

    private function rules(?User $user = null, bool $allowRole = true): array
    {
        $emailRule = 'required|email|max:255|unique:users,email';

        if ($user) {
            $emailRule .= ',' . $user->id;
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => $emailRule,
            'password' => $user
                ? 'nullable|string|min:8|confirmed'
                : 'required|string|min:8|confirmed',
            'transportista_id' => 'nullable|exists:transportistas,id',
            'avatar' => 'nullable|image|max:2048',
        ];

        if ($allowRole) {
            $rules['role'] = 'required|string|in:' . implode(',', User::roles());
        }

        $rules['profile_supplier_id'] = 'nullable|exists:suppliers,id';
        $rules['profile_license'] = 'nullable|string|max:100';
        $rules['profile_base_location'] = 'nullable|string|max:255';
        $rules['profile_notes'] = 'nullable|string|max:500';
        $rules['profile_color'] = ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'];

        // Solo validar el estado activo cuando el usuario (nuevo o existente) es transportista.
        $isTransportistaRole = ($user && $user->normalizedRole() === User::ROLE_TRANSPORTISTA)
            || request('role') === User::ROLE_TRANSPORTISTA;
        if ($isTransportistaRole) {
            // Acepta checkbox/switch (on/off/1/0/true/false) sin exigir que esté presente.
            $rules['profile_active'] = 'sometimes|in:1,0,on,off,true,false';
        }

        return $rules;
    }

    private function syncTransportistaProfile(User $user, Request $request): void
    {
        if ($user->normalizedRole() !== User::ROLE_TRANSPORTISTA) {
            $profile = $user->transportistaProfile;
            if ($profile) {
                $profile->delete();
            }

            $user->update(['transportista_id' => null]);
            return;
        }

        $profile = $user->transportistaProfile;
        $profileData = [
            'user_id' => $user->id,
            'name' => $user->name,
            'business_name' => optional($profile)->business_name,
            'email' => $user->email,
            'phone' => $request->input('phone'),
            'license_number' => $request->input('profile_license'),
            'base_location' => $request->input('profile_base_location'),
            'supplier_id' => $request->input('profile_supplier_id'),
            'notes' => $request->input('profile_notes'),
            'color' => $request->input('profile_color') ?: optional($profile)->color,
            'is_active' => $request->has('profile_active')
                ? $request->boolean('profile_active')
                : false,
        ];

        if ($profile) {
            $profile->update($profileData);
        } else {
            $profile = Transportista::create($profileData);
        }

        if ($user->transportista_id !== $profile->id) {
            $user->update(['transportista_id' => $profile->id]);
        }
    }
}
