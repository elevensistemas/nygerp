@extends('layouts.app')
@section('title','Agenda de pagos')

@section('content')
@php
    $fmt = static fn (float $value): string => '$' . number_format($value, 2, ',', '.');
@endphp

<style>
.agenda-status-badge.overdue { background-color: #f8d7da; color: #842029; }
.agenda-status-badge.imminent { background-color: #ffe8a1; color: #7a5600; }
.agenda-status-badge.soon { background-color: #fff3cd; color: #7a5600; }
.agenda-status-badge.future { background-color: #d1e7dd; color: #0f5132; }

.agenda-card { border-radius: .65rem; padding: 1rem; border: 1px solid #f1f3f5; background-color: #fff; }
.agenda-card.overdue { border-left: 4px solid #dc3545; background-color: #fff6f6; }
.agenda-card.imminent { border-left: 4px solid #fd7e14; background-color: #fff7e6; }
.agenda-card.soon { border-left: 4px solid #ffc107; background-color: #fff9e6; }
.agenda-card.future { border-left: 4px solid #198754; background-color: #f4fcf6; }

.agenda-row.status-overdue { background-color: #f8d7da; }
.agenda-row.status-imminent { background-color: #fff3cd; }
.agenda-row.status-soon { background-color: #fff8e1; }
.agenda-row.status-future { background-color: #f6fff6; }

.agenda-row.status-overdue td,
.agenda-row.status-imminent td,
.agenda-row.status-soon td,
.agenda-row.status-future td { border-top-color: rgba(0,0,0,.05); }
</style>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
  <div>
    <h1 class="h4 mb-1">Agenda de pagos</h1>
    <p class="text-muted mb-0">Visualizá los vencimientos próximos y reprogramá los atrasados desde un calendario de colores.</p>
  </div>
  <div class="btn-group">
    <a href="{{ route('dashboard', array_merge($viewToggleQuery ?? [], ['view' => 'calendar'])) }}"
       class="btn btn-sm {{ $viewMode === 'calendar' ? 'btn-primary' : 'btn-outline-primary' }}">
      Vista calendario
    </a>
    <a href="{{ route('dashboard', array_merge($viewToggleQuery ?? [], ['view' => 'table'])) }}"
       class="btn btn-sm {{ $viewMode === 'table' ? 'btn-primary' : 'btn-outline-primary' }}">
      Vista tabla
    </a>
  </div>
</div>

<form method="GET" action="{{ route('dashboard') }}" class="card shadow-sm border-0 mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Filtros</span>
    <a href="{{ route('dashboard') }}" class="text-decoration-none small">Limpiar</a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="from" value="{{ $filters['from'] }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="to" value="{{ $filters['to'] }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Condición de pago</label>
        <select class="form-select" name="term_id">
          <option value="">Todas</option>
          @foreach($terms as $term)
            <option value="{{ $term->id }}" {{ ($filters['term_id'] ?? null) == $term->id ? 'selected' : '' }}>
              {{ $term->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Vista</label>
        <select class="form-select" name="view">
          <option value="calendar" {{ $filters['view'] === 'calendar' ? 'selected' : '' }}>Calendario</option>
          <option value="table" {{ $filters['view'] === 'table' ? 'selected' : '' }}>Tabla</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div class="small text-muted">
      Ventana seleccionada: {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}
      – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
    </div>
    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
  </div>
</form>

<div class="d-flex flex-column flex-lg-row align-items-stretch gap-3 mb-4">
  <div class="card border-0 shadow-sm flex-fill">
    <div class="card-body">
      <div class="text-muted small">Pagos en ventana</div>
      <div class="fs-4 fw-semibold">{{ $summary['upcoming_count'] }} vencimientos</div>
      <div class="small text-muted">{{ $fmt($summary['upcoming_total']) }} totales</div>
    </div>
  </div>
  <div class="card border-0 shadow-sm flex-fill">
    <div class="card-body">
      <div class="text-muted small">Pagos vencidos</div>
      <div class="fs-4 fw-semibold text-danger">{{ $summary['overdue_count'] }}</div>
      <div class="small text-muted">{{ $fmt($summary['overdue_total']) }} pendientes</div>
    </div>
  </div>
  <div class="card border-0 shadow-sm flex-fill">
    <div class="card-body d-flex flex-column justify-content-between h-100">
      <div>
        <div class="text-muted small">Exportar</div>
        <div class="small text-muted mb-2">Descargá la agenda según filtros aplicados.</div>
      </div>
      <a class="btn btn-outline-primary btn-sm align-self-start" href="{{ route('exports.payments.csv', $exportQuery ?? []) }}">
        Exportar CSV
      </a>
    </div>
  </div>
</div>

@if($overdue->isNotEmpty())
  <div class="card border-danger-subtle shadow-sm mb-4">
    <div class="card-header bg-danger-subtle text-danger d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Pagos vencidos ({{ $overdue->count() }})</span>
      <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#overdueCollapse">
        Mostrar / ocultar
      </button>
    </div>
    <div class="collapse show" id="overdueCollapse">
      <div class="list-group list-group-flush">
        @foreach($overdue as $item)
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold">{{ $item->document->supplier->name ?? 'Sin proveedor' }}</div>
              <div class="small text-muted">
                {{ $item->document->doctype_label }} {{ $item->document->number }}
                · Vencido el {{ optional($item->due_date)->format('d/m/Y') }}
              </div>
            </div>
            <div class="text-end">
              <div class="fw-semibold text-danger">{{ $fmt((float) $item->amount) }}</div>
              <a class="btn btn-sm btn-outline-danger" href="{{ route('documents.show', $item->document_id) }}">Ver comprobante</a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
@endif

@if($viewMode === 'calendar')
  <div class="row g-3">
    @forelse($calendarGroups as $date => $items)
      @php
        $dateObj = \Carbon\Carbon::parse($date)->locale(app()->getLocale());
        $dayLabel = $dateObj->translatedFormat('l');
        $shortDate = $dateObj->format('d/m');
      @endphp
      <div class="col-xl-4 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
            <span>{{ ucfirst($dayLabel) }} · {{ $shortDate }}</span>
            <small class="text-muted">{{ $fmt((float) $items->sum('amount')) }}</small>
          </div>
          <div class="card-body d-flex flex-column gap-3">
            @foreach($items as $item)
              <div class="agenda-card {{ $item->status_key }}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <div class="fw-semibold">{{ $item->document->supplier->name ?? 'Sin proveedor' }}</div>
                    <div class="small text-muted">
                      {{ $item->document->doctype_label }} {{ $item->document->number }}
                      @if($item->status_key === 'overdue')
                        · <span class="text-danger">Vencido el {{ optional($item->due_date)->format('d/m') }}</span>
                      @endif
                    </div>
                    <div class="small text-muted">
                      Condición: {{ optional($item->document->term)->name ?? 'Sin asignar' }} · Cuota #{{ $item->installment_number }}
                    </div>
                  </div>
                  <div class="text-end">
                    <div class="fw-semibold">{{ $fmt((float) $item->amount) }}</div>
                    <span class="badge rounded-pill agenda-status-badge {{ $item->status_key }}">
                      {{ $item->status_label }}
                    </span>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="alert alert-light text-center mb-0">
          No hay vencimientos en la ventana seleccionada.
        </div>
      </div>
    @endforelse
  </div>
@else
  <div class="table-responsive bg-white shadow-sm rounded">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Vencimiento</th>
          <th>Estado</th>
          <th>Proveedor</th>
          <th>Comprobante</th>
          <th>Condición</th>
          <th class="text-end">Monto</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @php
          $tableRows = $calendarGroups->flatten(1);
        @endphp
        @forelse($tableRows as $item)
          <tr class="agenda-row status-{{ $item->status_key }}">
            <td>{{ optional($item->due_date)->format('d/m/Y') }}</td>
            <td>
              <span class="badge rounded-pill agenda-status-badge {{ $item->status_key }}">
                {{ $item->status_label }}
              </span>
            </td>
            <td>{{ $item->document->supplier->name ?? 'Sin proveedor' }}</td>
            <td>
              <a href="{{ route('documents.show', $item->document_id) }}" class="text-decoration-none">
                {{ $item->document->doctype_label }} {{ $item->document->number }}
              </a>
            </td>
            <td>{{ optional($item->document->term)->name ?? 'Sin asignar' }}</td>
            <td class="text-end fw-semibold">{{ $fmt((float) $item->amount) }}</td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show', $item->document_id) }}">
                Ver
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              No hay vencimientos para mostrar.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif
@endsection
