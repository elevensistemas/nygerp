@extends('layouts.app')

@section('title', 'Reporte de rutas')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Reporte de rutas</h1>
      <p class="text-muted mb-0">Filtra y visualiza las paradas en un solo mapa.</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtersOffcanvas">
        <i class="fa-solid fa-filter me-1"></i> Filtros
      </button>
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a tráfico
      </a>
    </div>
  </div>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h2 class="h5 mb-1">Mapa combinado</h2>
        @php
          $activeFilterLabels = [];
          $transportistasById = $transportistas->keyBy('id');
          $ordersById = $orders->keyBy('id');
          $reasonsById = $deliveryReasons->keyBy('id');

          if (!empty($filters['transportistas'])) {
              $names = collect($filters['transportistas'])
                  ->map(fn ($id) => optional($transportistasById->get($id))->name)
                  ->filter()
                  ->values()
                  ->all();
              if ($names) $activeFilterLabels[] = 'Transportistas: ' . implode(', ', $names);
          }
          if (!empty($filters['orders'])) {
              $names = collect($filters['orders'])
                  ->map(function ($id) use ($ordersById) {
                      $order = $ordersById->get($id);
                      return $order ? ($order->order_number . ' - ' . $order->client_name) : null;
                  })
                  ->filter()
                  ->values()
                  ->all();
              if ($names) $activeFilterLabels[] = 'Pedidos: ' . implode(', ', $names);
          }
          if (!empty($filters['statuses'])) {
              $statusMap = \App\Models\TrafficRouteStop::statusOptions();
              $names = collect($filters['statuses'])
                  ->map(fn ($status) => $statusMap[$status] ?? $status)
                  ->filter()
                  ->values()
                  ->all();
              if ($names) $activeFilterLabels[] = 'Estados: ' . implode(', ', $names);
          }
          if (!empty($filters['route_status'])) {
              $names = collect($filters['route_status'])
                  ->map(fn ($status) => $routeStatusOptions[$status] ?? $status)
                  ->filter()
                  ->values()
                  ->all();
              if ($names) $activeFilterLabels[] = 'Estado ruta: ' . implode(', ', $names);
          }
          if (!empty($filters['reasons'])) {
              $names = collect($filters['reasons'])
                  ->map(fn ($id) => optional($reasonsById->get($id))->name)
                  ->filter()
                  ->values()
                  ->all();
              if ($names) $activeFilterLabels[] = 'Motivos: ' . implode(', ', $names);
          }
          if (!empty($filters['sent'])) {
              $activeFilterLabels[] = $filters['sent'] === 'yes' ? 'Envio: Enviadas' : 'Envio: No enviadas';
          }
          if (!empty($filters['address'])) {
              $activeFilterLabels[] = 'Direccion: ' . $filters['address'];
          }
        @endphp
        <div class="d-flex flex-wrap gap-2">
          @if(empty($activeFilterLabels))
            <span class="badge text-bg-light">Mostrando: Todo</span>
          @else
            @foreach($activeFilterLabels as $label)
              <span class="badge text-bg-light">{{ $label }}</span>
            @endforeach
          @endif
        </div>
      </div>
      <span class="text-muted small">{{ $stops->count() }} paradas</span>
    </div>
    <div class="card-body p-0">
      <div id="routesReportMap" class="map-frame" style="height: 520px;"></div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h2 class="h5 mb-0">Listado de rutas</h2>
      <div class="d-flex gap-2">
        <form method="GET" action="{{ route('traffic.routes.report') }}">
          @foreach($filters['transportistas'] as $t)<input type="hidden" name="transportistas[]" value="{{ $t }}">@endforeach
          @foreach($filters['orders'] as $o)<input type="hidden" name="orders[]" value="{{ $o }}">@endforeach
          @foreach($filters['statuses'] as $s)<input type="hidden" name="statuses[]" value="{{ $s }}">@endforeach
          @foreach($filters['reasons'] as $r)<input type="hidden" name="reasons[]" value="{{ $r }}">@endforeach
          @foreach($filters['route_status'] as $rs)<input type="hidden" name="route_status[]" value="{{ $rs }}">@endforeach
          @if(!empty($filters['sent']))<input type="hidden" name="sent" value="{{ $filters['sent'] }}">@endif
          @if(!empty($filters['address']))<input type="hidden" name="address" value="{{ $filters['address'] }}">@endif
          <input type="hidden" name="export" value="excel">
          <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fa-solid fa-file-excel me-1"></i> Exportar Excel</button>
        </form>
        @if(! $isTransportista && !empty($sendableRouteIds))
          <form method="POST" action="{{ route('traffic.routes.sendBatch') }}">
            @csrf
            @foreach($sendableRouteIds as $routeId)
              <input type="hidden" name="route_ids[]" value="{{ $routeId }}">
            @endforeach
            <button class="btn btn-sm btn-outline-success" type="submit">
              <i class="fa-solid fa-paper-plane me-1"></i> Enviar todas
            </button>
          </form>
        @endif
        <button class="btn btn-sm btn-outline-secondary" type="button" id="printTableBtn">
          <i class="fa-solid fa-print me-1"></i> Imprimir PDF
        </button>
      </div>
    </div>
    <div class="card-body p-3">
    <div class="accordion routes-accordion" id="routesAccordion">
      @forelse($routesData as $data)
        @php
          /** @var \App\Models\TrafficRoute|null $routeModel */
          $routeModel = $data['route'];
          $routeId = optional($routeModel)->id;
          $carrier = optional(optional($routeModel)->transportista);
          $carrierColor = $carrier->color ?? '#2563eb';
          $statusCounts = $data['status_counts'] ?? ['delivered' => 0, 'failed' => 0, 'pending' => 0];
          $ordersList = optional(optional($routeModel)->orders)->map(fn ($o) => $o->order_number . ' - ' . $o->client_name)->all() ?? [];
          $collapseId = 'routeStops' . $routeId;
          $routeStatus = optional($routeModel)->status;
          $routeStatusLabel = $routeStatus ? ($routeStatusOptions[$routeStatus] ?? ucfirst($routeStatus)) : 'Planificada';
        @endphp
        <div class="accordion-item">
          <h2 class="accordion-header" id="heading{{ $routeId }}">
            <div class="d-flex align-items-stretch gap-2">
              <div class="checkbox-cell d-flex align-items-center">
                <div class="form-check mb-0">
                  <input class="form-check-input js-route-toggle" type="checkbox" data-route-id="{{ $routeId }}" {{ $routeId ? 'checked' : 'disabled' }}>
                </div>
              </div>
              <div class="flex-grow-1 d-flex align-items-center">
                <button class="accordion-button collapsed flex-grow-1 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                  <div class="route-row d-flex flex-wrap align-items-center w-100 gap-3">
                    <div class="d-flex align-items-center gap-2 me-2">
                      <span class="badge text-bg-light">{{ optional($routeModel)->code ?? 'Ruta' }}</span>
                      <span class="carrier-color-dot" style="background: {{ $carrierColor }};"></span>
                      <strong class="text-dark">{{ $carrier->name ?? 'Transportista' }}</strong>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="badge bg-success-subtle text-success">Entregadas: {{ $statusCounts['delivered'] ?? 0 }}</span>
                      <span class="badge bg-danger-subtle text-danger">Fallidas: {{ $statusCounts['failed'] ?? 0 }}</span>
                      <span class="badge bg-secondary-subtle text-dark">Pendientes: {{ $statusCounts['pending'] ?? 0 }}</span>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                      @if($routeModel)
                        <span class="badge text-bg-light">{{ $routeStatusLabel }}</span>
                        @if($routeModel->sent_at)
                          <span class="badge text-bg-success">Enviada</span>
                        @else
                          <span class="badge text-bg-secondary">No enviada</span>
                        @endif
                      @endif
                    </div>
                  </div>
                </button>
                @if($routeModel && !$isTransportista && $routeModel->sent_at === null)
                  <form method="POST" action="{{ route('traffic.routes.send', $routeModel) }}" class="ps-2">
                    @csrf
                    <button class="btn btn-sm btn-outline-success" type="submit">Enviar</button>
                  </form>
                @endif
              </div>
            </div>
          </h2>
          <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="heading{{ $routeId }}" data-bs-parent="#routesAccordion">
            <div class="accordion-body">
              <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                <div><strong>Pedidos:</strong></div>
                @if(empty($ordersList))
                  <span class="text-muted small">Sin pedidos asociados</span>
                @else
                  @foreach($ordersList as $orderLabel)
                    <span class="badge bg-light text-dark">{{ $orderLabel }}</span>
                  @endforeach
                @endif
              </div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 70px;">#</th>
                      <th>Parada</th>
                      <th>Dirección</th>
                      <th>Estado</th>
                      <th>Motivo</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($data['stops'] as $stop)
                      <tr>
                        <td class="fw-semibold">{{ $stop->sequence }}</td>
                        <td>{{ $stop->label }}</td>
                        <td class="text-muted">{{ $stop->address }}</td>
                        <td>{{ \App\Models\TrafficRouteStop::statusOptions()[$stop->status] ?? 'Pendiente' }}</td>
                        <td>
                          @if($stop->deliveryReason)
                            <span class="badge text-white" style="background: {{ $stop->deliveryReason->color ?? '#6c757d' }};">{{ $stop->deliveryReason->name }}</span>
                          @else
                            -
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="p-3 text-center text-muted">Sin rutas para mostrar.</div>
      @endforelse
    </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="routeInfoOffcanvas" aria-labelledby="routeInfoOffcanvasLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="routeInfoOffcanvasLabel">Ruta</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <p class="mb-1"><strong>Código:</strong> <span data-route-info="code">-</span></p>
      <p class="mb-1 d-flex align-items-center gap-2">
        <strong>Transportista:</strong>
        <span class="carrier-color-dot" data-route-info="carrier_color" style="background:#2563eb;"></span>
        <span data-route-info="carrier">-</span>
      </p>
      <p class="mb-1"><strong>Pedidos:</strong></p>
      <ul class="list-unstyled" data-route-info="orders"><li class="text-muted small">Sin pedidos asociados.</li></ul>
    </div>
  </div>

  <div class="offcanvas offcanvas-start" tabindex="-1" id="filtersOffcanvas" aria-labelledby="filtersOffcanvasLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="filtersOffcanvasLabel">Filtros</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
            <form class="row g-3" method="GET" action="{{ route('traffic.routes.report') }}">
        @unless($isTransportista)
          <div class="col-12">
            <label class="form-label">Transportistas</label>
            <select class="form-select" name="transportistas[]" multiple size="6">
              <option value="" {{ empty($filters['transportistas']) ? 'selected' : '' }}>Todos</option>
              @foreach($transportistas as $t)
                <option value="{{ $t->id }}" {{ in_array($t->id, $filters['transportistas'] ?? []) ? 'selected' : '' }}>{{ $t->name }}</option>
              @endforeach
            </select>
          </div>
        @endunless
        <div class="col-12">
          <label class="form-label">Pedidos</label>
          <select class="form-select" name="orders[]" multiple size="6">
            <option value="" {{ empty($filters['orders']) ? 'selected' : '' }}>Todos</option>
            @foreach($orders as $order)
              <option value="{{ $order->id }}" {{ in_array($order->id, $filters['orders'] ?? []) ? 'selected' : '' }}>{{ $order->order_number }} - {{ $order->client_name }}</option>
            @endforeach
          </select>
          <small class="text-muted">Ultimos 100 pedidos listados para filtro rapido.</small>
        </div>
        <div class="col-12">
          <label class="form-label">Estado</label>
          <select class="form-select" name="statuses[]" multiple size="3">
            <option value="{{ \App\Models\TrafficRouteStop::STATUS_ENTREGADO }}" {{ in_array(\App\Models\TrafficRouteStop::STATUS_ENTREGADO, $filters['statuses'] ?? []) ? 'selected' : '' }}>Entregado</option>
            <option value="{{ \App\Models\TrafficRouteStop::STATUS_NO_ENTREGADO }}" {{ in_array(\App\Models\TrafficRouteStop::STATUS_NO_ENTREGADO, $filters['statuses'] ?? []) ? 'selected' : '' }}>No entregado</option>
            <option value="{{ \App\Models\TrafficRouteStop::STATUS_PENDING }}" {{ in_array(\App\Models\TrafficRouteStop::STATUS_PENDING, $filters['statuses'] ?? []) ? 'selected' : '' }}>Pendiente</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Estado de la ruta</label>
          <select class="form-select" name="route_status[]" multiple size="4">
            @foreach($routeStatusOptions as $value => $label)
              <option value="{{ $value }}" {{ in_array($value, $filters['route_status'] ?? []) ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Dirección contiene</label>
          <input class="form-control" type="text" name="address" value="{{ $filters['address'] ?? '' }}" placeholder="Calle, ciudad, referencia">
        </div>
        <div class="col-12">
          <label class="form-label">Motivo (no entregado)</label>
          <select class="form-select" name="reasons[]" multiple size="6">
            <option value="" {{ empty($filters['reasons']) ? 'selected' : '' }}>Todos</option>
            @foreach($deliveryReasons as $reason)
              <option value="{{ $reason->id }}" {{ in_array($reason->id, $filters['reasons'] ?? []) ? 'selected' : '' }}>{{ $reason->name }}</option>
            @endforeach
          </select>
        </div>
        @unless($isTransportista)
          <div class="col-12">
            <label class="form-label">Envio</label>
            <select class="form-select" name="sent">
              <option value="">Todas</option>
              <option value="yes" {{ ($filters['sent'] ?? '') === 'yes' ? 'selected' : '' }}>Enviadas</option>
              <option value="no" {{ ($filters['sent'] ?? '') === 'no' ? 'selected' : '' }}>No enviadas</option>
            </select>
          </div>
        @endunless
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Aplicar filtros</button>
          <a class="btn btn-outline-secondary" href="{{ route('traffic.routes.report') }}">Limpiar</a>
        </div>
      </form>

    </div>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .routes-accordion .accordion-item {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      margin-bottom: 10px;
      width: 100%;
      overflow: hidden;
      background: #fff;
    }
    .routes-accordion .accordion-button {
      padding: 0.75rem 1rem;
      background: #fff;
      box-shadow: none;
      gap: 0.75rem;
    }
    .routes-accordion .accordion-button:not(.collapsed) {
      background: #f8fafc;
    }
    .routes-accordion .form-check-input {
      width: 1.1rem;
      height: 1.1rem;
      margin: 0;
    }
    .routes-accordion .accordion-body {
      background: #fff;
    }
    .routes-accordion .badge {
      font-size: 0.75rem;
    }
    .routes-accordion .carrier-color-dot {
      width: 14px;
      height: 14px;
    }
    .routes-accordion .route-row {
      min-height: 44px;
    }
    .routes-accordion .checkbox-cell {
      width: 52px;
      background: #f8fafc;
      border-right: 1px solid #e5e7eb;
      justify-content: center;
    }
  </style>
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      const STATUS_ENTREGADO = '{{ \App\Models\TrafficRouteStop::STATUS_ENTREGADO }}';
      const STATUS_NO_ENTREGADO = '{{ \App\Models\TrafficRouteStop::STATUS_NO_ENTREGADO }}';
      const STATUS_PENDING = '{{ \App\Models\TrafficRouteStop::STATUS_PENDING }}';

      document.addEventListener('DOMContentLoaded', () => {
        // Reaplicar selección en selects múltiples (evita problemas de cache de vistas/HTML tras filtros)
        const stops = @json($stopsForMap);
        const segments = @json($segmentsForMap);
        const routesInfo = @json($routesForMap);
        const zones = @json($zonesForMap);

        const map = L.map('routesReportMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const markerHtml = (number, isExtra, status, carrierColor) => {
          const colors = {
            pending: 'rgba(148, 163, 184, 0.35)',
            delivered: 'rgba(34, 197, 94, 0.35)',
            failed: 'rgba(239, 68, 68, 0.35)',
          };
          let bg = colors.pending;
          let color = '#0f172a';
          if (isExtra) {
            bg = 'rgba(255, 255, 255, 0.7)';
            color = '#0f172a';
          } else if (status === STATUS_ENTREGADO) {
            bg = colors.delivered;
            color = '#0f172a';
          } else if (status === STATUS_NO_ENTREGADO) {
            bg = colors.failed;
            color = '#0f172a';
          }
          const border = carrierColor || '#2563eb';
          const ring = 'rgba(15, 23, 42, 0.12)';

          return `
            <div style="
              height:36px;width:36px;
              border-radius:50%;
              background:${bg};
              color:${color};
              display:flex;
              align-items:center;
              justify-content:center;
              font-weight:700;
              border:2px solid ${border};
              box-shadow:0 8px 16px ${ring};
              font-size:14px;
            ">
              ${isExtra ? '' : number}
            </div>`;
        };

        const routeVisibility = {};
        const routeToggles = Array.from(document.querySelectorAll('.js-route-toggle'));
        routeToggles.forEach((cb) => {
          const routeId = cb.dataset.routeId;
          if (routeId) {
            routeVisibility[routeId] = cb.checked;
          }
        });

        const routeLayers = {};
        const ensureRoute = (routeId) => {
          if (!routeLayers[routeId]) {
            routeLayers[routeId] = { markers: [], polylines: [] };
          }
          return routeLayers[routeId];
        };
        const isVisible = (routeId) => routeVisibility[routeId] !== false;
        const addLayer = (routeId, layer, type) => {
          if (!routeId) return;
          const entry = ensureRoute(routeId);
          entry[type].push(layer);
          if (isVisible(routeId)) {
            layer.addTo(map);
          }
        };
        const refreshBounds = () => {
          const layers = [];
          Object.keys(routeLayers).forEach((routeId) => {
            if (!isVisible(routeId)) return;
            const entry = routeLayers[routeId];
            layers.push(...entry.polylines, ...entry.markers);
          });
          if (layers.length) {
            const group = L.featureGroup(layers);
            map.fitBounds(group.getBounds(), { padding: [20, 20] });
          } else {
            map.setView([-34.6037, -58.3816], 5);
          }
        };
        const setRouteVisibility = (routeId, visible) => {
          routeVisibility[routeId] = visible;
          const entry = routeLayers[routeId];
          if (!entry) {
            refreshBounds();
            return;
          }
          const layers = [...entry.polylines, ...entry.markers];
          layers.forEach((layer) => {
            if (visible) {
              if (!map.hasLayer(layer)) {
                layer.addTo(map);
              }
            } else if (map.hasLayer(layer)) {
              map.removeLayer(layer);
            }
          });
          refreshBounds();
        };

        const polylines = [];
        segments.forEach((segment) => {
          if (!segment.latlngs || segment.latlngs.length < 2) return;
          const line = L.polyline(segment.latlngs, { color: segment.color || '#0d6efd', weight: 8, opacity: 0.8 });
          line.routeId = segment.route_id || null;
          polylines.push(line);
          addLayer(line.routeId, line, 'polylines');
        });

        const markers = [];
        stops.forEach((stop) => {
          if (!stop.lat || !stop.lng) return;
          const html = markerHtml(stop.sequence || '', !!stop.is_extra, stop.status, stop.carrier_color);
          const icon = L.divIcon({
              html,
              className: 'routes-report-marker',
              iconSize: [26, 26],
              iconAnchor: [13, 26],
              popupAnchor: [0, -20],
          });
          const marker = L.marker([stop.lat, stop.lng], { icon })
            .bindPopup(`<strong>${stop.route_code || ''} #${stop.sequence || ''}</strong><div>${stop.label || ''}</div>${stop.reason_name ? '<div>Motivo: ' + stop.reason_name + '</div>' : ''}`);
          marker.routeId = stop.route_id || null;
          markers.push(marker);
          addLayer(marker.routeId, marker, 'markers');
        });

        refreshBounds();

        const syncRouteToggles = (routeId, checked, source) => {
          routeToggles.forEach((cb) => {
            if (cb.dataset.routeId === routeId && cb !== source) {
              cb.checked = checked;
            }
          });
        };
        routeToggles.forEach((cb) => {
          cb.addEventListener('change', (event) => {
            const routeId = event.target.dataset.routeId;
            if (!routeId) return;
            const checked = event.target.checked;
            syncRouteToggles(routeId, checked, event.target);
            setRouteVisibility(routeId, checked);
          });
          cb.addEventListener('click', (event) => {
            // No toggles accordion when clicking the checkbox
            event.stopPropagation();
          });
        });

        const zoneLayers = [];
        zones.forEach((zone) => {
          const color = zone.color || '#2563eb';
          if (zone.type === 'circle' && zone.center_lat && zone.center_lng && zone.radius_km) {
            const circle = L.circle([zone.center_lat, zone.center_lng], {
              radius: zone.radius_km * 1000,
              color,
              weight: 2,
              opacity: 0.6,
              fillColor: color,
              fillOpacity: 0.12,
            }).bindPopup(`<strong>${zone.transportista}</strong><div>Zona ${zone.priority || ''}</div>`);
            zoneLayers.push(circle.addTo(map));
          } else if (zone.type === 'polygon' && Array.isArray(zone.polygon) && zone.polygon.length >= 3) {
            const coords = zone.polygon.map(p => [p.lat ?? p[0], p.lng ?? p[1]]);
            const poly = L.polygon(coords, {
              color,
              weight: 2,
              opacity: 0.6,
              fillColor: color,
              fillOpacity: 0.12,
            }).bindPopup(`<strong>${zone.transportista}</strong><div>Zona ${zone.priority || ''}</div>`);
            zoneLayers.push(poly.addTo(map));
          }
        });

        const offcanvasEl = document.getElementById('routeInfoOffcanvas');
        const offcanvasInstance = offcanvasEl ? new bootstrap.Offcanvas(offcanvasEl) : null;
        const setRouteInfo = (routeId) => {
          if (!offcanvasEl) return;
          const data = routesInfo[routeId] || null;
          offcanvasEl.querySelector('[data-route-info="code"]').textContent = data ? (data.code || '-') : '-';
          offcanvasEl.querySelector('[data-route-info="carrier"]').textContent = data ? (data.transportista || '-') : '-';
          const carrierDot = offcanvasEl.querySelector('[data-route-info="carrier_color"]');
          if (carrierDot) {
            carrierDot.style.background = data?.transportista_color || '#2563eb';
          }
          const ordersList = offcanvasEl.querySelector('[data-route-info="orders"]');
          ordersList.innerHTML = '';
          const orders = data && data.orders ? data.orders : [];
          if (!orders.length) {
            const li = document.createElement('li');
            li.className = 'text-muted small';
            li.textContent = 'Sin pedidos asociados.';
            ordersList.appendChild(li);
          } else {
            orders.forEach((order) => {
              const li = document.createElement('li');
              li.textContent = order;
              ordersList.appendChild(li);
            });
          }
          offcanvasInstance?.show();
        };

        polylines.forEach((line) => {
          line.on('click', () => {
            if (line.routeId) {
              setRouteInfo(line.routeId);
            }
          });
        });

        // Print only the table
        const printBtn = document.getElementById('printTableBtn');
        if (printBtn) {
          printBtn.addEventListener('click', () => {
            const accordion = document.getElementById('routesAccordion');
            if (!accordion) return;
            const w = window.open('', '_blank');
            if (!w) return;
            w.document.write('<html><head><title>Paradas</title>');
            w.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">');
            w.document.write('</head><body class="p-3">');
            w.document.write(accordion.outerHTML);
            w.document.write('</body></html>');
            w.document.close();
            w.focus();
            w.print();
            w.close();
          });
        }
      });
    })();
  </script>
@endpush
