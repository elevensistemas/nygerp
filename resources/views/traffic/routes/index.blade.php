@extends('layouts.app')

@section('title', 'Rutas planificadas')

@section('content')
    <div class="page-header">
      <div class="title-block">
        <h1 class="h3 mb-1">Rutas planificadas</h1>
        <p class="text-muted mb-0">Historial de rutas generadas con información de tráfico en tiempo real.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
      @unless($isTransportistaView)
        <a class="btn btn-primary d-none" href="{{ route('traffic.routes.create') }}">
          <i class="fa-solid fa-route me-1"></i> Nueva ruta
        </a>
        <form class="d-inline" method="POST" action="{{ route('traffic.routes.destroyAll') }}"
              data-confirm="¿Eliminar todas las rutas no iniciadas?">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger" type="submit">
            <i class="fa-solid fa-trash me-1"></i> Eliminar todas
          </button>
        </form>
      @endunless
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body border-bottom">
      <form class="row g-3 align-items-end routes-filters" method="GET" action="{{ route('traffic.routes.index') }}">
        @unless($isTransportistaView)
          <div class="col-md-4">
            <label class="form-label">Transportista</label>
            <select class="form-select" name="transportista_id">
              <option value="">Todos</option>
              @foreach($transportistas as $transportista)
                <option value="{{ $transportista->id }}" {{ (int)($filters['transportista_id'] ?? 0) === $transportista->id ? 'selected' : '' }}>
                  {{ $transportista->name }}
                </option>
              @endforeach
            </select>
          </div>
        @endunless
        <div class="{{ $isTransportistaView ? 'col-md-2' : 'col-md-3' }}">
          <label class="form-label">Fecha</label>
          <input type="date" class="form-control" name="scheduled_date" value="{{ $filters['scheduled_date'] ?? '' }}">
        </div>
        <div class="{{ $isTransportistaView ? 'col-md-2' : 'col-md-3' }}">
          <label class="form-label">Estado</label>
          <select class="form-select" name="status">
            <option value="">Todos</option>
            @foreach($statusOptions as $key => $label)
              <option value="{{ $key }}" {{ ($filters['status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        @if($isTransportistaView)
          <div class="col-md-2">
            <label class="form-label">Mostrar</label>
            <select class="form-select" name="completion">
              <option value="pending" {{ ($filters['completion'] ?? 'pending') === 'pending' ? 'selected' : '' }}>Pendientes</option>
              <option value="completed" {{ ($filters['completion'] ?? '') === 'completed' ? 'selected' : '' }}>Completadas</option>
              <option value="all" {{ ($filters['completion'] ?? '') === 'all' ? 'selected' : '' }}>Todas</option>
            </select>
          </div>
        @endif
        <div class="{{ $isTransportistaView ? 'col-md-2' : 'col-md-2' }}">
          <label class="form-label">Envio</label>
          <select class="form-select" name="sent">
            <option value="">Todos</option>
            <option value="yes" {{ ($filters['sent'] ?? '') === 'yes' ? 'selected' : '' }}>Enviadas</option>
            <option value="no" {{ ($filters['sent'] ?? '') === 'no' ? 'selected' : '' }}>No enviadas</option>
          </select>
        </div>
        <div class="{{ $isTransportistaView ? 'col-md-4' : 'col-md-2' }} d-flex gap-2">
          <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
          <a class="btn btn-outline-secondary" href="{{ route('traffic.routes.index') }}">Limpiar</a>
        </div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0 routes-table">
        <thead>
          <tr>
            <th>Código</th>
            <th class="d-none d-md-table-cell">Transportista</th>
            <th>Pedido</th>
            <th class="d-none d-lg-table-cell">Transporte</th>
            <th>Fecha</th>
            <th class="d-none d-lg-table-cell">Distancia</th>
            <th>Duración</th>
            <th>Tráfico</th>
            <th>Estado real</th>
            @unless($isTransportistaView)
              <th>Envio</th>
            @endunless
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($routes as $route)
            <tr>
              <td class="fw-semibold">{{ $route->code }}</td>
              <td class="d-none d-md-table-cell">
                <span class="carrier-color-dot" style="background: {{ $route->transportista->color ?? '#2563eb' }};"></span>
                {{ $route->transportista->name ?? 'N/D' }}
              </td>
              <td>
                @if($route->order)
                  <strong>{{ $route->order->order_number }}</strong>
                  <div class="text-muted small">{{ $route->order->client_name }}</div>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td class="d-none d-lg-table-cell">{{ $route->transporte->alias ?? 'N/D' }}</td>
              <td>{{ optional($route->scheduled_date)->format('d/m/Y') ?? 'Sin fecha' }}</td>
              <td class="d-none d-lg-table-cell">{{ $route->distance_km ? $route->distance_km . ' km' : 'n/d' }}</td>
              <td class="d-none d-lg-table-cell">{{ $route->duration_formatted ?? 'n/d' }}</td>
              <td class="d-none d-xl-table-cell">
                <span class="badge text-bg-light">{{ $route->traffic_summary ?? 'Sin datos' }}</span>
              </td>
              <td>
                <span class="badge bg-light text-dark">{{ ucfirst($route->status ?? 'planificada') }}</span>
                @if($route->actual_duration_formatted)
                  <div class="text-muted small">Real: {{ $route->actual_duration_formatted }}</div>
                @endif
              </td>
              @unless($isTransportistaView)
                <td>
                  @if($route->sent_at)
                    <span class="badge text-bg-success">Enviada</span>
                  @else
                    <span class="badge text-bg-secondary">No enviada</span>
                  @endif
                </td>
              @endunless
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('traffic.routes.show', $route) }}">Ver</a>
                @if(! $isTransportistaView && $route->sent_at === null)
                  <form class="d-inline-block ms-1" method="POST" action="{{ route('traffic.routes.send', $route) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-success" type="submit">Enviar</button>
                  </form>
                @endif
                @if(! $isTransportistaView && ! $route->started_at)
                  <form class="d-inline-block ms-1"
                        method="POST"
                        action="{{ route('traffic.routes.destroy', $route) }}"
                        data-confirm="¿Eliminar ruta?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $isTransportistaView ? 10 : 11 }}" class="text-center text-muted py-4">No hay rutas generadas.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $routes->links() }}
    </div>
  </div>
@endsection

@push('styles')
  <style>
    @media (max-width: 768px) {
      .routes-filters .form-label {
        font-size: 0.8rem;
      }
      .routes-filters .btn {
        width: 100%;
      }
      .routes-filters .d-flex {
        flex-direction: column;
      }
      .routes-table td {
        vertical-align: top;
      }
      .routes-table .btn {
        width: 100%;
        margin-top: 6px;
      }
    }
  </style>
@endpush

