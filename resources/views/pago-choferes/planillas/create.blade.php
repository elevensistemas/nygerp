@extends('layouts.app')

@section('title', 'Crear Planilla')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Crear Planilla</h1>
    <p class="text-muted mb-0">Selecciona recibos en estado cargado o pendiente_pago.</p>
  </div>
</div>

<form method="GET" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Transportista</label>
      <select name="transportista_id" class="form-select">
        <option value="">Todos</option>
        @foreach($transportistas as $transportista)
          <option value="{{ $transportista->id }}" {{ (int) ($filters['transportista_id'] ?? 0) === $transportista->id ? 'selected' : '' }}>
            {{ $transportista->name }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Tipo período</label>
      <select name="tipo_periodo" class="form-select">
        <option value="">Todos</option>
        <option value="quincenal" {{ ($filters['tipo_periodo'] ?? '') === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
        <option value="mensual" {{ ($filters['tipo_periodo'] ?? '') === 'mensual' ? 'selected' : '' }}>Mensual</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        <option value="cargado" {{ ($filters['estado'] ?? '') === 'cargado' ? 'selected' : '' }}>cargado</option>
        <option value="pendiente_pago" {{ ($filters['estado'] ?? '') === 'pendiente_pago' ? 'selected' : '' }}>pendiente_pago</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Zona</label>
      <input type="text" name="zona" class="form-control" value="{{ $filters['zona'] ?? '' }}" placeholder="Zona">
    </div>
    <div class="col-md-3">
      <label class="form-label">Buscar</label>
      <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="ID, transportista o concepto">
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-outline-primary w-100">Filtrar</button>
      <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.planillas.create') }}">Limpiar</a>
    </div>
  </div>
</form>

<form method="POST" action="{{ route('pago-choferes.planillas.store') }}" class="card card-body">
  @csrf
  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label">Fecha</label>
      <input type="date" name="fecha" class="form-control" value="{{ date('Y-m-d') }}" required>
    </div>
    <div class="col-md-9">
      <label class="form-label">Observaciones</label>
      <input type="text" name="observaciones" class="form-control">
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="small text-muted">{{ $recibos->total() }} recibos disponibles</div>
    <div>{{ $recibos->links() }}</div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-sm align-middle">
      <thead>
      <tr>
        <th><input type="checkbox" id="selectAllRecibosPlanilla"></th>
        <th>Recibo</th>
        <th>Transportista</th>
        <th>Zona</th>
        <th>Periodo</th>
        <th>Estado</th>
        <th class="text-end">Importe</th>
      </tr>
      </thead>
      <tbody>
      @forelse($recibos as $recibo)
        @php
          $zonaItem = optional($recibo->items->first())->zona;
          if ($zonaItem === null || $zonaItem === '') {
            $zonaItem = data_get(optional($recibo->items->first())->meta, 'zona_hoja')
              ?: data_get(optional($recibo->items->first())->meta, 'zona')
              ?: data_get(optional($recibo->items->first())->meta, 'raw.zona');
          }
        @endphp
        <tr>
          <td><input type="checkbox" class="planilla-recibo-check" data-recibo-id="{{ $recibo->id }}"></td>
          <td>#{{ $recibo->id }}</td>
          <td>{{ $recibo->displayName() ?: '-' }}</td>
          <td>{{ $zonaItem ?: '-' }}</td>
          <td>{{ optional($recibo->periodo_desde)->format('d/m/Y') }} - {{ optional($recibo->periodo_hasta)->format('d/m/Y') }}</td>
          <td>{{ $recibo->estado }}</td>
          <td class="text-end">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted">No hay recibos disponibles.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-end mb-3">
    {{ $recibos->links() }}
  </div>

  <div class="sticky-bottom bg-white border-top d-flex gap-2 shadow" style="position: sticky; bottom: 0; z-index: 1020; margin: 0 -20px -20px -20px; padding: 15px 20px; background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(5px); border-bottom-left-radius: 0.375rem; border-bottom-right-radius: 0.375rem;">
    <button class="btn btn-primary">Crear planilla</button>
    <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.planillas.index') }}">Cancelar</a>
  </div>
</form>

@push('scripts')
<script>
  (function () {
    const storageKey = 'planilla_create_selected_recibos';
    const checks = Array.from(document.querySelectorAll('.planilla-recibo-check'));
    const selectAll = document.getElementById('selectAllRecibosPlanilla');
    const form = document.querySelector('form[action="{{ route('pago-choferes.planillas.store') }}"]');

    const loadSelected = function () {
      try {
        const parsed = JSON.parse(localStorage.getItem(storageKey) || '[]');
        return Array.isArray(parsed) ? new Set(parsed.map(String)) : new Set();
      } catch (e) {
        return new Set();
      }
    };

    const saveSelected = function (set) {
      localStorage.setItem(storageKey, JSON.stringify(Array.from(set)));
    };

    const selected = loadSelected();

    checks.forEach(function (check) {
      const id = check.dataset.reciboId;
      check.checked = selected.has(String(id));
      check.addEventListener('change', function () {
        if (check.checked) {
          selected.add(String(id));
        } else {
          selected.delete(String(id));
        }
        saveSelected(selected);
      });
    });

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checks.forEach(function (check) {
          check.checked = !!selectAll.checked;
          const id = check.dataset.reciboId;
          if (check.checked) {
            selected.add(String(id));
          } else {
            selected.delete(String(id));
          }
        });
        saveSelected(selected);
      });
    }

    if (form) {
      form.addEventListener('submit', function () {
        form.querySelectorAll('input[name="recibo_ids[]"]').forEach(function (node) {
          node.remove();
        });
        Array.from(selected).forEach(function (id) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'recibo_ids[]';
          input.value = id;
          form.appendChild(input);
        });
        localStorage.removeItem(storageKey);
      });
    }
  })();
</script>
@endpush
@endsection
