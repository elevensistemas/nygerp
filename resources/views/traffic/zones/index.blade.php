@extends('layouts.app')

@section('title', 'Zonas')

@section('content')
  @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link class="leaflet-draw-css" rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
    <style>
      #zonesMap { height: 450px; border-radius: 0.5rem; }
      .geocode-suggestions {
        position: relative;
      }
      .geocode-suggestions ul {
        position: absolute;
        z-index: 1050;
        left: 0;
        right: 0;
        top: 100%;
        margin: 0;
        padding: 0;
        list-style: none;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        max-height: 200px;
        overflow-y: auto;
      }
      .geocode-suggestions li {
        padding: 8px 10px;
        cursor: pointer;
      }
      .geocode-suggestions li:hover {
        background: #f3f4f6;
      }
      .truck-marker {
        background: #2563eb;
        color: #fff;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        font-size: 16px;
      }
    </style>
  @endpush

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Zonas</h1>
      <p class="text-muted mb-0">Crea y administra las zonas geográficas asignadas a transportistas.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary px-3 d-flex align-items-center gap-1" type="button" id="btnCreateZone">
        <i class="fa-solid fa-plus"></i> Nueva zona
      </button>
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('ok') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ $errors->first() }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <!-- Tabla de listado de Zonas cargadas (Ancho Completo) -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
      <h2 class="h5 mb-0 text-secondary fw-semibold">Zonas cargadas</h2>
      <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">{{ $zones->total() }} zonas</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 text-nowrap">
        <thead class="table-light">
          <tr>
            <th scope="col" class="ps-4">Nombre de la Zona</th>
            <th scope="col">Tipo de Geometría</th>
            <th scope="col">Prioridad</th>
            <th scope="col">Blanda (Soft)</th>
            <th scope="col" class="text-center">Límite de Paradas</th>
            <th scope="col">SVS Configurados</th>
            <th scope="col" class="text-end pe-4" style="width: 150px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($zones as $zone)
            <tr>
              <td class="ps-4 fw-semibold text-dark">{{ $zone->name }}</td>
              <td>
                @if($zone->type === 'circle')
                  <span class="badge bg-light text-primary border border-primary-subtle rounded-pill">
                    <i class="fa-solid fa-circle-dot me-1"></i> Círculo
                  </span>
                @else
                  <span class="badge bg-light text-success border border-success-subtle rounded-pill">
                    <i class="fa-solid fa-draw-polygon me-1"></i> Polígono
                  </span>
                @endif
              </td>
              <td>
                @if($zone->priority === 'primary')
                  <span class="badge bg-primary rounded-pill">Primaria</span>
                @else
                  <span class="badge bg-secondary rounded-pill">Secundaria</span>
                @endif
              </td>
              <td>
                @if($zone->is_soft)
                  <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Sí
                  </span>
                @else
                  <span class="text-muted">No</span>
                @endif
              </td>
              <td class="text-center fw-bold">{{ $zone->max_stops ?? 'Sin límite' }}</td>
              <td>
                @if($zone->svs_values && count($zone->svs_values) > 0)
                  <div class="d-flex flex-wrap gap-1" style="max-width: 300px;">
                    @foreach($zone->svs_values as $svs)
                      <span class="badge bg-light text-dark border border-secondary-subtle rounded-pill">{{ $svs }}</span>
                    @endforeach
                  </div>
                @else
                  <span class="text-muted small">Ninguno</span>
                @endif
              </td>
              <td class="text-end pe-4">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary" type="button" data-zone='@json($zone)'>
                    <i class="fa-solid fa-pen-to-square"></i> Editar
                  </button>
                  <form method="POST" action="{{ route('traffic.zones.destroy', $zone) }}"
                        data-confirm="¿Eliminar la zona {{ $zone->name }}?" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">
                      <i class="fa-solid fa-trash-can"></i> Borrar
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="fa-solid fa-draw-polygon fs-2 mb-2 d-block opacity-50"></i>
                No hay zonas cargadas.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $zones->withQueryString()->links() }}
    </div>
  </div>

  <!-- Modal de Edición/Creación de Zona -->
  <div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" id="zoneForm" action="{{ route('traffic.zones.store') }}">
          @csrf
          <input type="hidden" name="_method" value="POST" id="zoneMethod">
          
          <div class="modal-header bg-light">
            <h5 class="modal-title fw-bold text-dark" id="zoneFormTitle">Nueva zona</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <div class="modal-body">
            <div class="row g-4">
              <!-- Formulario de Configuración -->
              <div class="col-lg-5">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                  <span class="text-muted small">Campos obligatorios marcados con (*)</span>
                  <button class="btn btn-xs btn-outline-secondary px-2 py-1" type="button" id="zoneResetBtn" style="font-size: 0.75rem;">Limpiar</button>
                </div>
                
                <div class="mb-3">
                  <label class="form-label fw-semibold">Nombre de la Zona *</label>
                  <input class="form-control" name="name" id="zoneName" required placeholder="Ej: AMBA am, Córdoba, etc.">
                </div>
                
                <input type="hidden" name="type" id="zoneType" value="circle">
                <div class="mb-3 text-muted small"><i class="fa-solid fa-info-circle me-1"></i> El tipo (círculo/polígono) se tomará de la figura que dibujes en el mapa.</div>
                
                <div class="row g-2 mb-3 circle-fields">
                  <div class="col-6">
                    <label class="form-label fw-semibold">Latitud centro</label>
                    <input class="form-control" name="center_lat" id="zoneLat" type="number" step="0.000001" placeholder="Auto-completado">
                  </div>
                  <div class="col-6">
                    <label class="form-label fw-semibold">Longitud centro</label>
                    <input class="form-control" name="center_lng" id="zoneLng" type="number" step="0.000001" placeholder="Auto-completado">
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label fw-semibold">Dirección base (geocoding)</label>
                  <div class="geocode-suggestions">
                    <div class="input-group">
                      <span class="input-group-text"><i class="fa-solid fa-magnifying-glass-location"></i></span>
                      <input class="form-control" type="text" id="zoneAddress" placeholder="Ej: Av. Siempre Viva 123, CABA">
                    </div>
                    <ul class="d-none border border-top-0 rounded-bottom shadow-sm" id="zoneAddressSuggestions"></ul>
                  </div>
                  <small class="text-muted">Busca y selecciona una dirección para centrar el mapa y los valores Lat/Lng.</small>
                </div>
                
                <div class="mb-3 circle-fields">
                  <label class="form-label fw-semibold">Radio (km)</label>
                  <input class="form-control" name="radius_km" id="zoneRadius" type="number" step="0.01" min="0" placeholder="Ej: 5.0">
                </div>
                
                <div class="mb-3 polygon-fields d-none">
                  <label class="form-label fw-semibold">Polígono (JSON de puntos)</label>
                  <textarea class="form-control" name="polygon" id="zonePolygon" rows="3" placeholder='[{"lat":-34.60,"lng":-58.38}, {"lat":-34.61,"lng":-58.40}]'></textarea>
                  <small class="text-muted">Se genera automáticamente al dibujar en el mapa.</small>
                </div>
                
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label fw-semibold">Prioridad</label>
                    <select class="form-select" name="priority" id="zonePriority">
                      <option value="primary">Primaria</option>
                      <option value="secondary">Secundaria</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="form-label fw-semibold">Máx. paradas</label>
                    <input class="form-control" name="max_stops" id="zoneMaxStops" type="number" min="1" placeholder="Sin límite">
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label fw-semibold">Valores SVS (uno por línea)</label>
                  <textarea class="form-control" name="svs_values" id="zoneSvsValues" rows="4" placeholder="Ej: AMBA am 1&#10;AMBA am 2"></textarea>
                  <small class="text-muted">Ingresa un valor por renglón. Se desplegarán como opciones en la planilla diaria.</small>
                </div>
                
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="zoneSoft" name="is_soft" value="1">
                  <label class="form-check-label fw-semibold" for="zoneSoft">Zona blanda (penaliza asignación en lugar de prohibir)</label>
                </div>
              </div>
              
              <!-- Mapa Interactivo -->
              <div class="col-lg-7">
                <div class="d-flex flex-column h-100" style="min-height: 480px;">
                  <div class="mb-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-secondary"><i class="fa-solid fa-map-location-dot me-1"></i> Dibujo del Mapa de Zona</span>
                    <small class="text-muted text-end">Usa las herramientas de la izquierda para dibujar</small>
                  </div>
                  <div id="zonesMap" style="flex-grow: 1; border-radius: 0.5rem; border: 1px solid #ced4da; min-height: 450px;"></div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar zona</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
  <script>
    const mapboxToken = "{{ config('services.mapbox.token') ?? env('MAPBOX_TOKEN') }}";
    const zonesData = @json($zones->items());
    let zonesMap = null;
    let zonesDrawnItems = null;
    let zonesDrawControl = null;
    let zoneCenterMarker = null;

    const zoneForm = document.getElementById('zoneForm');
    const zoneMethod = document.getElementById('zoneMethod');
    const zoneFormTitle = document.getElementById('zoneFormTitle');
    const zoneResetBtn = document.getElementById('zoneResetBtn');
    const zoneTypeSelect = document.getElementById('zoneType');
    const zonesMapEl = document.getElementById('zonesMap');
    const zoneAddressInput = document.getElementById('zoneAddress');
    const zoneAddressSuggestions = document.getElementById('zoneAddressSuggestions');
    let geocodeTimer = null;
    let zoneModal = null;

    // Initialize modal references
    const zoneModalEl = document.getElementById('zoneModal');
    if (zoneModalEl) {
      zoneModal = new bootstrap.Modal(zoneModalEl);
    }

    function toggleZoneFields(type) {
      const circleFields = document.querySelectorAll('.circle-fields');
      const polygonFields = document.querySelectorAll('.polygon-fields');
      if (type === 'polygon') {
        circleFields.forEach(el => el.classList.add('d-none'));
        polygonFields.forEach(el => el.classList.remove('d-none'));
      } else {
        circleFields.forEach(el => el.classList.remove('d-none'));
        polygonFields.forEach(el => el.classList.add('d-none'));
      }
      document.getElementById('zoneType').value = type;
    }

    function resetZoneForm() {
      zoneForm.reset();
      zoneForm.action = "{{ route('traffic.zones.store') }}";
      zoneMethod.value = 'POST';
      zoneFormTitle.textContent = 'Nueva zona';
      document.getElementById('zoneType').value = 'circle';
      toggleZoneFields('circle');
      document.getElementById('zonePolygon').value = '';
      document.getElementById('zoneSvsValues').value = '';
      if (zonesDrawnItems) zonesDrawnItems.clearLayers();
      if (zoneCenterMarker && zonesMap) {
        zonesMap.removeLayer(zoneCenterMarker);
        zoneCenterMarker = null;
      }
    }

    function initZonesMap() {
      if (zonesMap || !zonesMapEl) return;
      zonesMap = L.map('zonesMap').setView([-34.603722, -58.381592], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
      }).addTo(zonesMap);

      zonesDrawnItems = new L.FeatureGroup();
      zonesMap.addLayer(zonesDrawnItems);

      zonesDrawControl = new L.Control.Draw({
        draw: {
          polygon: true,
          circle: true,
          rectangle: false,
          polyline: false,
          marker: false,
          circlemarker: false,
        },
        edit: {
          featureGroup: zonesDrawnItems
        }
      });
      zonesMap.addControl(zonesDrawControl);

      zonesMap.on(L.Draw.Event.CREATED, function (e) {
        zonesDrawnItems.clearLayers();
        zonesDrawnItems.addLayer(e.layer);
        syncZoneFromLayer(e.layer);
      });
      zonesMap.on(L.Draw.Event.EDITED, function (e) {
        e.layers.eachLayer(layer => syncZoneFromLayer(layer));
      });
      zonesMap.on('click', function (e) {
        document.getElementById('zoneLat').value = e.latlng.lat.toFixed(6);
        document.getElementById('zoneLng').value = e.latlng.lng.toFixed(6);
        setCenterMarker({ lat: e.latlng.lat, lng: e.latlng.lng }, true);
      });
    }

    function syncZoneFromLayer(layer) {
      if (layer instanceof L.Circle) {
        const center = layer.getLatLng();
        const radiusKm = (layer.getRadius() / 1000).toFixed(2);
        zoneTypeSelect.value = 'circle';
        toggleZoneFields('circle');
        document.getElementById('zoneLat').value = center.lat.toFixed(6);
        document.getElementById('zoneLng').value = center.lng.toFixed(6);
        document.getElementById('zoneRadius').value = radiusKm;
        document.getElementById('zonePolygon').value = '';
        setCenterMarker(center, true);
      } else if (layer instanceof L.Polygon) {
        const latlngs = layer.getLatLngs()[0] || [];
        const mapped = latlngs.map(p => ({ lat: Number(p.lat.toFixed(6)), lng: Number(p.lng.toFixed(6)) }));
        zoneTypeSelect.value = 'polygon';
        toggleZoneFields('polygon');
        document.getElementById('zonePolygon').value = JSON.stringify(mapped);
        document.getElementById('zoneLat').value = '';
        document.getElementById('zoneLng').value = '';
        document.getElementById('zoneRadius').value = '';
        if (mapped.length) {
          setCenterMarker(mapped[0], true);
        }
      }
    }

    function createZoneLayer(zone) {
      if (!zonesDrawnItems || !zonesMap) return null;
      let layer = null;
      if (zone.type === 'circle' && zone.center_lat && zone.center_lng && zone.radius_km) {
        layer = L.circle([zone.center_lat, zone.center_lng], { radius: zone.radius_km * 1000, color: '#2563eb' });
      }
      if (zone.type === 'polygon' && Array.isArray(zone.polygon) && zone.polygon.length >= 3) {
        const coords = zone.polygon.map(p => [p.lat ?? p[0], p.lng ?? p[1]]);
        layer = L.polygon(coords, { color: '#2563eb' });
      }
      return layer;
    }

    function renderZoneShape(zone) {
      if (!zonesDrawnItems || !zonesMap) return;
      zonesDrawnItems.clearLayers();
      const layer = createZoneLayer(zone);
      if (layer) {
        zonesDrawnItems.addLayer(layer);
      }
      if (zone.type === 'circle' && zone.center_lat && zone.center_lng) {
        setCenterMarker({ lat: zone.center_lat, lng: zone.center_lng }, true);
      } else {
        zoneCenterMarker && zonesMap.removeLayer(zoneCenterMarker);
        zoneCenterMarker = null;
      }
    }

    function renderZonesMap(zones, { fitBounds = false } = {}) {
      if (!zonesDrawnItems || !zonesMap) return;
      zonesDrawnItems.clearLayers();
      const layers = [];
      zones.forEach(zone => {
        const layer = createZoneLayer(zone);
        if (!layer) return;
        zonesDrawnItems.addLayer(layer);
        layers.push(layer);
      });
      if (layers.length && fitBounds) {
        const group = L.featureGroup(layers);
        zonesMap.fitBounds(group.getBounds().pad(0.2));
      }
    }

    function setCenterMarker(latlng, keepZoom = false) {
      if (!zonesMap || !latlng) return;
      const icon = L.divIcon({ className: 'truck-marker', html: '<i class="fa-solid fa-truck"></i>', iconSize: [32, 32], iconAnchor: [16, 16] });
      if (!zoneCenterMarker) {
        zoneCenterMarker = L.marker(latlng, { icon, draggable: true }).addTo(zonesMap);
        zoneCenterMarker.on('dragend', (e) => {
          const pos = e.target.getLatLng();
          document.getElementById('zoneLat').value = pos.lat.toFixed(6);
          document.getElementById('zoneLng').value = pos.lng.toFixed(6);
        });
      } else {
        zoneCenterMarker.setLatLng(latlng);
      }
      if (!keepZoom) {
        zonesMap.panTo(latlng);
      }
    }

    async function geocodeAddress(q) {
      if (!mapboxToken) {
        zoneAddressSuggestions.classList.add('d-none');
        zoneAddressSuggestions.innerHTML = '<li>Configura MAPBOX_TOKEN</li>';
        return;
      }
      const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(q)}.json?access_token=${mapboxToken}&language=es&country=AR&limit=5`;
      const res = await fetch(url);
      if (!res.ok) return;
      const data = await res.json();
      const features = data.features || [];
      if (!features.length) {
        zoneAddressSuggestions.classList.remove('d-none');
        zoneAddressSuggestions.innerHTML = '<li class="text-muted">Sin resultados</li>';
        return;
      }
      zoneAddressSuggestions.classList.remove('d-none');
      zoneAddressSuggestions.innerHTML = features.map(f => `<li data-lat="${f.center[1]}" data-lng="${f.center[0]}" data-name="${f.place_name}">${f.place_name}</li>`).join('');
    }

    if (zoneAddressInput) {
      zoneAddressInput.addEventListener('input', (e) => {
        const val = e.target.value || '';
        if (geocodeTimer) clearTimeout(geocodeTimer);
        if (val.trim().length < 4) {
          zoneAddressSuggestions.classList.add('d-none');
          return;
        }
        geocodeTimer = setTimeout(() => geocodeAddress(val.trim()), 400);
      });
    }

    if (zoneAddressSuggestions) {
      zoneAddressSuggestions.addEventListener('click', (e) => {
        const target = e.target.closest('li');
        if (!target) return;
        const lat = parseFloat(target.dataset.lat);
        const lng = parseFloat(target.dataset.lng);
        const name = target.dataset.name;
        zoneAddressInput.value = name;
        document.getElementById('zoneLat').value = lat.toFixed(6);
        document.getElementById('zoneLng').value = lng.toFixed(6);
        zoneAddressSuggestions.classList.add('d-none');
        setCenterMarker({ lat, lng }, true);
      });
    }

    if (zoneResetBtn) {
      zoneResetBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resetZoneForm();
      });
    }

    // Modal display triggers map rendering correctly
    if (zoneModalEl) {
      zoneModalEl.addEventListener('shown.bs.modal', function () {
        if (!zonesMap) {
          initZonesMap();
        } else {
          zonesMap.invalidateSize();
        }

        const latVal = parseFloat(document.getElementById('zoneLat').value);
        const lngVal = parseFloat(document.getElementById('zoneLng').value);
        const radiusVal = parseFloat(document.getElementById('zoneRadius').value);
        const polyVal = document.getElementById('zonePolygon').value;

        if (document.getElementById('zoneName').value) {
          // Editing mode, render the specific zone
          const tempZone = {
            type: document.getElementById('zoneType').value,
            center_lat: isNaN(latVal) ? null : latVal,
            center_lng: isNaN(lngVal) ? null : lngVal,
            radius_km: isNaN(radiusVal) ? null : radiusVal,
            polygon: polyVal ? JSON.parse(polyVal) : null
          };
          renderZoneShape(tempZone);
          
          if (zonesDrawnItems && zonesDrawnItems.getLayers().length > 0) {
            const group = L.featureGroup(zonesDrawnItems.getLayers());
            zonesMap.fitBounds(group.getBounds().pad(0.2));
          }
        } else {
          // Creating mode, render all zones so we see them
          renderZonesMap(zonesData, { fitBounds: true });
        }
      });
    }

    const btnCreateZone = document.getElementById('btnCreateZone');
    if (btnCreateZone) {
      btnCreateZone.addEventListener('click', () => {
        resetZoneForm();
        if (zoneModal) {
          zoneModal.show();
        }
      });
    }

    document.querySelectorAll('[data-zone]').forEach((button) => {
      button.addEventListener('click', () => {
        const zone = JSON.parse(button.dataset.zone);
        zoneFormTitle.textContent = 'Editar zona';
        zoneForm.action = "{{ route('traffic.zones.index') }}/" + zone.id;
        zoneMethod.value = 'PUT';
        document.getElementById('zoneName').value = zone.name || '';
        document.getElementById('zoneType').value = zone.type || 'circle';
        document.getElementById('zoneLat').value = zone.center_lat ?? '';
        document.getElementById('zoneLng').value = zone.center_lng ?? '';
        document.getElementById('zoneRadius').value = zone.radius_km ?? '';
        document.getElementById('zonePolygon').value = zone.polygon ? JSON.stringify(zone.polygon) : '';
        document.getElementById('zoneSvsValues').value = zone.svs_values ? zone.svs_values.join('\n') : '';
        document.getElementById('zonePriority').value = zone.priority || 'primary';
        document.getElementById('zoneMaxStops').value = zone.max_stops ?? '';
        document.getElementById('zoneSoft').checked = !!zone.is_soft;
        toggleZoneFields(zone.type || 'circle');
        
        if (zoneModal) {
          zoneModal.show();
        }
      });
    });
  </script>
@endpush
