@extends('layouts.app')

@section('title', 'Pago a Choferes - Recibos')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Recibos de Liquidacion</h1>
    <p class="text-muted mb-0">Gestion de recibos importados y conciliados para choferes transportistas.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-outline-primary" href="{{ route('pago-choferes.import.create') }}">Importar Excel de Trafico</a>
    <a class="btn btn-primary" id="createPlanillaFromSelection" href="{{ route('pago-choferes.planillas.create') }}">Crear Planilla</a>
  </div>
</div>

<form method="GET" class="card card-body mb-3" id="recibosFilterForm">
  <div class="row g-2">
    <div class="col-md-2">
      <label class="form-label fw-semibold">Periodo</label>
      <select name="tipo_periodo" class="form-select">
        <option value="">Todos</option>
        <option value="quincenal" {{ ($filters['tipo_periodo'] ?? '') === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
        <option value="mensual" {{ ($filters['tipo_periodo'] ?? '') === 'mensual' ? 'selected' : '' }}>Mensual</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Desde</label>
      <input type="date" name="desde" value="{{ $filters['desde'] ?? '' }}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Hasta</label>
      <input type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Transportista</label>
      <select name="transportista_id[]" class="form-select select2-filter" multiple data-placeholder="Todos los choferes">
        @foreach($transportistas as $transportista)
          <option value="{{ $transportista->id }}" {{ in_array((string)$transportista->id, array_map('strval', (array)($filters['transportista_id'] ?? [])), true) ? 'selected' : '' }}>{{ $transportista->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold">Zona (Plaza)</label>
      <select name="zona[]" class="form-select select2-filter" multiple data-placeholder="Todas las zonas">
        @foreach($zonasOptions as $zonaOpt)
          <option value="{{ $zonaOpt }}" {{ in_array((string)$zonaOpt, array_map('strval', (array)($filters['zona'] ?? [])), true) ? 'selected' : '' }}>{{ $zonaOpt }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-1">
      <label class="form-label fw-semibold">Estado</label>
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        @foreach($states as $state)
          <option value="{{ $state }}" {{ ($filters['estado'] ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-1">
      <label class="form-label fw-semibold">Ruta / Nº</label>
      <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar..." class="form-control">
    </div>
  </div>
  <div class="d-flex gap-2 mt-3 align-items-center">
    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm" id="btnApplyFilter">
      <i class="fa-solid fa-spinner fa-spin d-none" id="filterSpinner"></i>
      <i class="fa-solid fa-filter" id="filterIcon"></i>
      <span>Aplicar</span>
    </button>
    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" id="btnCleanFilter" href="{{ route('pago-choferes.recibos.index') }}">
      <i class="fa-solid fa-rotate-left"></i>
      <span>Limpiar</span>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <label class="text-muted text-nowrap mb-0">Mostrar:</label>
      <select name="per_page" class="form-select form-select-sm" style="width: 80px;" onchange="showLoadingState(); this.form.submit();">
        <option value="10" {{ $perPage === 10 ? 'selected' : '' }}>10</option>
        <option value="20" {{ $perPage === 20 ? 'selected' : '' }}>20</option>
        <option value="50" {{ $perPage === 50 ? 'selected' : '' }}>50</option>
        <option value="100" {{ $perPage === 100 ? 'selected' : '' }}>100</option>
      </select>
    </div>
  </div>
</form>

<div class="card position-relative" id="recibosTableCard">
  <!-- Overlay de carga activo -->
  <div id="tableLoadingOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center bg-white bg-opacity-75" style="z-index: 50; min-height: 200px;">
    <div class="text-center p-4 rounded bg-white shadow-sm border">
      <div class="spinner-border text-primary mb-2" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
      <div class="fw-semibold text-dark">Cargando recibos filtrados...</div>
      <div class="small text-muted">Por favor espere un momento.</div>
    </div>
  </div>
  <div class="card-body border-bottom d-flex flex-wrap gap-2">
    <form method="POST" action="{{ route('pago-choferes.recibos.bulk-destroy') }}" id="bulkDeleteRecibosForm" class="d-flex gap-2" data-confirm="¿Eliminar los recibos seleccionados? Solo se borrarán los que estén en estado cargado.">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn btn-outline-danger btn-sm">
        Eliminar seleccionados
      </button>
    </form>

    <form method="POST" action="{{ route('pago-choferes.recibos.destroy-all-cargados') }}" data-confirm="¿Eliminar todos los recibos cargados de Excel para este filtro?">
      @csrf
      @method('DELETE')
      <input type="hidden" name="tipo_periodo" value="{{ $filters['tipo_periodo'] ?? '' }}">
      <input type="hidden" name="desde" value="{{ $filters['desde'] ?? '' }}">
      <input type="hidden" name="hasta" value="{{ $filters['hasta'] ?? '' }}">
      @foreach((array)($filters['transportista_id'] ?? []) as $tId)
        <input type="hidden" name="transportista_id[]" value="{{ is_scalar($tId) ? $tId : '' }}">
      @endforeach
      <input type="hidden" name="plaza" value="{{ $filters['plaza'] ?? '' }}">
      @foreach((array)($filters['zona'] ?? []) as $zVal)
        <input type="hidden" name="zona[]" value="{{ is_scalar($zVal) ? $zVal : '' }}">
      @endforeach
      <input type="hidden" name="estado" value="{{ $filters['estado'] ?? '' }}">
      <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
      <button type="submit" class="btn btn-outline-danger btn-sm">
        Eliminar todos los cargados (filtro actual)
      </button>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
      <tr>
        <th style="width: 36px;">
          <input type="checkbox" id="selectAllRecibos">
        </th>
        <th>Fecha emision</th>
        <th>Transportista</th>
        <th>Plaza</th>
        <th>Tipo periodo</th>
        <th>Desde</th>
        <th>Hasta</th>
        <th>Estado</th>
        <th class="text-end">Total</th>
        <th>Acciones</th>
      </tr>
      </thead>
      <tbody>
      @forelse($recibos as $recibo)
        @php
          $deletable = $recibo->estado === \App\Models\ReciboChofer::ESTADO_CARGADO
            && $recibo->origen === 'excel_trafico'
            && (int) ($recibo->planilla_links_count ?? 0) === 0;
          $planillable = in_array($recibo->estado, [\App\Models\ReciboChofer::ESTADO_CARGADO, \App\Models\ReciboChofer::ESTADO_PENDIENTE_PAGO], true)
            && (int) ($recibo->planilla_links_count ?? 0) === 0;
          $isNew = $recibo->transportista && $recibo->transportista->isNewDriver();
        @endphp
        <tr class="{{ $isNew ? 'new-driver-row' : '' }}">
          <td>
            <input
              type="checkbox"
              class="recibo-select"
              value="{{ $recibo->id }}"
              data-deletable="{{ $deletable ? '1' : '0' }}"
              data-planillable="{{ $planillable ? '1' : '0' }}"
              {{ ($deletable || $planillable) ? '' : 'disabled' }}>
          </td>
          <td>{{ optional($recibo->fecha_emision)->format('d/m/Y') }}</td>
          <td>
            {{ $recibo->displayName() ?: '-' }}
            @if($isNew)
              <span class="new-driver-badge" title="Nuevo chofer ({{ $recibo->transportista->days_since_hired }} días dado de alta)"><i class="fa-solid fa-user-plus"></i> Nuevo ({{ $recibo->transportista->days_since_hired }} d)</span>
            @endif
          </td>
          <td>{{ $recibo->plaza ?: '-' }}</td>
          <td>{{ $recibo->tipo_periodo }}</td>
          <td>{{ optional($recibo->periodo_desde)->format('d/m/Y') }}</td>
          <td>{{ optional($recibo->periodo_hasta)->format('d/m/Y') }}</td>
          <td><span class="badge text-bg-light">{{ $recibo->estado }}</span></td>
          <td class="text-end">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</td>
          <td>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('pago-choferes.recibos.show', $recibo) }}">Ver detalle</a>
            @if($deletable)
              <form method="POST" action="{{ route('pago-choferes.recibos.destroy', $recibo) }}" class="d-inline" data-confirm="¿Eliminar este recibo importado?">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="text-center text-muted">Sin recibos para los filtros seleccionados.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body">
    {{ $recibos->links() }}
  </div>
</div>

@push('scripts')
<script>
  window.showLoadingState = function () {
    const spinner = document.getElementById('filterSpinner');
    const icon = document.getElementById('filterIcon');
    const btn = document.getElementById('btnApplyFilter');
    const overlay = document.getElementById('tableLoadingOverlay');

    if (spinner) spinner.classList.remove('d-none');
    if (icon) icon.classList.add('d-none');
    if (btn) btn.classList.add('disabled');
    if (overlay) {
      overlay.classList.remove('d-none');
      overlay.classList.add('d-flex');
    }
  };

  (function () {
    const filterForm = document.getElementById('recibosFilterForm');
    if (filterForm) {
      filterForm.addEventListener('submit', function () {
        window.showLoadingState();
      });
    }

    document.querySelectorAll('.pagination a').forEach(function (link) {
      link.addEventListener('click', function () {
        window.showLoadingState();
      });
    });

    const planillaSelectionKey = 'planilla_create_selected_recibos';
    const selectAll = document.getElementById('selectAllRecibos');
    const checks = Array.from(document.querySelectorAll('.recibo-select'));
    const bulkForm = document.getElementById('bulkDeleteRecibosForm');
    const createPlanillaLink = document.getElementById('createPlanillaFromSelection');

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checks.forEach(function (check) {
          if (!check.disabled) {
            check.checked = selectAll.checked;
          }
        });
      });
    }

    if (bulkForm) {
      bulkForm.addEventListener('submit', function () {
        bulkForm.querySelectorAll('input[name="ids[]"]').forEach(function (node) { node.remove(); });
        checks.filter(function (check) { return check.checked && check.dataset.deletable === '1'; }).forEach(function (check) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = check.value;
          bulkForm.appendChild(input);
        });
      });
    }

    if (createPlanillaLink) {
      createPlanillaLink.addEventListener('click', function () {
        const ids = checks
          .filter(function (check) { return check.checked && check.dataset.planillable === '1'; })
          .map(function (check) { return String(check.value); });

        if (ids.length > 0) {
          localStorage.setItem(planillaSelectionKey, JSON.stringify(ids));
        } else {
          localStorage.removeItem(planillaSelectionKey);
        }
      });
    }
  })();
</script>
@endpush
@endsection
