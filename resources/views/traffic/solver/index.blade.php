@extends('layouts.app')

@section('title', 'Solver logistico')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Modelo solver logistico</h1>
      <p class="text-muted mb-0">
        Distribui pedidos entre transportistas usando Google OR-Tools y geocodificacion de Mapbox/Google Maps.
      </p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
      <button class="btn btn-primary js-solver-submit" type="submit" data-action="solve" id="solverSubmitBtn">
        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Ejecutar previsualizacion
      </button>
    </div>
  </div>

  <div class="solver-stepper">
    <button type="button" class="stepper-item active" data-step-target="1">1. Clusters</button>
    <button type="button" class="stepper-item" data-step-target="2">2. Configuración</button>
    <button type="button" class="stepper-item" data-step-target="3">3. Resultado</button>
  </div>
  @if($errors->any())
    <div class="alert alert-danger">
      {{ $errors->first() }}
    </div>
  @endif
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  @if(!empty($warnings))
    <div class="alert alert-warning" style="max-height: 15ex;overflow-y: scroll;">
      {!! implode('<br>', $warnings) !!}
    </div>
  @endif

  <form id="solverForm" class="card shadow-sm border-0 mb-3" method="POST" action="{{ route('traffic.solver.preview') }}" enctype="multipart/form-data">
    @csrf
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <div>
        <h2 class="h5 mb-0">Configuracion del solver</h2>
        <small class="text-muted">
          Parametros base, lat/long via Mapbox/Google Maps y resolucion con OR-Tools.
        </small>
      </div>
      <div></div>
    </div>
    <div id="solverConfig">
      <div class="card-body">
        @php
          $selectedTransportistas = old('transportista_ids', $selectedTransportistas ?? []);
          $balancedLoadValue = old('balanced_load', ($balancedLoad ?? false) ? '1' : '0');
        @endphp
        <input type="hidden" name="manual_addresses" id="solverManualAddresses" value="">
        <input type="hidden" name="solver_action" id="solverAction" value="{{ old('solver_action', 'validate') }}">
        <input type="hidden" name="balanced_load" id="solverBalancedLoadInput" value="{{ $balancedLoadValue }}">

        <div class="solver-steps">
          <section class="solver-step active" data-step="1">
            <div class="row g-4">
              <div class="col-lg-6 d-none">
                <div class="solver-panel light h-100">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="panel-title">Paso 1 - Cluster</div>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="solverValidateBtn">
                      <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                      <i class="fa-solid fa-shield-check me-1"></i>
                      <span class="solver-validate-label">Validar direcciones</span>
                    </button>
                  </div>
                  <div class="panel-subtitle mb-3">Carga el Excel, revisa la hoja y valida direcciones.</div>
                  <div class="mb-3">
                    <label class="form-label">Cliente del Excel</label>
                    @php $selectedCustomer = old('party_id', $selectedCustomer ?? null); @endphp
                    <select class="form-select" name="party_id" id="solverCustomerSelect">
                      <option value="">Selecciona el cliente</option>
                      @foreach(($customers ?? []) as $customer)
                        <option value="{{ $customer->id }}" {{ (int) $selectedCustomer === (int) $customer->id ? 'selected' : '' }}>
                          {{ $customer->business_name ?: $customer->name }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-text">Obligatorio cuando subas un Excel; se usa para guardar direcciones sueltas.</div>
                  </div>
                  <input class="form-control" type="file" name="file" accept=".xlsx,.xls,.csv" aria-label="Archivo de pedidos">
                  <div class="form-text">Debe incluir columnas de direccion y referencia del pedido.</div>
                  <input type="hidden" name="sheet" id="solverSheetInput" value="">

                  <div id="solverPreview" class="solver-preview d-none mt-3">
                    <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-2 bg-white">
                      <small class="text-muted">Vista previa por hoja</small>
                      <div id="solverPreviewLoading" class="small text-muted d-none">Cargando...</div>
                    </div>
                    <ul class="nav nav-tabs px-3 pt-2" id="solverPreviewTabs" role="tablist"></ul>
                    <div class="tab-content p-3" id="solverPreviewContent"></div>
                  </div>
                </div>
              </div>
              <div class="col-lg-12">
                <div class="solver-panel h-100">
                  <div class="panel-title">Paso 1 - Validacion</div>
                  <div class="panel-subtitle mb-3">Confirmamos direcciones validas con Mapbox o Google.</div>
                  <div class="empty-state" id="solverValidationEmpty">Carga un archivo y presiona "Validar direcciones".</div>
                  <div class="solver-validation d-none" id="solverValidationSummary">
                    <div class="summary-grid">
                      <div class="summary-item">
                        <div class="summary-label">Direcciones</div>
                        <div class="summary-value" id="solverTotalStops">0</div>
                      </div>
                      <div class="summary-item">
                        <div class="summary-label">Invalidas</div>
                        <div class="summary-value text-danger" id="solverInvalidStops">0</div>
                      </div>
                      <div class="summary-item">
                        <div class="summary-label">Proveedor</div>
                        <div class="summary-value" id="solverProviderLabel">-</div>
                      </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                      <button class="btn btn-outline-warning" type="button" id="solverInvalidBtn" data-bs-toggle="modal" data-bs-target="#solverInvalidModal">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Ver direcciones invalidas
                      </button>
                      <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#ordersModal">
                        <i class="fa-solid fa-list me-1"></i> Ver pedidos ({{ $uploadedStops->count() }})
                      </button>
                    </div>
                    <div class="mt-3">
                      <div class="small text-muted mb-2">Mapa general de pedidos</div>
                      <div id="solverOverviewMap" class="map-frame" style="height: 220px;"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div id="clusterSummaryPanel" class="mt-4 solver-panel d-none">
              <div class="d-flex justify-content-between align-items-baseline mb-2">
                <div>
                  <div class="panel-title">Clusters generados</div>
                  <div class="panel-subtitle mb-0">Visualiza cómo quedaron asignadas las paradas y la densidad por transportista.</div>
                </div>
                <div class="text-muted small" id="clusterSummaryMeta"></div>
              </div>
              <div class="row g-3">
                <div class="col-lg-8">
                  <div id="clusterSummaryMap" class="map-frame" style="height: 320px; border-radius: 0.75rem; border: 1px solid #e5e7eb;"></div>
                </div>
                <div class="col-lg-4">
                  <div id="clusterSummaryList" class="cluster-summary-list"></div>
                </div>
              </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <button class="btn btn-primary" type="button" data-step-next="2">
                Continuar <i class="fa-solid fa-arrow-right ms-1"></i>
              </button>
            </div>
          </section>

          <section class="solver-step" data-step="2">
            <div class="row g-4">
              <div class="col-lg-5">
                <div class="solver-panel h-100">
                  <div class="panel-title">Paso 2 - Configuración</div>
                  <div class="panel-subtitle mb-3">Parámetros de ruteo y criterios de costo.</div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Max. paradas por transportista</label>
                      <input class="form-control" type="number" min="1" name="max_stops" value="{{ old('max_stops', $maxStops ?? 15) }}">
                      <div class="form-text">Restriccion inicial del modelo.</div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Estrategia de armado</label>
                      <select class="form-select" name="strategy">
                        <option value="route" {{ (old('strategy', $strategy ?? 'route') === 'route') ? 'selected' : '' }}>Eficiencia de ruta</option>
                        <option value="cost" {{ (old('strategy', $strategy ?? 'route') === 'cost') ? 'selected' : '' }}>Minimizar costo</option>
                        <option value="weight" {{ (old('strategy', $strategy ?? 'route') === 'weight') ? 'selected' : '' }}>Ponderacion</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Regreso a deposito</label>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="returnToDepot" name="return_to_depot" value="1" {{ $returnToDepot ? 'checked' : '' }}>
                        <label class="form-check-label" for="returnToDepot">Regresar al finalizar</label>
                      </div>
                      <div class="mt-2 {{ $returnToDepot ? '' : 'd-none' }}" id="depotSelectWrapper">
                        <select class="form-select" id="locationSelect" name="location_id">
                          <option value="">Seleccionar deposito</option>
                          @foreach(($locations ?? []) as $location)
                            <option value="{{ $location->id }}" {{ (int) ($selectedLocation ?? 0) === $location->id ? 'selected' : '' }}>
                              {{ $location->name }} - {{ $location->address }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Opciones</label>
                      <div class="border rounded p-3 bg-light">
                        <div class="form-check mb-2">
                          <input class="form-check-input" type="checkbox" id="avoidTolls" name="avoid_tolls" value="1" {{ request('avoid_tolls', false) ? 'checked' : '' }}>
                          <label class="form-check-label" for="avoidTolls">Evitar peajes</label>
                        </div>
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="avoidHighways" name="avoid_highways" value="1" {{ request('avoid_highways', false) ? 'checked' : '' }}>
                          <label class="form-check-label" for="avoidHighways">Evitar autopistas</label>
                        </div>
                      </div>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#solverAdvanced" aria-expanded="false">
                        <i class="fa-solid fa-sliders me-1"></i> Opciones avanzadas
                      </button>
                      <div class="collapse mt-2" id="solverAdvanced">
                        <label class="form-label">Proveedor de mapas</label>
                        <select class="form-select d-none" id="mapProvider" name="map_provider">
                          <option value="openstreet" >OpenStreetMap</option>
                          <option value="mapbox" selected>Mapbox</option>
                          <option value="google" >Google Maps</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="solver-panel h-100">
                  <div class="panel-title">Paso 2 - Transportistas</div>
                   <div class="panel-subtitle mb-3">Selecciona quienes participan y el transporte a usar.</div>
                  @php
                    $transportistasListCollection = $transportistasList ?? collect();
                    $activeTransportistas = $transportistasListCollection->filter(fn($carrier) => ($carrier->is_active ?? true));
                  @endphp
                  @if($activeTransportistas->isEmpty())
                    <div class="alert alert-warning mb-0">Carga transportistas para continuar.</div>
                  @else
                    <div class="table-responsive solver-carriers">
                      <table class="table align-middle mb-0">
                        <thead>
                          <tr>
                            <th style="width: 70px;">Incluir</th>
                            <th>Nombre</th>
                            <th>Transporte</th>
                            <th>Costo</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($activeTransportistas as $carrier)
                            @php
                              $hasTransport = ($carrier->transportes_count ?? 0) > 0;
                              $checked = in_array($carrier->id, $selectedTransportistas, false);
                            @endphp
                            <tr class="{{ $hasTransport ? '' : 'text-muted' }}">
                              <td>
                                <input class="form-check-input" type="checkbox" name="transportista_ids[]" form="solverForm"
                                       id="carrierRow{{ $carrier->id }}" value="{{ $carrier->id }}"
                                       {{ $checked && $hasTransport ? 'checked' : '' }}
                                       {{ $hasTransport ? '' : 'disabled' }}>
                              </td>
                              <td class="fw-semibold">
                                <label for="carrierRow{{ $carrier->id }}" class="mb-0 {{ $hasTransport ? '' : 'text-muted' }}">
                                  <span class="carrier-color-dot" style="background: {{ $carrier->color ?? '#2563eb' }};"></span>
                                  {{ $carrier->name }}
                                  @if(!$hasTransport)
                                    <span class="small text-muted">(sin transporte)</span>
                                  @endif
                                </label>
                              </td>
                              <td>
                                <select class="form-select form-select-sm transporte-select" data-transportista-id="{{ $carrier->id }}" name="transporte_id_{{ $carrier->id }}" {{ $hasTransport ? '' : 'disabled' }}>
                                  <option value="">-</option>
                                </select>
                              </td>
                              <td>{{ number_format($carrier->cost_efficiency ?? 1, 2) }}</td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  @endif
                </div>
              </div>
            </div>
            <div class="d-flex justify-content-between gap-2 mt-3">
              <button class="btn btn-outline-secondary" type="button" data-step-prev="1">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver
              </button>
              <button class="btn btn-primary js-solver-submit" type="submit" data-action="solve">
                Validar y generar vista previa <i class="fa-solid fa-wand-magic-sparkles ms-1"></i>
              </button>
            </div>
          </section>
          <section class="solver-step" data-step="3">
            <div class="solver-panel">
              <div class="solver-panel-head">
                <div>
                  <h2 class="h5 mb-1">Propuesta inicial</h2>
                  <small class="text-muted">Modelo generado segun estrategia y maximos configurados.</small>
                </div>
                <div class="d-flex gap-2">
                  <input type="date" name="scheduled_date" id="generateScheduledDate" class="form-control"value="{{date('Y-m-d');}}">
                  <button class="btn btn-sm btn-primary" type="button" id="generateRoutesBtn">
                    <i class="fa-solid fa-road me-1"></i> Generar rutas
                  </button>
                </div>
              </div>
              <div class="solver-panel-body">
                @forelse($previewPlans as $plan)
                  <div class="solver-panel plan-card mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                      <div>
                        <h3 class="h6 mb-0">
                          <span class="carrier-color-dot" style="background: {{ is_array($plan['carrier']) ? ($plan['carrier']['color'] ?? '#2563eb') : ($plan['carrier']->color ?? '#2563eb') }};"></span>
                          {{ is_array($plan['carrier']) ? $plan['carrier']['name'] : $plan['carrier']->name }}
                        </h3>
                        <small class="text-muted">
                          Costo {{ is_array($plan['carrier']) ? number_format($plan['carrier']['cost_efficiency'] ?? 0, 2) : number_format($plan['carrier']->cost_efficiency ?? 0, 2) }},
                          Ponderacion {{ is_array($plan['carrier']) ? number_format($plan['carrier']['performance_weight'] ?? 1, 2) : number_format($plan['carrier']->performance_weight ?? 1, 2) }}
                          @if(!empty($plan['transporte']))
                            · Transporte: <strong>{{ is_array($plan['transporte']) ? $plan['transporte']['alias'] : $plan['transporte']->alias }}</strong>
                          @endif
                        </small>
                      </div>
                      <span class="badge text-bg-light">
                        Distancia estimada: {{ number_format($plan['distance'], 1) }} km
                      </span>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead>
                              <tr>
                                <th style="width:90px;">Orden</th>
                                <th style="width:36px;"></th>
                                <th>Direccion</th>
                                <th>Pedido</th>
                              </tr>
                            </thead>
                            <tbody id="planStopsBody{{ $loop->index }}">
                              @foreach($plan['stops'] as $stop)
                                <tr data-plan-index="{{ $loop->parent->index }}" data-stop-index="{{ $loop->index }}">
                                  <td>
                                    <div class="d-flex align-items-center gap-2">
                                      <span class="badge bg-light text-dark order-badge">#{{ $loop->iteration }}</span>
                                      <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-link text-muted p-0 js-move-stop" type="button"
                                                data-plan-index="{{ $loop->parent->index }}"
                                                data-stop-index="{{ $loop->index }}"
                                                data-direction="up" title="Mover arriba">
                                          <i class="fa-solid fa-arrow-up"></i>
                                        </button>
                                        <button class="btn btn-link text-muted p-0 js-move-stop" type="button"
                                                data-plan-index="{{ $loop->parent->index }}"
                                                data-stop-index="{{ $loop->index }}"
                                                data-direction="down" title="Mover abajo">
                                          <i class="fa-solid fa-arrow-down"></i>
                                        </button>
                                      </div>
                                    </div>
                                  </td>
                                   <td>
                                     <div class="d-flex flex-column gap-1">
                                       <input class="form-check-input stop-select"
                                              type="checkbox"
                                              data-plan="{{ $loop->parent->index }}"
                                              data-code="{{ $stop['code'] }}"
                                              data-lat="{{ $stop['lat'] }}"
                                              data-lng="{{ $stop['lng'] }}"
                                              checked>
                                       <button type="button"
                                               class="btn btn-sm btn-outline-secondary js-open-reassign-btn"
                                               data-plan="{{ $loop->parent->index }}"
                                               data-stop-index="{{ $loop->index }}"
                                               data-lat="{{ $stop['lat'] }}"
                                               data-lng="{{ $stop['lng'] }}">
                                         <i class="fa-solid fa-arrow-right-arrow-left"></i> Reasignar
                                       </button>
                                     </div>
                                   </td>
                                  <td>{{ $stop['address'] }}</td>
                                  <td class="text-muted small">{{ $stop['code'] }}</td>
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div id="map-{{ $loop->index }}" class="solver-map map-frame" style="height: 300px;"></div>
                      </div>
                    </div>
                  </div>
                @empty
                  <p class="text-muted mb-0">Sube pedidos y transportistas para ver un plan sugerido.</p>
                @endforelse
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </form>

  <form id="generateForm" class="d-none" method="POST" action="{{ route('traffic.solver.generate') }}">
    @csrf
    <input type="hidden" name="plans" id="plansInput" value='@json($plansPayload ?? [])'>
    <input type="hidden" name="return_to_depot" id="generateReturnDepot" value="0">
    <input type="hidden" name="location_id" id="generateLocationId" value="">
    <input type="hidden" name="avoid_tolls" id="generateAvoidTolls" value="{{ request('avoid_tolls', false) ? '1' : '0' }}">
    <input type="hidden" name="avoid_highways" id="generateAvoidHighways" value="{{ request('avoid_highways', false) ? '1' : '0' }}">
    <input type="hidden" name="scheduled_date" id="generateScheduledDateHidden" value="">

  </form>

  <div class="modal fade" id="solverInvalidModal" tabindex="-1" aria-labelledby="solverInvalidModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="solverInvalidModalLabel">Direcciones a corregir</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small">
            Ajusta las direcciones sin validar y vuelve a ejecutar la previsualizacion.
          </div>
          <div id="solverInvalidList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-warning" id="solverRevalidateBtn">Revalidar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="ordersModal" tabindex="-1" aria-labelledby="ordersModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="ordersModalLabel">Pedidos cargados ({{ $uploadedStops->count() }})</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Pedido</th>
                  <th>Direccion</th>
                  <th>Prioridad</th>
                </tr>
              </thead>
              <tbody>
                @forelse($uploadedStops as $stop)
                  <tr>
                    <td class="fw-semibold">{{ $stop['code'] }}</td>
                    <td>{{ $stop['address'] }}</td>
                    <td><span class="badge text-bg-light">{{ $stop['priority'] }}</span></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">Sube un Excel para ver los pedidos.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <div id="solverReassignMenu" class="solver-reassign-menu d-none">
    <div class="card shadow">
      <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted small">Reasignar parada</div>
          <button type="button" class="btn-close btn-close-sm" aria-label="Cerrar" onclick="document.getElementById('solverReassignMenu')?.classList.add('d-none')"></button>
        </div>
        <div id="solverReassignList" class="list-group list-group-flush"></div>
      </div>
    </div>
  </div>

@endsection

@push('styles')
  <style>
    .solver-panel {
      background: #ffffff;
      border: 1px solid #e6edf3;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    }
    .solver-panel.light {
      background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%);
    }
    .solver-panel .panel-title {
      font-size: 0.78rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 700;
      color: #6c757d;
    }
    .solver-panel .panel-subtitle {
      font-size: 0.9rem;
      color: #6c757d;
    }
    .solver-preview {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      background: #ffffff;
      overflow: hidden;
    }
    .solver-preview .nav-tabs .nav-link {
      font-size: 0.85rem;
    }
    .solver-panel.plan-card {
      background: #ffffff;
    }
    .cluster-summary-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      max-height: 320px;
      overflow-y: auto;
    }
    .cluster-summary-list .cluster-item {
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      padding: 10px 12px;
      background: #fff;
      display: grid;
      gap: 0.35rem;
      font-size: 0.85rem;
    }
    .cluster-summary-list .cluster-item-header {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:0.5rem;
    }
    .cluster-summary-list .cluster-color {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display:inline-block;
      margin-right: 0.35rem;
    }
    .cluster-summary-list .cluster-meta {
      color: #6c757d;
      font-size: 0.8rem;
    }
    .solver-reassign-menu {
      position: absolute;
      z-index: 1400;
      min-width: 220px;
    }
    .solver-reassign-menu .list-group-item {
      cursor: pointer;
      padding: 0.5rem 0.75rem;
      font-size: 0.85rem;
    }
    .solver-stepper {
      display: flex;
      gap: 8px;
      margin: 0 0 16px;
      flex-wrap: wrap;
    }
    .solver-stepper .stepper-item {
      border: 1px solid #d7dee8;
      background: #ffffff;
      color: #6c757d;
      padding: 8px 14px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.9rem;
    }
    .solver-stepper .stepper-item.active {
      background: #0f172a;
      color: #fff;
      border-color: #0f172a;
    }
    .solver-step {
      display: none;
      animation: solverFade 0.35s ease;
    }
    .solver-step.active {
      display: block;
    }
    @keyframes solverFade {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .solver-carriers {
      max-height: 280px;
      overflow: auto;
    }
    .solver-validation .summary-value {
      font-size: 1.2rem;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 12px;
    }
    .summary-item {
      border: 1px solid #e6edf3;
      border-radius: 12px;
      padding: 10px 12px;
      background: #ffffff;
    }
    .summary-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #6c757d;
      font-weight: 700;
    }
    .summary-value {
      font-size: 1.3rem;
      font-weight: 700;
      color: #111827;
    }
    .solver-panel-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      border-bottom: 1px solid #e6edf3;
      padding-bottom: 12px;
      margin-bottom: 16px;
    }
    .solver-panel-body {
      padding: 0;
    }
    .empty-state {
      border: 1px dashed #d7dee8;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
      color: #6c757d;
      background: #f8fafc;
      font-size: 0.95rem;
    }
  </style>
@endpush

@push('scripts')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      const solverForm = document.getElementById('solverForm');
      const fileInput = solverForm ? solverForm.querySelector('input[name="file"]') : null;
      const sheetInput = document.getElementById('solverSheetInput');
      const previewWrapper = document.getElementById('solverPreview');
      const previewTabs = document.getElementById('solverPreviewTabs');
      const previewContent = document.getElementById('solverPreviewContent');
      const previewLoading = document.getElementById('solverPreviewLoading');
      const uploadedStops = @json($uploadedStops);
      const manualInput = document.getElementById('solverManualAddresses');
      const actionInput = document.getElementById('solverAction');
      const submitButtons = Array.from(document.querySelectorAll('.js-solver-submit'));
      const validateBtn = document.getElementById('solverValidateBtn');
      const validateSpinner = validateBtn ? validateBtn.querySelector('.spinner-border') : null;
      const validateLabel = validateBtn ? validateBtn.querySelector('.solver-validate-label') : null;
      const invalidBtn = document.getElementById('solverInvalidBtn');
      const invalidList = document.getElementById('solverInvalidList');
      const revalidateBtn = document.getElementById('solverRevalidateBtn');
      const validationEmpty = document.getElementById('solverValidationEmpty');
      const validationSummary = document.getElementById('solverValidationSummary');
      const totalStopsEl = document.getElementById('solverTotalStops');
      const invalidStopsEl = document.getElementById('solverInvalidStops');
      const providerLabel = document.getElementById('solverProviderLabel');
      const mapboxToken = @json(config('services.mapbox.token') ?? env('MAPBOX_TOKEN'));
      const customerSelect = document.getElementById('solverCustomerSelect');

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const showCustomerWarning = () => {
        const msg = 'Selecciona el cliente al que pertenece el Excel.';
        if (window.nygAlert) {
          window.nygAlert(msg, 'warning');
        }
      };

      const resetPreview = () => {
        if (previewTabs) previewTabs.innerHTML = '';
        if (previewContent) previewContent.innerHTML = '';
        if (sheetInput) sheetInput.value = '';
        if (previewWrapper) previewWrapper.classList.add('d-none');
      };

      const setPreviewLoading = (loading, message) => {
        if (!previewLoading) return;
        previewLoading.classList.toggle('d-none', !loading);
        previewLoading.textContent = message || 'Cargando...';
      };

      const renderPreview = (data) => {
        if (!previewWrapper || !previewTabs || !previewContent) return;
        const columns = Array.isArray(data.columns) && data.columns.length ? data.columns : ['A', 'B', 'C', 'D', 'E'];
        const sheets = Array.isArray(data.sheets) ? data.sheets : [];
        previewWrapper.classList.remove('d-none');

        if (!sheets.length) {
          previewTabs.innerHTML = '';
          previewContent.innerHTML = '<div class="text-muted small p-2">No se encontraron hojas para previsualizar.</div>';
          if (sheetInput) sheetInput.value = '';
          return;
        }

        previewTabs.innerHTML = sheets.map((sheet, index) => {
          const label = escapeHtml(sheet.name || `Hoja ${index + 1}`);
          const active = index === 0 ? 'active' : '';
          return `<li class="nav-item" role="presentation">
            <button class="nav-link ${active}" data-bs-toggle="tab" data-bs-target="#solverSheet${index}" type="button" role="tab" data-sheet="${label}">
              ${label}
            </button>
          </li>`;
        }).join('');

        previewContent.innerHTML = sheets.map((sheet, index) => {
          const rows = Array.isArray(sheet.rows) ? sheet.rows : [];
          const active = index === 0 ? 'show active' : '';
          const bodyHtml = rows.length
            ? rows.map((row) => {
              const rowCells = columns.map((col) => `<td>${escapeHtml(row?.cells?.[col] ?? '')}</td>`).join('');
              return `<tr><td class="text-muted">${escapeHtml(row?.row ?? '')}</td>${rowCells}</tr>`;
            }).join('')
            : `<tr><td colspan="${columns.length + 1}" class="text-muted text-center">Sin datos para mostrar</td></tr>`;

          return `<div class="tab-pane fade ${active}" id="solverSheet${index}" role="tabpanel">
            <div class="table-responsive" style="max-height: 240px; overflow: auto;">
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:50px;">#</th>
                    ${columns.map(col => `<th>${escapeHtml(col)}</th>`).join('')}
                  </tr>
                </thead>
                <tbody>${bodyHtml}</tbody>
              </table>
            </div>
          </div>`;
        }).join('');

        if (sheetInput) {
          sheetInput.value = sheets[0].name || '0';
        }

        previewTabs.querySelectorAll('button[data-sheet]').forEach((btn) => {
          btn.addEventListener('shown.bs.tab', (event) => {
            if (sheetInput) {
              sheetInput.value = event.target.getAttribute('data-sheet') || '';
            }
          });
        });
      };
      const mapProviderSelect = document.getElementById('mapProvider');

      const normalizeStops = (stops) => (Array.isArray(stops) ? stops.map((stop, index) => ({
        code: stop.code || `PED-${String(index + 1).padStart(3, '0')}`,
        address: stop.address || '',
        priority: stop.priority || 'Media',
        lat: stop.lat ?? stop.latitude ?? null,
        lng: stop.lng ?? stop.longitude ?? null,
        notes: stop.notes || '',
      })) : []);

      let stopsState = normalizeStops(uploadedStops);
      const invalidStops = () => stopsState.filter((stop) => {
        const lat = parseFloat(stop.lat);
        const lng = parseFloat(stop.lng);
        return !(Number.isFinite(lat) && Number.isFinite(lng));
      });
      let refreshContinue = () => {};

      const setupInvalidAutocomplete = (input, index) => {
        if (!mapboxToken) return;
        const wrapper = input.closest('.solver-invalid-wrapper');
        if (!wrapper) return;
        const suggestions = wrapper.querySelector('.solver-invalid-suggestions');
        if (!suggestions) return;

        const fetchSuggestions = async () => {
          const query = input.value.trim();
          suggestions.innerHTML = '';
          if (query.length < 3) return;
          const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json` +
            `?access_token=${mapboxToken}` +
            '&country=AR' +
            '&language=es' +
            '&autocomplete=true' +
            '&limit=5';
          try {
            const response = await fetch(url);
            if (!response.ok) return;
            const data = await response.json();
            if (!data.features || !data.features.length) return;
            data.features.forEach((feature) => {
              const item = document.createElement('button');
              item.type = 'button';
              item.className = 'list-group-item list-group-item-action';
              item.textContent = feature.place_name || '';
              item.addEventListener('click', () => {
                input.value = feature.place_name || '';
                const coords = feature.geometry?.coordinates;
                if (stopsState[index]) {
                  stopsState[index].address = input.value.trim();
                  if (Array.isArray(coords) && coords.length === 2) {
                    stopsState[index].lat = coords[1];
                    stopsState[index].lng = coords[0];
                  }
                }
                updateValidationSummary();
                renderInvalidList();
              });
              suggestions.appendChild(item);
            });
          } catch (error) {
            console.error('Error en autocomplete Mapbox:', error);
          }
        };

        input.addEventListener('input', fetchSuggestions);
      };

      const renderInvalidList = () => {
        if (!invalidList) return;
        const items = stopsState
          .map((stop, index) => ({ stop, index }))
          .filter((item) => {
            const lat = parseFloat(item.stop.lat);
            const lng = parseFloat(item.stop.lng);
            return !(Number.isFinite(lat) && Number.isFinite(lng));
          });
        if (!items.length) {
          invalidList.innerHTML = '<div class="text-muted">No hay direcciones invalidas.</div>';
          return;
        }
        invalidList.innerHTML = items.map((item) => `
          <div class="border rounded p-2 mb-2">
            <div class="small text-muted mb-1">Pedido ${escapeHtml(item.stop.code || `#${item.index + 1}`)}</div>
            <div class="d-flex gap-2 align-items-start">
              <div class="flex-grow-1 position-relative solver-invalid-wrapper">
                <input type="text" class="form-control form-control-sm solver-invalid-input" data-index="${item.index}" value="${escapeHtml(item.stop.address)}" autocomplete="off">
                <div class="list-group position-absolute w-100 solver-invalid-suggestions" style="z-index:1000; max-height:240px; overflow-y:auto; top:100%; left:0;"></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger solver-invalid-remove" data-index="${item.index}" title="Quitar direccion">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>
        `).join('');

        invalidList.querySelectorAll('.solver-invalid-input').forEach((input) => {
          const idx = Number(input.dataset.index);
          if (!Number.isFinite(idx)) return;
          setupInvalidAutocomplete(input, idx);
        });

        invalidList.querySelectorAll('.solver-invalid-remove').forEach((btn) => {
          btn.addEventListener('click', () => {
            const idx = Number(btn.dataset.index);
            if (!Number.isFinite(idx)) return;
            stopsState.splice(idx, 1);
            if (manualInput) {
              manualInput.value = JSON.stringify(stopsState);
            }
            renderInvalidList();
            updateValidationSummary();
          });
        });
      };

      const updateValidationSummary = () => {
        const total = stopsState.length;
        const invalidCount = invalidStops().length;
        if (validationEmpty) {
          validationEmpty.classList.toggle('d-none', total > 0);
        }
        if (validationSummary) {
          validationSummary.classList.toggle('d-none', total === 0);
        }
        if (totalStopsEl) totalStopsEl.textContent = String(total);
        if (invalidStopsEl) invalidStopsEl.textContent = String(invalidCount);
        if (providerLabel) {
          const provider = mapProviderSelect ? mapProviderSelect.value : '{{ $mapProvider ?? 'mapbox' }}';
          providerLabel.textContent = provider === 'google' ? 'Google Maps' : 'Mapbox';
        }
        if (invalidBtn) {
          invalidBtn.disabled = invalidCount === 0;
        }
        refreshContinue();
      };

      updateValidationSummary();
      renderInvalidList();

      document.addEventListener('click', (event) => {
        document.querySelectorAll('.solver-invalid-suggestions').forEach((list) => {
          const wrapper = list.closest('.solver-invalid-wrapper');
          if (!wrapper || wrapper.contains(event.target)) return;
          list.innerHTML = '';
        });
      });

      if (invalidBtn) {
        invalidBtn.addEventListener('click', renderInvalidList);
      }


      if (validateBtn) {
        if ((!fileInput || !fileInput.files.length) && (!uploadedStops || !uploadedStops.length)) {
          validateBtn.disabled = true;
        }
        validateBtn.addEventListener('click', () => {
          const hasFile = fileInput && fileInput.files.length;
          const hasStops = Array.isArray(stopsState) && stopsState.length;
          if (!hasFile && !hasStops) {
            return;
          }
          if (hasFile && customerSelect && !customerSelect.value) {
            showCustomerWarning();
            return;
          }
          validateBtn.disabled = true;
          if (validateSpinner) validateSpinner.classList.remove('d-none');
          if (validateLabel) validateLabel.textContent = 'Validando...';
          if (manualInput) {
            manualInput.value = hasFile ? '' : JSON.stringify(stopsState);
          }
          if (actionInput) {
            actionInput.value = 'validate';
          }
          solverForm?.submit();
        });
      }

      if (fileInput) {
        fileInput.addEventListener('change', async () => {
          if (validateBtn) {
            validateBtn.disabled = !fileInput.files.length && !(Array.isArray(uploadedStops) && uploadedStops.length);
          }
          if (customerSelect) {
            customerSelect.required = fileInput.files.length > 0;
          }
          if (validationEmpty) {
            validationEmpty.textContent = fileInput.files.length
              ? 'Archivo cargado. Presiona \"Validar direcciones\" para continuar.'
              : 'Carga un archivo para validar direcciones.';
          }
          if (!fileInput.files.length) {
            resetPreview();
            return;
          }
          if (manualInput) {
            manualInput.value = '';
          }
          stopsState = [];
          updateValidationSummary();
          renderInvalidList();
          if (previewTabs) previewTabs.innerHTML = '';
          if (previewContent) previewContent.innerHTML = '';
          if (previewWrapper) previewWrapper.classList.remove('d-none');
          setPreviewLoading(true);
          try {
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            const response = await fetch('{{ route('traffic.solver.preview.file') }}', {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
              body: formData,
            });
            if (!response.ok) {
              const data = await response.json().catch(() => ({}));
              const message = data.error || 'No se pudo previsualizar el archivo.';
              renderPreview({ columns: ['A', 'B', 'C', 'D', 'E'], sheets: [] });
              if (previewContent) {
                previewContent.innerHTML = `<div class="text-danger small p-2">${escapeHtml(message)}</div>`;
              }
              return;
            }
            const data = await response.json();
            renderPreview(data || {});
            if (validationEmpty) validationEmpty.classList.add('d-none');

          } catch (error) {
            console.error(error);
            renderPreview({ columns: ['A', 'B', 'C', 'D', 'E'], sheets: [] });
            if (previewContent) {
              previewContent.innerHTML = '<div class="text-danger small p-2">Ocurrio un error al previsualizar.</div>';
            }
          } finally {
            setPreviewLoading(false);
          }
        });
      }


      if (mapProviderSelect) {
        mapProviderSelect.addEventListener('change', updateValidationSummary);
      }

      if (revalidateBtn) {
        revalidateBtn.addEventListener('click', () => {
          const inputs = Array.from(document.querySelectorAll('.solver-invalid-input'));
          inputs.forEach((input) => {
            const idx = Number(input.dataset.index);
            if (!Number.isFinite(idx) || !stopsState[idx]) return;
            stopsState[idx].address = input.value.trim();
            stopsState[idx].lat = null;
            stopsState[idx].lng = null;
          });
          if (manualInput) {
            manualInput.value = JSON.stringify(stopsState);
          }
          if (actionInput) {
            actionInput.value = 'validate';
          }
          solverForm?.submit();
        });
      }

      const overviewEl = document.getElementById('solverOverviewMap');
      if (overviewEl && window.L) {
        const coords = Array.isArray(uploadedStops)
          ? uploadedStops.map((stop) => {
            const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
            const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
            return (Number.isFinite(lat) && Number.isFinite(lng)) ? [lat, lng] : null;
          }).filter(Boolean)
          : [];

        if (coords.length) {
          const overviewMap = window.L.map(overviewEl, { attributionControl: false, zoomControl: false });
          window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
          }).addTo(overviewMap);
          const layer = window.L.featureGroup().addTo(overviewMap);
          coords.forEach((c) => window.L.marker(c).addTo(layer));
          if (typeof layer.getBounds === 'function' && layer.getLayers().length) {
            overviewMap.fitBounds(layer.getBounds(), { padding: [20, 20] });
          }
        }
      }

      let plans = @json($previewPlans);
      
      // DEBUG: Verificar si loose_stop_id está presente en los planes
      console.log('DEBUG: plans from @json($previewPlans)', {
        plans_count: Array.isArray(plans) ? plans.length : 0,
        first_plan_stops: Array.isArray(plans) && plans.length > 0 ? plans[0].stops?.length : 0,
        first_stop: Array.isArray(plans) && plans.length > 0 && plans[0].stops?.length > 0 ? plans[0].stops[0] : null,
        first_stop_keys: Array.isArray(plans) && plans.length > 0 && plans[0].stops?.length > 0 ? Object.keys(plans[0].stops[0]) : [],
      });
      
      // DEBUG: Mostrar en consola el primer plan completo
      if (Array.isArray(plans) && plans.length > 0) {
        console.log('DEBUG: First plan structure:', JSON.stringify(plans[0], null, 2));
      }
      
        if (!Array.isArray(plans) || !plans.length) {
          try {
            const serverPlans = document.getElementById('plansInput')?.value;
            plans = serverPlans ? JSON.parse(serverPlans) : [];
          } catch (e) {
            plans = [];
          }
        }
        const clusterSummaryPanel = document.getElementById('clusterSummaryPanel');
        const clusterSummaryMapEl = document.getElementById('clusterSummaryMap');
        const clusterSummaryListEl = document.getElementById('clusterSummaryList');
        const clusterSummaryMeta = document.getElementById('clusterSummaryMeta');
        let clusterSummaryMap = null;
        let clusterSummaryLayer = null;

        function centroid(points) {
          if (!Array.isArray(points) || !points.length) return null;
          const sum = points.reduce(
            (acc, point) => ({
              lat: acc.lat + (point.lat ?? 0),
              lng: acc.lng + (point.lng ?? 0),
            }),
            { lat: 0, lng: 0 }
          );
          return { lat: sum.lat / points.length, lng: sum.lng / points.length };
        }

        const buildClusterData = () => {
          if (!Array.isArray(plans) || !plans.length) return [];
          return plans.map((plan, idx) => {
            const stops = Array.isArray(plan.stops)
              ? plan.stops.map((stop) => {
                const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
                const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
                return { lat, lng, code: stop.code ?? '', address: stop.address ?? '' };
              }).filter(Boolean)
              : [];
            const clusterCentroid = stops.length ? centroid(stops) : null;
            return {
              id: idx + 1,
              index: idx,
              carrierId: plan.carrier?.id ?? plan.carrier_id ?? null,
              carrierName: plan.carrier?.name ?? (`Cluster ${idx + 1}`),
              color: (plan.carrier?.color ?? palette[idx % palette.length]) || '#2563eb',
              stops,
              centroid: clusterCentroid,
            };
          }).filter((cluster) => cluster.stops.length > 0);
        };

        const focusClusterOnMap = (cluster) => {
          if (!clusterSummaryMap || !cluster || !cluster.stops.length) return;
          const bounds = cluster.stops.map((point) => [point.lat, point.lng]);
          if (cluster.centroid) {
            bounds.push([cluster.centroid.lat, cluster.centroid.lng]);
          }
          if (bounds.length) {
            clusterSummaryMap.fitBounds(bounds, { padding: [40, 40] });
          }
        };

        const renderClusterSummary = () => {
          const clusterData = buildClusterData();
          if (!clusterData.length) {
            clusterSummaryPanel?.classList.add('d-none');
            return;
          }
          clusterSummaryPanel?.classList.remove('d-none');
          const totalStops = clusterData.reduce((acc, cluster) => acc + cluster.stops.length, 0);
          if (clusterSummaryMeta) {
            clusterSummaryMeta.textContent = `${clusterData.length} clusters · ${totalStops} paradas`;
          }

          if (clusterSummaryMapEl && window.L) {
            if (!clusterSummaryMap) {
              clusterSummaryMap = window.L.map(clusterSummaryMapEl, { attributionControl: false, zoomControl: false });
              window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 20,
              }).addTo(clusterSummaryMap);
              clusterSummaryLayer = window.L.featureGroup().addTo(clusterSummaryMap);
            }
            clusterSummaryLayer.clearLayers();
            const bounds = [];
            clusterData.forEach((cluster) => {
              cluster.stops.forEach((point, idx) => {
                const marker = window.L.circleMarker([point.lat, point.lng], {
                  radius: 6,
                  color: cluster.color,
                  fillColor: cluster.color,
                  fillOpacity: 0.8,
                  weight: 2,
                }).addTo(clusterSummaryLayer);
                marker.bindTooltip(`#${idx + 1} ${point.address}`, { direction: 'top' });
                bounds.push([point.lat, point.lng]);
              });
              if (cluster.centroid) {
                const halo = window.L.circleMarker([cluster.centroid.lat, cluster.centroid.lng], {
                  radius: 12,
                  color: '#000',
                  weight: 2,
                  fillOpacity: 0,
                  dashArray: '6,6',
                }).addTo(clusterSummaryLayer);
                bounds.push([cluster.centroid.lat, cluster.centroid.lng]);
              }
            });
            if (bounds.length) {
              clusterSummaryMap.fitBounds(bounds, { padding: [20, 20] });
            }
            setTimeout(() => clusterSummaryMap.invalidateSize(), 200);
          }

          if (clusterSummaryListEl) {
            clusterSummaryListEl.innerHTML = clusterData.map((cluster) => `
              <div class="cluster-item" data-cluster-index="${cluster.index}">
                <div class="cluster-item-header">
                  <span class="cluster-color" style="background: ${cluster.color};"></span>
                  <div>
                    <div class="fw-semibold">Cluster ${cluster.id}</div>
                    <div class="cluster-meta">${cluster.carrierName}</div>
                  </div>
                  <span class="badge text-bg-light">${cluster.stops.length} paradas</span>
                </div>
                <div class="cluster-meta">Centro aproximado: ${cluster.centroid ? `${cluster.centroid.lat.toFixed(5)}, ${cluster.centroid.lng.toFixed(5)}` : 'Sin centroid'}</div>
              </div>
            `).join('');
            clusterSummaryListEl.querySelectorAll('.cluster-item').forEach((el) => {
              el.addEventListener('click', () => {
                const idx = Number(el.dataset.clusterIndex);
                const target = clusterData.find((c) => c.index === idx);
                focusClusterOnMap(target);
              });
            });
          }
        };

        renderClusterSummary();
      const steps = Array.from(document.querySelectorAll('.solver-step'));
      const stepperButtons = Array.from(document.querySelectorAll('.solver-stepper .stepper-item'));
      const continueBtn = document.querySelector('[data-step-next="2"]');
      const invalidModalEl = document.getElementById('solverInvalidModal');
      const invalidModal = (invalidModalEl && window.bootstrap && window.bootstrap.Modal)
        ? window.bootstrap.Modal.getOrCreateInstance(invalidModalEl)
        : null;

      const canContinue = () => {
        return stopsState.length > 0 && invalidStops().length === 0;
      };

      // Definir la función de carga de transportes ANTES de ser usada
      const loadTransportesForStep = async () => {
        const transporteSelects = document.querySelectorAll('.transporte-select');
        if (!transporteSelects.length) return;

        for (const select of transporteSelects) {
          const transportistaId = select.dataset.transportistaId;
          if (!transportistaId) continue;

          try {
            const response = await fetch(`/traffic/transportistas/${transportistaId}/transportes`);
            const json = await response.json();
            const transportes = json.data || [];

            // Limpiar opciones anteriores (excepto la primera)
            while (select.options.length > 1) {
              select.remove(1);
            }

            // Agregar nuevas opciones y marcar el default
            transportes.forEach(t => {
              const option = document.createElement('option');
              option.value = t.id;
              option.textContent = `${t.alias}${t.is_default ? ' (default)' : ''}`;
              if (t.is_default) {
                option.selected = true;
              }
              select.appendChild(option);
              console.log(option)
            });
          } catch (error) {
            console.error(`Error loading transportes for ${transportistaId}:`, error);
          }
        }
      };

      const setStep = (step) => {
        steps.forEach((section) => {
          section.classList.toggle('active', section.dataset.step === String(step));
        });
        stepperButtons.forEach((btn) => {
          btn.classList.toggle('active', btn.dataset.stepTarget === String(step));
        });
        
        // Cargar transportes cuando se abre el paso 2
        if (step === '2' || step === 2) {
          setTimeout(loadTransportesForStep, 100);
        }
        if (step === '1' || step === 1) {
          setTimeout(() => clusterSummaryMap?.invalidateSize(), 300);
        }
      };

      refreshContinue = () => {
        if (!continueBtn) return;
        continueBtn.disabled = !canContinue();
        submitButtons.forEach((btn) => {
          btn.disabled = !canContinue();
        });
      };

      let currentStep = Array.isArray(plans) && plans.length ? 3 : 1;
      setStep(currentStep);
      refreshContinue();

      stepperButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const target = btn.dataset.stepTarget;
          if (target === '2' && !canContinue()) {
            invalidModal?.show();
            return;
          }
          if (target === '3' && (!Array.isArray(plans) || !plans.length)) {
            return;
          }
          setStep(target);
        });
      });

      document.querySelectorAll('[data-step-next]').forEach((btn) => {
        btn.addEventListener('click', () => {
          if (!canContinue()) {
            invalidModal?.show();
            return;
          }
          setStep(btn.dataset.stepNext);
        });
      });

      document.querySelectorAll('[data-step-prev]').forEach((btn) => {
        btn.addEventListener('click', () => {
          setStep(btn.dataset.stepPrev);
        });
      });

      if (solverForm) {
        solverForm.addEventListener('submit', (event) => {
          const submitter = event.submitter;
          const action = submitter?.dataset?.action;
          if (action === 'solve') {
            if (fileInput && fileInput.files.length && customerSelect && !customerSelect.value) {
              event.preventDefault();
              showCustomerWarning();
              return;
            }
            if (!canContinue()) {
              event.preventDefault();
              invalidModal?.show();
              return;
            }
            if (manualInput && Array.isArray(stopsState) && stopsState.length) {
              manualInput.value = JSON.stringify(stopsState);
            }
            if (actionInput) {
              actionInput.value = 'solve';
            }
          }
        });
      }
        const numberedIcon = (label) => L.divIcon({
        html: `
          <div style="
            height:36px;width:36px;
            border-radius:50%;
            background:rgba(59, 130, 246, 0.92);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:700;
            border:2px solid #fff;
            box-shadow:0 8px 16px rgba(15,23,42,0.18);
            font-size:14px;
          ">${label}</div>`,
        className: 'nyg-stop-marker',
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -28],
      });

      const mapState = {};
      const notify = (msg, type = 'warning') => {
        if (window.nygAlert) {
          window.nygAlert(msg, type);
        }
      };
      const customOrderConfirmed = {};

      const confirmReorder = async (planIdx) => {
        if (customOrderConfirmed[planIdx]) {
          return true;
        }
        if (typeof Swal === 'undefined') {
          customOrderConfirmed[planIdx] = true;
          return true;
        }
        const result = await Swal.fire({
          title: 'Reordenar paradas',
          text: 'Vas a modificar el orden sugerido por el solver.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Si, reordenar',
          cancelButtonText: 'Cancelar',
        });
        if (result.isConfirmed) {
          customOrderConfirmed[planIdx] = true;
          return true;
        }
        return false;
      };

      const refreshOrderBadges = (planIdx) => {
        const rows = Array.from(document.querySelectorAll(`tr[data-plan-index="${planIdx}"]`));
        rows.forEach((row, index) => {
          row.dataset.stopIndex = String(index);
          const badge = row.querySelector('.order-badge');
          if (badge) {
            badge.textContent = `#${index + 1}`;
          }
          row.querySelectorAll('.js-move-stop').forEach((btn) => {
            btn.dataset.stopIndex = String(index);
          });
        });
      };

      const moveStop = async (planIdx, stopIdx, direction) => {
        const plan = plans[planIdx];
        if (!plan || !Array.isArray(plan.stops)) return;
        const approved = await confirmReorder(planIdx);
        if (!approved) return;

        const newIdx = direction === 'up' ? stopIdx - 1 : stopIdx + 1;
        if (newIdx < 0 || newIdx >= plan.stops.length) return;

        const [removed] = plan.stops.splice(stopIdx, 1);
        plan.stops.splice(newIdx, 0, removed);

        const row = document.querySelector(`tr[data-plan-index="${planIdx}"][data-stop-index="${stopIdx}"]`);
        const tbody = row?.parentElement;
        const targetRow = document.querySelector(`tr[data-plan-index="${planIdx}"][data-stop-index="${newIdx}"]`);

        if (row && tbody && targetRow) {
          if (direction === 'up') {
            tbody.insertBefore(row, targetRow);
          } else {
            tbody.insertBefore(row, targetRow.nextSibling);
          }
        }

        refreshOrderBadges(planIdx);
        redrawPlan(planIdx);
      };

      const redrawPlan = (planIdx) => {
        const plan = plans[planIdx];
        if (!plan) return;
        const state = mapState[planIdx];
        if (!state) return;

        if (state.layerGroup) {
          state.map.removeLayer(state.layerGroup);
        }

        const activeCodes = Array.from(document.querySelectorAll(`.stop-select[data-plan="${planIdx}"]:checked`))
          .map(cb => String(cb.dataset.code));

        const activeStops = (plan.stops || []).filter((stop) => {
          const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
          const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
          return activeCodes.includes(String(stop.code)) && Number.isFinite(lat) && Number.isFinite(lng);
        });

        const coords = activeStops.map(stop => [parseFloat(stop.lat), parseFloat(stop.lng)]);
        const layerGroup = L.layerGroup().addTo(state.map);

        if (coords.length) {
          const poly = L.polyline(coords, { color: '#0d6efd', weight: 4 }).addTo(layerGroup);
          coords.forEach((c, i) => {
            const marker = L.marker(c, {
              title: activeStops[i].code || '',
              icon: numberedIcon(i + 1),
            }).bindPopup(`#${i + 1} ${activeStops[i].address || ''}`).addTo(layerGroup);
            marker.on('contextmenu', (event) => {
              const pageX = event.originalEvent?.pageX ?? 0;
              const pageY = event.originalEvent?.pageY ?? 0;
              showReassignMenu(pageX, pageY, { planIdx: Number(planIdx), stopIdx: i });
            });
          });
          state.map.fitBounds(poly.getBounds(), { padding: [20, 20] });
        } else {
          state.map.setView([-34.6037, -58.3816], 5);
        }

        state.layerGroup = layerGroup;
      };

      plans.forEach((plan, idx) => {
        const mapId = `map-${idx}`;
        const el = document.getElementById(mapId);
        if (!el) return;
        const map = L.map(mapId);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        mapState[idx] = { map, layerGroup: null };
        redrawPlan(idx);
      });

      document.querySelectorAll('.js-move-stop').forEach((btn) => {
        btn.addEventListener('click', (event) => {
          const planIdx = Number(btn.dataset.planIndex);
          const stopIdx = Number(btn.dataset.stopIndex);
          const dir = btn.dataset.direction === 'up' ? 'up' : 'down';
          moveStop(planIdx, stopIdx, dir);
        });
      });

      plans.forEach((plan, idx) => refreshOrderBadges(idx));

      document.querySelectorAll('.stop-select').forEach((cb) => {
        cb.addEventListener('change', (e) => {
          const planIdx = e.target.dataset.plan;
          redrawPlan(planIdx);
        });
      });

      document.querySelectorAll('.solver-panel.plan-card tbody tr').forEach((row) => {
        row.addEventListener('contextmenu', (event) => {
          const planIdx = Number(row.dataset.planIndex);
          const stopIdx = Number(row.dataset.stopIndex);
          if (!Number.isFinite(planIdx) || !Number.isFinite(stopIdx)) return;
          event.preventDefault();
          showReassignMenu(event.pageX, event.pageY, { planIdx, stopIdx });
        });
      });

      document.querySelectorAll('.js-open-reassign-btn').forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          const planIdx = Number(btn.dataset.plan);
          const stopIdx = Number(btn.dataset.stopIndex);
          showReassignMenu(event.pageX, event.pageY, { planIdx, stopIdx });
        });
      });

      const reassignMenu = document.getElementById('solverReassignMenu');
      const reassignList = document.getElementById('solverReassignList');
      let pendingReassignStop = null;

      const hideReassignMenu = () => {
        if (!reassignMenu) return;
        reassignMenu.classList.add('d-none');
        pendingReassignStop = null;
      };

      const showReassignMenu = (x, y, payload) => {
        if (!reassignMenu || !payload) return;
        pendingReassignStop = payload;
        reassignMenu.style.left = `${x}px`;
        reassignMenu.style.top = `${y}px`;
        reassignMenu.classList.remove('d-none');
      };

        const updateReassignList = () => {
          if (!reassignList) return;
          reassignList.innerHTML = plans.map((plan, idx) => {
            const label = plan.carrier?.name ?? plan.carrier?.id ?? `Cluster ${idx + 1}`;
            return `<button type="button" class="list-group-item list-group-item-action" data-target-plan="${idx}">
              ${label}
            </button>`;
          }).join('');
            reassignList.querySelectorAll('[data-target-plan]').forEach((btn) => {
              btn.addEventListener('click', async () => {
                const target = Number(btn.dataset.targetPlan);
                await executeReassign(target);
              });
            });
      };

        const confirmReassignment = async (fromLabel, toLabel) => {
          const title = `Reasignar parada`;
          const text = `¿Deseas moverla de ${fromLabel} a ${toLabel}?`;
          if (typeof Swal === 'undefined') {
            return window.confirm(`${title}\n${text}`);
          }
          const result = await Swal.fire({
            icon: 'question',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: 'Sí, reasignar',
            cancelButtonText: 'Cancelar',
          });
          return result.isConfirmed;
        };

        const executeReassign = async (targetPlanIdx) => {
          if (!pendingReassignStop) {
            hideReassignMenu();
            return;
          }
          const sourcePlan = plans[pendingReassignStop.planIdx];
          const targetPlan = plans[targetPlanIdx];
          const sourceLabel = sourcePlan.carrier?.name ?? `Cluster ${pendingReassignStop.planIdx + 1}`;
          const targetLabel = targetPlan.carrier?.name ?? `Cluster ${targetPlanIdx + 1}`;
          if (!sourcePlan || !targetPlan || pendingReassignStop.planIdx === targetPlanIdx) {
            hideReassignMenu();
            return;
          }
          const stop = sourcePlan.stops.splice(pendingReassignStop.stopIdx, 1)[0];
          if (!stop) {
            hideReassignMenu();
            return;
          }
          const confirmed = await confirmReassignment(sourceLabel, targetLabel);
          if (!confirmed) {
            sourcePlan.stops.splice(pendingReassignStop.stopIdx, 0, stop);
            hideReassignMenu();
            return;
          }
          targetPlan.stops.push(stop);
          refreshOrderBadges(pendingReassignStop.planIdx);
          refreshOrderBadges(targetPlanIdx);
          redrawPlan(pendingReassignStop.planIdx);
          redrawPlan(targetPlanIdx);
          renderClusterSummary();
          updateReassignList();
          hideReassignMenu();
          notify(`Parada reasignada a ${targetPlan.carrier?.name ?? `Cluster ${targetPlanIdx + 1}`}`, 'success');
        };

      document.addEventListener('click', (event) => {
        if (reassignMenu && event.target.closest('#solverReassignMenu')) return;
        hideReassignMenu();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          hideReassignMenu();
        }
      });

      updateReassignList();

      const generateBtn = document.getElementById('generateRoutesBtn');
      const generateForm = document.getElementById('generateForm');
      const plansInput = document.getElementById('plansInput');
      const depotCheckbox = document.getElementById('returnToDepot');
      const depotWrapper = document.getElementById('depotSelectWrapper');
      const depotSelect = document.getElementById('locationSelect');
      const hiddenReturn = document.getElementById('generateReturnDepot');
      const hiddenLocation = document.getElementById('generateLocationId');
      const avoidTolls = document.getElementById('avoidTolls');
      const avoidHighways = document.getElementById('avoidHighways');
      const hiddenAvoidTolls = document.getElementById('generateAvoidTolls');
      const hiddenAvoidHighways = document.getElementById('generateAvoidHighways');
      const scheduledDateInput = document.getElementById('generateScheduledDate'); // el visible
      const scheduledDateHidden = document.getElementById('generateScheduledDateHidden'); // el hidden del generateForm

      const toggleDepot = () => {
        if (!depotWrapper || !depotCheckbox) return;
        depotWrapper.classList.toggle('d-none', !depotCheckbox.checked);
      };
      depotCheckbox?.addEventListener('change', toggleDepot);
      toggleDepot();

      generateBtn?.addEventListener('click', () => {
        if (!plans.length) {
          notify('No hay propuestas para generar rutas.');
          return;
        }
        const wantsDepot = depotCheckbox?.checked;
        const selectedLocation = depotSelect?.value || '';
        if (wantsDepot && !selectedLocation) {
          notify('Selecciona un depósito para regresar.');
          return;
        }
        const payload = plans.map((plan, idx) => {
          const activeCodes = Array.from(document.querySelectorAll(`.stop-select[data-plan="${idx}"]:checked`))
            .map(cb => String(cb.dataset.code));
          const stops = (plan.stops || []).filter(stop =>
            activeCodes.includes(String(stop.code))
          ).map(stop => ({
            code: stop.code,
            address: stop.address,
            lat: stop.lat ?? stop.latitude,
            lng: stop.lng ?? stop.longitude,
            priority: stop.priority,
            loose_stop_id: stop.loose_stop_id ?? stop.traffic_loose_stop_id,
          }));
          
          // Obtener el transporte seleccionado para este transportista
          const carrierId = plan.carrier?.id ?? plan.carrier_id ?? plan.id;
          const transporteSelect = document.querySelector(`.transporte-select[data-transportista-id="${carrierId}"]`);
          const transporteId = transporteSelect?.value ? parseInt(transporteSelect.value) : null;
          
          // DEBUG: Mostrar qué se está enviando
          if (idx === 0 && stops.length > 0) {
            console.log('DEBUG: First stop in payload:', stops[0]);
            console.log('DEBUG: All stops in payload:', stops);
            console.log('DEBUG: TransporteId:', transporteId);
          }
          
          return {
            carrier_id: carrierId,
            transporte_id: transporteId,
            stops,
          };
        }).filter(plan => plan.carrier_id && plan.stops && plan.stops.length >= 2);
        
        console.log('DEBUG: Final payload before submit:', payload);

        if (!payload.length) {
          notify('Selecciona al menos 2 paradas por transportista para generar rutas.');
          return;
        }

        if (plansInput) {
          plansInput.value = JSON.stringify(payload);
        }
        if (hiddenReturn) hiddenReturn.value = wantsDepot ? '1' : '0';
        if (hiddenLocation) hiddenLocation.value = wantsDepot ? selectedLocation : '';
        if (hiddenAvoidTolls) hiddenAvoidTolls.value = avoidTolls?.checked ? '1' : '0';
        if (hiddenAvoidHighways) hiddenAvoidHighways.value = avoidHighways?.checked ? '1' : '0';

        if (scheduledDateHidden) {
          scheduledDateHidden.value = scheduledDateInput?.value || '';
        }

        generateForm?.submit();
      });
    })();
  </script>
@endpush
