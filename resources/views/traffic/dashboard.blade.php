@extends('layouts.app')

@section('title', 'Tráfico')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Centro de tráfico</h1>
      <p class="text-muted mb-0">Seguimiento integral de carriers, transportes y rutas programadas.</p>
    </div>
    <div class="d-flex gap-2">
      @if($isLimitedTransportista ?? false)
        <a class="btn btn-outline-secondary" href="{{ route('traffic.routes.index') }}">
          <i class="fa-solid fa-route me-1"></i> Mis rutas
        </a>
      @else
        <a class="btn btn-outline-secondary" href="{{ route('traffic.transportes.index') }}">
          <i class="fa-solid fa-truck-moving me-1"></i> Gestionar transportes
        </a>
        <a class="btn btn-primary" href="{{ route('traffic.routes.create') }}">
          <i class="fa-solid fa-route me-1"></i> Generador de rutas
        </a>
      @endif
    </div>
  </div>

  @if($isLimitedTransportista ?? false)
    <div class="alert alert-warning">Estás viendo sólo tus rutas porque tenés el rol de transportista.</div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Transportistas activos</p>
          @if($isLimitedTransportista ?? false)
            <h2 class="fw-semibold">1</h2>
            <small class="text-muted">Sólo el assigned</small>
          @else
            <h2 class="fw-semibold">{{ $carrierCount }}</h2>
          @endif
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Transportes disponibles</p>
          <h2 class="fw-semibold">{{ $vehicleCount }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Rutas planificadas</p>
          <h2 class="fw-semibold">{{ $routeCount }}</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <h2 class="h5 mb-0">Últimas rutas</h2>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('traffic.routes.index') }}">Ver todas</a>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Código</th>
            <th>Transportista</th>
            <th>Transporte</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($latestRoutes as $route)
            <tr>
              <td class="fw-semibold">{{ $route->code }}</td>
              <td>{{ $route->transportista->name ?? 'N/D' }}</td>
              <td>{{ $route->transporte->alias ?? 'N/D' }}</td>
              <td>{{ optional($route->scheduled_date)->format('d/m/Y') ?? 'Sin fecha' }}</td>
              <td>
                <span class="badge text-bg-light">{{ ucfirst($route->status) }}</span>
              </td>
              <td>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('traffic.routes.show', $route) }}">
                  Ver detalle
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Todavía no se generaron rutas.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
