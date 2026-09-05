<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $this->guard();
        $locations = Location::orderBy('name')->paginate(15);

        return view('traffic.locations.index', [
            'locations' => $locations,
        ]);
    }

    public function store(Request $request)
    {
        $this->guard();
        $data = $this->validated($request);
        Location::create($data);

        return redirect()->route('traffic.locations.index')->with('ok', 'Depósito creado.');
    }

    public function update(Request $request, Location $location)
    {
        $this->guard();
        $location->update($this->validated($request));

        return redirect()->route('traffic.locations.index')->with('ok', 'Depósito actualizado.');
    }

    public function destroy(Location $location)
    {
        $this->guard();
        $location->delete();

        return redirect()->route('traffic.locations.index')->with('ok', 'Depósito eliminado.');
    }

    private function validated(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function guard(): void
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            abort(403);
        }
    }
}
