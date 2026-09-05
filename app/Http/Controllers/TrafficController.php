<?php

namespace App\Http\Controllers;

use App\Models\TrafficRoute;
use App\Models\Transportista;
use App\Models\Transporte;

class TrafficController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $isLimitedTransportista = $user
            && $user->isTransportista()
            && ! $user->isAdminOrSuper();

        $transportistaId = $isLimitedTransportista && $user ? $user->assignedTransportistaId() : null;

        // Base para rutas (para "Últimas rutas")
        $routeBaseQuery = TrafficRoute::with(['transportista', 'transporte']);

        if ($isLimitedTransportista) {
            if ($transportistaId) {
                $routeBaseQuery->where('transportista_id', $transportistaId);
            } else {
                $routeBaseQuery->whereRaw('0 = 1');
            }
        }

        $latestRoutes = (clone $routeBaseQuery)
            ->latest()
            ->take(5)
            ->get();

        // Base para el contador de rutas
        $routeCountQuery = TrafficRoute::query();

        if ($isLimitedTransportista) {
            if ($transportistaId) {
                $routeCountQuery->where('transportista_id', $transportistaId);
            } else {
                $routeCountQuery->whereRaw('0 = 1');
            }
        }

        // Cantidad de vehículos
        $vehicleCount = Transporte::count();
        if ($isLimitedTransportista && $transportistaId) {
            // Si tenés relación transportista -> transportes, usala
            if (method_exists(\App\Models\Transportista::class, 'transportes')) {
                $transportista = Transportista::find($transportistaId);
                if ($transportista) {
                    $vehicleCount = $transportista->transportes()->count();
                } else {
                    $vehicleCount = 0;
                }
            }
        }

        return view('traffic.dashboard', [
            'carrierCount'           => $isLimitedTransportista ? 1 : Transportista::count(),
            'vehicleCount'           => $vehicleCount,
            'routeCount'             => $routeCountQuery->count(),
            'latestRoutes'           => $latestRoutes,
            'isLimitedTransportista' => $isLimitedTransportista,
        ]);
    }
}
