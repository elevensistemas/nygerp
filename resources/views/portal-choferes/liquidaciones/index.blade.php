@extends('layouts.app')

@section('title', 'Portal Choferes - Mis Liquidaciones')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Mis Liquidaciones</h1>
    <p class="text-muted mb-0">Revisa y confirma los items de tus liquidaciones quincenales / mensuales.</p>
  </div>
</div>

<form method="GET" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" value="{{ $filters['desde'] ?? '' }}" class="form-control" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}" class="form-control" required>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-primary w-100">Filtrar</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 text-center">
      <thead>
      <tr>
        <th>Nro.</th>
        <th>Fecha emision</th>
        <th>Tipo periodo</th>
        <th>Desde</th>
        <th>Hasta</th>
        <th>Estado General</th>
        <th>Items</th>
        <th class="text-end">Total Liquidado</th>
        <th class="text-center">Acciones</th>
      </tr>
      </thead>
      <tbody>
      @forelse($recibos as $recibo)
        <tr>
          <td># {{ $recibo->id }}</td>
          <td>{{ optional($recibo->fecha_emision)->format('d/m/Y') }}</td>
          <td><span class="text-capitalize">{{ $recibo->tipo_periodo }}</span></td>
          <td>{{ optional($recibo->periodo_desde)->format('d/m/Y') }}</td>
          <td>{{ optional($recibo->periodo_hasta)->format('d/m/Y') }}</td>
          <td>
            @if($recibo->estado === 'Cargado')
              <span class="badge bg-secondary">Borrador</span>
            @elseif($recibo->estado === 'Pendiente de pago')
              <span class="badge bg-primary">Pendiente Pago</span>
            @elseif($recibo->estado === 'En planilla')
              <span class="badge bg-info text-dark">En proceso de Pago</span>
            @elseif($recibo->estado === 'Pagado')
              <span class="badge bg-success">Pagado</span>
            @else
              <span class="badge bg-secondary">{{ $recibo->estado }}</span>
            @endif
          </td>
          <td>{{ $recibo->items_count }} items</td>
          <td class="text-end fw-bold">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</td>
          <td class="text-center">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('portal-choferes.liquidaciones.show', $recibo) }}">
              <i class="fa-solid fa-eye d-sm-none"></i>
              <span class="d-none d-sm-inline">Ver detalle / Conciliar</span>
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="text-center text-muted py-4">No tienes liquidaciones disponibles para visualizar en este momento.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @if($recibos->hasPages())
    <div class="card-footer bg-white border-top-0 pt-3">
      {{ $recibos->links() }}
    </div>
  @endif
</div>
@endsection
