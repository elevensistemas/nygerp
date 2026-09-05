@extends('layouts.app')

@section('title', 'Pedidos logísticos')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Pedidos logísticos</h1>
      <p class="text-muted mb-0">Controla cada pedido antes de convertirlo en ruta.</p>
    </div>
    <div class="page-actions">
    <a class="btn btn-primary" href="{{ route('traffic.orders.create') }}">
      <i class="fa-solid fa-circle-plus me-1"></i> Nuevo pedido
    </a>
    </div>

  <div class="card shadow-sm border-0">
    @if(session('ok'))
      <div class="alert alert-success mb-0">{{ session('ok') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger mb-0">{{ session('error') }}</div>
    @endif
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>N° pedido</th>
            <th>Cliente</th>
            <th>Fecha</th>
            <th>Direcciones</th>
            <th>Estado</th>
            <th>Rutas</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $order)
            <tr>
              <td class="fw-semibold">{{ $order->order_number }}</td>
              <td>
                {{ $order->client_name }}
                @if($order->client_company)
                  <div class="text-muted small">{{ $order->client_company }}</div>
                @endif
              </td>
              <td>{{ optional($order->order_date)->format('d/m/Y') ?? 'Sin fecha' }}</td>
              <td>{{ $order->addresses_count ?? $order->addresses->count() }}</td>
              <td>
                <span class="badge bg-info text-dark">{{ ucfirst($order->status) }}</span>
              </td>
              <td>{{ $order->routes_count }}</td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('traffic.orders.show', $order) }}">Ver</a>
                <a class="btn btn-sm btn-outline-success ms-1" href="{{ route('traffic.routes.create', ['order' => $order->id]) }}">
                  Generar ruta
                </a>
                @php $canDelete = $order->routes->every(fn($route) => $route->started_at === null); @endphp
                @if($canDelete)
                  <form class="d-inline-block ms-1"
                        method="POST"
                        action="{{ route('traffic.orders.destroy', $order) }}"
                        data-confirm="¿Eliminar pedido pendiente?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No hay pedidos cargados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $orders->links() }}
    </div>
  </div>
@endsection
