@extends('layouts.app')

@section('title', 'Direcciones sueltas')

@section('content')
  @php
    $formatFieldLabels = \App\Models\TrafficLooseStopImportFormat::fieldLabels();
  @endphp
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Direcciones sin ruta asignada</h1>
      <p class="text-muted mb-0">
        Visualiza todas las direcciones subidas que quedaron sueltas y combinalas para armar nuevas rutas.
      </p>
    </div>
    <div class="page-actions d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.routes.report') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a reporte
      </a>
      <a class="btn btn-outline-primary" href="{{ route('traffic.solver.index') }}">
        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Abrir solver
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  @if(!empty($warnings))
    <div class="alert alert-warning d-none">
      {!! implode('<br>', $warnings) !!}
    </div>
  @endif

  <div class="row g-4 mb-3">
    <div class="col-lg-8">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div>
            <h2 class="h5 mb-0">Mapa de direcciones sueltas</h2>
            <small class="text-muted">Usa Ctrl + click sobre el mapa para sumar o quitar puntos.</small>
          </div>
          <span class="badge text-bg-light">{{ $stopsForMap->count() }} puntos activos</span>
        </div>
        <div class="card-body p-0">
          <div id="looseMap" class="map-frame" style="height: 520px;"></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h2 class="h6 mb-0 text-uppercase text-muted" style="letter-spacing: 0.08em;">Seleccion y filtros</h2>
              <p class="mb-0 small text-muted">Elige puntos con Ctrl + click en el mapa o la lista.</p>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('traffic.loose.template') }}" target="_blank" rel="noreferrer">
                <i class="fa-solid fa-file-excel me-1"></i> Plantilla Excel
              </a>
              <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#looseImportModal">
                <i class="fa-solid fa-upload me-1"></i> Subir Excel
              </button>
            </div>
          </div>
          <div class="small text-muted mb-3">
            La primera fila contiene los títulos en castellano; a partir de la fila 2 cargá tus direcciones antes de subir el archivo.
          </div>
          <div class="d-flex align-items-center justify-content-between mb-2 gap-2">
            <span class="badge text-bg-primary" id="selectedCounter">0 seleccionados</span>

            <div class="btn-group btn-group-sm" role="group" aria-label="Seleccion global">
              <button type="button" class="btn btn-outline-secondary" id="selectAllBtn">
                <i class="fa-solid fa-check-double me-1"></i> Todos
              </button>
              <button type="button" class="btn btn-outline-secondary" id="selectNoneBtn">
                <i class="fa-solid fa-ban me-1"></i> Ninguno
              </button>
            </div>
          </div>


          <form class="row g-2 mb-3" method="GET" action="{{ route('traffic.loose.index') }}">
            <div class="col-12">
              <label class="form-label">Cliente</label>
              <select class="form-select" name="clients[]" multiple size="4">
                @foreach($clients as $client)
                  <option value="{{ $client->id }}" {{ in_array($client->id, $filters['clients'] ?? [], false) ? 'selected' : '' }}>
                    {{ $client->business_name ?: $client->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">Filtra los puntos por cliente origen.</div>
            </div>
            <div class="col-6">
              <label class="form-label">Fecha pedido desde</label>
              <input type="date" class="form-control" name="order_from" value="{{ $filters['order_from'] ?? '' }}">
            </div>
            <div class="col-6">
              <label class="form-label">Fecha pedido hasta</label>
              <input type="date" class="form-control" name="order_to" value="{{ $filters['order_to'] ?? '' }}">
            </div>
            <div class="col-6">
              <label class="form-label">Prioridad</label>
              <select class="form-select" name="priority">
                <option value="">Todas</option>
                @foreach(['Alta', 'Media', 'Baja'] as $priority)
                  <option value="{{ $priority }}" {{ ($filters['priority'] ?? '') === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Código / Tracking / Barras</label>
              <input type="text" class="form-control" name="code" value="{{ $filters['code'] ?? '' }}" placeholder="Buscar en código, tracking o barras">
            </div>
            <div class="col-12 d-flex gap-2">
              <button class="btn btn-outline-primary flex-grow-1" type="submit">
                <i class="fa-solid fa-filter me-1"></i> Aplicar filtros
              </button>
              <a class="btn btn-outline-secondary" href="{{ route('traffic.loose.index') }}">Limpiar</a>
            </div>
          </form>

          <div class="border rounded-3 p-3 mb-3 bg-light">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <strong class="small text-muted text-uppercase">Envio al solver</strong>
              <span class="small text-muted">Paso rapido</span>
            </div>
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small mb-1">Balancea la carga</label>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="looseBalancedLoadSwitch" checked>
                  <label class="form-check-label small" for="looseBalancedLoadSwitch">
                    Distribuir las paradas equitativamente entre los transportistas seleccionados.
                  </label>
                </div>
                <div class="form-text small text-muted">
                  Activa para que el solver calcule <strong>max. paradas por transportista = paradas / transportistas elegidos</strong>.
                  Desactívala para dejar que el clustering decida sin límite.
                </div>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1">Estrategia</label>
                <select class="form-select form-select-sm" id="looseStrategy">
                  <option value="route">Eficiencia</option>
                  <option value="cost">Costo</option>
                  <option value="weight">Ponderada</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Clusterización</label>
                <select class="form-select form-select-sm" id="looseClusterBy">
                  <option value="address" {{ ($clusterBy ?? 'address') === 'address' ? 'selected' : '' }}>Dirección (densidad)</option>
                  <option value="priority" {{ ($clusterBy ?? 'address') === 'priority' ? 'selected' : '' }}>Prioridad</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1 d-none">Proveedor de mapas</label>
                <select class="form-select form-select-sm d-none" id="looseMapProvider">
                  <option value="mapbox" selected>Mapbox</option>
                </select>
              </div>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-primary flex-grow-1" type="button" id="sendToSolverBtn" disabled>
                <i class="fa-solid fa-road me-1"></i> Armar ruta en solver
              </button>
              <button class="btn btn-outline-secondary" type="button" id="clearSelectionBtn">
                Limpiar
              </button>
            </div>
            <div class="small text-muted mt-2">
              Al enviar abrimos el solver con las paradas elegidas; al generar la ruta dejaran de aparecer como sueltas.
            </div>
          </div>

          <div>
            <div class="small text-muted text-uppercase mb-2">Clientes</div>
            <div class="d-flex flex-wrap gap-2">
              @forelse($clientColors as $clientId => $color)
                @php
                  $client = $clients->firstWhere('id', $clientId);
                  $clientLabel = $client ? ($client->business_name ?: $client->name) : 'Cliente';
                @endphp
                <span class="badge" style="background: {{ $color }}; color: #fff;">{{ $clientLabel }}</span>
              @empty
                <span class="text-muted small">Sin clientes cargados.</span>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div>
            <h2 class="h5 mb-0">Listado de direcciones sueltas</h2>
            <small class="text-muted d-none">Ctrl + click sobre la fila para seleccionarla.</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-danger" type="button" id="bulkDeleteBtn">
              <i class="fa-solid fa-trash me-1"></i> Eliminar seleccionados
            </button>
            <span class="badge text-bg-light">{{ $stops->count() }} registros</span>
          </div>
        </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 50px;"></th>
            <th style="width: 90px;">Acción</th>
            <th>Cliente</th>
            <th>Pedido</th>
            <th>Direccion</th>
            <th>Prioridad</th>
            <th>Notas</th>
            <th style="width: 130px;">Detalle</th>
          </tr>
        </thead>
        <tbody id="looseTableBody">
          @forelse($stops as $stop)
            @php
              $client = $stop->party;
              $color = $clientColors[$stop->party_id] ?? '#2563eb';
              $hasCoords = is_numeric($stop->latitude) && is_numeric($stop->longitude);
              $length = is_numeric($stop->length) ? (float) $stop->length : null;
              $width = is_numeric($stop->width) ? (float) $stop->width : null;
              $height = is_numeric($stop->height) ? (float) $stop->height : null;
              $weightActual = is_numeric($stop->weight_actual) ? (float) $stop->weight_actual : null;
              $weightVolumetric = is_numeric($stop->weight_volumetric) ? (float) $stop->weight_volumetric : null;
              $dimensionParts = [];
              foreach ([$length, $width, $height] as $measure) {
                if ($measure !== null) {
                  $dimensionParts[] = rtrim(rtrim(number_format($measure, 2, '.', ''), '0'), '.');
                }
              }
              $dimensionsLabel = count($dimensionParts) === 3 ? implode(' x ', $dimensionParts) . ' cm' : null;
              $weightActualLabel = $weightActual !== null ? rtrim(rtrim(number_format($weightActual, 2, '.', ''), '0'), '.') . ' kg' : null;
              $weightVolumetricLabel = $weightVolumetric !== null ? rtrim(rtrim(number_format($weightVolumetric, 2, '.', ''), '0'), '.') . ' kg' : null;
              $orderDateLabel = $stop->order_date ? $stop->order_date->format('d/m/Y') : null;
            @endphp
            <tr data-stop-id="{{ $stop->id }}" class="loose-row">
              <td>
                <button type="button"
                        class="btn btn-sm btn-outline-danger loose-delete-btn"
                        data-route="{{ route('traffic.loose.destroy', $stop) }}"
                        data-readonly-block="true">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
              <td>
                <input class="form-check-input loose-check" type="checkbox" value="{{ $stop->id }}" {{ $hasCoords ? '' : 'disabled' }}>
              </td>
              <td>
                <span class="badge" style="background: {{ $color }}; color: #fff;">{{ $client ? ($client->business_name ?: $client->name) : 'Sin cliente' }}</span>
              </td>
              <td class="text-muted">{{ $stop->code ?: '' }}</td>
              <td>
                <div class="fw-semibold">{{ $stop->address }}</div>
                <div class="small text-muted">
                  Lat: {{ $stop->latitude ?? '' }} / Lng: {{ $stop->longitude ?? '' }}
                </div>
                @if($stop->recipient_name || $stop->recipient_contact)
                  <div class="small text-muted">Destinatario: {{ $stop->recipient_name ?? '' }} {{ $stop->recipient_contact ? '(' . $stop->recipient_contact . ')' : '' }}</div>
                @endif
                @if($stop->sender_name || $stop->sender_contact)
                  <div class="small text-muted">Remitente: {{ $stop->sender_name ?? '' }} {{ $stop->sender_contact ? '(' . $stop->sender_contact . ')' : '' }}</div>
                @endif
                @unless($hasCoords)
                  <div class="small text-danger">Sin coordenadas para ruteo</div>
                @endunless
              </td>
              <td>{{ $stop->priority ?: 'Media' }}</td>
              <td class="text-muted">{{ $stop->notes ?: '' }}</td>
              <td>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#looseDetail-{{ $stop->id }}"
                        aria-expanded="false"
                        aria-controls="looseDetail-{{ $stop->id }}">
                  <i class="fa-regular fa-circle-down me-1"></i> Detalle
                </button>
              </td>
            </tr>
            <tr class="collapse bg-light" id="looseDetail-{{ $stop->id }}">
              <td colspan="8" class="p-3 border-top-0">
                <div class="row g-3 small">
                  <div class="col-md-3">
                    <div class="text-uppercase text-muted mb-1" style="letter-spacing: 0.08em;">Remitente</div>
                    <div class="fw-semibold">{{ $stop->sender_name ?? 'Sin remitente' }}</div>
                    <div class="text-muted">{{ $stop->sender_contact ?? 'Sin contacto' }}</div>
                    <div class="text-muted">{{ $stop->sender_address ?? 'Sin dirección' }}</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-uppercase text-muted mb-1" style="letter-spacing: 0.08em;">Destinatario</div>
                    <div class="fw-semibold">{{ $stop->recipient_name ?? 'Sin destinatario' }}</div>
                    <div class="text-muted">{{ $stop->recipient_contact ?? 'Sin contacto' }}</div>
                    <div class="text-muted">{{ $stop->recipient_address ?? 'Sin dirección' }}</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-uppercase text-muted mb-1" style="letter-spacing: 0.08em;">Pieza</div>
                    <div><strong>Tracking:</strong> {{ $stop->tracking_number ?? '' }}</div>
                    <div><strong>Cód. barras:</strong> {{ $stop->barcode ?? '' }}</div>
                    <div><strong>Contenido:</strong> {{ $stop->content_description ?? '' }}</div>
                    <div><strong>Valor declarado:</strong> {{ $stop->declared_value !== null ? ('$ ' . number_format((float) $stop->declared_value, 2)) : '' }}</div>
                    <div><strong>Archivo origen:</strong> {{ $stop->source_filename ?? '' }}</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-uppercase text-muted mb-1" style="letter-spacing: 0.08em;">Datos del pedido</div>
                    <div><strong>Fecha pedido:</strong> {{ $orderDateLabel ?? '' }}</div>
                    <div><strong>Dimensiones:</strong> {{ $dimensionsLabel ?? '' }}</div>
                    <div><strong>Peso real:</strong> {{ $weightActualLabel ?? '' }}</div>
                    <div><strong>Peso volumetrico:</strong> {{ $weightVolumetricLabel ?? '' }}</div>
                    <div><strong>Notas:</strong> {{ $stop->notes ?: '' }}</div>
                  </div>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-4">No hay direcciones sueltas pendientes.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <form id="looseSolverForm" class="d-none" method="POST" action="{{ route('traffic.solver.preview') }}">
    @csrf
    <input type="hidden" name="manual_addresses" id="looseSelectedPayload" value="">
      <input type="hidden" name="max_stops" id="looseMaxStopsInput" value="15">
      <input type="hidden" name="balanced_load" id="looseBalancedLoadInput" value="1">
    <input type="hidden" name="strategy" id="looseStrategyInput" value="route">
    <input type="hidden" name="map_provider" id="looseMapProviderInput" value="{{ $mapProvider ?? 'openstreet' }}">
    <input type="hidden" name="cluster_by" id="looseClusterByInput" value="{{ $clusterBy ?? 'address' }}">
    <input type="hidden" name="solver_action" value="solve">
  </form>

  <div class="modal fade" id="clusterPreviewModal" tabindex="-1" aria-labelledby="clusterPreviewModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="clusterPreviewModalLabel">Previsualización de clusters</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-lg-9">
              <div id="clusterPreviewMap" style="height: 460px; border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb;"></div>
            </div>
            <div class="col-lg-3">
              <div class="small text-muted text-uppercase mb-2">Clusters</div>
              <div class="list-group" id="clusterPreviewList"></div>
              <div class="small text-muted mt-3" id="clusterPreviewMeta"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="clusterPreviewAdvanceBtn">
            <i class="fa-solid fa-forward me-1"></i> Avanzar
          </button>
        </div>
      </div>
    </div>
  </div>
  <form id="looseBulkDeleteForm" class="d-none" method="POST" action="{{ route('traffic.loose.destroy.bulk') }}">
    @csrf
    @method('DELETE')
  </form>

  <div class="modal fade" id="looseImportModal" tabindex="-1" aria-labelledby="looseImportModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="looseImportModalLabel">Importar direcciones sueltas</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <form id="looseImportForm" method="POST" action="{{ route('traffic.loose.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Cliente</label>
                <select class="form-select" name="party_id" required>
                  <option value="">Selecciona cliente</option>
                  @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ (int) $selectedCustomer === (int) $client->id ? 'selected' : '' }}>
                      {{ $client->business_name ?: $client->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Proveedor de mapas</label>
                <select class="form-select" name="map_provider">
                  <option value="openstreet" {{ ($mapProvider ?? 'openstreet') === 'openstreet' ? 'selected' : '' }}>OpenStreetMap</option>
                  <option value="mapbox" {{ ($mapProvider ?? 'openstreet') === 'mapbox' ? 'selected' : '' }}>Mapbox</option>
                  <option value="google" {{ ($mapProvider ?? 'openstreet') === 'google' ? 'selected' : '' }}>Google Maps</option>
                </select>
              </div>
            <div class="col-md-3">
              <label class="form-label">Hoja (opcional)</label>
              <input class="form-control" type="text" name="sheet" placeholder="Nombre o index">
            </div>
          </div>
            <x-traffic.excel-importer
              form-id="looseImportForm"
              prefix="loose"
              :uploaded-stops="$uploadedStops ?? collect()"
              :map-provider="$mapProvider ?? 'mapbox'"
              :warnings="$warnings ?? []"
              :show-map="false"
              preview-file-url="{{ route('traffic.solver.preview.file') }}"
            />
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="looseFormatModal" tabindex="-1" aria-labelledby="looseFormatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="looseFormatModalLabel">Formato de importación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <strong id="looseFormatModalName">Sin nombre</strong>
            <div class="small text-muted" id="looseFormatModalMeta"></div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Campo</th>
                  <th>Columna</th>
                  <th>Fila</th>
                </tr>
              </thead>
              <tbody id="looseFormatModalFields">
                <tr>
                  <td colspan="3" class="text-center text-muted small">Seleccioná un cliente con formato.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
  <div id="looseMapContextMenu" class="d-none">
    <button type="button" class="btn btn-sm btn-link text-danger w-100 text-start" data-action="delete">
      <i class="fa-solid fa-trash-can me-2"></i>Eliminar dirección
    </button>
  </div>
@endsection

@push('styles')
  <style>
    .loose-row.selected {
      background: #eef2ff;
    }
    .map-frame {
      min-height: 320px;
      background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
      border-bottom-left-radius: 10px;
      border-bottom-right-radius: 10px;
    }
    #looseMapContextMenu {
      position: absolute;
      z-index: 1600;
      min-width: 200px;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, 0.2);
      border-radius: 0.35rem;
      box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.15);
    }
    #looseMapContextMenu button {
      border: none;
      border-radius: 0;
      padding: 0.5rem 0.75rem;
    }
    #looseMapContextMenu button:hover {
      background: rgba(220, 38, 38, 0.08);
    }
    .loose-map-tooltip {
      background: #0f172a;
      color: #fff;
      border: none;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.35);
      border-radius: 0.5rem;
      padding: 8px 10px;
    }
    .loose-map-tooltip .small,
    .loose-map-tooltip .text-muted {
      color: rgba(255, 255, 255, 0.85) !important;
    }
  </style>
@endpush

@push('scripts')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


<script>
  (() => {
    const stops = @json($stopsForMap);
    const stopsById = new Map(stops.map((stop) => [Number(stop.id), stop]));
    
    console.log('DEBUG: stops from @json($stopsForMap)', {
      stops_count: stops.length,
      first_stop: stops.length > 0 ? stops[0] : null,
      first_stop_has_id: stops.length > 0 ? ('id' in stops[0]) : false,
    });
    
    console.log('DEBUG: stopsById map', {
      size: stopsById.size,
      first_entry: stopsById.size > 0 ? stopsById.entries().next().value : null,
    });

    // âœ… selected = "tildados/seleccionados"
    const selected = new Set();
    const markersById = new Map();
    const looseDeleteRoutes = new Map();
    const contextMenuEl = document.getElementById('looseMapContextMenu');
    const contextMenuButton = contextMenuEl?.querySelector('button[data-action="delete"]');

    const hideContextMenu = () => {
      if (!contextMenuEl) {
        return;
      }
      contextMenuEl.classList.add('d-none');
      contextMenuEl.style.removeProperty('top');
      contextMenuEl.style.removeProperty('left');
      delete contextMenuEl.dataset.stopId;
    };

    const showContextMenu = (pageX, pageY, stopId) => {
      if (!contextMenuEl || !Number.isFinite(stopId)) {
        return;
      }
      contextMenuEl.style.left = `${pageX}px`;
      contextMenuEl.style.top = `${pageY}px`;
      contextMenuEl.dataset.stopId = String(stopId);
      contextMenuEl.classList.remove('d-none');
    };

    contextMenuButton?.addEventListener('click', (event) => {
      event.stopPropagation();
      const stopId = Number(contextMenuEl?.dataset?.stopId);
      hideContextMenu();
      const route = looseDeleteRoutes.get(stopId);
      if (route && typeof window.confirmLooseDeletion === 'function') {
        window.confirmLooseDeletion(route);
      }
    });

    document.addEventListener('click', hideContextMenu);
    document.addEventListener('scroll', hideContextMenu, true);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        hideContextMenu();
      }
    });
    contextMenuEl?.addEventListener('click', (event) => event.stopPropagation());
    contextMenuEl?.addEventListener('contextmenu', (event) => event.preventDefault());

    const mapEl = document.getElementById('looseMap');
    mapEl?.addEventListener('contextmenu', (event) => event.preventDefault());
    const selectedCounter = document.getElementById('selectedCounter');
    const sendBtn = document.getElementById('sendToSolverBtn');
    const clearBtn = document.getElementById('clearSelectionBtn');

    // âœ… NUEVO: botones Todos / Ninguno
    const selectAllBtn = document.getElementById('selectAllBtn');
    const selectNoneBtn = document.getElementById('selectNoneBtn');

    const strategySelect = document.getElementById('looseStrategy');
    const clusterBySelect = document.getElementById('looseClusterBy');
    const providerSelect = document.getElementById('looseMapProvider');

    const solverForm = document.getElementById('looseSolverForm');
    const solverPayloadInput = document.getElementById('looseSelectedPayload');
    const solverMaxInput = document.getElementById('looseMaxStopsInput');
    const balancedLoadSwitch = document.getElementById('looseBalancedLoadSwitch');
    const solverBalancedInput = document.getElementById('looseBalancedLoadInput');
    const solverStrategyInput = document.getElementById('looseStrategyInput');
    const solverProviderInput = document.getElementById('looseMapProviderInput');
    const solverClusterByInput = document.getElementById('looseClusterByInput');
    const mapProviderSelect = document.getElementById('looseMapProvider');

    const notify = (msg, type = 'warning') => {
      if (window.nygAlert) {
        window.nygAlert(msg, type);
      }
    };
    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
    const buildStopTooltip = (stop) => {
      if (!stop) return '';

      const lines = [];
      lines.push(`<div class="fw-semibold mb-1">${escapeHtml(stop.address || 'Sin dirección')}</div>`);

      const meta = [];
      if (stop.code) meta.push(`Pedido: ${escapeHtml(stop.code)}`);
      if (stop.order_date) meta.push(`Fecha: ${escapeHtml(stop.order_date)}`);
      if (meta.length) {
        lines.push(`<div class="small text-muted">${meta.join(' • ')}</div>`);
      }

      if (stop.recipient_name || stop.recipient_contact || stop.recipient_address) {
        const contact = stop.recipient_contact ? ` (${escapeHtml(stop.recipient_contact)})` : '';
        lines.push(`<div class="small"><strong>Destinatario:</strong> ${escapeHtml(stop.recipient_name || 'Contacto')}${contact}</div>`);
        if (stop.recipient_address) {
          lines.push(`<div class="small text-muted">${escapeHtml(stop.recipient_address)}</div>`);
        }
      }

      if (stop.sender_name || stop.sender_contact || stop.sender_address) {
        const contact = stop.sender_contact ? ` (${escapeHtml(stop.sender_contact)})` : '';
        lines.push(`<div class="small"><strong>Remitente:</strong> ${escapeHtml(stop.sender_name || 'Contacto')}${contact}</div>`);
        if (stop.sender_address) {
          lines.push(`<div class="small text-muted">${escapeHtml(stop.sender_address)}</div>`);
        }
      }

      const extras = [];
      if (stop.tracking_number) extras.push(`Tracking ${escapeHtml(stop.tracking_number)}`);
      if (stop.barcode) extras.push(`Barras ${escapeHtml(stop.barcode)}`);
      if (extras.length) {
        lines.push(`<div class="small text-muted">${extras.join(' • ')}</div>`);
      }

      if (stop.priority) {
        lines.push(`<div class="small"><strong>Prioridad:</strong> ${escapeHtml(stop.priority)}</div>`);
      }

      if (stop.notes) {
        lines.push(`<div class="small text-muted mt-1">${escapeHtml(stop.notes)}</div>`);
      }

      return lines.join('');
    };

    // Preferencia provider
    const syncMapProvider = (value) => {
      if (mapProviderSelect && value) mapProviderSelect.value = value;
      if (solverProviderInput && value) solverProviderInput.value = value;
      if (window.localStorage) localStorage.setItem('loose_map_provider', value);
    };

    if (mapProviderSelect && window.localStorage) {
      const stored = localStorage.getItem('loose_map_provider');
      if (stored) syncMapProvider(stored);
      else if (solverProviderInput?.value) syncMapProvider(solverProviderInput.value);
    }
    mapProviderSelect?.addEventListener('change', (event) => syncMapProvider(event.target.value));

    // Preferencia + URL param: cluster_by
    const syncClusterBy = (value) => {
      const v = value === 'priority' ? 'priority' : 'address';
      if (clusterBySelect) clusterBySelect.value = v;
      if (solverClusterByInput) solverClusterByInput.value = v;
      if (window.localStorage) localStorage.setItem('loose_cluster_by', v);

      try {
        const url = new URL(window.location.href);
        url.searchParams.set('cluster_by', v);
        window.history.replaceState({}, '', url.toString());
      } catch (e) {
        // ignore
      }
    };

    if (clusterBySelect) {
      // Prioridad: querystring/server-rendered -> localStorage
      const fromUrl = (() => {
        try { return new URL(window.location.href).searchParams.get('cluster_by'); } catch (e) { return null; }
      })();
      const fromDom = clusterBySelect.value;
      const stored = window.localStorage ? localStorage.getItem('loose_cluster_by') : null;
      const initial = fromUrl || fromDom || stored || (solverClusterByInput?.value || 'address');
      syncClusterBy(initial);
    }
    clusterBySelect?.addEventListener('change', (event) => syncClusterBy(event.target.value));

    // âœ… Mapa / layer
    let map = null;
    let layer = null;

    // âœ… Inicial: todos "seleccionados" si tienen coords (vienen en stopsForMap)
    stops.forEach((s) => {
      const id = Number(s.id);
      const hasCoords = Number.isFinite(Number(s.lat)) && Number.isFinite(Number(s.lng));
      if (hasCoords) selected.add(id);
    });

    // âœ… Estilo: tildado vs destildado
    const refreshMarkersStyle = () => {
      markersById.forEach((marker, id) => {
        const stop = stopsById.get(id);
        if (!stop || !marker.setStyle) return;

        const baseColor = stop.color || '#2563eb';
        const isSelected = selected.has(id);

        marker.setStyle({
          color: isSelected ? baseColor : '#64748b',
          fillColor: isSelected ? baseColor : '#cbd5e1',
          fillOpacity: isSelected ? 0.95 : 0.35,
          radius: isSelected ? 11 : 7,
          weight: isSelected ? 3 : 2,
        });

        if (isSelected) marker.bringToFront();
      });
    };

    const updateCounter = () => {
      if (selectedCounter) selectedCounter.textContent = `${selected.size} seleccionados`;
      if (sendBtn) sendBtn.disabled = selected.size < 2;

      // âœ… sync tabla (row class + checkbox)
      document.querySelectorAll('.loose-row').forEach((row) => {
        const id = Number(row.dataset.stopId);
        row.classList.toggle('selected', selected.has(id));

        const checkbox = row.querySelector('.loose-check');
        if (checkbox && !checkbox.disabled) {
          checkbox.checked = selected.has(id);
        }
      });

      refreshMarkersStyle();
    };

    const setSelected = (id, isSelected) => {
      if (!stopsById.has(id)) return;
      if (isSelected) selected.add(id);
      else selected.delete(id);
      updateCounter();
    };

    const toggleSelection = (id) => {
      if (!stopsById.has(id)) return;
      setSelected(id, !selected.has(id));
    };

    // âœ… NUEVO: ids seleccionables (solo checkboxes habilitados = tienen coords en tabla)
    const selectableIds = () => {
      return Array.from(document.querySelectorAll('.loose-check'))
        .filter(ch => !ch.disabled)
        .map(ch => Number(ch.value))
        .filter(id => Number.isFinite(id));
    };

    // âœ… NUEVO: Todos / Ninguno
    const selectAll = () => {
      selected.clear();
      selectableIds().forEach(id => selected.add(id));
      updateCounter();
    };

    const selectNone = () => {
      selected.clear();
      updateCounter();
    };

    // âœ… MAPA
    if (mapEl && stops.length) {
      map = L.map('looseMap', { attributionControl: false });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map);
      layer = L.featureGroup().addTo(map);

      stops.forEach((stop) => {
        const id = Number(stop.id);
        const color = stop.color || '#2563eb';

        const lat = Number(stop.lat);
        const lng = Number(stop.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const marker = L.circleMarker([lat, lng], {
          color,
          fillColor: color,
          fillOpacity: 0.8,
          radius: 8,
          weight: 2,
        }).addTo(layer);

        markersById.set(id, marker);

        const tooltipHtml = buildStopTooltip(stop);
        if (tooltipHtml) {
          marker.bindTooltip(tooltipHtml, {
            direction: 'top',
            offset: [0, -10],
            opacity: 0.95,
            sticky: true,
            className: 'loose-map-tooltip',
          });
          marker.on('mouseover', () => marker.openTooltip());
          marker.on('mouseout', () => marker.closeTooltip());
        }

        // Ctrl/Meta click: toggle SIN tooltip
        marker.on('click', (event) => {
          const withCtrl = event.originalEvent && (event.originalEvent.ctrlKey || event.originalEvent.metaKey);

          // nunca abrir tooltip
          event.originalEvent?.preventDefault?.();
          event.originalEvent?.stopPropagation?.();
          marker.closeTooltip();

          if (!withCtrl) return;

          toggleSelection(id);
        });
        marker.on('contextmenu', (event) => {
          const pageX = event.originalEvent?.pageX ?? 0;
          const pageY = event.originalEvent?.pageY ?? 0;
          event.originalEvent?.preventDefault?.();
          event.originalEvent?.stopPropagation?.();
          showContextMenu(pageX, pageY, id);
        });
      });

      if (layer.getLayers().length) {
        map.fitBounds(layer.getBounds(), { padding: [20, 20] });
      } else {
        map.setView([-34.6037, -58.3816], 10);
      }
    } else if (mapEl) {
      mapEl.innerHTML = '<div class="p-4 text-center text-muted">No hay direcciones para mostrar.</div>';
    }

    // âœ… CLICK EN FILA: solo con Ctrl/Meta
    document.querySelectorAll('.loose-row').forEach((row) => {
      const rowId = Number(row.dataset.stopId);
      const deleteButton = row.querySelector('.loose-delete-btn');
      if (deleteButton?.dataset?.route && Number.isFinite(rowId)) {
        looseDeleteRoutes.set(rowId, deleteButton.dataset.route);
      }
      row.addEventListener('click', (event) => {
        if (event.target && event.target.classList && event.target.classList.contains('loose-check')) return;

        const withCtrl = event.ctrlKey || event.metaKey;
        if (!withCtrl) return;

        const id = Number(row.dataset.stopId);
        toggleSelection(id);
      });
    });

    // âœ… CHECKBOX: selecciona/deselecciona
    document.querySelectorAll('.loose-check').forEach((check) => {
      check.addEventListener('click', (e) => e.stopPropagation());

      check.addEventListener('change', (event) => {
        const id = Number(event.target.value);
        const checked = !!event.target.checked;
        setSelected(id, checked);
      });
    });

    // âœ… NUEVO: Todos / Ninguno
    selectAllBtn?.addEventListener('click', selectAll);
    selectNoneBtn?.addEventListener('click', selectNone);

    // âœ… Limpiar = Ninguno (así­ no duplicás lógica)
    clearBtn?.addEventListener('click', selectNone);

    const clusterModalEl = document.getElementById('clusterPreviewModal');
    const clusterAdvanceBtn = document.getElementById('clusterPreviewAdvanceBtn');
    const clusterListEl = document.getElementById('clusterPreviewList');
    const clusterMetaEl = document.getElementById('clusterPreviewMeta');
    const clusterMapEl = document.getElementById('clusterPreviewMap');

    let clusterPreviewMap = null;
    let clusterPreviewLayer = null;
    let pendingSolverPayload = null;
    let pendingClusterBy = 'address';

    const palette = [
      '#2563eb', '#ef4444', '#16a34a', '#f59e0b', '#7c3aed', '#0ea5e9',
      '#db2777', '#84cc16', '#14b8a6', '#f97316', '#64748b', '#a855f7',
    ];

    const haversineKm = (lat1, lng1, lat2, lng2) => {
      const toRad = (v) => (v * Math.PI) / 180;
      const R = 6371;
      const dLat = toRad(lat2 - lat1);
      const dLng = toRad(lng2 - lng1);
      const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * (Math.sin(dLng / 2) ** 2);
      return 2 * R * Math.asin(Math.sqrt(a));
    };

    const centroid = (points) => {
      if (!points.length) return null;
      const sum = points.reduce((acc, p) => ({ lat: acc.lat + p.lat, lng: acc.lng + p.lng }), { lat: 0, lng: 0 });
      return { lat: sum.lat / points.length, lng: sum.lng / points.length };
    };

    const dbscan = (points, epsKm, minPts) => {
      const UNCLASSIFIED = 0;
      const NOISE = -1;
      const labels = new Array(points.length).fill(UNCLASSIFIED);
      let clusterId = 0;

      const regionQuery = (idx) => {
        const p = points[idx];
        const neighbors = [];
        for (let j = 0; j < points.length; j++) {
          const q = points[j];
          const d = haversineKm(p.lat, p.lng, q.lat, q.lng);
          if (d <= epsKm) neighbors.push(j);
        }
        return neighbors;
      };

      const expandCluster = (idx, neighbors, cid) => {
        labels[idx] = cid;
        const queue = [...neighbors];
        const seen = new Set(queue);

        while (queue.length) {
          const nIdx = queue.shift();
          if (labels[nIdx] === NOISE) labels[nIdx] = cid;
          if (labels[nIdx] !== UNCLASSIFIED) continue;
          labels[nIdx] = cid;

          const nNeighbors = regionQuery(nIdx);
          if (nNeighbors.length >= minPts) {
            nNeighbors.forEach((x) => {
              if (!seen.has(x)) {
                seen.add(x);
                queue.push(x);
              }
            });
          }
        }
      };

      for (let i = 0; i < points.length; i++) {
        if (labels[i] !== UNCLASSIFIED) continue;
        const neighbors = regionQuery(i);
        if (neighbors.length < minPts) {
          labels[i] = NOISE;
          continue;
        }
        clusterId += 1;
        expandCluster(i, neighbors, clusterId);
      }

      const clusters = new Map();
      const noise = [];
      for (let i = 0; i < points.length; i++) {
        const label = labels[i];
        if (label === NOISE) {
          noise.push(points[i]);
          continue;
        }
        if (!clusters.has(label)) clusters.set(label, []);
        clusters.get(label).push(points[i]);
      }

      const clusterArr = Array.from(clusters.entries())
        .map(([id, stops]) => ({ id, stops, centroid: centroid(stops) }))
        .sort((a, b) => b.stops.length - a.stops.length);

      // Noise: asignar al cluster mÃ¡s cercano si existe y no estÃ¡ demasiado lejos; si no, cluster individual
      const maxAssignKm = epsKm * 3;
      let nextId = clusterArr.length ? Math.max(...clusterArr.map(c => c.id)) + 1 : 1;
      noise.forEach((p) => {
        if (!clusterArr.length) {
          clusterArr.push({ id: nextId++, stops: [p], centroid: centroid([p]) });
          return;
        }
        let best = null;
        let bestDist = Infinity;
        clusterArr.forEach((c) => {
          if (!c.centroid) return;
          const d = haversineKm(p.lat, p.lng, c.centroid.lat, c.centroid.lng);
          if (d < bestDist) { bestDist = d; best = c; }
        });
        if (best && bestDist <= maxAssignKm) {
          best.stops.push(p);
          best.centroid = centroid(best.stops);
        } else {
          clusterArr.push({ id: nextId++, stops: [p], centroid: centroid([p]) });
        }
      });

      // Re-ordenar por tamaÃ±o (manteniendo ids)
      return clusterArr.sort((a, b) => b.stops.length - a.stops.length);
    };

    const buildClusters = (payloadStops, clusterBy, maxStopsPerCluster) => {
      const maxStops = Math.max(2, Number(maxStopsPerCluster || 15));
      const by = clusterBy === 'priority' ? 'priority' : 'address';

      const points = payloadStops
        .map(s => ({ ...s, lat: Number(s.lat), lng: Number(s.lng) }))
        .filter(s => Number.isFinite(s.lat) && Number.isFinite(s.lng));

      if (!points.length) return [];

      if (by === 'priority') {
        const order = { 'Alta': 0, 'Media': 1, 'Baja': 2 };
        const groups = new Map();
        points.forEach(p => {
          const key = p.priority || 'Media';
          if (!groups.has(key)) groups.set(key, []);
          groups.get(key).push(p);
        });
        const sortedGroups = Array.from(groups.entries()).sort((a, b) => (order[a[0]] ?? 99) - (order[b[0]] ?? 99));
        const clusters = [];
        let nextId = 1;
        sortedGroups.forEach(([prio, pts]) => {
          const k = Math.max(1, Math.ceil(pts.length / maxStops));
          if (k <= 1) {
            clusters.push({ id: nextId++, priority_group: prio, stops: pts, centroid: centroid(pts) });
            return;
          }
          // opcional: dentro de prioridad, agrupar por cercanÃ­a usando DBSCAN
          const epsKm = 2.0;
          const minPts = 3;
          const sub = dbscan(pts, epsKm, minPts);
          sub.forEach((c) => clusters.push({ id: nextId++, priority_group: prio, stops: c.stops, centroid: c.centroid }));
        });
        return clusters;
      }

      // DirecciÃ³n: DBSCAN por densidad (Haversine)
      const epsKm = 2.0;
      const minPts = 3;
      return dbscan(points, epsKm, minPts);
    };

    const ensureClusterMap = () => {
      if (!clusterMapEl || !window.L) return;
      if (clusterPreviewMap) return;
      clusterPreviewMap = L.map(clusterMapEl, { attributionControl: false });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(clusterPreviewMap);
      clusterPreviewLayer = L.featureGroup().addTo(clusterPreviewMap);
    };

    const renderClustersPreview = (clusters) => {
      ensureClusterMap();
      if (!clusterPreviewMap || !clusterPreviewLayer) return;
      clusterPreviewLayer.clearLayers();
      if (clusterListEl) clusterListEl.innerHTML = '';

      const bounds = [];
      clusters.forEach((cluster, idx) => {
        const color = palette[(cluster.id - 1) % palette.length];
        const count = cluster.stops.length;

        if (clusterListEl) {
          clusterListEl.insertAdjacentHTML('beforeend', `
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle" style="width:10px;height:10px;background:${color};display:inline-block;"></span>
                <div>
                  <div class="fw-semibold">Cluster ${cluster.id}</div>
                  <div class="small text-muted">${count} stops${cluster.priority_group ? ' · ' + cluster.priority_group : ''}</div>
                </div>
              </div>
              <span class="badge text-bg-light">${count}</span>
            </div>
          `);
        }

        cluster.stops.forEach((s) => {
          const marker = L.circleMarker([s.lat, s.lng], {
            color,
            fillColor: color,
            fillOpacity: 0.85,
            radius: 8,
            weight: 2,
          }).addTo(clusterPreviewLayer);
          bounds.push([s.lat, s.lng]);
        });

        if (cluster.centroid) {
          const c = cluster.centroid;
          const centroidMarker = L.circleMarker([c.lat, c.lng], {
            color,
            fillColor: '#ffffff',
            fillOpacity: 1,
            radius: 5,
            weight: 3,
          }).addTo(clusterPreviewLayer);
          bounds.push([c.lat, c.lng]);
        }
      });

      if (clusterMetaEl) {
        const total = clusters.reduce((acc, c) => acc + c.stops.length, 0);
        clusterMetaEl.textContent = `${clusters.length} clusters · ${total} paradas seleccionadas`;
      }

      if (bounds.length) {
        clusterPreviewMap.fitBounds(bounds, { padding: [20, 20] });
      }

      setTimeout(() => clusterPreviewMap.invalidateSize(), 200);
    };

    const applyLoadBalanceSettings = (stopCount) => {
      const balanced = balancedLoadSwitch?.checked ?? true;
      if (solverBalancedInput) {
        solverBalancedInput.value = balanced ? '1' : '0';
      }
      if (solverMaxInput) {
        solverMaxInput.value = balanced ? 1 : Math.max(2, stopCount || 0);
      }
    };

    sendBtn?.addEventListener('click', () => {
      if (selected.size < 2) {
        notify('Selecciona al menos dos direcciones para armar la ruta.');
        return;
      }

      const payload = Array.from(selected)
        .map((id) => stopsById.get(id))
        .filter(Boolean)
        .map((stop) => ({
          code: stop.code,
          address: stop.address,
          lat: stop.lat,
          lng: stop.lng,
          priority: stop.priority || 'Media',
          notes: stop.notes || '',
          loose_stop_id: stop.id,
          party_id: stop.party_id || null,
          weight_actual: stop.weight_actual ?? null,
          weight_volumetric: stop.weight_volumetric ?? null,
          length: stop.length ?? null,
          width: stop.width ?? null,
          height: stop.height ?? null,
        }));

      if (!payload.length) {
        notify('No se pudieron leer las direcciones seleccionadas.');
        return;
      }

      const clusterBy = clusterBySelect?.value || solverClusterByInput?.value || 'address';

      solverPayloadInput.value = JSON.stringify(payload);
      applyLoadBalanceSettings(payload.length);
      solverStrategyInput.value = strategySelect?.value || 'route';
      solverProviderInput.value = providerSelect?.value || mapProviderSelect?.value || 'openstreet';
      solverClusterByInput.value = clusterBy;

      try {
        const actionUrl = new URL(solverForm.action, window.location.origin);
        actionUrl.searchParams.set('cluster_by', clusterBy);
        solverForm.action = actionUrl.toString();
      } catch (e) {
        // ignore
      }

      solverForm?.submit();
    });

    clusterAdvanceBtn?.addEventListener('click', () => {
      if (!pendingSolverPayload || !pendingSolverPayload.length) {
        notify('No hay paradas para enviar al solver.');
        return;
      }

      const clusterBy = pendingClusterBy || clusterBySelect?.value || 'address';

      solverPayloadInput.value = JSON.stringify(pendingSolverPayload);
      applyLoadBalanceSettings(pendingSolverPayload?.length || 0);
      solverStrategyInput.value = strategySelect?.value || 'route';
      solverProviderInput.value = providerSelect?.value || mapProviderSelect?.value || 'openstreet';
      solverClusterByInput.value = clusterBy;

      // llevar como querystring tambiÃ©n
      try {
        const actionUrl = new URL(solverForm.action, window.location.origin);
        actionUrl.searchParams.set('cluster_by', clusterBy);
        solverForm.action = actionUrl.toString();
      } catch (e) {
        // ignore
      }

      solverForm?.submit();
    });

    // âœ… al cargar
    updateCounter();
  })();
</script>

<script>
  (() => {
    const csrfToken = @json(csrf_token());
    const bulkDeleteForm = document.getElementById('looseBulkDeleteForm');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    const getBulkIds = () => Array.from(document.querySelectorAll('.loose-check:checked'))
      .map((input) => Number(input.value))
      .filter((id) => Number.isFinite(id));

    const submitBulkDelete = (ids) => {
      if (!bulkDeleteForm || !ids.length) return;
      bulkDeleteForm.innerHTML = '';
      const tokenInput = document.createElement('input');
      tokenInput.type = 'hidden';
      tokenInput.name = '_token';
      tokenInput.value = csrfToken;
      bulkDeleteForm.appendChild(tokenInput);
      const methodInput = document.createElement('input');
      methodInput.type = 'hidden';
      methodInput.name = '_method';
      methodInput.value = 'DELETE';
      bulkDeleteForm.appendChild(methodInput);
      ids.forEach((id) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'ids[]';
        hidden.value = String(id);
        bulkDeleteForm.appendChild(hidden);
      });
      bulkDeleteForm.submit();
    };

    const confirmLooseDeletion = (route) => {
      if (!route) {
        return;
      }
      Swal.fire({
        icon: 'warning',
        title: 'Eliminar dirección',
        text: '¿Estás seguro de eliminar esta dirección suelta?',
        showCancelButton: true,
        confirmButtonText: 'Sí­, eliminar',
        cancelButtonText: 'Cancelar',
      }).then((result) => {
        if (!result.isConfirmed) {
          return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route;
        const tokenInput = document.createElement('input');
        tokenInput.name = '_token';
        tokenInput.type = 'hidden';
        tokenInput.value = csrfToken;
        const methodInput = document.createElement('input');
        methodInput.name = '_method';
        methodInput.type = 'hidden';
        methodInput.value = 'DELETE';
        form.appendChild(tokenInput);
        form.appendChild(methodInput);
        document.body.appendChild(form);
        form.submit();
      });
    };

    window.confirmLooseDeletion = confirmLooseDeletion;

    document.querySelectorAll('.loose-delete-btn').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        confirmLooseDeletion(button.dataset.route);
      });
    });

    bulkDeleteBtn?.addEventListener('click', () => {
      const ids = getBulkIds();
      if (!ids.length) {
        const msg = 'Selecciona al menos una dirección para eliminar.';
        if (window.nygAlert) {
        window.nygAlert(msg, 'info');
      }
        return;
      }
      const confirmAction = () => submitBulkDelete(ids);

      if (window.Swal) {
        Swal.fire({
          icon: 'warning',
          title: 'Eliminar direcciones',
          text: `Vas a eliminar ${ids.length} dirección(es). ¿Continuar?`,
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
        }).then((result) => {
          if (result.isConfirmed) confirmAction();
        });
      } else if (window.nygConfirm) {
        window.nygConfirm({
          text: `?Eliminar ${ids.length} direcci?n(es)?`,
          confirmButtonText: 'S?, eliminar',
          cancelButtonText: 'Cancelar',
        }).then((result) => {
          if (result.isConfirmed) confirmAction();
        });
      }
    });

  })();
</script>


<script>
  (() => {
    $(function () {
      const importFormats = @json($importFormats ?? []);
      const formatFieldLabels = @json($formatFieldLabels);
      const $clientSelect = $('#looseImportModal select[name="party_id"]');
      const $description = $('#looseDescription');
      const defaultDescription = $description.data('default-description') || $description.html() || '';
      const $formatModal = $('#looseFormatModal');
      const formatModalInstance = $formatModal.length && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance($formatModal[0]) : null;
      const $formatModalName = $('#looseFormatModalName');
      const $formatModalMeta = $('#looseFormatModalMeta');
      const $formatModalFields = $('#looseFormatModalFields');
      const formatLinkId = 'looseImportFormatPreviewLink';

      const renderFormatModal = (format) => {
        if (!format) return;
        $formatModalName.text(format.display_name || 'Sin nombre');
        const metaParts = [];
        metaParts.push(`Hoja: ${format.sheet || 'activa'}`);
        metaParts.push(`Fila inicial: ${format.start_row ?? 5}`);
        if (format.order_date_cell_column || format.order_date_cell_row) {
          const col = format.order_date_cell_column ?? '';
          const row = format.order_date_cell_row ?? '';
          metaParts.push(`Fecha pedido fija: ${col}${row}`);
        }
        metaParts.push(`Localidad en dirección: ${format.address_includes_locality ? 'Sí­' : 'No'}`);
        $formatModalMeta.text(metaParts.join(' â€¢ '));

        const mappings = format.field_mappings || {};
        if (!Object.keys(mappings).length) {
          $formatModalFields.html('<tr><td colspan="3" class="text-center text-muted small">Sin campos mapeados.</td></tr>');
        } else {
          const rows = Object.entries(mappings).map(([field, mapping]) => {
            const label = formatFieldLabels[field] ?? field;
            return `<tr><td>${label}</td><td>${mapping.column ?? ''}</td><td>${mapping.row ?? ''}</td></tr>`;
          }).join('');
          $formatModalFields.html(rows);
        }
      };

      const attachPreviewHandler = (format) => {
        const $link = $('#' + formatLinkId);
        $link.off('click.looseFormat');
        if (!format) return;
        $link.on('click.looseFormat', (event) => {
          event.preventDefault();
          renderFormatModal(format);
          formatModalInstance?.show();
        });
      };

      const updateDescription = () => {
        const clientId = ($clientSelect.val() || '').toString();
        const currentFormat = clientId && Object.prototype.hasOwnProperty.call(importFormats, clientId) ? importFormats[clientId] : null;
        if (!currentFormat) {
          $description.html(defaultDescription);
          return;
        }
        const safeName = currentFormat.display_name || 'Sin nombre';
        $description.html(`Formato activo: <strong>${safeName}</strong> <a href="#" id="${formatLinkId}">Ver formato</a>`);
        attachPreviewHandler(currentFormat);
      };

      $clientSelect.on('change input', updateDescription);
      updateDescription();
    });
  })();
</script>


@endpush
