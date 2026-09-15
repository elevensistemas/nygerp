@extends('layouts.app')

@section('title', 'Planilla Diaria de Choferes')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 mb-1"><i class="fa-solid fa-table me-2 text-primary"></i>Planilla Diaria de Choferes</h1>
    <p class="text-muted mb-0">Gestión interactiva de rendición de viajes de transportistas.</p>
  </div>
  <div class="d-flex align-items-center gap-3">
    <!-- Contenedor de estado de autoguardado -->
    <div id="autosaveStatus" class="small fw-semibold" style="display: none;"></div>
    <a href="{{ asset('manuales/instructivo_planilla_diaria_choferes.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
      <i class="fa-solid fa-circle-question fs-5"></i>
    </a>
    <button type="button" class="btn btn-outline-warning text-dark fw-semibold d-flex align-items-center gap-2 shadow-sm" onclick="runCleanDuplicates()" title="Identificar y eliminar duplicados preexistentes">
      <i class="fa-solid fa-broom"></i>
      <span>Limpiar Duplicados</span>
    </button>
    <button type="button" class="btn btn-success d-flex align-items-center gap-2 shadow-sm" id="btnSaveAll">
      <i class="fa-solid fa-cloud-arrow-up"></i>
      <span>Guardar Planilla</span>
    </button>
  </div>
</div>

<!-- Filtros de búsqueda -->
<div class="card shadow-sm border-0 mb-4 bg-white">
  <div class="card-body">
    <form method="GET" action="{{ route('traffic.planilla-choferes.index') }}" class="row g-3 align-items-end">
      <div class="col-lg-2 col-md-4 col-sm-6">
        <label class="form-label fw-semibold">Fecha Desde</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa-solid fa-calendar-day text-muted"></i></span>
          <input type="date" class="form-control" name="fecha_desde" value="{{ $fechaDesde }}">
        </div>
      </div>
      <div class="col-lg-2 col-md-4 col-sm-6">
        <label class="form-label fw-semibold">Fecha Hasta</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa-solid fa-calendar-day text-muted"></i></span>
          <input type="date" class="form-control" name="fecha_hasta" value="{{ $fechaHasta }}">
        </div>
      </div>
      <div class="col-lg-3 col-md-4 col-sm-12">
        <label class="form-label fw-semibold">Chofer</label>
        <select class="form-select select2-filter" name="transportista_id">
          <option value="">-- Todos --</option>
          @foreach($carriers as $carrier)
            <option value="{{ $carrier->id }}" {{ $selectedCarrierId == $carrier->id ? 'selected' : '' }}>{{ $carrier->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-3 col-md-8 col-sm-12">
        <label class="form-label fw-semibold">Concepto (Columna Zona)</label>
        <select class="form-select select2-filter" name="concepto">
          <option value="">-- Todos --</option>
          @foreach($paymentConcepts as $concept)
            <option value="{{ $concept->name }}" {{ $selectedConcept == $concept->name ? 'selected' : '' }}>{{ $concept->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-sm-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <i class="fa-solid fa-filter"></i>
          <span>Filtrar</span>
        </button>
        <a href="{{ route('traffic.planilla-choferes.index') }}" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" title="Limpiar filtros">
          <i class="fa-solid fa-rotate-left"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Grilla interactiva por zonas -->
<div class="card shadow-sm border-0 mb-4 bg-white">
  <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
    <h2 class="h5 mb-0 text-dark fw-semibold"><i class="fa-solid fa-list-check me-2 text-primary"></i>Registros de Rendición</h2>
    <span class="badge bg-light text-dark border py-2 px-3 fw-normal" id="rowCountBadge">Total: 0 filas</span>
  </div>
  
  <div class="card-body">
    <!-- Nav tabs -->
    <ul class="nav nav-tabs" id="zoneTabs" role="tablist">
      @foreach($zones as $index => $zone)
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $index === 0 ? 'active' : '' }} fw-semibold d-flex align-items-center gap-2" id="tab-zone-{{ $zone->id }}" data-bs-toggle="tab" data-bs-target="#pane-zone-{{ $zone->id }}" type="button" role="tab" aria-controls="pane-zone-{{ $zone->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
            <span>{{ $zone->name }}</span>
            <span class="badge bg-secondary zone-count-badge" id="badge-count-{{ $zone->id }}">0</span>
          </button>
        </li>
      @endforeach
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-zone-none" data-bs-toggle="tab" data-bs-target="#pane-zone-none" type="button" role="tab" aria-controls="pane-zone-none" aria-selected="false">
          <span>Sin Zona</span>
          <span class="badge bg-secondary zone-count-badge" id="badge-count-none">0</span>
        </button>
      </li>
      <li class="nav-item ms-auto align-self-center" role="presentation">
        <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-1 my-1 me-2 shadow-sm" onclick="openCreateZoneModal()">
          <i class="fa-solid fa-plus"></i>
          <span>Crear Zona</span>
        </button>
      </li>
    </ul>

    <!-- Tab panes -->
    <div class="tab-content mt-3" id="zoneTabsContent">
      @foreach($zones as $index => $zone)
        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="pane-zone-{{ $zone->id }}" role="tabpanel" aria-labelledby="tab-zone-{{ $zone->id }}">
          <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 text-nowrap zone-table" data-zone-name="{{ $zone->name }}" id="table-zone-{{ $zone->id }}">
              <thead class="table-light sticky-top" style="z-index: 10;">
                <tr>
                  <th scope="col" style="width: 80px;">Acciones</th>
                  <th scope="col" style="min-width: 140px;">Fecha</th>
                  <th scope="col" style="min-width: 200px;">Chofer (Transportista)</th>
                  <th scope="col" style="min-width: 150px;">Vehículo</th>
                  <th scope="col" style="min-width: 160px;">Titular</th>
                  <th scope="col" style="min-width: 120px;">Patente</th>
                  <th scope="col" style="min-width: 90px;">Modelo</th>
                  <th scope="col" style="min-width: 120px;">Unidad</th>
                  <th scope="col" style="min-width: 180px;">Concepto (Zona)</th>
                  <th scope="col" style="min-width: 140px;">SVS</th>
                  <th scope="col" style="min-width: 130px;">Ruta</th>
                  <th scope="col" style="min-width: 130px;">Número</th>
                  <th scope="col" style="min-width: 90px;">Paradas</th>
                  <th scope="col" style="min-width: 90px;">Paquetes</th>
                  <th scope="col" style="min-width: 90px;">Entregados</th>
                  <th scope="col" style="min-width: 90px;">Deja en SVC</th>
                  <th scope="col" style="min-width: 90px;">Paq. NO Col.</th>
                  <th scope="col" style="min-width: 90px;">Nadie Dom.</th>
                  <th scope="col" style="min-width: 90px;">Neg. Cerrado</th>
                  <th scope="col" style="min-width: 90px;">QR</th>
                  <th scope="col" style="min-width: 90px;">Fuera Zona</th>
                  <th scope="col" style="min-width: 90px;">Z. Inaccesible</th>
                  <th scope="col" style="min-width: 90px;">Rechazado</th>
                  <th scope="col" style="min-width: 90px;">Sin Visitar</th>
                  <th scope="col" style="min-width: 90px;">Fraude</th>
                  <th scope="col" style="min-width: 110px;">P. Perdido</th>
                  <th scope="col" style="min-width: 110px;">P. Dañado</th>
                  <th scope="col" style="min-width: 110px;">P. Robado</th>
                  <th scope="col" style="min-width: 90px;">Porcentaje</th>
                  <th scope="col" style="min-width: 100px;">Kilómetros</th>
                  <th scope="col" style="min-width: 100px;">Km Est.</th>
                  <th scope="col" style="min-width: 80px;">Lejana</th>
                  <th scope="col" style="min-width: 250px;">Observación</th>
                </tr>
              </thead>
              <tbody class="sheetTableBody" id="sheetTableBody_{{ $zone->id }}">
                <!-- Rows will be added dynamically by JS -->
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2 shadow-sm btnAddRow" data-target-body="sheetTableBody_{{ $zone->id }}">
              <i class="fa-solid fa-plus"></i>
              <span>Agregar Fila en {{ $zone->name }}</span>
            </button>
          </div>
        </div>
      @endforeach

      <div class="tab-pane fade" id="pane-zone-none" role="tabpanel" aria-labelledby="tab-zone-none">
        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
          <table class="table table-hover align-middle mb-0 text-nowrap zone-table" data-zone-name="" id="table-zone-none">
            <thead class="table-light sticky-top" style="z-index: 10;">
              <tr>
                <th scope="col" style="width: 80px;">Acciones</th>
                <th scope="col" style="min-width: 140px;">Fecha</th>
                <th scope="col" style="min-width: 200px;">Chofer (Transportista)</th>
                <th scope="col" style="min-width: 150px;">Vehículo</th>
                <th scope="col" style="min-width: 160px;">Titular</th>
                <th scope="col" style="min-width: 120px;">Patente</th>
                <th scope="col" style="min-width: 90px;">Modelo</th>
                <th scope="col" style="min-width: 120px;">Unidad</th>
                <th scope="col" style="min-width: 180px;">Concepto (Zona)</th>
                <th scope="col" style="min-width: 140px;">SVS</th>
                <th scope="col" style="min-width: 130px;">Ruta</th>
                <th scope="col" style="min-width: 130px;">Número</th>
                <th scope="col" style="min-width: 90px;">Paradas</th>
                <th scope="col" style="min-width: 90px;">Paquetes</th>
                <th scope="col" style="min-width: 90px;">Entregados</th>
                <th scope="col" style="min-width: 90px;">Deja en SVC</th>
                <th scope="col" style="min-width: 90px;">Paq. NO Col.</th>
                <th scope="col" style="min-width: 90px;">Nadie Dom.</th>
                <th scope="col" style="min-width: 90px;">Neg. Cerrado</th>
                <th scope="col" style="min-width: 90px;">QR</th>
                <th scope="col" style="min-width: 90px;">Fuera Zona</th>
                <th scope="col" style="min-width: 90px;">Z. Inaccesible</th>
                <th scope="col" style="min-width: 90px;">Rechazado</th>
                <th scope="col" style="min-width: 90px;">Sin Visitar</th>
                <th scope="col" style="min-width: 90px;">Fraude</th>
                <th scope="col" style="min-width: 110px;">P. Perdido</th>
                <th scope="col" style="min-width: 110px;">P. Dañado</th>
                <th scope="col" style="min-width: 110px;">P. Robado</th>
                <th scope="col" style="min-width: 90px;">Porcentaje</th>
                <th scope="col" style="min-width: 100px;">Kilómetros</th>
                <th scope="col" style="min-width: 100px;">Km Est.</th>
                <th scope="col" style="min-width: 80px;">Lejana</th>
                <th scope="col" style="min-width: 250px;">Observación</th>
              </tr>
            </thead>
            <tbody class="sheetTableBody" id="sheetTableBody_none">
              <!-- Rows will be added dynamically by JS -->
            </tbody>
          </table>
        </div>
        <div class="mt-3">
          <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2 shadow-sm btnAddRow" data-target-body="sheetTableBody_none">
            <i class="fa-solid fa-plus"></i>
            <span>Agregar Fila Sin Zona</span>
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <div class="card-footer bg-light py-3 border-top d-flex justify-content-end">
    <button type="button" class="btn btn-success d-flex align-items-center gap-2 shadow-sm" id="btnSaveAllFooter">
      <i class="fa-solid fa-cloud-arrow-up"></i>
      <span>Guardar Planilla</span>
    </button>
  </div>
</div>

<div class="card shadow-sm border-0 bg-white">
  <div class="card-body">
    <h3 class="h6 fw-bold mb-3"><i class="fa-solid fa-circle-info text-info me-2"></i>Instrucciones de Uso</h3>
    <ul class="small text-muted mb-0 ps-3">
      <li class="mb-1">Utilice los filtros superiores para cargar registros dentro de un rango de fechas.</li>
      <li class="mb-1">Haga clic en <strong>Agregar Fila</strong> dentro de cada pestaña de zona para registrar un viaje asignado a esa zona.</li>
      <li class="mb-1">Al seleccionar un <strong>Chofer</strong>, se cargará su listado de camiones/vehículos y se auto-completarán los datos de Titular, Patente, Modelo y Tipo.</li>
      <li class="mb-1">Para ingresar comentarios sobre paquetes perdidos, dañados o robados, presione el ícono de comentario <i class="fa-solid fa-comment-dots text-primary"></i> al lado del valor numérico.</li>
      <li class="mb-1">Las columnas de color gris claro son de solo lectura y se calculan o cargan automáticamente para asegurar la consistencia.</li>
      <li class="mb-1">Haga clic en <strong>Guardar Planilla</strong> para confirmar todos los cambios (altas, bajas y modificaciones) realizados en todas las pestañas.</li>
    </ul>
  </div>
</div>

<!-- Modal para agregar vehículo rápidamente -->
<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="addVehicleModalLabel"><i class="fa-solid fa-truck me-2 text-primary"></i>Agregar Vehículo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="addVehicleForm">
          <input type="hidden" id="modal_transportista_id">
          <input type="hidden" id="modal_row_id">
          <input type="hidden" id="modal_vehicle_id">
          
          <div class="mb-3">
            <label for="modal_alias" class="form-label fw-semibold">Alias / Identificador <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="modal_alias" required placeholder="Ej: Camioneta blanca">
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="modal_license_plate" class="form-label fw-semibold">Patente / Placa</label>
              <input type="text" class="form-control" id="modal_license_plate" placeholder="Ej: AB123CD">
            </div>
            <div class="col-md-6 mb-3">
              <label for="modal_type" class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
              <select class="form-select no-select2" id="modal_type" required>
                @foreach(\App\Models\Transporte::paymentVehicleTypes(true) as $typeValue => $typeLabel)
                  <option value="{{ $typeValue }}" {{ $typeValue === 'camioneta' ? 'selected' : '' }}>{{ $typeLabel }}</option>
                @endforeach
              </select>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="modal_owner_name" class="form-label fw-semibold">Titular / Propietario</label>
            <input type="text" class="form-control" id="modal_owner_name" placeholder="Ej: Juan Pérez">
          </div>
          
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="modal_brand" class="form-label fw-semibold">Marca</label>
              <input type="text" class="form-control" id="modal_brand" placeholder="Ej: Ford">
            </div>
            <div class="col-md-4 mb-3">
              <label for="modal_model" class="form-label fw-semibold">Modelo</label>
              <input type="text" class="form-control" id="modal_model" placeholder="Ej: Ranger">
            </div>
            <div class="col-md-4 mb-3">
              <label for="modal_year" class="form-label fw-semibold">Año</label>
              <input type="number" class="form-control" id="modal_year" min="1950" max="{{ date('Y') + 1 }}" placeholder="Ej: 2020">
            </div>
          </div>
          
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="modal_is_default">
            <label class="form-check-label fw-semibold" for="modal_is_default">Establecer como vehículo por defecto</label>
          </div>
        </form>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnSaveVehicle">
          <i class="fa-solid fa-save me-1"></i> Guardar Vehículo
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para ver historial de cambios -->
<div class="modal fade" id="logHistoryModal" tabindex="-1" aria-labelledby="logHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="logHistoryModalLabel"><i class="fa-solid fa-history me-2 text-primary"></i>Historial de Cambios de la Fila</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive" style="max-height: 450px;">
          <table class="table table-hover table-striped align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th style="width: 180px;">Fecha/Hora</th>
                <th style="width: 150px;">Usuario</th>
                <th style="width: 110px;">Acción</th>
                <th>Detalles de los Cambios</th>
              </tr>
            </thead>
            <tbody id="logHistoryTableBody">
              <!-- Logs will be loaded dynamically -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para configurar valores SVS de una zona -->
<div class="modal fade" id="svsConfigModal" tabindex="-1" aria-labelledby="svsConfigModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="svsConfigModalLabel">
          <i class="fa-solid fa-list-check me-2 text-primary"></i>
          Configurar Valores SVS
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="svsConfigZoneId">
        <input type="hidden" id="svsConfigRowId">
        
        <div class="mb-3">
          <label class="form-label fw-semibold">Zona Seleccionada</label>
          <input type="text" class="form-control bg-light fw-bold" id="svsConfigZoneName" readonly>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-semibold" for="modalSvsValues">Valores SVS (uno por línea)</label>
          <textarea class="form-control" id="modalSvsValues" rows="6" placeholder="Ej: AMBA am 1&#10;AMBA am 2"></textarea>
          <small class="text-muted">Escribe un valor de SVS por línea. Se actualizarán inmediatamente las opciones en la planilla de esta zona.</small>
        </div>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary px-4" id="btnSaveSvsFromPlanilla">
          <i class="fa-solid fa-floppy-disk me-1"></i> Guardar SVS
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para CRUD de Conceptos de Pago -->
<div class="modal fade" id="conceptsConfigModal" tabindex="-1" aria-labelledby="conceptsConfigModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="conceptsConfigModalLabel">
          <i class="fa-solid fa-gears me-2 text-primary"></i>
          Configurar Conceptos de Pago
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <!-- Formulario de Crear / Editar -->
        <div class="card shadow-none border-secondary-subtle mb-4">
          <div class="card-header bg-light py-2">
            <span class="fw-semibold text-secondary" id="conceptFormTitle">Nuevo Concepto</span>
          </div>
          <div class="card-body">
            <form id="conceptCrudForm">
              <input type="hidden" id="conceptId">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Nombre *</label>
                  <input type="text" class="form-control" id="conceptNameInput" required placeholder="Ej: Pago base paradas">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Tipo de Valor *</label>
                  <select class="form-select" id="conceptValueType" required>
                    <option value="fixed">Fijo</option>
                    <option value="reference">Referencia</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Signo *</label>
                  <select class="form-select" id="conceptSign" required>
                    <option value="1">Suma (+)</option>
                    <option value="-1">Resta (-)</option>
                  </select>
                </div>
              </div>

              <!-- Fixed Type Fields -->
              <div class="row g-3 mt-2" id="conceptFixedFields">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Monto por Defecto</label>
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" min="0" class="form-control" id="conceptDefaultAmount" placeholder="0.00">
                  </div>
                </div>
              </div>

              <!-- Reference Type Fields -->
              <div class="row g-3 mt-2 d-none" id="conceptReferenceFields">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Concepto Base</label>
                  <select class="form-select" id="conceptReferenceId">
                    <option value="">-- Seleccionar --</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Multiplicador</label>
                  <input type="number" step="0.0001" class="form-control" id="conceptReferenceMultiplier" placeholder="1.0000">
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between mt-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="conceptActive" checked value="1">
                  <label class="form-check-label fw-semibold" for="conceptActive">Concepto Activo</label>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCancelConceptEdit" style="display:none;">Cancelar Edición</button>
                  <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveConcept">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Concepto
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Listado de Conceptos -->
        <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-list me-1 text-secondary"></i> Conceptos cargados</h6>
        <div class="table-responsive" style="max-height: 250px;">
          <table class="table table-sm table-striped table-hover align-middle border text-nowrap">
            <thead class="table-light sticky-top">
              <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Monto / Ref</th>
                <th>Signo</th>
                <th>Estado</th>
                <th class="text-end" style="width: 100px;">Acciones</th>
              </tr>
            </thead>
            <tbody id="conceptsTableBody">
              <tr>
                <td colspan="6" class="text-center py-3 text-muted">Cargando conceptos...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para CRUD de Choferes (Transportistas) -->
<div class="modal fade" id="carriersConfigModal" tabindex="-1" aria-labelledby="carriersConfigModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="carriersConfigModalLabel">
          <i class="fa-solid fa-user-gear me-2 text-primary"></i>
          Configurar Chofer (Transportista)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <!-- Formulario de Crear / Editar -->
        <div class="card shadow-none border-secondary-subtle">
          <div class="card-header bg-light py-2">
            <span class="fw-semibold text-secondary" id="carrierFormTitle">Nuevo Chofer</span>
          </div>
          <div class="card-body">
            <form id="carrierCrudForm">
              <input type="hidden" id="carrierIdInput">
              <input type="hidden" id="carrierRowIdInput">
              
              <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Nombre *</label>
                <input type="text" class="form-control" id="carrierNameInput" required placeholder="Ej: Juan Pérez">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Email</label>
                <input type="email" class="form-control" id="carrierEmailInput" placeholder="Ej: juan@ejemplo.com">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold text-dark">CBU</label>
                <input type="text" class="form-control" id="carrierCbuInput" placeholder="Clave Bancaria Uniforme (22 dígitos)">
              </div>

              <div class="mb-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold text-dark">Estado del Chofer</span>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="carrierActiveInput" checked value="1">
                  <label class="form-check-label fw-semibold text-dark" for="carrierActiveInput">Activo</label>
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-2 border-top">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4" id="btnSaveCarrier">
                  <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Chofer
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Crear Nueva Zona -->
<div class="modal fade" id="createZoneFromPlanillaModal" tabindex="-1" aria-labelledby="createZoneFromPlanillaModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form id="createZoneFromPlanillaForm">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold" id="createZoneFromPlanillaModalLabel">
            <i class="fa-solid fa-plus-circle me-2 text-primary"></i>
            Crear Nueva Zona Operativa
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre de la Zona *</label>
            <input type="text" class="form-control" id="newZoneName" required placeholder="Ej: BAHIA BLANCA, CORDOBA, etc.">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Prioridad</label>
            <select class="form-select" id="newZonePriority" required>
              <option value="primary">Primaria</option>
              <option value="secondary">Secundaria</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Valores SVS iniciales (uno por línea)</label>
            <textarea class="form-control" id="newZoneSvsValues" rows="4" placeholder="Ej: Bahía am 1&#10;Bahía am 2"></textarea>
            <small class="text-muted">Opcional. Puedes agregar valores SVS iniciales para esta zona.</small>
          </div>
        </div>
        <div class="modal-footer bg-light border-top-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4" id="btnSaveNewZoneFromPlanilla">
            <i class="fa-solid fa-floppy-disk me-1"></i> Crear Zona
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('styles')
<style>
  #zoneTabs .nav-link {
    color: #495057;
    background-color: #f8f9fa;
    border-color: #dee2e6;
  }
  #zoneTabs .nav-link.active {
    color: #0d6efd;
    background-color: #fff;
    border-bottom-color: transparent;
    font-weight: 600;
  }
  .zone-count-badge {
    font-size: 0.75rem;
    padding: 0.25em 0.6em;
  }
  .sheetTableBody input, .sheetTableBody select {
    border-radius: 6px;
    padding: 0.25rem 0.5rem;
    font-size: 0.9rem;
    border: 1px solid #ced4da;
    height: 32px;
  }
  .sheetTableBody input:focus, .sheetTableBody select:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.15rem rgba(255, 193, 7, 0.25);
    outline: 0;
  }
  .sheetTableBody input[readonly], .sheetTableBody input[disabled] {
    background-color: #f8fafc;
    border-color: #e2e8f0;
    color: #64748b;
    cursor: not-allowed;
  }
  .row-fade-in {
    animation: fadeInRow 0.3s ease-out forwards;
  }
  @keyframes fadeInRow {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .btn-remove-row {
    color: #ef4444;
    background: transparent;
    border: none;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
  }
  .btn-remove-row:hover {
    background-color: #fee2e2;
    transform: scale(1.1);
  }
  .btn-comment {
    transition: transform 0.1s ease;
  }
  .btn-comment:hover {
    transform: scale(1.2);
  }
  .animate-pulse {
    animation: pulse-animation 1.5s infinite;
  }
  @keyframes pulse-animation {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
  }
</style>
@endpush

@push('scripts')
<script>
  (function ($) {
    // Inject DB resources
    const carriersData = @json($carriers);
    const zonesData = @json($zones);
    const locationsData = @json($locations);
    const paymentConceptsData = @json($paymentConcepts);
    const initialRecords = @json($records);
    const defaultDate = "{{ date('Y-m-d') }}";
    
    const vehicleTypeLabels = @json(\App\Models\Transporte::paymentVehicleTypes(true));

    let deletedIds = [];
    let rowCounter = 0;
    let vehicleModal = null;
    let logHistoryModal = null;
    let svsConfigModal = null;
    let conceptsConfigModal = null;
    let createZoneFromPlanillaModal = null;
    let carriersConfigModal = null;
    let allCarriersCached = [];
    let allConceptsCached = [];
    let autosaveTimeout = null;
    let isSaving = false;
    let savePending = false;
    let pendingSilentMode = true;
    let recordsCountByBody = {};

    function showAutosaveStatus(state) {
      const $status = $('#autosaveStatus');
      if (!$status.length) return;

      let icon = '';
      let text = '';
      let textColorClass = 'text-muted';

      switch (state) {
        case 'unsaved':
          icon = '<i class="fa-solid fa-pen-to-square text-warning animate-pulse"></i>';
          text = 'Cambios sin guardar...';
          textColorClass = 'text-warning';
          break;
        case 'invalid':
          icon = '<i class="fa-solid fa-circle-exclamation text-info"></i>';
          text = 'Esperando datos obligatorios...';
          textColorClass = 'text-info';
          break;
        case 'saving':
          icon = '<i class="fa-solid fa-spinner fa-spin text-primary"></i>';
          text = 'Autoguardando...';
          textColorClass = 'text-primary';
          break;
        case 'saved':
          icon = '<i class="fa-solid fa-cloud-check text-success"></i>';
          text = 'Guardado';
          textColorClass = 'text-success';
          setTimeout(() => {
            if ($status.data('current-state') === 'saved') {
              $status.fadeOut(300);
            }
          }, 3000);
          break;
        case 'error':
          icon = '<i class="fa-solid fa-cloud-exclamation text-danger"></i>';
          text = 'Error al autoguardar';
          textColorClass = 'text-danger';
          break;
      }

      $status.data('current-state', state);
      $status.removeClass('text-muted text-warning text-info text-primary text-success text-danger')
             .addClass(textColorClass);
      $status.html(`${icon} <span class="ms-1">${text}</span>`);
      $status.fadeIn(150);
    }

    function triggerAutosave() {
      if (autosaveTimeout) {
        clearTimeout(autosaveTimeout);
      }
      showAutosaveStatus('unsaved');
      autosaveTimeout = setTimeout(function() {
        saveAll(true);
      }, 2000);
    }

    // Render initial records
    $(document).ready(function () {
      const modalEl = document.getElementById('addVehicleModal');
      if (modalEl) {
        vehicleModal = new bootstrap.Modal(modalEl);
      }

      const logModalEl = document.getElementById('logHistoryModal');
      if (logModalEl) {
        logHistoryModal = new bootstrap.Modal(logModalEl);
      }

      const svsModalEl = document.getElementById('svsConfigModal');
      if (svsModalEl) {
        svsConfigModal = new bootstrap.Modal(svsModalEl);
      }

      const conceptsModalEl = document.getElementById('conceptsConfigModal');
      if (conceptsModalEl) {
        conceptsConfigModal = new bootstrap.Modal(conceptsModalEl);
      }

      const createZoneModalEl = document.getElementById('createZoneFromPlanillaModal');
      if (createZoneModalEl) {
        createZoneFromPlanillaModal = new bootstrap.Modal(createZoneModalEl);
      }

      const carriersModalEl = document.getElementById('carriersConfigModal');
      if (carriersModalEl) {
        carriersConfigModal = new bootstrap.Modal(carriersModalEl);
      }

      if (initialRecords.length > 0) {
        recordsRawByBody = {};
        recordsCountByBody = {};
        renderedIndexByBody = {};

        // 1. Group raw records by bodyId in memory (ultra-fast < 1ms)
        initialRecords.forEach(record => {
          let trafficZoneId = record.traffic_zone_id || null;
          let zona = record.zona || '';
          let bodyId = 'sheetTableBody_none';

          if (!trafficZoneId && zona) {
            const matchingZoneByName = zonesData.find(z => z.name === zona);
            if (matchingZoneByName) {
              trafficZoneId = matchingZoneByName.id;
            }
          }
          if (trafficZoneId) {
            const matchingZone = zonesData.find(z => z.id === trafficZoneId);
            if (matchingZone) {
              bodyId = `sheetTableBody_${matchingZone.id}`;
            }
          }

          if (!recordsRawByBody[bodyId]) {
            recordsRawByBody[bodyId] = [];
            recordsCountByBody[bodyId] = 0;
          }
          recordsRawByBody[bodyId].push(record);
          recordsCountByBody[bodyId]++;
        });

        const RENDER_CHUNK_SIZE = 35;

        window.renderTabBodyIfNeeded = function (bodyId) {
          if (!recordsRawByBody[bodyId] || recordsRawByBody[bodyId].length === 0) return;
          if (renderedIndexByBody[bodyId] === undefined) {
            renderedIndexByBody[bodyId] = 0;
          }

          const rawRecords = recordsRawByBody[bodyId];
          const total = rawRecords.length;

          if (renderedIndexByBody[bodyId] >= total) return;

          const zoneId = bodyId.replace('sheetTableBody_', '');
          const $tabBtn = zoneId === 'none' ? $('#tab-zone-none') : $(`#tab-zone-${zoneId}`);

          if (renderedIndexByBody[bodyId] === 0) {
            if ($tabBtn.length && !$tabBtn.find('.tab-spinner').length) {
              $tabBtn.append('<i class="fa-solid fa-spinner fa-spin ms-1 text-primary tab-spinner"></i>');
            }
            if (!$(`#${bodyId} .tab-loading-row`).length && $(`#${bodyId} tr`).length === 0) {
              $(`#${bodyId}`).html(`
                <tr class="tab-loading-row">
                  <td colspan="35" class="text-center py-4 bg-light">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <span class="fw-semibold text-secondary">Cargando registros de esta zona...</span>
                  </td>
                </tr>
              `);
            }
          }

          function renderNextChunk() {
            const startIndex = renderedIndexByBody[bodyId];
            if (startIndex >= total) {
              $tabBtn.find('.tab-spinner').remove();
              $(`#${bodyId} .tab-loading-row`).remove();
              return;
            }

            const endIndex = Math.min(startIndex + RENDER_CHUNK_SIZE, total);
            const chunkHtmlArray = [];

            for (let i = startIndex; i < endIndex; i++) {
              const res = generateRowHtml(rawRecords[i], bodyId);
              chunkHtmlArray.push(res.html);
            }

            $(`#${bodyId} .tab-loading-row`).remove();
            $(`#${bodyId}`).append(chunkHtmlArray.join(''));
            renderedIndexByBody[bodyId] = endIndex;

            if (endIndex < total) {
              requestAnimationFrame(renderNextChunk);
            } else {
              $tabBtn.find('.tab-spinner').remove();
            }
          }

          setTimeout(renderNextChunk, 10);
        };

        // Determine active tab body ID
        const $activeTabBtn = $('#zoneTabs .nav-link.active');
        let activeBodyId = 'sheetTableBody_none';
        if ($activeTabBtn.length) {
          const targetPane = $activeTabBtn.attr('data-bs-target');
          if (targetPane) {
            const zoneId = targetPane.replace('#pane-zone-', '');
            activeBodyId = `sheetTableBody_${zoneId}`;
          }
        }

        renderTabBodyIfNeeded(activeBodyId);
        updateRowCount();

        // Lazy render tab rows when user switches tabs
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
          const targetPane = $(e.target).attr('data-bs-target');
          if (targetPane) {
            const zoneId = targetPane.replace('#pane-zone-', '');
            const bodyId = `sheetTableBody_${zoneId}`;
            renderTabBodyIfNeeded(bodyId);
            updateRowCount();
          }
        });
      } else {
        const firstZoneId = zonesData.length > 0 ? zonesData[0].id : 'none';
        addRow(null, `sheetTableBody_${firstZoneId}`);
        updateRowCount();
      }

      $(document).on('input', '[data-field="porcentaje"]', function () {
        if ($(this).val().trim() === '') {
          $(this).removeClass('is-custom');
          const rowId = $(this).closest('tr').attr('id');
          if (rowId) {
            window.onQtyChange(rowId);
          }
        } else {
          $(this).addClass('is-custom');
        }
      });

      $('.select2-filter').select2({
        width: '100%'
      });

      $(document).on('change input', '.sheetTableBody input, .sheetTableBody select', function () {
        triggerAutosave();
      });

      // Lazy initialize Select2 in table rows on mouse down or focus (Only for Driver select box)
      $(document).on('mousedown focusin', '.sheetTableBody select[data-field="transportista_id"]:not(.select2-hidden-accessible)', function () {
        $(this).select2({
          width: '100%'
        });
      });

      // Delegated change events for dynamic dropdown updates
      $(document).on('change', '.sheetTableBody select[data-field="transportista_id"]', function() {
        const rowId = $(this).closest('tr').attr('id');
        onCarrierChange(rowId);
      });

      $(document).on('change', '.sheetTableBody select[data-field="transporte_id"]', function() {
        const rowId = $(this).closest('tr').attr('id');
        onTruckChange(rowId);
      });
    });

    // Add row button
    $(document).on('click', '.btnAddRow', function () {
      const targetBodyId = $(this).attr('data-target-body');
      addRow(null, targetBodyId);
      updateRowCount();
    });

    // Save buttons
    $('#btnSaveAll, #btnSaveAllFooter').on('click', function () {
      saveAll();
    });

    function addRow(data = null, targetBodyId = null) {
      const res = generateRowHtml(data, targetBodyId);
      $(`#${res.bodyId}`).append(res.html);
    }

    let cachedBaseCarrierOptions = null;
    function buildCarrierOptions(selectedId) {
      if (!cachedBaseCarrierOptions) {
        let opts = `<option value="">-- Seleccionar --</option>`;
        carriersData.forEach(c => {
          opts += `<option value="${c.id}">${c.name}</option>`;
        });
        cachedBaseCarrierOptions = opts;
      }
      if (!selectedId) return cachedBaseCarrierOptions;
      return cachedBaseCarrierOptions.replace(`value="${selectedId}"`, `value="${selectedId}" selected`);
    }

    let cachedBaseConceptOptions = null;
    function buildPaymentConceptOptions(selectedName) {
      if (!cachedBaseConceptOptions) {
        let opts = `<option value="">-- Seleccionar --</option>`;
        paymentConceptsData.forEach(c => {
          opts += `<option value="${c.name}">${c.name}</option>`;
        });
        cachedBaseConceptOptions = opts;
      }
      if (!selectedName) return cachedBaseConceptOptions;
      return cachedBaseConceptOptions.replace(`value="${selectedName}"`, `value="${selectedName}" selected`);
    }

    function generateRowHtml(data = null, targetBodyId = null) {
      rowCounter++;
      const uniqueId = `row_${rowCounter}`;
      
      const recordId = data ? data.id : '';
      const fecha = data ? data.fecha : defaultDate;
      const carrierId = data ? data.transportista_id : '';
      const transporteId = data ? data.transporte_id : '';
      const svc = data ? data.svc : '';
      const ruta = data ? data.ruta : '';
      const numero = data ? data.numero : '';
      
      // Determine zone and destination body
      let trafficZoneId = null;
      let zona = '';
      let bodyId = 'sheetTableBody_none';

      if (data) {
        zona = data.zona || '';
        trafficZoneId = data.traffic_zone_id || null;
        if (!trafficZoneId && zona) {
          const matchingZoneByName = zonesData.find(z => z.name === zona);
          if (matchingZoneByName) {
            trafficZoneId = matchingZoneByName.id;
            // Clear older operational zone name so it doesn't display in concepts dropdown
            zona = '';
          }
        }
        if (trafficZoneId) {
          const matchingZone = zonesData.find(z => z.id === trafficZoneId);
          if (matchingZone) {
            bodyId = `sheetTableBody_${matchingZone.id}`;
          }
        }
      } else if (targetBodyId) {
        bodyId = targetBodyId;
        const zoneIdStr = bodyId.replace('sheetTableBody_', '');
        trafficZoneId = zoneIdStr === 'none' ? null : parseInt(zoneIdStr);
      }

      const paradas = data ? data.paradas : 0;
      const paquetes = data ? data.paquetes : 0;
      const entregados = data ? data.entregados : 0;
      const deja_svc = data ? data.deja_en_svc : 0;
      const paq_no_col = data ? data.paq_no_colectado : 0;
      const nadie_dom = data ? data.nadie_en_domicilio : 0;
      const neg_cerrado = data ? data.negocio_cerrado : 0;
      const qr = data ? data.qr : 0;
      const fuera_zona = data ? data.fuera_de_zona : 0;
      const z_inaccesible = data ? data.zona_inaccesible : 0;
      const rechazado = data ? data.rechazado : 0;
      const sin_visitar = data ? data.sin_visitar : 0;
      const fraude = data ? data.fraude : 0;
      const p_perdido = data ? data.paquete_perdido : 0;
      const p_danado = data ? data.paquete_danado : 0;
      const p_robado = data ? data.paquete_robado : 0;

      const comentario_perdido = data ? (data.comentario_perdido || '') : '';
      const comentario_danado = data ? (data.comentario_danado || '') : '';
      const comentario_robado = data ? (data.comentario_robado || '') : '';

      const porcentaje = data ? parseFloat(data.porcentaje || 0).toFixed(2) : '0.00';
      const kilometros = data ? data.kilometros : 0.00;
      const kilometros_estimados = data ? data.kilometros_estimados : 0.00;
      const zona_lejana = data ? !!data.zona_lejana : false;
      const observacion = data ? data.observacion : '';

      // Generate Carrier select options
      const carrierOptions = buildCarrierOptions(carrierId);

      // Generate Concept options for payment zone (zona field)
      const paymentConceptOptions = buildPaymentConceptOptions(zona);

      // Generate SVS options associated with this operational zone (svc field)
      let zoneOptionsForSvc = `<option value="">-- Seleccionar --</option>`;
      if (trafficZoneId) {
        const zoneObj = zonesData.find(z => z.id === trafficZoneId);
        if (zoneObj && Array.isArray(zoneObj.svs_values)) {
          zoneObj.svs_values.forEach(val => {
            zoneOptionsForSvc += `<option value="${val}" ${val === svc ? 'selected' : ''}>${val}</option>`;
          });
        }
      }

      // Pre-calculate vehicle options and metadata in memory to prevent DOM thrashing on load
      let truckOptions = '<option value="">-- Vehículo --</option>';
      let titular = '';
      let patente = '';
      let modelo = '';
      let unidad = '';
      let isTruckSelectDisabled = true;
      let isAddVehicleDisabled = true;
      let isEditVehicleDisabled = true;
      let resolvedTransporteId = transporteId;

      if (carrierId) {
        isAddVehicleDisabled = false;
        const carrierObj = carriersData.find(c => c.id == carrierId);
        if (carrierObj && carrierObj.transportes && carrierObj.transportes.length > 0) {
          isTruckSelectDisabled = false;
          
          let selectedTruck = null;
          if (resolvedTransporteId) {
            selectedTruck = carrierObj.transportes.find(t => t.id == resolvedTransporteId);
          }
          if (!selectedTruck) {
            selectedTruck = carrierObj.transportes.find(t => t.is_default);
          }
          if (!selectedTruck && carrierObj.transportes.length > 0) {
            selectedTruck = carrierObj.transportes[0];
          }

          if (selectedTruck) {
            resolvedTransporteId = selectedTruck.id;
            titular = selectedTruck.owner_name || '';
            patente = selectedTruck.license_plate || '';
            modelo = selectedTruck.model || '';
            unidad = vehicleTypeLabels[selectedTruck.type] || selectedTruck.type || '';
            isEditVehicleDisabled = false;
          }

          carrierObj.transportes.forEach(t => {
            const isSelected = t.id == resolvedTransporteId;
            const labelSuffix = t.is_default ? ' (Predeterminado)' : '';
            truckOptions += `<option value="${t.id}" ${isSelected ? 'selected' : ''}>${t.alias || t.license_plate}${labelSuffix}</option>`;
          });
        }
      }

      const rowHtml = `
        <tr id="${uniqueId}" class="row-fade-in ${data ? '' : 'is-dirty'}" data-db-id="${recordId}">
          <td>
            <div class="d-flex gap-1 align-items-center">
              <button type="button" class="btn-remove-row text-danger" onclick="removeRow('${uniqueId}')" title="Eliminar fila">
                <i class="fa-solid fa-trash-can"></i>
              </button>
              ${recordId ? `
              <button type="button" class="btn btn-sm btn-link text-secondary p-0" onclick="showLogs(${recordId})" title="Ver Historial" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-history"></i>
              </button>
              ` : `
              <button type="button" class="btn btn-sm btn-link text-muted p-0" disabled title="Sin Historial" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; opacity: 0.5;">
                <i class="fa-solid fa-history"></i>
              </button>
              `}
            </div>
          </td>
          <td>
            <input type="date" class="form-control" data-field="fecha" value="${fecha}" required>
          </td>
          <td>
            <div class="d-flex gap-1 align-items-center" style="min-width: 200px;">
              <select class="form-select select2-simple" data-field="transportista_id" required style="flex-grow: 1;">
                ${carrierOptions}
              </select>
              <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center btn-config-carriers" onclick="openCarriersModal('${uniqueId}')" style="height: 32px; width: 32px; flex-shrink: 0;" title="Configurar Choferes">
                <i class="fa-solid fa-gear"></i>
              </button>
            </div>
          </td>
          <td>
            <div class="d-flex gap-1 align-items-center" style="min-width: 200px;">
              <select class="form-select" data-field="transporte_id" ${isTruckSelectDisabled ? 'disabled' : ''} style="flex-grow: 1;">
                ${truckOptions}
              </select>
              <button type="button" class="btn btn-sm btn-outline-success btn-add-vehicle d-flex align-items-center justify-content-center" onclick="openAddVehicleModal('${uniqueId}')" ${isAddVehicleDisabled ? 'disabled' : ''} style="height: 32px; width: 32px; flex-shrink: 0;" title="Agregar Vehículo">
                <i class="fa-solid fa-plus"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-warning btn-edit-vehicle d-flex align-items-center justify-content-center" onclick="openEditVehicleModal('${uniqueId}')" ${isEditVehicleDisabled ? 'disabled' : ''} style="height: 32px; width: 32px; flex-shrink: 0;" title="Editar Vehículo">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
            </div>
          </td>
          <td>
            <input type="text" class="form-control" data-field="titular" readonly placeholder="-" value="${titular}">
          </td>
          <td>
            <input type="text" class="form-control" data-field="patente" readonly placeholder="-" value="${patente}">
          </td>
          <td>
            <input type="text" class="form-control" data-field="modelo" readonly placeholder="-" value="${modelo}">
          </td>
          <td>
            <input type="text" class="form-control" data-field="unidad" readonly placeholder="-" value="${unidad}">
          </td>
          <td>
            <div class="d-flex gap-1 align-items-center" style="min-width: 180px;">
              <select class="form-select" data-field="zona" style="flex-grow: 1;">
                ${paymentConceptOptions}
              </select>
              <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center btn-config-concepts" onclick="openConceptsModal()" style="height: 32px; width: 32px; flex-shrink: 0;" title="Configurar Conceptos">
                <i class="fa-solid fa-gear"></i>
              </button>
            </div>
          </td>
          <td>
            <div class="d-flex gap-1 align-items-center" style="min-width: 180px;">
              <select class="form-select" data-field="svc" style="flex-grow: 1;">
                ${zoneOptionsForSvc}
              </select>
              <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center btn-config-svs" onclick="openSvsModal('${uniqueId}')" style="height: 32px; width: 32px; flex-shrink: 0;" title="Configurar SVS">
                <i class="fa-solid fa-gear"></i>
              </button>
            </div>
          </td>
          <td>
            <input type="text" class="form-control" data-field="ruta" value="${ruta}" placeholder="Ruta">
          </td>
          <td>
            <input type="text" class="form-control" data-field="numero" value="${numero}" placeholder="Número">
            <input type="hidden" data-field="traffic_zone_id" value="${trafficZoneId || ''}">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="paradas" value="${paradas}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="paquetes" value="${paquetes}" min="0" style="width: 80px;" onchange="onQtyChange('${uniqueId}')">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="entregados" value="${entregados}" min="0" style="width: 80px;" onchange="onQtyChange('${uniqueId}')">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="deja_en_svc" value="${deja_svc}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="paq_no_colectado" value="${paq_no_col}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="nadie_en_domicilio" value="${nadie_dom}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="negocio_cerrado" value="${neg_cerrado}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="qr" value="${qr}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="fuera_de_zona" value="${fuera_zona}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="zona_inaccesible" value="${z_inaccesible}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="rechazado" value="${rechazado}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="sin_visitar" value="${sin_visitar}" min="0" style="width: 80px;">
          </td>
          <td>
            <input type="number" class="form-control text-end" data-field="fraude" value="${fraude}" min="0" style="width: 80px;">
          </td>
          <td>
            <div class="d-flex align-items-center gap-1">
              <input type="number" class="form-control text-end" data-field="paquete_perdido" value="${p_perdido}" min="0" style="width: 60px;">
              <input type="hidden" data-field="comentario_perdido" value="${comentario_perdido}">
              <button type="button" class="btn btn-sm p-0 btn-comment" onclick="editDetailComment('${uniqueId}', 'comentario_perdido', 'Comentario de Paquete Perdido')" title="Agregar comentario" style="color: ${comentario_perdido ? '#0d6efd' : '#ced4da'}; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px;">
                <i class="fa-solid fa-comment-dots"></i>
              </button>
            </div>
          </td>
          <td>
            <div class="d-flex align-items-center gap-1">
              <input type="number" class="form-control text-end" data-field="paquete_danado" value="${p_danado}" min="0" style="width: 60px;">
              <input type="hidden" data-field="comentario_danado" value="${comentario_danado}">
              <button type="button" class="btn btn-sm p-0 btn-comment" onclick="editDetailComment('${uniqueId}', 'comentario_danado', 'Comentario de Paquete Dañado')" title="Agregar comentario" style="color: ${comentario_danado ? '#0d6efd' : '#ced4da'}; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px;">
                <i class="fa-solid fa-comment-dots"></i>
              </button>
            </div>
          </td>
          <td>
            <div class="d-flex align-items-center gap-1">
              <input type="number" class="form-control text-end" data-field="paquete_robado" value="${p_robado}" min="0" style="width: 60px;">
              <input type="hidden" data-field="comentario_robado" value="${comentario_robado}">
              <button type="button" class="btn btn-sm p-0 btn-comment" onclick="editDetailComment('${uniqueId}', 'comentario_robado', 'Comentario de Paquete Robado')" title="Agregar comentario" style="color: ${comentario_robado ? '#0d6efd' : '#ced4da'}; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px;">
                <i class="fa-solid fa-comment-dots"></i>
              </button>
            </div>
          </td>
          <td>
            <input type="text" class="form-control text-end" data-field="porcentaje" value="${porcentaje}" style="width: 80px;" placeholder="0.00">
          </td>
          <td>
            <input type="number" step="0.01" class="form-control text-end" data-field="kilometros" value="${kilometros}" min="0" style="width: 100px;">
          </td>
          <td>
            <input type="number" step="0.01" class="form-control text-end" data-field="kilometros_estimados" value="${kilometros_estimados}" min="0" style="width: 100px;">
          </td>
          <td class="text-center">
            <input type="checkbox" class="form-check-input" data-field="zona_lejana" ${zona_lejana ? 'checked' : ''}>
          </td>
          <td>
            <input type="text" class="form-control" data-field="observacion" value="${observacion}" placeholder="Observaciones..." style="width: 250px;">
          </td>
        </tr>
      `;

      return { bodyId, html: rowHtml };
    }

    // Export removeRow to window so onclick works
    window.removeRow = function (rowId) {
      const $row = $(`#${rowId}`);
      const dbId = $row.attr('data-db-id');

      const titleText = dbId ? '¿Eliminar registro por completo?' : '¿Quitar fila?';
      const bodyText = dbId 
        ? 'El registro se eliminará permanentemente de la base de datos. Esta acción no se puede deshacer.'
        : 'Esta fila aún no ha sido guardada. ¿Desea quitarla?';

      Swal.fire({
        title: titleText,
        text: bodyText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar por completo',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          if (dbId) {
            Swal.fire({
              title: 'Eliminando...',
              text: 'Eliminando registro de la base de datos...',
              allowOutsideClick: false,
              didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
              url: `/traffic/planilla-choferes/${dbId}`,
              type: 'POST',
              data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                _method: 'DELETE'
              },
              dataType: 'json',
              success: function (res) {
                $row.remove();
                updateRowCount();
                
                Swal.fire({
                  icon: 'success',
                  title: 'Eliminado',
                  text: res.message || 'Registro eliminado por completo.',
                  timer: 1500,
                  showConfirmButton: false
                });

                const totalRows = $('.sheetTableBody tr').length;
                if (totalRows === 0) {
                  const firstZoneId = zonesData.length > 0 ? zonesData[0].id : 'none';
                  addRow(null, `sheetTableBody_${firstZoneId}`);
                  updateRowCount();
                }
              },
              error: function (xhr) {
                let errMsg = 'No se pudo eliminar el registro.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                  errMsg = xhr.responseJSON.message;
                }
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: errMsg
                });
              }
            });
          } else {
            $row.remove();
            updateRowCount();

            const totalRows = $('.sheetTableBody tr').length;
            if (totalRows === 0) {
              const firstZoneId = zonesData.length > 0 ? zonesData[0].id : 'none';
              addRow(null, `sheetTableBody_${firstZoneId}`);
              updateRowCount();
            }
          }
        }
      });
    };

    // Update row count badge per tab and grand total
    function updateRowCount() {
      let grandTotal = 0;
      
      // Update each zone count
      zonesData.forEach(z => {
        const bodyId = `sheetTableBody_${z.id}`;
        const count = recordsCountByBody[bodyId] || 0;
        $(`#badge-count-${z.id}`).text(count);
        grandTotal += count;
      });
      
      // None zone
      const bodyNone = 'sheetTableBody_none';
      const countNone = recordsCountByBody[bodyNone] || 0;
      $('#badge-count-none').text(countNone);
      grandTotal += countNone;

      $('#rowCountBadge').text(`Total: ${grandTotal} filas`);
    }

    // Dynamic truck list populator (memory-based)
    window.onCarrierChange = function (rowId, selectedTruckId = null) {
      const $row = $(`#${rowId}`);
      const carrierId = $row.find('[data-field="transportista_id"]').val();
      const $truckSelect = $row.find('[data-field="transporte_id"]');
      const $addVehicleBtn = $row.find('.btn-add-vehicle');
      const $editVehicleBtn = $row.find('.btn-edit-vehicle');

      $truckSelect.empty();
      $truckSelect.append('<option value="">-- Vehículo --</option>');

      if (!carrierId) {
        $truckSelect.prop('disabled', true);
        $addVehicleBtn.prop('disabled', true);
        $editVehicleBtn.prop('disabled', true);
        $truckSelect.trigger('change.select2');
        clearTruckMeta(rowId);
        return;
      }

      $addVehicleBtn.prop('disabled', false);

      // Use preloaded carriersData in memory!
      const carrier = carriersData.find(c => c.id == carrierId);
      if (!carrier || !carrier.transportes || carrier.transportes.length === 0) {
        $truckSelect.prop('disabled', true);
        $truckSelect.trigger('change.select2');
        $editVehicleBtn.prop('disabled', true);
        clearTruckMeta(rowId);
        return;
      }

      $truckSelect.prop('disabled', false);

      let defaultTruckId = selectedTruckId;
      carrier.transportes.forEach(t => {
        const isSelected = (selectedTruckId && t.id == selectedTruckId) || (!selectedTruckId && t.is_default);
        if (isSelected && !defaultTruckId) {
          defaultTruckId = t.id;
        }
        const labelSuffix = t.is_default ? ' (Predeterminado)' : '';
        $truckSelect.append(`<option value="${t.id}" ${isSelected ? 'selected' : ''}>${t.alias || t.license_plate}${labelSuffix}</option>`);
      });

      if (defaultTruckId) {
        $truckSelect.val(defaultTruckId);
      }
      $truckSelect.trigger('change.select2');
      onTruckChange(rowId);
    };

    window.onTruckChange = function (rowId) {
      const $row = $(`#${rowId}`);
      const carrierId = $row.find('[data-field="transportista_id"]').val();
      const truckId = $row.find('[data-field="transporte_id"]').val();
      const $editVehicleBtn = $row.find('.btn-edit-vehicle');

      if (!carrierId || !truckId) {
        $editVehicleBtn.prop('disabled', true);
        clearTruckMeta(rowId);
        return;
      }

      $editVehicleBtn.prop('disabled', false);

      const carrier = carriersData.find(c => c.id == carrierId);
      if (!carrier) return;

      const truck = carrier.transportes.find(t => t.id == truckId);
      if (!truck) {
        $editVehicleBtn.prop('disabled', true);
        clearTruckMeta(rowId);
        return;
      }

      $row.find('[data-field="titular"]').val(truck.owner_name || '-');
      $row.find('[data-field="patente"]').val(truck.license_plate || '-');
      $row.find('[data-field="modelo"]').val(truck.year || '-');
      
      const typeLabel = vehicleTypeLabels[truck.type] || truck.type || 'General';
      $row.find('[data-field="unidad"]').val(typeLabel);
    };

    function clearTruckMeta(rowId) {
      const $row = $(`#${rowId}`);
      $row.find('[data-field="titular"]').val('');
      $row.find('[data-field="patente"]').val('');
      $row.find('[data-field="modelo"]').val('');
      $row.find('[data-field="unidad"]').val('');
    }

    // Modal actions for adding vehicle
    window.openAddVehicleModal = function (rowId) {
      const $row = $(`#${rowId}`);
      const carrierId = $row.find('[data-field="transportista_id"]').val();
      if (!carrierId) {
        Swal.fire({
          icon: 'warning',
          title: 'Atención',
          text: 'Por favor, selecciona un chofer primero.'
        });
        return;
      }
      
      const carrier = carriersData.find(c => c.id == carrierId);
      const carrierName = carrier ? carrier.name : 'Chofer';
      
      // Reset form
      $('#addVehicleForm')[0].reset();
      $('#modal_transportista_id').val(carrierId);
      $('#modal_row_id').val(rowId);
      $('#modal_vehicle_id').val(''); // Clear edit ID
      $('#addVehicleModalLabel').html(`<i class="fa-solid fa-truck me-2 text-primary"></i>Agregar Vehículo para ${carrierName}`);
      
      if (vehicleModal) {
        vehicleModal.show();
      }
    };

    // Modal actions for editing vehicle
    window.openEditVehicleModal = function (rowId) {
      const $row = $(`#${rowId}`);
      const carrierId = $row.find('[data-field="transportista_id"]').val();
      const vehicleId = $row.find('[data-field="transporte_id"]').val();
      
      if (!carrierId || !vehicleId) {
        Swal.fire({
          icon: 'warning',
          title: 'Atención',
          text: 'Por favor, selecciona un vehículo primero.'
        });
        return;
      }
      
      const carrier = carriersData.find(c => c.id == carrierId);
      const carrierName = carrier ? carrier.name : 'Chofer';
      
      // Show loading overlay
      Swal.fire({
        title: 'Cargando datos...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      // Fetch vehicle details via AJAX
      $.ajax({
        url: `/traffic/transportes/${vehicleId}/edit`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
          Swal.close();
          if (response.ok) {
            const v = response.data;
            
            // Populate form
            $('#addVehicleForm')[0].reset();
            $('#modal_transportista_id').val(carrierId);
            $('#modal_row_id').val(rowId);
            $('#modal_vehicle_id').val(v.id);
            
            $('#modal_alias').val(v.alias || '');
            $('#modal_license_plate').val(v.license_plate || '');
            $('#modal_type').val(v.type || 'camioneta');
            $('#modal_owner_name').val(v.owner_name || '');
            $('#modal_brand').val(v.brand || '');
            $('#modal_model').val(v.model || '');
            $('#modal_year').val(v.year || '');
            $('#modal_is_default').prop('checked', !!v.is_default);
            
            $('#addVehicleModalLabel').html(`<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Editar Vehículo para ${carrierName}`);
            
            if (vehicleModal) {
              vehicleModal.show();
            }
          }
        },
        error: function () {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudieron cargar los datos del vehículo.'
          });
        }
      });
    };

    $('#btnSaveVehicle').on('click', function () {
      const carrierId = $('#modal_transportista_id').val();
      const alias = $('#modal_alias').val().trim();
      const license_plate = $('#modal_license_plate').val().trim();
      const type = $('#modal_type').val();
      const owner_name = $('#modal_owner_name').val().trim();
      const brand = $('#modal_brand').val().trim();
      const model = $('#modal_model').val().trim();
      const year = $('#modal_year').val().trim();
      const is_default = $('#modal_is_default').is(':checked') ? 1 : 0;
      const rowId = $('#modal_row_id').val();
      const vehicleId = $('#modal_vehicle_id').val();
      const isEdit = !!vehicleId;

      if (!alias) {
        Swal.fire({
          icon: 'error',
          title: 'Error de Validación',
          text: 'El campo Alias / Identificador es obligatorio.'
        });
        return;
      }

      const ajaxUrl = isEdit ? `/traffic/transportes/${vehicleId}` : "{{ route('traffic.transportes.store') }}";
      const ajaxMethod = isEdit ? "PUT" : "POST";

      $.ajax({
        url: ajaxUrl,
        method: ajaxMethod,
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        data: {
          transportista_id: carrierId,
          alias: alias,
          license_plate: license_plate,
          type: type,
          owner_name: owner_name,
          brand: brand,
          model: model,
          year: year,
          is_default: is_default,
          is_active: 1
        },
        dataType: 'json',
        success: function (response) {
          if (response.ok) {
            const newVehicle = response.data;

            // Update local carriersData in memory!
            const carrier = carriersData.find(c => c.id == carrierId);
            if (carrier) {
              if (!carrier.transportes) {
                carrier.transportes = [];
              }
              
              if (newVehicle.is_default) {
                carrier.transportes.forEach(t => t.is_default = false);
              }

              const existingIdx = carrier.transportes.findIndex(t => t.id == newVehicle.id);
              if (existingIdx !== -1) {
                carrier.transportes[existingIdx] = newVehicle;
              } else {
                carrier.transportes.push(newVehicle);
              }
            }

            if (vehicleModal) {
              vehicleModal.hide();
            }
            Swal.fire({
              icon: 'success',
              title: isEdit ? 'Vehículo Actualizado' : 'Vehículo Guardado',
              text: response.message || 'El vehículo se guardó correctamente.',
              timer: 1500,
              showConfirmButton: false
            });
            
            // Reload fleet via carrier change and select new truck
            onCarrierChange(rowId, newVehicle.id);
          }
        },
        error: function (xhr) {
          let errMsg = 'No se pudo guardar el vehículo.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            errMsg = xhr.responseJSON.message;
          } else if (xhr.responseJSON && xhr.responseJSON.errors) {
            errMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
          }
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errMsg
          });
        }
      });
    });

    // Modal actions for SVS configuration
    window.openSvsModal = function (rowId) {
      const $row = $(`#${rowId}`);
      const trafficZoneId = $row.find('[data-field="traffic_zone_id"]').val();
      
      if (!trafficZoneId) {
        Swal.fire({
          icon: 'warning',
          title: 'Atención',
          text: 'Esta fila no está asociada a ninguna zona operacional. Por favor, asegúrate de que la pestaña tenga una zona asignada.'
        });
        return;
      }
      
      const zoneObj = zonesData.find(z => z.id == trafficZoneId);
      if (!zoneObj) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'No se pudo encontrar la zona operacional correspondiente en la base de datos.'
        });
        return;
      }

      // Populate form inside modal
      $('#svsConfigZoneId').val(zoneObj.id);
      $('#svsConfigRowId').val(rowId);
      $('#svsConfigZoneName').val(zoneObj.name);
      // Ensure svs_values exists
      const svsArray = zoneObj.svs_values || [];
      $('#modalSvsValues').val(Array.isArray(svsArray) ? svsArray.join('\n') : '');

      if (svsConfigModal) {
        svsConfigModal.show();
      }
    };

    $(document).on('click', '#btnSaveSvsFromPlanilla', function () {
      const zoneId = $('#svsConfigZoneId').val();
      const rowId = $('#svsConfigRowId').val();
      const svsValuesText = $('#modalSvsValues').val();
      
      if (!zoneId) return;

      const $btn = $(this);
      const originalText = $btn.html();
      $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...');

      $.ajax({
        url: `/traffic/zones/${zoneId}/update-svs`,
        type: 'POST',
        data: {
          _token: $('meta[name="csrf-token"]').attr('content'),
          _method: 'PATCH',
          svs_values: svsValuesText
        },
        success: function (res) {
          if (res.ok) {
            // Update local zonesData
            const zoneObj = zonesData.find(z => z.id == zoneId);
            if (zoneObj) {
              zoneObj.svs_values = res.svs_values;
            }

            // Update SVS dropdowns for all rows belonging to this zone
            $(`.zone-table tbody tr`).each(function () {
              const $r = $(this);
              const rZoneId = $r.find('[data-field="traffic_zone_id"]').val();
              if (rZoneId == zoneId) {
                const $svcSelect = $r.find('[data-field="svc"]');
                const currentValue = $svcSelect.val();
                
                // Re-generate option list
                let optionsHtml = '<option value="">-- Seleccionar --</option>';
                res.svs_values.forEach(val => {
                  optionsHtml += `<option value="${val}" ${val === currentValue ? 'selected' : ''}>${val}</option>`;
                });
                
                $svcSelect.html(optionsHtml);
                $svcSelect.trigger('change.select2');
              }
            });

            Swal.fire({
              icon: 'success',
              title: 'Guardado',
              text: 'Los valores SVS se actualizaron correctamente para esta zona.',
              timer: 2000,
              showConfirmButton: false
            });

            if (svsConfigModal) {
              svsConfigModal.hide();
            }
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Ocurrió un error al guardar los valores SVS.'
            });
          }
        },
        error: function () {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error en el servidor. Por favor, reintenta.'
          });
        },
        complete: function () {
          $btn.prop('disabled', false).html(originalText);
        }
      });
    });

    // Modal actions for Concepts configuration
    window.openConceptsModal = function () {
      loadConceptsList();
      if (conceptsConfigModal) {
        conceptsConfigModal.show();
      }
    };

    $(document).on('change', '#conceptValueType', function () {
      if ($(this).val() === 'reference') {
        $('#conceptFixedFields').addClass('d-none');
        $('#conceptReferenceFields').removeClass('d-none');
      } else {
        $('#conceptFixedFields').removeClass('d-none');
        $('#conceptReferenceFields').addClass('d-none');
      }
    });

    function loadConceptsList() {
      const $tbody = $('#conceptsTableBody');
      $tbody.html('<tr><td colspan="6" class="text-center py-3"><i class="fa-solid fa-spinner fa-spin me-1"></i> Cargando conceptos...</td></tr>');
      
      $.ajax({
        url: '{{ route("traffic.planilla-choferes.concepts") }}',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
          allConceptsCached = res;
          
          // Populate reference concepts dropdown in the form (exclude current if editing)
          const currentEditingId = $('#conceptId').val();
          let refOptionsHtml = '<option value="">-- Seleccionar --</option>';
          res.forEach(c => {
            if (c.id != currentEditingId && c.value_type === 'fixed') {
              refOptionsHtml += `<option value="${c.id}">${c.name}</option>`;
            }
          });
          $('#conceptReferenceId').html(refOptionsHtml);

          // Populate the table
          if (res.length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center py-3 text-muted">No hay conceptos cargados.</td></tr>');
            return;
          }

          let rowsHtml = '';
          res.forEach(c => {
            const valTypeLabel = c.value_type === 'fixed' ? 'Fijo' : 'Referencia';
            let amountOrRef = '';
            if (c.value_type === 'fixed') {
              amountOrRef = `$${parseFloat(c.default_amount || 0).toFixed(2)}`;
            } else {
              const baseConcept = res.find(bc => bc.id == c.reference_concept_id);
              const baseName = baseConcept ? baseConcept.name : 'N/A';
              const mult = parseFloat(c.reference_multiplier || 1).toFixed(4);
              amountOrRef = `${baseName} x ${mult}`;
            }

            const signLabel = c.sign == -1 ? '<span class="text-danger fw-bold">Resta (-)</span>' : '<span class="text-success fw-bold">Suma (+)</span>';
            const stateLabel = c.active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>';

            rowsHtml += `
              <tr data-id="${c.id}">
                <td class="fw-semibold">${c.name}</td>
                <td>${valTypeLabel}</td>
                <td>${amountOrRef}</td>
                <td>${signLabel}</td>
                <td>${stateLabel}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-warning" onclick="editConcept(${c.id})" title="Editar">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteConcept(${c.id})" title="Borrar">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </div>
                </td>
              </tr>
            `;
          });
          $tbody.html(rowsHtml);
        },
        error: function () {
          $tbody.html('<tr><td colspan="6" class="text-center py-3 text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Error al cargar los conceptos.</td></tr>');
        }
      });
    }

    window.editConcept = function (id) {
      const concept = allConceptsCached.find(c => c.id == id);
      if (!concept) return;

      $('#conceptId').val(concept.id);
      $('#conceptNameInput').val(concept.name);
      $('#conceptValueType').val(concept.value_type).trigger('change');
      $('#conceptSign').val(concept.sign);
      $('#conceptActive').prop('checked', concept.active);

      if (concept.value_type === 'fixed') {
        $('#conceptDefaultAmount').val(concept.default_amount);
      } else {
        let refOptionsHtml = '<option value="">-- Seleccionar --</option>';
        allConceptsCached.forEach(c => {
          if (c.id != id && c.value_type === 'fixed') {
            refOptionsHtml += `<option value="${c.id}">${c.name}</option>`;
          }
        });
        $('#conceptReferenceId').html(refOptionsHtml);
        $('#conceptReferenceId').val(concept.reference_concept_id);
        $('#conceptReferenceMultiplier').val(concept.reference_multiplier);
      }

      $('#conceptFormTitle').text('Editar Concepto');
      $('#btnCancelConceptEdit').show();
    };

    window.resetConceptForm = function () {
      $('#conceptId').val('');
      $('#conceptCrudForm')[0].reset();
      $('#conceptValueType').val('fixed').trigger('change');
      $('#conceptFormTitle').text('Nuevo Concepto');
      $('#btnCancelConceptEdit').hide();
      
      let refOptionsHtml = '<option value="">-- Seleccionar --</option>';
      allConceptsCached.forEach(c => {
        if (c.value_type === 'fixed') {
          refOptionsHtml += `<option value="${c.id}">${c.name}</option>`;
        }
      });
      $('#conceptReferenceId').html(refOptionsHtml);
    };

    $(document).on('click', '#btnCancelConceptEdit', function () {
      resetConceptForm();
    });

    $(document).on('submit', '#conceptCrudForm', function (e) {
      e.preventDefault();
      
      const conceptId = $('#conceptId').val();
      const isEdit = !!conceptId;
      
      const nameVal = $('#conceptNameInput').val();
      const valueTypeVal = $('#conceptValueType').val();
      const signVal = $('#conceptSign').val();
      const activeVal = $('#conceptActive').is(':checked') ? 1 : 0;
      
      const defaultAmountVal = $('#conceptDefaultAmount').val();
      const refIdVal = $('#conceptReferenceId').val();
      const refMultVal = $('#conceptReferenceMultiplier').val();

      const url = isEdit 
        ? `/pago-choferes/configuracion/conceptos/${conceptId}` 
        : `/pago-choferes/configuracion/conceptos`;
        
      const method = isEdit ? 'PUT' : 'POST';

      const data = {
        _token: $('meta[name="csrf-token"]').attr('content'),
        value_type: valueTypeVal,
        sign: signVal,
        active: activeVal
      };

      if (isEdit) {
        data._method = 'PUT';
        data.name = nameVal;
      } else {
        data.concept_name = nameVal;
      }

      if (valueTypeVal === 'fixed') {
        data.default_amount = defaultAmountVal;
      } else {
        data.reference_concept_id = refIdVal;
        data.reference_multiplier = refMultVal;
      }

      const $btn = $('#btnSaveConcept');
      const originalHtml = $btn.html();
      $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...');

      $.ajax({
        url: url,
        type: 'POST',
        data: data,
        dataType: 'json',
        headers: {
          'Accept': 'application/json'
        },
        success: function (res) {
          Swal.fire({
            icon: 'success',
            title: isEdit ? 'Concepto Actualizado' : 'Concepto Creado',
            text: 'El concepto de pago se guardó correctamente.',
            timer: 2000,
            showConfirmButton: false
          });
          
          resetConceptForm();
          loadConceptsList();
          updatePlanillaConcepts();
        },
        error: function (xhr) {
          let errorMsg = 'Ocurrió un error al guardar el concepto.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMsg = xhr.responseJSON.message;
          } else if (xhr.responseJSON && xhr.responseJSON.errors) {
            errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
          }
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMsg
          });
        },
        complete: function () {
          $btn.prop('disabled', false).html(originalHtml);
        }
      });
    });

    window.deleteConcept = function (id) {
      const concept = allConceptsCached.find(c => c.id == id);
      if (!concept) return;

      Swal.fire({
        title: '¿Eliminar concepto?',
        text: `¿Estás seguro de que deseas eliminar el concepto "${concept.name}"? Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: `/pago-choferes/configuracion/conceptos/${id}`,
            type: 'POST',
            data: {
              _token: $('meta[name="csrf-token"]').attr('content'),
              _method: 'DELETE'
            },
            dataType: 'json',
            headers: {
              'Accept': 'application/json'
            },
            success: function (res) {
              Swal.fire({
                icon: 'success',
                title: 'Eliminado',
                text: 'El concepto fue eliminado correctamente.',
                timer: 2000,
                showConfirmButton: false
              });
              
              if ($('#conceptId').val() == id) {
                resetConceptForm();
              }
              
              loadConceptsList();
              updatePlanillaConcepts();
            },
            error: function (xhr) {
              let errorMsg = 'No se pudo eliminar el concepto.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              }
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg
              });
            }
          });
        }
      });
    };

    function updatePlanillaConcepts() {
      $.ajax({
        url: '{{ route("traffic.planilla-choferes.concepts") }}',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
          paymentConceptsData = res.filter(c => c.active);
          
          $(`.zone-table tbody tr`).each(function () {
            const $r = $(this);
            const $zonaSelect = $r.find('[data-field="zona"]');
            const currentValue = $zonaSelect.val();

            let optionsHtml = '<option value="">-- Seleccionar --</option>';
            paymentConceptsData.forEach(c => {
              optionsHtml += `<option value="${c.name}" ${c.name === currentValue ? 'selected' : ''}>${c.name}</option>`;
            });

            if ($zonaSelect.hasClass('select2-hidden-accessible')) {
              $zonaSelect.select2('destroy');
              $zonaSelect.html(optionsHtml);
              $zonaSelect.select2({
                width: '100%'
              });
            } else {
              $zonaSelect.html(optionsHtml);
            }
          });
        }
      });
    }

    // Modal actions for Carrier CRUD (Single Driver Mode)
    window.openCarriersModal = function (rowId) {
      resetCarrierForm();
      $('#carrierRowIdInput').val(rowId);

      const $row = $(`#${rowId}`);
      const carrierId = $row.find('[data-field="transportista_id"]').val();

      if (carrierId) {
        // Find existing driver details in memory
        const carrier = carriersData.find(c => c.id == carrierId);
        if (carrier) {
          $('#carrierIdInput').val(carrier.id);
          $('#carrierNameInput').val(carrier.name || '');
          $('#carrierEmailInput').val(carrier.email || '');
          $('#carrierCbuInput').val(carrier.cbu || '');
          $('#carrierActiveInput').prop('checked', carrier.is_active === undefined ? true : !!carrier.is_active);
          $('#carrierFormTitle').text(`Editar Chofer: ${carrier.name}`);
        } else {
          $('#carrierFormTitle').text('Configurar Chofer');
        }
      } else {
        $('#carrierFormTitle').text('Nuevo Chofer');
      }

      if (carriersConfigModal) {
        carriersConfigModal.show();
      }
    };

    window.resetCarrierForm = function () {
      $('#carrierIdInput').val('');
      $('#carrierRowIdInput').val('');
      $('#carrierCrudForm')[0].reset();
      $('#carrierActiveInput').prop('checked', true);
      $('#carrierFormTitle').text('Nuevo Chofer');
    };

    function updatePlanillaCarriers(rowId = null, selectCarrierId = null) {
      $.ajax({
        url: '{{ route("traffic.planilla-choferes.carriers-list") }}',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
          // Update carriersData cache in memory
          carriersData = res;
          
          // Update the filter dropdown "Chofer" in the search form
          const $filterSelect = $('select[name="transportista_id"]');
          const currentFilterValue = $filterSelect.val();
          let filterOptionsHtml = '<option value="">-- Todos --</option>';
          res.forEach(c => {
            if (c.is_active) {
              filterOptionsHtml += `<option value="${c.id}" ${c.id == currentFilterValue ? 'selected' : ''}>${c.name}</option>`;
            }
          });
          $filterSelect.html(filterOptionsHtml);
          if ($filterSelect.data('select2')) {
            $filterSelect.trigger('change.select2');
          }
          
          // Rebuild carriers options for all rows in the spreadsheet
          $(`.zone-table tbody tr`).each(function () {
            const $r = $(this);
            const $carrierSelect = $r.find('[data-field="transportista_id"]');
            let currentValue = $carrierSelect.val();

            // If this is the row that triggered the modal and we want to select a new carrier
            if ($r.attr('id') === rowId && selectCarrierId) {
              currentValue = selectCarrierId;
            }

            let optionsHtml = '<option value="">-- Seleccionar --</option>';
            res.forEach(c => {
              if (c.is_active || c.id == currentValue) {
                optionsHtml += `<option value="${c.id}" ${c.id == currentValue ? 'selected' : ''}>${c.name}</option>`;
              }
            });

            if ($carrierSelect.hasClass('select2-hidden-accessible')) {
              $carrierSelect.select2('destroy');
              $carrierSelect.html(optionsHtml);
              $carrierSelect.select2({
                width: '100%'
              });
            } else {
              $carrierSelect.html(optionsHtml);
            }

            // Trigger change if we changed selection to update vehicles list
            if ($r.attr('id') === rowId && selectCarrierId) {
              $carrierSelect.val(selectCarrierId).trigger('change');
            }
          });
        }
      });
    }

    $(document).on('submit', '#carrierCrudForm', function (e) {
      e.preventDefault();
      
      const carrierId = $('#carrierIdInput').val();
      const rowId = $('#carrierRowIdInput').val();
      const isEdit = !!carrierId;
      
      const nameVal = $('#carrierNameInput').val();
      const emailVal = $('#carrierEmailInput').val();
      const cbuVal = $('#carrierCbuInput').val();
      const activeVal = $('#carrierActiveInput').is(':checked') ? 1 : 0;

      const url = isEdit 
        ? `/traffic/transportistas/${carrierId}` 
        : `/traffic/transportistas`;

      const data = {
        _token: $('meta[name="csrf-token"]').attr('content'),
        name: nameVal,
        email: emailVal,
        cbu: cbuVal,
        is_active: activeVal
      };

      if (isEdit) {
        data._method = 'PUT';
      }

      const $btn = $('#btnSaveCarrier');
      const originalHtml = $btn.html();
      $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...');

      $.ajax({
        url: url,
        type: 'POST',
        data: data,
        dataType: 'json',
        headers: {
          'Accept': 'application/json'
        },
        success: function (res) {
          if (carriersConfigModal) {
            carriersConfigModal.hide();
          }
          Swal.fire({
            icon: 'success',
            title: isEdit ? 'Chofer Actualizado' : 'Chofer Creado',
            text: 'El chofer se guardó correctamente.',
            timer: 2000,
            showConfirmButton: false
          });
          
          const newCarrierId = res.data ? res.data.id : null;
          updatePlanillaCarriers(rowId, newCarrierId);
        },
        error: function (xhr) {
          let errorMsg = 'Ocurrió un error al guardar el chofer.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMsg = xhr.responseJSON.message;
          } else if (xhr.responseJSON && xhr.responseJSON.errors) {
            errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
          }
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMsg
          });
        },
        complete: function () {
          $btn.prop('disabled', false).html(originalHtml);
        }
      });
    });

    // Modal actions for Zone creation
    window.openCreateZoneModal = function () {
      $('#createZoneFromPlanillaForm')[0].reset();
      if (createZoneFromPlanillaModal) {
        createZoneFromPlanillaModal.show();
      }
    };

    $(document).on('submit', '#createZoneFromPlanillaForm', function (e) {
      e.preventDefault();

      const nameVal = $('#newZoneName').val();
      const priorityVal = $('#newZonePriority').val();
      const svsVal = $('#newZoneSvsValues').val();

      Swal.fire({
        title: '¿Crear nueva zona?',
        text: 'La página se recargará para mostrar la nueva pestaña. Los cambios no guardados en la planilla se perderán.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Sí, crear y recargar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          const $btn = $('#btnSaveNewZoneFromPlanilla');
          const originalHtml = $btn.html();
          $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Creando...');

          $.ajax({
            url: '{{ route("traffic.zones.store") }}',
            type: 'POST',
            data: {
              _token: $('meta[name="csrf-token"]').attr('content'),
              name: nameVal,
              priority: priorityVal,
              type: 'circle',
              is_soft: 1,
              svs_values: svsVal
            },
            dataType: 'json',
            headers: {
              'Accept': 'application/json'
            },
            success: function (res) {
              Swal.fire({
                icon: 'success',
                title: 'Zona Creada',
                text: 'La zona operativa se creó con éxito. Recargando la página...',
                timer: 2000,
                showConfirmButton: false
              });

              setTimeout(() => {
                window.location.reload();
              }, 1500);
            },
            error: function (xhr) {
              let errorMsg = 'Error al crear la zona.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
              }
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg
              });
            },
            complete: function () {
              $btn.prop('disabled', false).html(originalHtml);
            }
          });
        }
      });
    });

    // Auto-calculate percentage
    window.onQtyChange = function (rowId) {
      const $row = $(`#${rowId}`);
      const packages = parseInt($row.find('[data-field="paquetes"]').val() || 0);
      const delivered = parseInt($row.find('[data-field="entregados"]').val() || 0);
      let percentage = '0.00';

      if (packages > 0) {
        percentage = ((delivered / packages) * 100).toFixed(2);
      }

      const $porcentajeInput = $row.find('[data-field="porcentaje"]');
      $porcentajeInput.attr('placeholder', percentage);
      if (!$porcentajeInput.hasClass('is-custom')) {
        $porcentajeInput.val(percentage);
      }
    };

    // Edit package details comments via SweetAlert2
    window.editDetailComment = function (rowId, fieldName, title) {
      const $row = $(`#${rowId}`);
      const $hiddenInput = $row.find(`[data-field="${fieldName}"]`);
      const currentValue = $hiddenInput.val();
      
      Swal.fire({
        title: title,
        input: 'textarea',
        inputLabel: 'Ingrese un comentario aclaratorio sobre este paquete:',
        inputValue: currentValue,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        inputPlaceholder: 'Escriba aquí el motivo o descripción...',
        inputAttributes: {
          'aria-label': 'Escriba aquí el comentario'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const newValue = result.value || '';
          $hiddenInput.val(newValue);
          $row.addClass('is-dirty');
          triggerAutosave();
          
          // Update button icon color to show active status
          const $btn = $hiddenInput.siblings('.btn-comment');
          if (newValue.trim()) {
            $btn.css('color', '#0d6efd'); // Active: blue
          } else {
            $btn.css('color', '#ced4da'); // Inactive: gray
          }
        }
      });
    };

    // Show audit log modal
    window.showLogs = function (recordId) {
      if (!recordId) return;
      
      $('#logHistoryTableBody').html('<tr><td colspan="4" class="text-center py-3"><i class="fa-solid fa-spinner fa-spin me-2 text-primary"></i>Cargando historial de cambios...</td></tr>');
      if (logHistoryModal) {
        logHistoryModal.show();
      }
      
      $.ajax({
        url: `/traffic/planilla-choferes/${recordId}/logs`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
          if (response.success && response.data.length > 0) {
            let rowsHtml = '';
            response.data.forEach(log => {
              let badgeColor = 'bg-secondary';
              if (log.action.toLowerCase() === 'crear') badgeColor = 'bg-success';
              else if (log.action.toLowerCase() === 'modificar') badgeColor = 'bg-warning text-dark';
              else if (log.action.toLowerCase() === 'eliminar') badgeColor = 'bg-danger';

              rowsHtml += `
                <tr>
                  <td>${log.fecha}</td>
                  <td><span class="fw-semibold">${log.user}</span></td>
                  <td><span class="badge ${badgeColor}">${log.action}</span></td>
                  <td class="text-wrap" style="max-width: 350px; font-size: 0.85rem;">${log.details || '-'}</td>
                </tr>
              `;
            });
            $('#logHistoryTableBody').html(rowsHtml);
          } else {
            $('#logHistoryTableBody').html('<tr><td colspan="4" class="text-center py-3 text-muted">No se registraron cambios para esta línea.</td></tr>');
          }
        },
        error: function () {
          $('#logHistoryTableBody').html('<tr><td colspan="4" class="text-center py-3 text-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>Error al cargar el historial.</td></tr>');
        }
      });
    };

    // Clean duplicates from UI
    window.runCleanDuplicates = function () {
      Swal.fire({
        title: '¿Limpiar registros duplicados?',
        text: 'Se consolidarán los registros duplicados existentes en la base de datos conservando la fila con mayor información. ¿Desea proceder?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Sí, limpiar duplicados',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: 'Limpiando duplicados...',
            text: 'Por favor espere un momento.',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          $.ajax({
            url: '{{ route("traffic.planilla-choferes.clean-duplicates") }}',
            type: 'POST',
            data: {
              _token: $('meta[name="csrf-token"]').attr('content'),
              fecha_desde: $('input[name="fecha_desde"]').val(),
              fecha_hasta: $('input[name="fecha_hasta"]').val()
            },
            dataType: 'json',
            success: function (res) {
              if (res.success) {
                Swal.fire({
                  icon: 'success',
                  title: 'Limpieza Completada',
                  text: res.message,
                  confirmButtonText: 'Entendido'
                }).then(() => {
                  window.location.reload();
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: res.message || 'No se pudo realizar la limpieza.'
                });
              }
            },
            error: function (xhr) {
              let errorMsg = 'Error al ejecutar la limpieza de duplicados.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              }
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg
              });
            }
          });
        }
      });
    };

    // Bulk Save Ajax
    function saveAll(isSilent = false) {
      if (isSaving) {
        savePending = true;
        if (!isSilent) {
          pendingSilentMode = false;
        }
        return;
      }

      isSaving = true;

      const rows = [];
      let isValid = true;
      const $dirtyRows = $('.sheetTableBody tr.is-dirty');

      if ($dirtyRows.length === 0 && deletedIds.length === 0) {
        isSaving = false;
        if (isSilent) {
          showAutosaveStatus('saved');
        }
        if (savePending) {
          const nextSilent = pendingSilentMode;
          savePending = false;
          pendingSilentMode = true;
          saveAll(nextSilent);
        }
        return;
      }

      $dirtyRows.each(function () {
        const $row = $(this);
        const dbId = $row.attr('data-db-id');
        
        const fecha = $row.find('[data-field="fecha"]').val();
        const transportista_id = $row.find('[data-field="transportista_id"]').val();
        
        if (!fecha && !transportista_id && !dbId) {
          // Skip completely empty new rows
          return;
        }

        if (!fecha || !transportista_id) {
          $row.addClass('table-danger');
          isValid = false;
        } else {
          $row.removeClass('table-danger');
        }

        const parseNumOrNull = function($fieldInput) {
          const rawVal = $fieldInput.val();
          if (rawVal === undefined || rawVal === null || String(rawVal).trim() === '') {
            return null;
          }
          const parsed = parseInt(rawVal);
          return isNaN(parsed) ? null : parsed;
        };

        const rawEntregados = parseNumOrNull($row.find('[data-field="entregados"]'));
        const rawParadas = parseNumOrNull($row.find('[data-field="paradas"]'));
        const rawPaquetes = parseNumOrNull($row.find('[data-field="paquetes"]'));
        const entregadosStr = String($row.find('[data-field="entregados"]').val() || '').trim();

        rows.push({
          id: dbId ? parseInt(dbId) : null,
          temp_id: $row.attr('id'),
          is_autosave: isSilent,
          explicit_zero_entregados: (entregadosStr === '0'),
          fecha: fecha,
          transportista_id: transportista_id ? parseInt(transportista_id) : null,
          transporte_id: $row.find('[data-field="transporte_id"]').val() ? parseInt($row.find('[data-field="transporte_id"]').val()) : null,
          traffic_zone_id: $row.find('[data-field="traffic_zone_id"]').val() ? parseInt($row.find('[data-field="traffic_zone_id"]').val()) : null,
          svc: $row.find('[data-field="svc"]').val(),
          ruta: $row.find('[data-field="ruta"]').val(),
          numero: $row.find('[data-field="numero"]').val(),
          zona: $row.find('[data-field="zona"]').val(),
          paradas: rawParadas,
          paquetes: rawPaquetes,
          entregados: rawEntregados,
          deja_en_svc: parseNumOrNull($row.find('[data-field="deja_en_svc"]')) || 0,
          paq_no_colectado: parseNumOrNull($row.find('[data-field="paq_no_colectado"]')) || 0,
          nadie_en_domicilio: parseNumOrNull($row.find('[data-field="nadie_en_domicilio"]')) || 0,
          negocio_cerrado: parseNumOrNull($row.find('[data-field="negocio_cerrado"]')) || 0,
          qr: parseNumOrNull($row.find('[data-field="qr"]')) || 0,
          fuera_de_zona: parseNumOrNull($row.find('[data-field="fuera_de_zona"]')) || 0,
          zona_inaccesible: parseNumOrNull($row.find('[data-field="zona_inaccesible"]')) || 0,
          rechazado: parseNumOrNull($row.find('[data-field="rechazado"]')) || 0,
          sin_visitar: parseNumOrNull($row.find('[data-field="sin_visitar"]')) || 0,
          fraude: parseNumOrNull($row.find('[data-field="fraude"]')) || 0,
          paquete_perdido: parseNumOrNull($row.find('[data-field="paquete_perdido"]')) || 0,
          paquete_danado: parseNumOrNull($row.find('[data-field="paquete_danado"]')) || 0,
          paquete_robado: parseNumOrNull($row.find('[data-field="paquete_robado"]')) || 0,
          comentario_perdido: $row.find('[data-field="comentario_perdido"]').val() || null,
          comentario_danado: $row.find('[data-field="comentario_danado"]').val() || null,
          comentario_robado: $row.find('[data-field="comentario_robado"]').val() || null,
          porcentaje: $row.find('[data-field="porcentaje"]').val() !== '' ? parseFloat($row.find('[data-field="porcentaje"]').val()) : null,
          kilometros: parseFloat($row.find('[data-field="kilometros"]').val() || 0),
          kilometros_estimados: parseFloat($row.find('[data-field="kilometros_estimados"]').val() || 0),
          zona_lejana: $row.find('[data-field="zona_lejana"]').is(':checked'),
          observacion: $row.find('[data-field="observacion"]').val()
        });
      });

      if (!isValid) {
        isSaving = false;
        if (isSilent) {
          showAutosaveStatus('invalid');
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error de Validación',
            text: 'Por favor complete todos los campos obligatorios (Fecha y Chofer) marcados en rojo.'
          });
        }
        return;
      }

      if (rows.length === 0 && deletedIds.length === 0) {
        isSaving = false;
        if (isSilent) {
          showAutosaveStatus('saved');
        }
        return;
      }

      if (isSilent) {
        showAutosaveStatus('saving');
      } else {
        // Show save overlay
        Swal.fire({
          title: 'Guardando planilla...',
          text: 'Por favor espere un momento.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
      }

      fetch("{{ route('traffic.planilla-choferes.save') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          rows: rows,
          deleted_ids: deletedIds
        })
      })
      .then(res => {
        if (!res.ok) {
          return res.json().then(err => { throw err; });
        }
        return res.json();
      })
      .then(data => {
        deletedIds = [];
        $dirtyRows.removeClass('is-dirty');

        if (data.saved_rows) {
          data.saved_rows.forEach(item => {
            const $row = $(`#${item.temp_id}`);
            if ($row.length) {
              $row.attr('data-db-id', item.id);
              const $actionCol = $row.find('td').first();
              const $historyBtn = $actionCol.find('button[title="Ver Historial"], button[title="Sin Historial"]');
              if ($historyBtn.length && $historyBtn.prop('disabled')) {
                const logBtnHtml = `
                  <button type="button" class="btn btn-sm btn-link text-secondary p-0" onclick="showLogs(${item.id})" title="Ver Historial" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-history"></i>
                  </button>
                `;
                $historyBtn.replaceWith(logBtnHtml);
              }
            }
          });
        }

        if (isSilent) {
          showAutosaveStatus('saved');
        } else {
          Swal.fire({
            icon: 'success',
            title: 'Guardado',
            text: data.message || 'La planilla se guardó correctamente.',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        }
      })
      .catch(err => {
        const msg = (err && err.message) ? err.message : '';
        if (msg.includes('CSRF') || msg.includes('token mismatch') || msg.includes('Unauthenticated')) {
          if (typeof window.handleSessionExpired === 'function') {
            window.handleSessionExpired();
          }
          return;
        }

        if (isSilent) {
          showAutosaveStatus('error');
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error al Guardar',
            text: msg || 'No se pudo procesar la solicitud en el servidor.'
          });
        }
      })
      .finally(() => {
        isSaving = false;
        if (savePending) {
          savePending = false;
          const nextSilent = pendingSilentMode;
          pendingSilentMode = true;
          saveAll(nextSilent);
        }
      });
    }

    // Make columns sortable like Excel
    $(document).ready(function() {
      // Add visual style & indicators to headers dynamically
      $('.zone-table thead th').each(function(index) {
        if (index === 0) return; // Skip Actions column
        
        $(this).css({
          'cursor': 'pointer',
          'user-select': 'none'
        }).addClass('sortable-header');
        
        // Wrap header text and append sorting icon placeholder
        const text = $(this).text().trim();
        $(this).html(`
          <div class="d-flex align-items-center justify-content-between gap-2">
            <span>${text}</span>
            <span class="sort-icon text-muted opacity-50"><i class="fa-solid fa-sort ms-1" style="font-size: 0.8rem;"></i></span>
          </div>
        `);
      });

      // Handle column header clicks to sort rows
      $(document).on('click', '.zone-table thead th.sortable-header', function() {
        const $th = $(this);
        const $table = $th.closest('.zone-table');
        const $tbody = $table.find('.sheetTableBody');
        const index = $th.index();

        let asc = $th.data('asc');
        if (asc === undefined) asc = true;
        else asc = !asc;
        $th.data('asc', asc);

        // Reset all other headers in this table
        $table.find('thead th.sortable-header').not($th).removeData('asc').find('.sort-icon').html('<i class="fa-solid fa-sort ms-1" style="font-size: 0.8rem;"></i>').addClass('opacity-50').removeClass('text-primary');

        // Update current header's sort icon
        let sortIconHtml = asc
          ? '<i class="fa-solid fa-sort-up ms-1" style="font-size: 0.8rem;"></i>'
          : '<i class="fa-solid fa-sort-down ms-1" style="font-size: 0.8rem;"></i>';
        $th.find('.sort-icon').html(sortIconHtml).removeClass('opacity-50').addClass('text-primary');

        const rows = $tbody.find('tr').get();
        rows.sort(function(a, b) {
          const $a = $(a);
          const $b = $(b);

          let valA = getCellValue($a, index);
          let valB = getCellValue($b, index);

          if (valA === valB) return 0;

          // Numerical comparison
          const numA = Number(valA);
          const numB = Number(valB);
          const isNum = valA !== '' && valB !== '' && !isNaN(numA) && !isNaN(numB);
          if (isNum) {
            return asc ? (numA - numB) : (numB - numA);
          }

          // Lexicographical comparison
          return asc
            ? valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
            : valB.localeCompare(valA, undefined, { numeric: true, sensitivity: 'base' });
        });

        // Re-append sorted rows to the tbody
        $.each(rows, function(idx, row) {
          $tbody.append(row);
        });
      });

      function getCellValue($row, colIndex) {
        const $td = $row.find('td').eq(colIndex);

        // Check for date input
        const $dateInput = $td.find('input[type="date"]');
        if ($dateInput.length > 0) {
          return $dateInput.val() || '';
        }

        // Check for select (we want the text of the selected option, so it sorts alphabetically by name/alias)
        const $select = $td.find('select');
        if ($select.length > 0) {
          return $select.find('option:selected').text().trim() || '';
        }

        // Check for other inputs (number, text)
        const $input = $td.find('input[type="number"], input[type="text"]');
        if ($input.length > 0) {
          return $input.val() || '';
        }

        // Check for checkbox
        const $checkbox = $td.find('input[type="checkbox"]');
        if ($checkbox.length > 0) {
          return $checkbox.is(':checked') ? '1' : '0';
        }

        return $td.text().trim();
      }
    });

    // Keyboard Arrow Navigation (Excel-like navigation)
    function focusTargetInput($targetInput) {
      if (!$targetInput.length) return;
      
      if ($targetInput.hasClass('select2-hidden-accessible')) {
        // Focus the Select2 container selection element
        $targetInput.siblings('.select2-container').find('.select2-selection').focus();
      } else {
        $targetInput.focus();
        // Select text for text and number inputs to make overwriting easy
        if ($targetInput.is('input[type="text"], input[type="number"], input:not([type])')) {
          try {
            $targetInput.select();
          } catch(e) {}
        }
      }
    }

    $(document).on('keydown', '.sheetTableBody input, .sheetTableBody select, .sheetTableBody textarea, .sheetTableBody .select2-selection', function (e) {
      const key = e.which || e.keyCode;
      if (key !== 37 && key !== 38 && key !== 39 && key !== 40) {
        return; // Only arrows
      }

      let $current = $(this);
      
      // If focused on Select2 selection, resolve to the hidden select element
      if ($current.hasClass('select2-selection')) {
        $current = $current.closest('.select2-container').siblings('select');
      }

      // Check if Select2 dropdown is open
      if ($current.hasClass('select2-hidden-accessible')) {
        const $container = $current.siblings('.select2-container');
        if ($container.hasClass('select2-container--open')) {
          return; // Let Select2 handle arrow keys
        }
      }

      const $td = $current.closest('td');
      const $tr = $current.closest('tr');
      const colIndex = $td.index();

      const isTextInput = $current.is('input[type="text"], textarea') || ($current.is('input') && !$current.is('[type="checkbox"], [type="radio"], [type="date"], [type="number"]'));

      if (key === 37) { // Left arrow
        if (isTextInput && $current[0].selectionStart !== 0) {
          return; // Let user edit characters
        }
        const $rowInputs = $tr.find('input:not([type="hidden"]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([readonly]):not([disabled])');
        const currentIndex = $rowInputs.index($current);
        if (currentIndex > 0) {
          e.preventDefault();
          focusTargetInput($rowInputs.eq(currentIndex - 1));
        }
      } 
      else if (key === 39) { // Right arrow
        if (isTextInput) {
          const valLength = $current.val().length;
          if ($current[0].selectionEnd !== valLength) {
            return; // Let user edit characters
          }
        }
        const $rowInputs = $tr.find('input:not([type="hidden"]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([readonly]):not([disabled])');
        const currentIndex = $rowInputs.index($current);
        if (currentIndex !== -1 && currentIndex < $rowInputs.length - 1) {
          e.preventDefault();
          focusTargetInput($rowInputs.eq(currentIndex + 1));
        }
      } 
      else if (key === 38) { // Up arrow
        const $prevTr = $tr.prev('tr');
        if ($prevTr.length) {
          const $targetTd = $prevTr.children().eq(colIndex);
          const $targetInput = $targetTd.find('input:not([type="hidden"]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([readonly]):not([disabled])');
          if ($targetInput.length) {
            e.preventDefault();
            focusTargetInput($targetInput.first());
          }
        }
      } 
      else if (key === 40) { // Down arrow
        const $nextTr = $tr.next('tr');
        if ($nextTr.length) {
          const $targetTd = $nextTr.children().eq(colIndex);
          const $targetInput = $targetTd.find('input:not([type="hidden"]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([readonly]):not([disabled])');
          if ($targetInput.length) {
            e.preventDefault();
            focusTargetInput($targetInput.first());
          }
        }
      }
    });
  })(jQuery);
</script>
@endpush

