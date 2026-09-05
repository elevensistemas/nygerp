@extends('layouts.app')

@section('title', 'Reportes de pago a choferes')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Reportes de pago a choferes</h1>
    <p class="text-muted mb-0">Selecciona un reporte a la izquierda y visualizalo en PDF.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-3">
    <div class="card">
      <div class="list-group list-group-flush">
        @forelse($reports as $report)
          <a href="{{ route('pago-choferes.reportes.index', ['report' => $report->id]) }}"
             class="list-group-item list-group-item-action {{ $selectedReport && $selectedReport->id === $report->id ? 'active' : '' }}">
            <div class="fw-semibold">{{ $report->alias }}</div>
            <div class="small {{ $selectedReport && $selectedReport->id === $report->id ? 'text-white-50' : 'text-muted' }}">{{ $report->nombre }}</div>
          </a>
        @empty
          <div class="list-group-item text-muted">No hay reportes configurados.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="card">
      <div class="card-body border-bottom d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 mb-1">{{ $selectedReport ? $selectedReport->alias : 'Sin reporte seleccionado' }}</h2>
          @if($selectedReport)
            <div class="text-muted small">{{ $selectedReport->nombre }}</div>
          @endif
        </div>
        @if($selectedReport)
          <a class="btn btn-outline-primary" target="_blank" href="{{ route('pago-choferes.reportes.pdf', array_merge(['report' => $selectedReport->id], request()->only(['desde', 'hasta', 'tipo_reclamo']))) }}">
            <i class="fa-solid fa-file-pdf me-2"></i> Abrir PDF
          </a>
        @endif
      </div>
      @if($selectedReport)
        <div class="card-body bg-light border-bottom">
          <form method="GET" action="{{ route('pago-choferes.reportes.index') }}" class="row g-3 align-items-end">
            <input type="hidden" name="report" value="{{ $selectedReport->id }}">
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Desde</label>
              <input type="date" name="desde" class="form-control form-control-sm" value="{{ request('desde', now()->startOfMonth()->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Hasta</label>
              <input type="date" name="hasta" class="form-control form-control-sm" value="{{ request('hasta', now()->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Tipo de Reclamo</label>
              <select name="tipo_reclamo" class="form-select form-select-sm">
                <option value="todos" {{ request('tipo_reclamo') == 'todos' ? 'selected' : '' }}>Todos</option>
                <option value="dias" {{ request('tipo_reclamo') == 'dias' ? 'selected' : '' }}>Días Faltantes</option>
                <option value="liquidacion" {{ request('tipo_reclamo') == 'liquidacion' ? 'selected' : '' }}>Liquidación</option>
              </select>
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
            </div>
          </form>
        </div>
      @endif
      <div class="card-body p-0">
        @if($selectedReport)
          <iframe
            title="visor-reporte"
            src="{{ route('pago-choferes.reportes.pdf', array_merge(['report' => $selectedReport->id], request()->only(['desde', 'hasta', 'tipo_reclamo']))) }}"
            style="width: 100%; min-height: 78vh; border: 0;">
          </iframe>
        @else
          <div class="p-4 text-muted">No hay reportes disponibles.</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
