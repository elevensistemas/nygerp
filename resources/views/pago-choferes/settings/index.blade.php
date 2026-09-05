@extends('layouts.app')

@section('title', 'Configuracion Pago Choferes')

@section('content')
@php
  $sections = [
    ['id' => 'cronograma', 'number' => '01', 'label' => 'Cronograma', 'description' => 'Periodos de liquidacion y mapeo del importador.', 'count' => $settings->count().' reglas'],
    ['id' => 'zonas', 'number' => '02', 'label' => 'Zonas', 'description' => 'Tipo de calculo y parametros por zona.', 'count' => $zones->count().' zonas'],
    ['id' => 'conceptos', 'number' => '03', 'label' => 'Conceptos', 'description' => 'Catalogo y overrides por zona/vehiculo.', 'count' => $concepts->count().' conceptos'],
    ['id' => 'flotas', 'number' => '04', 'label' => 'Flotas', 'description' => 'Agrupa choferes para liquidarlos juntos.', 'count' => $fleets->count().' flotas'],
    ['id' => 'ajustes', 'number' => '05', 'label' => 'Ajustes', 'description' => 'Sumas y descuentos automaticos.', 'count' => $adjustmentRules->count().' reglas'],
    ['id' => 'tipos-pago', 'number' => '06', 'label' => 'Tipos de pago', 'description' => 'Opciones visibles en planillas.', 'count' => $paymentTypes->count().' tipos'],
    ['id' => 'paquete', 'number' => '07', 'label' => 'Paquete', 'description' => 'Fallback general del calculo por paquete.', 'count' => '$ '.number_format((float) $packageRateDefault, 4, ',', '.')],
    ['id' => 'km', 'number' => '08', 'label' => 'Rangos KM', 'description' => 'Matrices por zona y vehiculo.', 'count' => $kmRanges->count().' rangos'],
  ];
@endphp

<style>
  .driver-settings-shell {
    --ds-border: #dbe3f1;
    --ds-ink: #17324d;
    --ds-muted: #61748a;
    --ds-soft: #f6f9ff;
    --ds-accent: #17324d;
  }
  .driver-settings-hero {
    background: linear-gradient(135deg, #f5f7fb 0%, #eef3ff 100%);
    border: 1px solid var(--ds-border);
    border-radius: 24px;
    padding: 1.5rem;
    margin-bottom: 1rem;
  }
  .driver-settings-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: .75rem;
    margin-top: 1rem;
  }
  .driver-settings-kpi,
  .driver-settings-panel,
  .driver-settings-note {
    border: 1px solid var(--ds-border);
    border-radius: 18px;
    background: #fff;
  }
  .driver-settings-kpi {
    padding: .9rem 1rem;
  }
  .driver-settings-kpi strong {
    display: block;
    color: var(--ds-ink);
    font-size: 1.1rem;
  }
  .driver-settings-tabs-wrap {
    position: sticky;
    top: 84px;
    z-index: 10;
    background: rgba(255, 255, 255, .94);
    backdrop-filter: blur(10px);
    border: 1px solid var(--ds-border);
    border-radius: 22px;
    padding: .75rem;
    margin-bottom: 1rem;
    overflow-x: auto;
  }
  .driver-settings-tabs {
    display: grid;
    grid-template-columns: repeat(8, minmax(170px, 1fr));
    gap: .65rem;
  }
  .driver-settings-tab {
    width: 100%;
    text-align: left;
    border: 1px solid var(--ds-border);
    border-radius: 18px;
    background: #fff;
    color: var(--ds-ink);
    padding: .85rem 1rem;
  }
  .driver-settings-tab.active {
    background: linear-gradient(135deg, #17324d 0%, #215487 100%);
    border-color: #17324d;
    color: #fff;
    box-shadow: 0 14px 28px rgba(23, 50, 77, .16);
  }
  .driver-settings-tab small,
  .driver-settings-tab .tab-meta {
    display: block;
    color: var(--ds-muted);
  }
  .driver-settings-tab.active small,
  .driver-settings-tab.active .tab-meta {
    color: rgba(255, 255, 255, .78);
  }
  .driver-settings-tab .tab-title {
    display: block;
    font-weight: 700;
    margin: .15rem 0;
  }
  .driver-settings-panel {
    display: none;
    overflow: hidden;
    box-shadow: 0 14px 35px rgba(20, 47, 74, .06);
  }
  .driver-settings-panel.active {
    display: block;
  }
  .driver-settings-panel-head {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #fff 0%, var(--ds-soft) 100%);
    border-bottom: 1px solid var(--ds-border);
  }
  .driver-settings-panel-body {
    padding: 1.5rem;
  }
  .driver-settings-subcard {
    border: 1px solid #e8edf5;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(19, 39, 61, .04);
  }
  .driver-settings-subcard .card-header {
    background: #f9fbff;
    border-bottom: 1px solid #e8edf5;
    font-weight: 600;
  }
  .driver-settings-scroller {
    max-height: 360px;
    overflow: auto;
  }
  .driver-settings-note {
    background: #eaf2ff;
    color: #31557d;
    padding: 1rem;
  }
  @media (max-width: 991.98px) {
    .driver-settings-tabs-wrap { top: 72px; }
    .driver-settings-tabs { display: flex; min-width: max-content; }
    .driver-settings-tab { min-width: 220px; }
    .driver-settings-panel-head, .driver-settings-panel-body, .driver-settings-hero { padding: 1rem; }
  }
</style>

<div class="driver-settings-shell">
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Configuracion > Pago Choferes</h1>
      
    </div>
  </div>

  <div class="driver-settings-tabs-wrap">
    <div class="driver-settings-tabs" role="tablist" aria-label="Modulos de configuracion">
      @foreach($sections as $section)
        <button type="button" class="driver-settings-tab{{ $loop->first ? ' active' : '' }}" data-tab-target="{{ $section['id'] }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
          <small>{{ $section['number'] }}</small>
          <span class="tab-title">{{ $section['label'] }}</span>
          <small>{{ $section['description'] }}</small>
          <span class="tab-meta">{{ $section['count'] }}</span>
        </button>
      @endforeach
    </div>
  </div>

  <section id="cronograma" class="driver-settings-panel active" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Cronograma de liquidacion</h2>
      <p class="text-muted mb-0">Define periodos quincenales o mensuales y el mapeo opcional del importador Excel.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="card card-body driver-settings-subcard mb-3">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h3 class="h6 mb-0">Nueva regla</h3>
                <span class="badge text-bg-light">Dias del mes (1-31)</span>
              </div>
              <div class="small text-muted mb-2">
                Las reglas son generales. Solo se define el dia del mes en que empieza y termina cada periodo.
              </div>
              <form method="POST" action="{{ route('pago-choferes.settings.store') }}" class="row g-2">
                @csrf
                <div class="col-md-4">
                  <label class="form-label">Tipo</label>
                  <select name="tipo_periodo" class="form-select" required>
                    <option value="quincenal">Quincenal</option>
                    <option value="mensual">Mensual</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Quincena</label>
                  <select name="quincena" class="form-select">
                    <option value="">-</option>
                    <option value="1">Q1</option>
                    <option value="2">Q2</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Activo</label>
                  <select name="activo" class="form-select">
                    <option value="1">Si</option>
                    <option value="0">No</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Dia desde</label>
                  <input type="number" name="desde" min="1" max="31" class="form-control" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Dia hasta</label>
                  <input type="number" name="hasta" min="1" max="31" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Mapeo columnas JSON (opcional)</label>
                  <textarea name="column_map_json" class="form-control" rows="4" placeholder='{"numero":["numero","nro"]}'>{{ old('column_map_json', json_encode(optional($columnMap)->setting_value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) }}</textarea>
                </div>
                <div class="col-12">
                  <button class="btn btn-primary">Guardar regla</button>
                </div>
              </form>
            </div>

            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Tipo de liquidacion inicial por defecto</h3>
              <p class="small text-muted">Aplica cuando se importa pagos de un chofer que no tiene configurado en su legajo si cobra de manera quincenal o mensual.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.default-period.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                  <label class="form-label mb-1">Periodo por defecto</label>
                  <select name="default_period_type" class="form-select" required>
                    @php $defaultVal = optional($defaultPeriodType)->setting_value ?? 'mensual'; @endphp
                    <option value="quincenal" {{ $defaultVal === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                    <option value="mensual" {{ $defaultVal === 'mensual' ? 'selected' : '' }}>Mensual</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <button class="btn btn-outline-primary">Guardar parametro</button>
                </div>
              </form>
            </div>

            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Modo General de Importación de Logística</h3>
              <p class="small text-muted">Configura el comportamiento general por defecto para las importaciones manuales y automáticas (cron / background) de la Planilla Diaria.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.logistics-import-mode.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                  <label class="form-label mb-1">Modo General</label>
                  <select name="logistics_import_mode" class="form-select" required>
                    @php $logisticsModeVal = optional($logisticsImportMode)->setting_value ?? 'replace'; @endphp
                    <option value="replace" {{ $logisticsModeVal === 'replace' ? 'selected' : '' }}>Reemplazar y reinsertar (Pisar todo lo existente en el rango)</option>
                    <option value="merge" {{ $logisticsModeVal === 'merge' ? 'selected' : '' }}>Mantener y actualizar (Solo agregar lo que falte)</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <button class="btn btn-outline-primary">Guardar parámetro</button>
                </div>
              </form>
            </div>

            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Configuración de Choferes Nuevos</h3>
              <p class="small text-muted">Define la cantidad de días que un chofer es considerado nuevo y el color destacado para pintarlo en los listados.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.new-drivers.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                  <label class="form-label mb-1">Días para ser considerado nuevo</label>
                  <input type="number" name="new_driver_days" class="form-control" min="0" value="{{ optional($newDriverDays)->setting_value ?? 30 }}" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label mb-1">Color de resaltado</label>
                  <div class="d-flex gap-2">
                    <input type="color" name="new_driver_color" class="form-control form-control-color" style="width: 50px; min-width: 50px; height: 38px; padding: 2px;" value="{{ optional($newDriverColor)->setting_value ?? '#28a745' }}" required>
                    <input type="text" class="form-control" value="{{ optional($newDriverColor)->setting_value ?? '#28a745' }}" readonly style="max-width: 100px;">
                  </div>
                </div>
                <div class="col-md-4">
                  <button class="btn btn-outline-primary">Guardar parámetros</button>
                </div>
              </form>
            </div>

            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Alias de tipos de vehículo</h3>
              <p class="small text-muted">Configura los nombres y etiquetas visibles para cada tipo de vehículo en las planillas, recibos y CRUD de transportes.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.vehicle-aliases.store') }}" class="row g-3">
                @csrf
                <div class="col-md-2">
                  <label class="form-label mb-1 fw-semibold">Moto</label>
                  <input type="text" name="aliases[moto]" class="form-control form-control-sm" value="{{ \App\Models\Transporte::paymentVehicleTypes()['moto'] ?? 'Moto' }}" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label mb-1 fw-semibold">Camioneta</label>
                  <input type="text" name="aliases[camioneta]" class="form-control form-control-sm" value="{{ \App\Models\Transporte::paymentVehicleTypes()['camioneta'] ?? 'Camioneta' }}" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label mb-1 fw-semibold">Camioneta mediana</label>
                  <input type="text" name="aliases[camioneta_mediana]" class="form-control form-control-sm" value="{{ \App\Models\Transporte::paymentVehicleTypes()['camioneta_mediana'] ?? 'Camioneta mediana' }}" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label mb-1 fw-semibold">Camioneta grande</label>
                  <input type="text" name="aliases[camioneta_grande]" class="form-control form-control-sm" value="{{ \App\Models\Transporte::paymentVehicleTypes()['camioneta_grande'] ?? 'Camioneta grande' }}" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label mb-1 fw-semibold">General (Otros)</label>
                  <input type="text" name="aliases[general]" class="form-control form-control-sm" value="{{ \App\Models\Transporte::paymentVehicleTypes(true)['general'] ?? 'General' }}" required>
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-primary btn-sm px-3">Guardar alias</button>
                </div>
              </form>
            </div>

            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Mapeo de unidades (Excel a Transporte)</h3>
              <p class="small text-muted">Aplica a la columna "unidad" del Excel importado. Permite tomar un valor textual y asociarlo al tipo de transporte correspondiente en el sistema.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.vehicle-maps.store') }}" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-md-5">
                  <label class="form-label mb-1">Valor en Excel</label>
                  <input type="text" name="excel_value" class="form-control form-control-sm" placeholder="ej. camioneta chica, fiorino, kangoo" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label mb-1">Tipo de transporte sistema</label>
                  <select name="vehicle_type" class="form-select form-select-sm" required>
                    @foreach($vehicleTypes as $vehicleTypeValue => $vehicleTypeLabel)
                      <option value="{{ $vehicleTypeValue }}">{{ $vehicleTypeLabel }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <button class="btn btn-outline-primary btn-sm w-100">Agregar</button>
                </div>
              </form>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="bg-light">
                    <tr id="zone-{{ $zone->id }}">
                      <th>Valor Excel</th>
                      <th>Mapeado a</th>
                      <th class="text-end">Accion</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($vehicleMaps ?? [] as $map)
                    <tr>
                      <td><code>{{ $map->excel_value }}</code></td>
                      <td>{{ $vehicleTypes[$map->vehicle_type] ?? $map->vehicle_type }}</td>
                      <td class="text-end">
                        <form method="POST" action="{{ route('pago-choferes.settings.vehicle-maps.destroy', $map) }}" class="d-inline" data-confirm="Eliminar mapeo de unidad?">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted">No hay mapeos cargados.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card driver-settings-subcard">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span>Reglas existentes</span>
                <span class="small text-muted">{{ $settings->count() }} reglas</span>
              </div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Tipo</th>
                      <th>Dia desde</th>
                      <th>Dia hasta</th>
                      <th>Quincena</th>
                      <th>Activo</th>
                      <th class="text-end">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($settings as $setting)
                    <tr>
                      <td style="width: 130px;">
                        <form id="setting-update-{{ $setting->id }}" method="POST" action="{{ route('pago-choferes.settings.update', $setting) }}">
                          @csrf
                          @method('PUT')
                          <select name="tipo_periodo" class="form-select form-select-sm">
                            <option value="quincenal" {{ $setting->tipo_periodo === 'quincenal' ? 'selected' : '' }}>quincenal</option>
                            <option value="mensual" {{ $setting->tipo_periodo === 'mensual' ? 'selected' : '' }}>mensual</option>
                          </select>
                        </form>
                      </td>
                      <td style="width: 100px;"><input form="setting-update-{{ $setting->id }}" type="number" min="1" max="31" name="desde" value="{{ $setting->desde }}" class="form-control form-control-sm"></td>
                      <td style="width: 100px;"><input form="setting-update-{{ $setting->id }}" type="number" min="1" max="31" name="hasta" value="{{ $setting->hasta }}" class="form-control form-control-sm"></td>
                      <td style="width: 160px;">
                        <select form="setting-update-{{ $setting->id }}" name="quincena" class="form-select form-select-sm">
                          <option value="">-</option>
                          <option value="1" {{ (int) $setting->quincena === 1 ? 'selected' : '' }}>Q1</option>
                          <option value="2" {{ (int) $setting->quincena === 2 ? 'selected' : '' }}>Q2</option>
                        </select>
                      </td>
                      <td style="width: 100px;">
                        <select form="setting-update-{{ $setting->id }}" name="activo" class="form-select form-select-sm">
                          <option value="1" {{ $setting->activo ? 'selected' : '' }}>Si</option>
                          <option value="0" {{ ! $setting->activo ? 'selected' : '' }}>No</option>
                        </select>
                      </td>
                      <td class="text-end" style="width: 180px;">
                        <button form="setting-update-{{ $setting->id }}" class="btn btn-sm btn-outline-primary">Actualizar</button>
                        <form method="POST" action="{{ route('pago-choferes.settings.destroy', $setting) }}" class="d-inline" data-confirm="Eliminar esta regla de cronograma?">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="text-center text-muted">Sin configuraciones.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
    </div>
  </section>

  <section id="zonas" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Zonas y tipo de calculo</h2>
      <p class="text-muted mb-0">Centraliza la estrategia de liquidacion por zona y deja visibles los parametros especiales de KM y paquete.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Alta de zona de liquidacion</h3>
              <p class="small text-muted">El nombre debe coincidir con el nombre de hoja del Excel.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.zones.store') }}" class="row g-2">
                @csrf
                <div class="col-md-6">
                  <label class="form-label">Nombre de zona</label>
                  <input type="text" name="zone_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tipo de calculo</label>
                  <select name="zone_calc_type" class="form-select">
                    <option value="ZONA">ZONA</option>
                    <option value="KM">KM</option>
                    <option value="PAQUETE">PAQUETE</option>
                  </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button class="btn btn-outline-primary w-100">Crear zona</button>
                </div>
              </form>
            </div>

            <div class="card driver-settings-subcard">
              <div class="card-header">Zonas existentes</div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Zona</th>
                      <th style="width: 65%;">Regla</th>
                      <th class="text-end" style="width: 130px;">Accion</th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($zones as $zone)
                    @php $zoneSetting = $zoneSettings[$zone->id] ?? null; @endphp
                    <tr>
                      <td><strong>{{ $zone->name }}</strong></td>
                      <td>
                        <form id="zone-type-{{ $zone->id }}" method="POST" action="{{ route('pago-choferes.settings.zones.type.update', $zone) }}">
                          @csrf
                          @method('PUT')
                          <div class="row g-2">
                            <div class="col-md-3">
                              <label class="form-label small mb-1">Tipo</label>
                              <select name="calc_type" class="form-select form-select-sm">
                                <option value="ZONA" {{ (($zoneSetting->calc_type ?? 'ZONA') === 'ZONA') ? 'selected' : '' }}>ZONA</option>
                                <option value="KM" {{ (($zoneSetting->calc_type ?? 'ZONA') === 'KM') ? 'selected' : '' }}>KM</option>
                                <option value="PAQUETE" {{ (($zoneSetting->calc_type ?? 'ZONA') === 'PAQUETE') ? 'selected' : '' }}>PAQUETE</option>
                              </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                              <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="uses_model_year_values" value="1" id="zone-year-values-{{ $zone->id }}" {{ $zone->uses_model_year_values ? 'checked' : '' }}>
                                <label class="form-check-label small" for="zone-year-values-{{ $zone->id }}">Valores por año</label>
                              </div>
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">Valor paquete gral.</label>
                              <input type="number" step="0.0001" min="0" name="package_rate" class="form-control form-control-sm" value="{{ $zoneSetting ? $zoneSetting->package_rate : '' }}" placeholder="fallback">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">KM plus zona lejana CG</label>
                              <input type="number" step="0.01" min="0" name="km_remote_zone_plus_large" class="form-control form-control-sm" value="{{ optional($zoneSetting)->km_remote_zone_plus_large }}" placeholder="35000">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">Paquete entregado</label>
                              <input type="number" step="0.0001" min="0" name="package_delivered_rate" class="form-control form-control-sm" value="{{ optional($zoneSetting)->package_delivered_rate }}" placeholder="camioneta">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">% ausente</label>
                              <input type="number" step="0.0001" min="0" name="package_absent_rate_multiplier" class="form-control form-control-sm" value="{{ optional($zoneSetting)->package_absent_rate_multiplier }}" placeholder="0.5">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">Fijo camioneta grande</label>
                              <input type="number" step="0.01" min="0" name="package_fixed_amount" class="form-control form-control-sm" value="{{ optional($zoneSetting)->package_fixed_amount }}" placeholder="hasta umbral">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">Umbral excedente</label>
                              <input type="number" min="0" name="package_excess_threshold" class="form-control form-control-sm" value="{{ optional($zoneSetting)->package_excess_threshold }}" placeholder="50">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small mb-1">Valor excedido</label>
                              <input type="number" step="0.01" min="0" name="package_excess_amount" class="form-control form-control-sm" value="{{ optional($zoneSetting)->package_excess_amount }}" placeholder="por paquete">
                            </div>
                          </div>
                        </form>
                      </td>
                      <td class="text-end"><button form="zone-type-{{ $zone->id }}" class="btn btn-sm btn-outline-primary">Guardar</button></td>
                    </tr>
                  @empty
                    <tr><td colspan="3" class="text-center text-muted">Sin zonas.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
    </div>
  </section>

  <section id="conceptos" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Conceptos generales y overrides</h2>
      <p class="text-muted mb-0">Separa el catalogo general de los overrides operativos para mantener una lectura clara de precedencias.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="row g-3">
              <div class="col-12">
                <div class="card card-body driver-settings-subcard">
                  <h3 class="h6">Catalogo de conceptos</h3>
                  <p class="small text-muted">Cada concepto puede ser fijo o calcularse como referencia de otro concepto con coeficiente. Aplica a recibos ZONA, KM y PAQUETE.</p>
                  <form method="POST" action="{{ route('pago-choferes.settings.concepts.store') }}" class="row g-2 mb-2">
                    @csrf
                    <div class="col-md-6">
                      <label class="form-label">Nuevo concepto</label>
                      <input type="text" name="concept_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Tipo valor</label>
                      <select name="value_type" class="form-select js-value-type" data-target-prefix="concept-create" required>
                        <option value="fixed">Fijo</option>
                        <option value="reference">Referencia</option>
                      </select>
                    </div>
                    <div class="col-md-3 js-value-fixed" data-target="concept-create">
                      <label class="form-label">Valor default</label>
                      <input type="number" name="default_amount" class="form-control" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Impacto en recibo</label>
                      <select name="sign" class="form-select">
                        <option value="1">Suma</option>
                        <option value="-1">Resta</option>
                      </select>
                    </div>
                    <div class="col-md-8 js-value-reference d-none" data-target="concept-create">
                      <label class="form-label">Concepto base</label>
                      <select name="reference_concept_id" class="form-select">
                        <option value="">Seleccionar...</option>
                        @foreach($concepts as $concept)
                          <option value="{{ $concept->id }}">{{ $concept->name }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="col-md-4 js-value-reference d-none" data-target="concept-create">
                      <label class="form-label">Coeficiente</label>
                      <input type="number" name="reference_multiplier" class="form-control" min="0.0001" step="0.0001" value="1">
                    </div>
                    <div class="col-12"><button class="btn btn-outline-primary">Crear concepto</button></div>
                  </form>
                  <hr>
                  <div class="driver-settings-scroller">
                    @forelse($concepts as $concept)
                      <div class="border rounded p-2 mb-1" id="concept-{{ $concept->id }}">
                        <form method="POST" action="{{ route('pago-choferes.settings.concepts.update', $concept) }}" class="row g-2 align-items-end">
                          @csrf
                          @method('PUT')
                          <div class="col-md-4">
                            <label class="form-label small mb-1">Nombre</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $concept->name }}" required>
                          </div>
                          <div class="col-md-3">
                            <label class="form-label small mb-1">Tipo valor</label>
                            <select name="value_type" class="form-select form-select-sm js-value-type" data-target-prefix="concept-edit-{{ $concept->id }}">
                              <option value="fixed" {{ ($concept->value_type ?? 'fixed') === 'fixed' ? 'selected' : '' }}>Fijo</option>
                              <option value="reference" {{ ($concept->value_type ?? 'fixed') === 'reference' ? 'selected' : '' }}>Referencia</option>
                            </select>
                          </div>
                          <div class="col-md-2 js-value-fixed {{ ($concept->value_type ?? 'fixed') === 'reference' ? 'd-none' : '' }}" data-target="concept-edit-{{ $concept->id }}">
                            <label class="form-label small mb-1">Valor</label>
                            <input type="number" step="0.01" min="0" name="default_amount" class="form-control form-control-sm" value="{{ $concept->default_amount }}">
                          </div>
                          <div class="col-md-2">
                            <label class="form-label small mb-1">Impacto</label>
                            <select name="sign" class="form-select form-select-sm">
                              <option value="1" {{ (int) ($concept->sign ?? 1) === 1 ? 'selected' : '' }}>Suma</option>
                              <option value="-1" {{ (int) ($concept->sign ?? 1) === -1 ? 'selected' : '' }}>Resta</option>
                            </select>
                          </div>
                          <div class="col-md-2 js-value-reference {{ ($concept->value_type ?? 'fixed') === 'reference' ? '' : 'd-none' }}" data-target="concept-edit-{{ $concept->id }}">
                            <label class="form-label small mb-1">Base</label>
                            <select name="reference_concept_id" class="form-select form-select-sm">
                              <option value="">Seleccionar...</option>
                              @foreach($concepts as $referenceConcept)
                                @if((int) $referenceConcept->id !== (int) $concept->id)
                                  <option value="{{ $referenceConcept->id }}" {{ (int) $concept->reference_concept_id === (int) $referenceConcept->id ? 'selected' : '' }}>{{ $referenceConcept->name }}</option>
                                @endif
                              @endforeach
                            </select>
                          </div>
                          <div class="col-md-1 js-value-reference {{ ($concept->value_type ?? 'fixed') === 'reference' ? '' : 'd-none' }}" data-target="concept-edit-{{ $concept->id }}">
                            <label class="form-label small mb-1">Coef.</label>
                            <input type="number" step="0.0001" min="0.0001" name="reference_multiplier" class="form-control form-control-sm" value="{{ $concept->reference_multiplier !== null ? number_format((float) $concept->reference_multiplier, 4, '.', '') : '1.0000' }}">
                          </div>
                          <div class="col-md-2">
                            <label class="form-label small mb-1">Activo</label>
                            <select name="active" class="form-select form-select-sm">
                              <option value="1" {{ $concept->active ? 'selected' : '' }}>Si</option>
                              <option value="0" {{ ! $concept->active ? 'selected' : '' }}>No</option>
                            </select>
                          </div>
                          <div class="col-12 d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                              @if(($concept->value_type ?? 'fixed') === 'reference')
                                Referencia: {{ optional($concept->referenceConcept)->name ?: '-' }} x {{ number_format((float) ($concept->reference_multiplier ?? 1), 4, ',', '.') }}
                              @else
                                Valor general: $ {{ number_format((float) $concept->default_amount, 2, ',', '.') }}
                              @endif
                              | {{ (int) ($concept->sign ?? 1) === -1 ? 'Resta del total' : 'Suma al total' }}
                            </div>
                            <button class="btn btn-sm btn-outline-primary">Guardar</button>
                          </div>
                        </form>
                        <form method="POST" action="{{ route('pago-choferes.settings.concepts.destroy', $concept) }}" class="mt-1" data-confirm="Eliminar concepto?">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </div>
                    @empty
                      <div class="small text-muted">Sin conceptos.</div>
                    @endforelse
                  </div>
                </div>
              </div>

              @include('pago-choferes.settings.pricing-grid')
            </div>
    </div>
  </section>

  <section id="flotas" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Flotas de choferes</h2>
      <p class="text-muted mb-0">Permite agrupar varios choferes para generar una sola liquidacion y planilla a nombre de la flota.</p>
    </div>
    <div class="driver-settings-panel-body">
      <div class="card card-body driver-settings-subcard mb-3">
        <h3 class="h6">Nueva flota</h3>
        <p class="small text-muted">El chofer de cobro se usa como titular del recibo y de la planilla cuando la flota se liquida en conjunto.</p>
        <form method="POST" action="{{ route('pago-choferes.settings.fleets.store') }}" class="row g-2">
          @csrf
          <div class="col-md-4">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Chofer de cobro</label>
            <select name="billing_transportista_id" class="form-select">
              <option value="">Seleccionar...</option>
              @foreach($transportistas as $transportista)
                <option value="{{ $transportista->id }}">{{ $transportista->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Activo</label>
            <select name="active" class="form-select">
              <option value="1">Si</option>
              <option value="0">No</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Choferes incluidos</label>
            <select name="transportista_ids[]" class="form-select" multiple size="8" required>
              @foreach($transportistas as $transportista)
                <option value="{{ $transportista->id }}">{{ $transportista->name }}</option>
              @endforeach
            </select>
            <div class="small text-muted mt-1">Usa Ctrl/Cmd para seleccionar varios.</div>
          </div>
          <div class="col-12">
            <button class="btn btn-outline-primary">Crear flota</button>
          </div>
        </form>
      </div>

      <div class="card driver-settings-subcard">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Flotas existentes</span>
          <span class="small text-muted">{{ $fleets->count() }} flotas</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Flota</th>
                <th>Choferes</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
            @forelse($fleets as $fleet)
              <tr>
                <td style="min-width: 260px;">
                  <form id="fleet-update-{{ $fleet->id }}" method="POST" action="{{ route('pago-choferes.settings.fleets.update', $fleet) }}" class="row g-2">
                    @csrf
                    @method('PUT')
                    <div class="col-12">
                      <input type="text" name="name" class="form-control form-control-sm" value="{{ $fleet->name }}" required>
                    </div>
                    <div class="col-12">
                      <select name="billing_transportista_id" class="form-select form-select-sm">
                        <option value="">Chofer de cobro...</option>
                        @foreach($transportistas as $transportista)
                          <option value="{{ $transportista->id }}" {{ (int) optional($fleet->billingTransportista)->id === (int) $transportista->id ? 'selected' : '' }}>{{ $transportista->name }}</option>
                        @endforeach
                      </select>
                    </div>
                  </form>
                </td>
                <td style="min-width: 320px;">
                  <select form="fleet-update-{{ $fleet->id }}" name="transportista_ids[]" class="form-select form-select-sm" multiple size="7" required>
                    @php $memberIds = $fleet->transportistas->pluck('id')->map(fn ($id) => (int) $id)->all(); @endphp
                    @foreach($transportistas as $transportista)
                      <option value="{{ $transportista->id }}" {{ in_array((int) $transportista->id, $memberIds, true) ? 'selected' : '' }}>{{ $transportista->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td style="width: 120px;">
                  <select form="fleet-update-{{ $fleet->id }}" name="active" class="form-select form-select-sm">
                    <option value="1" {{ $fleet->active ? 'selected' : '' }}>Si</option>
                    <option value="0" {{ ! $fleet->active ? 'selected' : '' }}>No</option>
                  </select>
                </td>
                <td class="text-end" style="width: 180px;">
                  <button form="fleet-update-{{ $fleet->id }}" class="btn btn-sm btn-outline-primary">Guardar</button>
                  <form method="POST" action="{{ route('pago-choferes.settings.fleets.destroy', $fleet) }}" class="d-inline" data-confirm="Eliminar flota?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">Sin flotas configuradas.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <section id="ajustes" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Ajustes y descuentos automaticos</h2>
      <p class="text-muted mb-0">Reglas aplicadas una vez por recibo si coinciden chofer, zona y vehiculo.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Nueva regla</h3>
              <p class="small text-muted">Se aplica una vez por recibo si coincide con el chofer y al menos un item de la zona/tipo indicados. Usa signo negativo para descuentos.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.adjustments.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                  <label class="form-label">Concepto</label>
                  <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Monto</label>
                  <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Signo</label>
                  <select name="sign" class="form-select">
                    <option value="-1">Descuento</option>
                    <option value="1">Suma</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Zona</label>
                  <select name="traffic_zone_id" class="form-select">
                    <option value="">General</option>
                    @foreach($zones as $zone)
                      <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Chofer</label>
                  <select name="transportista_id" class="form-select">
                    <option value="">General</option>
                    @foreach($transportistas as $transportista)
                      <option value="{{ $transportista->id }}">{{ $transportista->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Vehiculo</label>
                  <select name="vehicle_type" class="form-select">
                    @foreach($vehicleTypes as $vehicleTypeValue => $vehicleTypeLabel)
                      <option value="{{ $vehicleTypeValue }}">{{ $vehicleTypeLabel }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-7">
                  <label class="form-label">Notas</label>
                  <input type="text" name="notes" class="form-control">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Activo</label>
                  <select name="active" class="form-select">
                    <option value="1">Si</option>
                    <option value="0">No</option>
                  </select>
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-primary">Guardar ajuste</button>
                </div>
              </form>
            </div>

            <div class="card driver-settings-subcard">
              <div class="card-header">Reglas existentes</div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Concepto</th>
                      <th>Ambito</th>
                      <th>Monto</th>
                      <th class="text-end">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($adjustmentRules as $rule)
                    <tr>
                      <td>
                        <form id="adjustment-rule-{{ $rule->id }}" method="POST" action="{{ route('pago-choferes.settings.adjustments.update', $rule) }}" class="row g-2">
                          @csrf
                          @method('PUT')
                          <div class="col-12">
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $rule->name }}" required>
                          </div>
                          <div class="col-12">
                            <input type="text" name="notes" class="form-control form-control-sm" value="{{ $rule->notes }}" placeholder="Notas">
                          </div>
                        </form>
                      </td>
                      <td>
                        <div class="row g-2">
                          <div class="col-md-4">
                            <select form="adjustment-rule-{{ $rule->id }}" name="traffic_zone_id" class="form-select form-select-sm">
                              <option value="">Zona general</option>
                              @foreach($zones as $zone)
                                <option value="{{ $zone->id }}" {{ (int) $rule->traffic_zone_id === (int) $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                              @endforeach
                            </select>
                          </div>
                          <div class="col-md-4">
                            <select form="adjustment-rule-{{ $rule->id }}" name="transportista_id" class="form-select form-select-sm">
                              <option value="">Chofer general</option>
                              @foreach($transportistas as $transportista)
                                <option value="{{ $transportista->id }}" {{ (int) $rule->transportista_id === (int) $transportista->id ? 'selected' : '' }}>{{ $transportista->name }}</option>
                              @endforeach
                            </select>
                          </div>
                          <div class="col-md-4">
                            <select form="adjustment-rule-{{ $rule->id }}" name="vehicle_type" class="form-select form-select-sm">
                              @foreach($vehicleTypes as $vehicleTypeValue => $vehicleTypeLabel)
                                <option value="{{ $vehicleTypeValue }}" {{ ($rule->vehicle_type ?? 'general') === $vehicleTypeValue ? 'selected' : '' }}>{{ $vehicleTypeLabel }}</option>
                              @endforeach
                            </select>
                          </div>
                        </div>
                      </td>
                      <td style="min-width: 210px;">
                        <div class="row g-2">
                          <div class="col-md-5">
                            <input form="adjustment-rule-{{ $rule->id }}" type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" value="{{ $rule->amount }}">
                          </div>
                          <div class="col-md-4">
                            <select form="adjustment-rule-{{ $rule->id }}" name="sign" class="form-select form-select-sm">
                              <option value="-1" {{ (int) $rule->sign === -1 ? 'selected' : '' }}>Desc.</option>
                              <option value="1" {{ (int) $rule->sign === 1 ? 'selected' : '' }}>Suma</option>
                            </select>
                          </div>
                          <div class="col-md-3">
                            <select form="adjustment-rule-{{ $rule->id }}" name="active" class="form-select form-select-sm">
                              <option value="1" {{ $rule->active ? 'selected' : '' }}>Si</option>
                              <option value="0" {{ ! $rule->active ? 'selected' : '' }}>No</option>
                            </select>
                          </div>
                        </div>
                      </td>
                      <td class="text-end">
                        <button form="adjustment-rule-{{ $rule->id }}" class="btn btn-sm btn-outline-primary">Guardar</button>
                        <form method="POST" action="{{ route('pago-choferes.settings.adjustments.destroy', $rule) }}" class="d-inline" data-confirm="Eliminar ajuste automatico?">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="4" class="text-center text-muted">Sin ajustes configurados.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
    </div>
  </section>

  <section id="tipos-pago" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Tipos de pago para planillas</h2>
      <p class="text-muted mb-0">Administra las opciones visibles en la planilla y su impacto pagado o no pagado.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="row g-3">
              <div class="col-lg-5">
                <div class="card card-body driver-settings-subcard h-100">
                  <h3 class="h6">Nuevo tipo de pago</h3>
                  <p class="small text-muted">La descripcion se muestra en el combo de la planilla. El campo tipo define si impacta como pagado o no pagado.</p>
                  <form method="POST" action="{{ route('pago-choferes.settings.payment-types.store') }}" class="row g-2">
                    @csrf
                    <div class="col-12">
                      <label class="form-label">Descripcion</label>
                      <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Type</label>
                      <select name="type" class="form-select" required>
                        <option value="1">Pagado</option>
                        <option value="0">No pagado</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-outline-primary">Crear tipo</button>
                    </div>
                  </form>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="card card-body driver-settings-subcard h-100">
                  <h3 class="h6">Tipos existentes</h3>
                  <div class="driver-settings-scroller">
                    @forelse($paymentTypes as $paymentType)
                      <div class="border rounded p-2 mb-2">
                        <form method="POST" action="{{ route('pago-choferes.settings.payment-types.update', $paymentType) }}" class="row g-2 align-items-end">
                          @csrf
                          @method('PUT')
                          <div class="col-md-7">
                            <label class="form-label">Descripcion</label>
                            <input type="text" name="description" class="form-control form-control-sm" value="{{ $paymentType->description }}" required>
                          </div>
                          <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select form-select-sm">
                              <option value="1" {{ $paymentType->type ? 'selected' : '' }}>Pagado</option>
                              <option value="0" {{ ! $paymentType->type ? 'selected' : '' }}>No pagado</option>
                            </select>
                          </div>
                          <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-primary w-100">Guardar</button>
                          </div>
                        </form>
                        <form method="POST" action="{{ route('pago-choferes.settings.payment-types.destroy', $paymentType) }}" class="mt-2" data-confirm="Eliminar tipo de pago?">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </div>
                    @empty
                      <div class="small text-muted">Sin tipos de pago.</div>
                    @endforelse
                  </div>
                </div>
              </div>
            </div>
    </div>
  </section>

  <section id="paquete" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Calculo por PAQUETE</h2>
      <p class="text-muted mb-0">Configura el valor fallback general. Los refinamientos por tipo de vehiculo siguen viviendo dentro de cada zona.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="card card-body driver-settings-subcard">
              <h3 class="h6">Valor general por paquete</h3>
              <p class="small text-muted">Se usa como fallback. Las reglas finas de camioneta/camioneta grande se cargan en cada zona.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.package-rate-default.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                  <label class="form-label">Valor general</label>
                  <input type="number" name="package_rate_default" step="0.0001" min="0" class="form-control" value="{{ $packageRateDefault }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button class="btn btn-outline-primary w-100">Guardar</button>
                </div>
              </form>
            </div>
    </div>
  </section>

  <section id="km" class="driver-settings-panel" role="tabpanel">
    <div class="driver-settings-panel-head">
      <h2 class="h4 mb-1">Calculo por KM</h2>
      <p class="text-muted mb-0">Matriz de rangos con prioridad por zona y tipo de vehiculo; luego fallback global.</p>
    </div>
    <div class="driver-settings-panel-body">
            <div class="card card-body driver-settings-subcard mb-3">
              <h3 class="h6">Moto: paquete excedido</h3>
              <p class="small text-muted">Cuando una moto supera el umbral de paquetes, la importacion suma este adicional por cada paquete excedido.</p>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Zona</th>
                      <th>Umbral paquetes</th>
                      <th>Valor por paquete excedido</th>
                      <th class="text-end"></th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($zones as $zone)
                    @php $zoneSetting = $zoneSettings[$zone->id] ?? null; @endphp
                    <tr>
                      <td>{{ $zone->name }}</td>
                      <td>
                        <form id="zone-km-excess-{{ $zone->id }}" method="POST" action="{{ route('pago-choferes.settings.zones.km-excess.update', $zone) }}" class="d-none">
                          @csrf
                          @method('PUT')
                        </form>
                        <input form="zone-km-excess-{{ $zone->id }}" type="number" min="0" name="km_package_threshold" class="form-control form-control-sm" value="{{ optional($zoneSetting)->km_package_threshold }}" placeholder="30">
                      </td>
                      <td>
                        <input form="zone-km-excess-{{ $zone->id }}" type="number" step="0.01" min="0" name="km_excess_package_amount" class="form-control form-control-sm" value="{{ optional($zoneSetting)->km_excess_package_amount }}" placeholder="0,00">
                      </td>
                      <td class="text-end">
                        <button form="zone-km-excess-{{ $zone->id }}" class="btn btn-sm btn-outline-primary">Guardar</button>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="4" class="text-center text-muted">Sin zonas configuradas.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card card-body driver-settings-subcard">
              <h3 class="h6">Tabla de rangos KM</h3>
              <p class="small text-muted">El valor del rango se interpreta como monto fijo de la matriz. Se prioriza zona + vehiculo; luego global.</p>
              <form method="POST" action="{{ route('pago-choferes.settings.km-ranges.store') }}" class="row g-2 mb-2">
                @csrf
                <div class="col-md-3">
                  <label class="form-label">Zona (opcional)</label>
                  <select name="traffic_zone_id" class="form-select">
                    <option value="">Global</option>
                    @foreach($zones as $zone)
                      <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Vehiculo</label>
                  <select name="vehicle_type" class="form-select" required>
                    @foreach($vehicleTypes as $vehicleTypeValue => $vehicleTypeLabel)
                      <option value="{{ $vehicleTypeValue }}">{{ $vehicleTypeLabel }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Desde KM</label>
                  <input type="number" step="0.001" min="0" name="km_from" class="form-control" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Hasta KM</label>
                  <input type="number" step="0.001" min="0" name="km_to" class="form-control" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Valor</label>
                  <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button class="btn btn-outline-primary w-100">Agregar</button>
                </div>
              </form>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Ambito</th>
                      <th>Vehiculo</th>
                      <th>KM desde</th>
                      <th>KM hasta</th>
                      <th>Valor</th>
                      <th class="text-end"></th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($kmRanges as $range)
                    <tr>
                      <td>{{ optional($range->zone)->name ?: 'Global' }}</td>
                      <td>{{ $vehicleTypes[$range->vehicle_type ?? 'general'] ?? ucfirst(str_replace('_', ' ', $range->vehicle_type ?? 'general')) }}</td>
                      <td>{{ $range->km_from }}</td>
                      <td>{{ $range->km_to }}</td>
                      <td>$ {{ number_format((float) $range->amount, 2, ',', '.') }}</td>
                      <td class="text-end">
                        <form method="POST" action="{{ route('pago-choferes.settings.km-ranges.destroy', $range) }}" data-confirm="Eliminar rango KM?">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="text-center text-muted">Sin rangos configurados.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
    </div>
  </section>
</div>

<script>
  (function () {
    const tabs = Array.from(document.querySelectorAll('[data-tab-target]'));
    const panels = Array.from(document.querySelectorAll('.driver-settings-panel'));
    const tabsWrap = document.querySelector('.driver-settings-tabs-wrap');

    const activateTab = (targetId, updateHash = true) => {
      const nextPanel = document.getElementById(targetId);
      if (!nextPanel) {
        return;
      }

      tabs.forEach((tab) => {
        const isActive = tab.dataset.tabTarget === targetId;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        panel.classList.toggle('active', panel.id === targetId);
      });

      if (updateHash) {
        window.history.replaceState(null, '', '#' + targetId);
      }

      if (tabsWrap && window.innerWidth < 992) {
        tabs.find((tab) => tab.dataset.tabTarget === targetId)?.scrollIntoView({
          behavior: 'smooth',
          inline: 'center',
          block: 'nearest',
        });
      }

      const jq = window.jQuery;
      if (jq && jq.fn.select2) {
        jq(nextPanel).find('select:not(.no-select2)').each(function() {
          if (!jq(this).data('select2')) {
            jq(this).select2({ width: '100%' });
          }
        });
      }
    };

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => activateTab(tab.dataset.tabTarget));
    });

    const initializeActivePanelSelects = () => {
      const jq = window.jQuery;
      if (!(jq && jq.fn.select2)) {
        return;
      }

      jq('.driver-settings-panel.active').find('select:not(.no-select2)').each(function() {
        if (!jq(this).data('select2')) {
          jq(this).select2({ width: '100%' });
        }
      });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initializeActivePanelSelects, { once: true });
    } else {
      initializeActivePanelSelects();
    }

    const initialHash = window.location.hash.replace('#', '');
    if (initialHash) {
      activateTab(initialHash, false);
    }

    window.addEventListener('hashchange', () => {
      const hash = window.location.hash.replace('#', '');
      if (hash) {
        activateTab(hash, false);
      }
    });

    const applyFocusTarget = () => {
      const params = new URLSearchParams(window.location.search);
      const focus = params.get('focus');
      if (!focus) return;

      const target = document.getElementById(focus);
      if (!target) return;

      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      target.classList.add('border-primary', 'shadow-sm');
      setTimeout(() => target.classList.remove('border-primary', 'shadow-sm'), 2600);
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', applyFocusTarget, { once: true });
    } else {
      applyFocusTarget();
    }

    document.querySelectorAll('.js-value-type').forEach((select) => {
      const prefix = select.dataset.targetPrefix;
      const sync = () => {
        const isReference = select.value === 'reference';
        document.querySelectorAll('.js-value-fixed[data-target="' + prefix + '"]').forEach((el) => {
          el.classList.toggle('d-none', isReference);
        });
        document.querySelectorAll('.js-value-reference[data-target="' + prefix + '"]').forEach((el) => {
          el.classList.toggle('d-none', !isReference);
        });
      };

      select.addEventListener('change', sync);
      sync();
    });

    // --- Logica original para modales manuales
    const conceptSelect = document.querySelector('select[name="driver_payment_concept_id"]');
    const amountInput = document.querySelector('input[name="monto_efectivo"]');
    const typeSelect = document.querySelector('form[action$="/configuracion/relaciones"] select[name="value_type"]');

    const applyDefault = () => {
      if (!conceptSelect || !amountInput || !typeSelect || typeSelect.value !== 'fixed') {
        return;
      }
      const selected = conceptSelect.options[conceptSelect.selectedIndex];
      if (!selected) return;
      const defaultAmount = selected.getAttribute('data-default-amount');
      if (amountInput.value === '' && defaultAmount !== null && defaultAmount !== '') {
        amountInput.value = defaultAmount;
      }
    };

    conceptSelect?.addEventListener('change', applyDefault);
    typeSelect?.addEventListener('change', applyDefault);
    applyDefault();
  })();
</script>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script>
  const { createApp, ref, computed, watch, onMounted } = Vue;

  const initialZoneConcepts = @json($zoneConcepts);
  const initialZoneConceptYearValues = @json($zoneConceptYearValues);
  const zonesRaw = @json($zones);
  const conceptsRaw = @json($concepts);
  const vehicleTypesRaw = @json($vehicleTypes);
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const app = createApp({
    setup() {
      const zoneConcepts = ref(initialZoneConcepts);
      const zoneConceptYearValues = ref(initialZoneConceptYearValues);
      const zones = ref(zonesRaw.map(z => ({ id: z.id, name: z.name, uses_model_year_values: !!z.uses_model_year_values })));
      const concepts = ref(conceptsRaw.map(c => ({ id: c.id, name: c.name })));
      const vehicleTypes = ref(Object.keys(vehicleTypesRaw).map(k => ({ id: k, name: vehicleTypesRaw[k] })));

      const groupBy = ref('zone');
      const groupValue = ref('');
      const saveStatus = ref('idle');
      const yearModalContext = ref(null);
      const yearModalRows = ref([]);
      const yearForm = ref({ model_year: '', amount: '' });
      const cellDrafts = ref({});
      const savingCells = ref({});
      const skippedCommits = ref({});
      let statusTimer = null;
      let yearValuesModal = null;

      const cellKey = (dims) => [dims.traffic_zone_id, dims.driver_payment_concept_id, dims.vehicle_type].map(String).join('::');
      const cloneState = (state) => ({ ...state });

      const zoneIndex = computed(() => {
        const index = new Map();
        zones.value.forEach((zone) => {
          index.set(String(zone.id), zone);
        });
        return index;
      });

      const conceptIndex = computed(() => {
        const index = new Map();
        concepts.value.forEach((concept) => {
          index.set(String(concept.id), concept);
        });
        return index;
      });

      const vehicleIndex = computed(() => {
        const index = new Map();
        vehicleTypes.value.forEach((vehicle) => {
          index.set(String(vehicle.id), vehicle);
        });
        return index;
      });

      const zoneConceptIndex = computed(() => {
        const index = new Map();
        zoneConcepts.value.forEach((zoneConcept) => {
          index.set(cellKey(zoneConcept), zoneConcept);
        });
        return index;
      });

      const yearValuesIndex = computed(() => {
        const index = new Map();
        zoneConceptYearValues.value.forEach((row) => {
          const key = cellKey(row);
          if (!index.has(key)) {
            index.set(key, []);
          }
          index.get(key).push(row);
        });

        index.forEach((rows, key) => {
          index.set(key, rows.slice().sort((a, b) => Number(a.model_year) - Number(b.model_year)));
        });

        return index;
      });

      const groupLabel = computed(() => {
        if(groupBy.value === 'zone') return 'Zona';
        if(groupBy.value === 'concept') return 'Concepto';
        return 'Tipo de Transporte';
      });

      const groupOptions = computed(() => {
        if(groupBy.value === 'zone') return zones.value;
        if(groupBy.value === 'concept') return concepts.value;
        return vehicleTypes.value;
      });

      watch(groupBy, () => {
        groupValue.value = groupOptions.value[0]?.id || '';
      });

      const ensureYearValuesModal = () => {
        const modalElement = document.getElementById('zoneConceptYearValuesModal');
        if (!modalElement || !window.bootstrap?.Modal) {
          return null;
        }

        yearValuesModal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        return yearValuesModal;
      };

      onMounted(() => {
        if(groupOptions.value.length > 0) {
          groupValue.value = groupOptions.value[0].id;
        }

        ensureYearValuesModal();
      });

      const rowLabel = computed(() => {
        if(groupBy.value === 'zone') return 'Concepto';
        if(groupBy.value === 'concept') return 'Zona';
        return 'Zona';
      });

      const colLabel = computed(() => {
        if(groupBy.value === 'zone') return 'Transporte';
        if(groupBy.value === 'concept') return 'Transporte';
        return 'Concepto';
      });

      const rows = computed(() => {
        if(groupBy.value === 'zone') return concepts.value;
        if(groupBy.value === 'concept') return zones.value;
        return zones.value;
      });

      const cols = computed(() => {
        if(groupBy.value === 'zone') return vehicleTypes.value;
        if(groupBy.value === 'concept') return vehicleTypes.value;
        return concepts.value;
      });

      const extractDimensions = (rowId, colId) => {
        let zId, cId, vId;
        if(groupBy.value === 'zone') {
          zId = groupValue.value;
          cId = rowId;
          vId = colId;
        } else if(groupBy.value === 'concept') {
          cId = groupValue.value;
          zId = rowId;
          vId = colId;
        } else {
          vId = groupValue.value;
          zId = rowId;
          cId = colId;
        }
        return { traffic_zone_id: zId, driver_payment_concept_id: cId, vehicle_type: vId };
      };

      const getStoredCellRecord = (rowId, colId) => {
        const dims = extractDimensions(rowId, colId);
        return zoneConceptIndex.value.get(cellKey(dims)) || null;
      };

      const getCellValue = (rowId, colId) => {
        const found = getStoredCellRecord(rowId, colId);
        return found && found.value_type === 'fixed' ? String(Number(found.monto_efectivo)) : '';
      };

      const getDisplayCellValue = (rowId, colId) => {
        const dims = extractDimensions(rowId, colId);
        const key = cellKey(dims);
        if (Object.prototype.hasOwnProperty.call(cellDrafts.value, key)) {
          return cellDrafts.value[key];
        }
        return getCellValue(rowId, colId);
      };

      const cellHasValue = (rowId, colId) => getDisplayCellValue(rowId, colId) !== '';

      const startCellEdit = (rowId, colId) => {
        const dims = extractDimensions(rowId, colId);
        const key = cellKey(dims);
        if (!Object.prototype.hasOwnProperty.call(cellDrafts.value, key)) {
          cellDrafts.value = {
            ...cellDrafts.value,
            [key]: getCellValue(rowId, colId),
          };
        }
      };

      const updateCellDraft = (rowId, colId, value) => {
        const dims = extractDimensions(rowId, colId);
        const key = cellKey(dims);
        cellDrafts.value = {
          ...cellDrafts.value,
          [key]: value,
        };
      };

      const clearCellDraft = (rowId, colId) => {
        const dims = extractDimensions(rowId, colId);
        const key = cellKey(dims);
        if (!Object.prototype.hasOwnProperty.call(cellDrafts.value, key)) {
          return;
        }

        const nextDrafts = cloneState(cellDrafts.value);
        delete nextDrafts[key];
        cellDrafts.value = nextDrafts;
      };

      const cancelCellEdit = (rowId, colId, target = null) => {
        const dims = extractDimensions(rowId, colId);
        const key = cellKey(dims);
        skippedCommits.value = {
          ...skippedCommits.value,
          [key]: true,
        };
        clearCellDraft(rowId, colId);
        if (target) {
          target.value = getCellValue(rowId, colId);
          target.blur();
        }
      };

      const isCellSaving = (rowId, colId) => {
        const dims = extractDimensions(rowId, colId);
        return !!savingCells.value[cellKey(dims)];
      };

      const setCellSaving = (dims, saving) => {
        const key = cellKey(dims);
        const nextSavingCells = cloneState(savingCells.value);

        if (saving) {
          nextSavingCells[key] = true;
        } else {
          delete nextSavingCells[key];
        }

        savingCells.value = nextSavingCells;
      };

      const cellUsesModelYear = (rowId, colId) => {
        const d = extractDimensions(rowId, colId);
        const zone = zoneIndex.value.get(String(d.traffic_zone_id));
        return !!zone?.uses_model_year_values;
      };

      const getYearRowsForCell = (rowId, colId) => {
        const d = extractDimensions(rowId, colId);
        return yearValuesIndex.value.get(cellKey(d)) || [];
      };

      const getYearValueSummary = (rowId, colId) => {
        const rows = getYearRowsForCell(rowId, colId);
        if (rows.length === 0) {
          return 'Sin valores cargados';
        }
        if (rows.length === 1) {
          return rows[0].model_year + ': $ ' + formatAmount(rows[0].amount);
        }
        return rows.length + ' años cargados';
      };

      const formatAmount = (amount) => Number(amount || 0).toFixed(2).replace('.', ',');

      const upsertLocalCellValue = (dims, amount) => {
        const existing = zoneConceptIndex.value.get(cellKey(dims));

        if (existing) {
          existing.monto_efectivo = amount;
          existing.value_type = 'fixed';
          existing.active = 1;
          return;
        }

        zoneConcepts.value.push({
          traffic_zone_id: dims.traffic_zone_id,
          driver_payment_concept_id: dims.driver_payment_concept_id,
          vehicle_type: dims.vehicle_type,
          monto_efectivo: amount,
          value_type: 'fixed',
          active: 1
        });
      };

      const commitCell = async (rowId, colId, value) => {
        const val = value.trim();
        const numVal = val === '' ? 0 : parseFloat(val);
        const d = extractDimensions(rowId, colId);
        const key = cellKey(d);
        const currentRecord = zoneConceptIndex.value.get(key);

        if (skippedCommits.value[key]) {
          const nextSkippedCommits = cloneState(skippedCommits.value);
          delete nextSkippedCommits[key];
          skippedCommits.value = nextSkippedCommits;
          return;
        }

        if (Number.isNaN(numVal) || numVal < 0) {
          if(statusTimer) clearTimeout(statusTimer);
          clearCellDraft(rowId, colId);
          saveStatus.value = 'error';
          statusTimer = setTimeout(() => { if(saveStatus.value === 'error') saveStatus.value = 'idle'; }, 3000);
          return;
        }

        if (currentRecord && currentRecord.value_type === 'fixed' && Number(currentRecord.monto_efectivo) === numVal) {
          clearCellDraft(rowId, colId);
          return;
        }

        saveStatus.value = 'saving';
        if(statusTimer) clearTimeout(statusTimer);
        setCellSaving(d, true);

        try {
          const res = await fetch('{{ route("pago-choferes.settings.zone-concepts.store") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              traffic_zone_id: d.traffic_zone_id,
              driver_payment_concept_id: d.driver_payment_concept_id,
              vehicle_type: d.vehicle_type,
              value_type: 'fixed',
              monto_efectivo: numVal,
              active: 1
            })
          });

          if(!res.ok) {
            let errorMessage = 'Save failed';
            try {
              const payload = await res.json();
              errorMessage = payload.message || Object.values(payload.errors || {}).flat().join(' ') || errorMessage;
            } catch (error) {
              // Mantener un mensaje simple cuando la respuesta no sea JSON.
            }
            throw new Error(errorMessage);
          }

          upsertLocalCellValue(d, numVal);
          clearCellDraft(rowId, colId);
          saveStatus.value = 'saved';
          statusTimer = setTimeout(() => { if(saveStatus.value === 'saved') saveStatus.value = 'idle'; }, 2000);
        } catch(e) {
          console.error(e);
          saveStatus.value = 'error';
          statusTimer = setTimeout(() => { if(saveStatus.value === 'error') saveStatus.value = 'idle'; }, 3000);
        } finally {
          setCellSaving(d, false);
        }
      };

      const openYearValuesModal = (rowId, colId) => {
        const d = extractDimensions(rowId, colId);
        yearModalContext.value = {
          ...d,
          zone_name: zoneIndex.value.get(String(d.traffic_zone_id))?.name || 'Zona',
          concept_name: conceptIndex.value.get(String(d.driver_payment_concept_id))?.name || 'Concepto',
          vehicle_label: vehicleIndex.value.get(String(d.vehicle_type))?.name || d.vehicle_type,
        };
        yearModalRows.value = getYearRowsForCell(rowId, colId).map(row => ({
          model_year: Number(row.model_year),
          amount: Number(row.amount),
        }));
        yearForm.value = { model_year: '', amount: '' };
        ensureYearValuesModal()?.show();
      };

      const addYearValueRow = () => {
        const modelYear = parseInt(yearForm.value.model_year, 10);
        const amount = parseFloat(yearForm.value.amount);

        if (!Number.isInteger(modelYear) || modelYear < 1900 || modelYear > 2100 || Number.isNaN(amount) || amount < 0) {
          return;
        }

        const existing = yearModalRows.value.find(row => Number(row.model_year) === modelYear);
        if (existing) {
          existing.amount = amount;
        } else {
          yearModalRows.value.push({ model_year: modelYear, amount });
        }

        yearModalRows.value = yearModalRows.value.slice().sort((a, b) => a.model_year - b.model_year);
        yearForm.value = { model_year: '', amount: '' };
      };

      const removeYearValueRow = (index) => {
        yearModalRows.value.splice(index, 1);
      };

      const saveYearValueRows = async () => {
        if (!yearModalContext.value) {
          return;
        }

        saveStatus.value = 'saving';
        if(statusTimer) clearTimeout(statusTimer);

        try {
          const res = await fetch('{{ route("pago-choferes.settings.zone-concepts.year-values.store") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              traffic_zone_id: yearModalContext.value.traffic_zone_id,
              driver_payment_concept_id: yearModalContext.value.driver_payment_concept_id,
              vehicle_type: yearModalContext.value.vehicle_type,
              rows: yearModalRows.value.map(row => ({
                model_year: row.model_year,
                amount: row.amount
              }))
            })
          });

          if(!res.ok) throw new Error('Save failed');

          zoneConceptYearValues.value = zoneConceptYearValues.value.filter(row =>
            !(
              String(row.traffic_zone_id) === String(yearModalContext.value.traffic_zone_id) &&
              String(row.driver_payment_concept_id) === String(yearModalContext.value.driver_payment_concept_id) &&
              String(row.vehicle_type) === String(yearModalContext.value.vehicle_type)
            )
          );

          yearModalRows.value.forEach(row => {
            zoneConceptYearValues.value.push({
              traffic_zone_id: yearModalContext.value.traffic_zone_id,
              driver_payment_concept_id: yearModalContext.value.driver_payment_concept_id,
              vehicle_type: yearModalContext.value.vehicle_type,
              model_year: row.model_year,
              amount: row.amount,
            });
          });

          let existing = zoneConcepts.value.find(zc =>
            String(zc.traffic_zone_id) === String(yearModalContext.value.traffic_zone_id) &&
            String(zc.driver_payment_concept_id) === String(yearModalContext.value.driver_payment_concept_id) &&
            String(zc.vehicle_type) === String(yearModalContext.value.vehicle_type)
          );

          if(!existing) {
            zoneConcepts.value.push({
              traffic_zone_id: yearModalContext.value.traffic_zone_id,
              driver_payment_concept_id: yearModalContext.value.driver_payment_concept_id,
              vehicle_type: yearModalContext.value.vehicle_type,
              monto_efectivo: null,
              value_type: 'fixed',
              active: 1
            });
          }

          saveStatus.value = 'saved';
          ensureYearValuesModal()?.hide();
          statusTimer = setTimeout(() => { if(saveStatus.value === 'saved') saveStatus.value = 'idle'; }, 2000);
        } catch(e) {
          console.error(e);
          saveStatus.value = 'error';
          statusTimer = setTimeout(() => { if(saveStatus.value === 'error') saveStatus.value = 'idle'; }, 3000);
        }
      };

      return {
        groupBy, groupValue, groupOptions, groupLabel,
        rowLabel, colLabel, rows, cols, getDisplayCellValue, updateCellDraft,
        startCellEdit, commitCell, cancelCellEdit, cellHasValue, isCellSaving,
        saveStatus, cellUsesModelYear, openYearValuesModal, getYearValueSummary,
        yearModalContext, yearModalRows, yearForm, addYearValueRow, removeYearValueRow,
        saveYearValueRows, formatAmount
      };
    }
  });

  app.mount('#pricing-pivot-app');
</script>
@endsection
