@extends('layouts.app')

@section('title', 'Pedido ' . $order->order_number)

@section('content')
  @php
    $client = $order->client;
    $clientLabel = $client ? ($client->business_name ?: $client->name) : $order->client_name;
  @endphp
  @php $canDelete = $order->routes->every(fn($route) => $route->started_at === null); @endphp
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Pedido {{ $order->order_number }}</h1>
      <p class="text-muted mb-0">
        Cliente {{ $clientLabel }} · {{ ucfirst($order->status) }}
      </p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.orders.index') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
      <a class="btn btn-primary" href="{{ route('traffic.routes.create', ['order' => $order->id]) }}">
        <i class="fa-solid fa-route me-1"></i> Generar ruta
      </a>
      @if($canDelete)
        <form method="POST" action="{{ route('traffic.orders.destroy', $order) }}" data-confirm="¿Eliminar este pedido? Las rutas planificadas (no iniciadas) también se borrarán.">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger" type="submit">
            <i class="fa-solid fa-trash me-1"></i> Eliminar pedido
          </button>
        </form>
      @endif
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted small mb-1">Fecha del pedido</p>
          <strong>{{ optional($order->order_date)->format('d/m/Y') ?? 'Sin fecha' }}</strong>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted small mb-1">Direcciones</p>
          <strong>{{ $order->addresses->count() }}</strong>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted small mb-1">Estado</p>
          <span class="badge bg-info text-dark">{{ ucfirst($order->status) }}</span>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
      <h2 class="h5 mb-0">Direcciones</h2>
    </div>
    <div class="list-group list-group-flush">
      @foreach($order->addresses as $address)
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-center">
            <strong>#{{ $address->sequence }} {{ $address->label ?? 'Punto' }}</strong>
            <span class="text-muted small">{{ $address->city }}</span>
          </div>
          <div>{{ $address->address }}</div>
          @if($address->contact_name)
            <div class="text-muted small">Contacto: {{ $address->contact_name }} ({{ $address->contact_phone ?: 'sin teléfono' }})</div>
          @endif
          @if($address->notes)
            <div class="text-muted small">Notas: {{ $address->notes }}</div>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white">
      <h2 class="h5 mb-0">Rutas asociadas</h2>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Código</th>
            <th>Transportista</th>
            <th>Transporte</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($order->routes as $route)
            <tr>
              <td>{{ $route->code }}</td>
              <td>
                <span class="carrier-color-dot" style="background: {{ $route->transportista->color ?? '#2563eb' }};"></span>
                {{ $route->transportista->name ?? 'N/D' }}
              </td>
              <td>{{ $route->transporte->alias ?? 'N/D' }}</td>
              <td>{{ optional($route->scheduled_date)->format('d/m/Y') ?? 'Sin fecha' }}</td>
              <td><span class="badge bg-secondary">{{ ucfirst($route->status ?? 'planificada') }}</span></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('traffic.routes.show', $route) }}">Ver</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No hay rutas generadas para este pedido.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
