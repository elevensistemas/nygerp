@extends('layouts.app')

@php
  use Illuminate\Support\Str;
  use Carbon\Carbon;
  $routeModel = $routeModel ?? null;
  $isEdit = (bool) $routeModel;
  $associatedOrder = $order ?? ($routeModel->order ?? null);
  $pageTitle = $isEdit ? 'Editar ruta ' . $routeModel->code : 'Generador de rutas';
  $formAction = $isEdit
    ? route('traffic.routes.update', $routeModel)
    : route('traffic.routes.store');
  $submitLabel = $isEdit ? 'Guardar cambios' : 'Generar ruta';
  $selectedCarrier = old('transportista_id', $isEdit ? $routeModel->transportista_id : null);
  $selectedVehicle = old('transporte_id', $isEdit ? $routeModel->transporte_id : null);
  $selectedDate = old(
    'scheduled_date',
    $isEdit && $routeModel->scheduled_date
      ? $routeModel->scheduled_date->format('Y-m-d')
      : Carbon::now()->format('Y-m-d')
  );

  // 👉 Ahora incluimos todos los campos de dirección, igual que en el pedido
  $defaultStops = $isEdit
    ? $routeModel->stops->map(function ($stop) {
        return [
          'label'         => $stop->label,
          'address'       => $stop->address,
          'contact_name'  => $stop->contact_name,
          'contact_phone' => $stop->contact_phone,
          'city'          => $stop->city ?? '',
          'postal_code'   => $stop->postal_code ?? '',
          'notes'         => $stop->notes,
          'latitude'      => $stop->latitude ?? '',
          'longitude'     => $stop->longitude ?? '',
          'is_valid'      => is_numeric($stop->latitude) && is_numeric($stop->longitude),
        ];
      })->values()->toArray()
    : [];

  // Si viene de un pedido asociado, también traemos todos los campos
  $orderStops = (!$isEdit && $associatedOrder)
    ? $associatedOrder->addresses->map(function ($address) {
        return [
          'label'         => $address->label,
          'address'       => $address->address,
          'contact_name'  => $address->contact_name,
          'contact_phone' => $address->contact_phone,
          'city'          => $address->city ?? '',
          'postal_code'   => $address->postal_code ?? '',
          'notes'         => $address->notes,
          'latitude'      => $address->latitude ?? '',
          'longitude'     => $address->longitude ?? '',
          'is_valid'      => is_numeric($address->latitude) && is_numeric($address->longitude),
        ];
      })->values()->toArray()
    : [];

  $stopsData = old('stops', $orderStops ?: $defaultStops);
  $selectedOrderId = old('order_id', $associatedOrder->id ?? null);
  $returnToDepot = old('return_to_depot', false);
  $selectedLocation = old('location_id');
  $existingPreferences = $isEdit ? (data_get($routeModel->raw_payload, 'options', []) ?? []) : [];
  $avoidTolls = old('avoid_tolls', $existingPreferences['avoid_tolls'] ?? false);
  $avoidHighways = old('avoid_highways', $existingPreferences['avoid_highways'] ?? false);

  // 👉 Token de Mapbox igual que en el blade de pedido
  $mapboxToken = config('services.mapbox.token');
@endphp

@section('title', $pageTitle)

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">{{ $pageTitle }}</h1>
      <p class="text-muted mb-0">
        {{ $isEdit ? 'Actualizá las direcciones y regenerá la ruta.' : 'Planificá la ruta más directa considerando el estado del tránsito.' }}
      </p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.routes.index') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
      @if($isEdit && isset($routeModel))
        <a class="btn btn-outline-primary" href="{{ route('traffic.routes.show', $routeModel) }}">
          <i class="fa-solid fa-eye me-1"></i> Ver ruta
        </a>
      @endif
    </div>
  </div>

  <div class="alert alert-info">
    <strong>Tip:</strong> completá las direcciones lo más precisas posible. El sistema usa Mapbox (tráfico en tiempo real)
    y OpenStreetMap para dibujar la ruta. Se necesitan al menos dos puntos.
  </div>

  @if($errors->has('generator'))
    <div class="alert alert-danger">{{ $errors->first('generator') }}</div>
  @endif

  @if($associatedOrder)
    @php
      $associatedClient = $associatedOrder->client;
      $associatedClientLabel = $associatedClient ? ($associatedClient->business_name ?: $associatedClient->name) : $associatedOrder->client_name;
    @endphp
    <div class="alert alert-secondary border-0 shadow-sm mb-4">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
        <div>
          <strong>Pedido {{ $associatedOrder->order_number }}</strong>
          <div class="text-muted small">Cliente {{ $associatedClientLabel }}</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('traffic.orders.show', $associatedOrder) }}">
          <i class="fa-solid fa-eye me-1"></i> Ver pedido
        </a>
      </div>
      <div class="row mt-3 g-3">
        <div class="col-md-4">
          <p class="text-muted small mb-1">Fecha de pedido</p>
          <strong>{{ optional($associatedOrder->order_date)->format('d/m/Y') ?? 'Sin fecha' }}</strong>
        </div>
        <div class="col-md-4">
          <p class="text-muted small mb-1">Direcciones</p>
          <strong>{{ $associatedOrder->addresses->count() }}</strong>
        </div>
        <div class="col-md-4">
          <p class="text-muted small mb-1">Estado</p>
          <span class="badge bg-info text-dark">{{ Str::title($associatedOrder->status ?? 'pendiente') }}</span>
        </div>
      </div>
      @if($associatedOrder->delivery_instructions)
        <p class="mt-3 mb-0 small text-muted">Instrucciones: {{ $associatedOrder->delivery_instructions }}</p>
      @endif
    </div>
  @endif

  <form method="POST" action="{{ $formAction }}">
    @csrf
    <input type="hidden" name="order_id" value="{{ $selectedOrderId }}">
    @if($isEdit)
      @method('PUT')
    @endif

    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-md-5 mb-3">
            <label class="form-label">Transportista *</label>
            <select class="form-select @error('transportista_id') is-invalid @enderror"
                    id="carrierSelect"
                    name="transportista_id"
                    required>
              <option value="">Seleccionar...</option>
              @foreach($transportistas as $transportista)
                <option value="{{ $transportista->id }}"
                  {{ (int) $selectedCarrier === $transportista->id ? 'selected' : '' }}>
                  {{ optional($transportista->user)->name ?: $transportista->name }}
                </option>
              @endforeach
            </select>
            @error('transportista_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Transporte *</label>
            <select class="form-select @error('transporte_id') is-invalid @enderror"
                    id="vehicleSelect"
                    name="transporte_id"
                    required>
              <option value="">Seleccionar...</option>
            </select>
            @error('transporte_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Fecha programada</label>
            <input class="form-control @error('scheduled_date') is-invalid @enderror"
                   type="date"
                   name="scheduled_date"
                   id="scheduledDate"
                   value="{{ $selectedDate }}"
                   required>
            @error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    </div>

  <div class="card shadow-sm border-0">
      @include('traffic.components.addresses-form', [
        'wrapperId' => 'stopsWrapper',
        'addButtonId' => 'addStopBtn',
        'title' => 'Direcciones',
        'description' => 'El sistema las optimiza automaticamente.',
        'addButtonLabel' => 'Agregar destino',
        'collectionName' => 'stops',
        'addresses' => $stopsData,
        'minItems' => 2,
        'minItemsMessage' => 'La ruta necesita al menos dos direcciones.',
        'startEmpty' => true,
      ])
      <div class="card-body border-top">
        <div class="small text-muted mb-2">Mapa general de direcciones (sin ruta)</div>
        <div id="stopsOverviewMap" class="map-frame" style="height: 220px;"></div>
      </div>
      <div class="card-footer bg-white">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="avoidTolls" name="avoid_tolls" value="1" {{ $avoidTolls ? 'checked' : '' }}>
              <label class="form-check-label" for="avoidTolls">Evitar peajes</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="avoidHighways" name="avoid_highways" value="1" {{ $avoidHighways ? 'checked' : '' }}>
              <label class="form-check-label" for="avoidHighways">Evitar autopistas</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="returnToDepot" name="return_to_depot" value="1" {{ $returnToDepot ? 'checked' : '' }}>
              <label class="form-check-label" for="returnToDepot">Regresar a deposito</label>
            </div>
          </div>
        </div>
        <div class="mb-3 mt-3 {{ $returnToDepot ? '' : 'd-none' }}" id="depotSelectWrapper">
          <label class="form-label">Deposito</label>
          <select class="form-select" name="location_id" id="depotSelect">
            <option value="">Seleccionar deposito</option>
            @foreach(($locations ?? []) as $location)
              <option value="{{ $location->id }}" {{ (int) $selectedLocation === $location->id ? 'selected' : '' }}>
                {{ $location->name }} - {{ $location->address }}
              </option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-primary" type="submit" id="generateRouteBtn">
          <i class="fa-solid fa-route me-1"></i> {{ $submitLabel }}
        </button>
      </div>
    </div>
  </form>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  (function ($) {
    var carriersRoute = "{{ route('traffic.transportistas.transportes', ['transportista' => '__carrier__']) }}";

    var carrierSelect = $('#carrierSelect');
    var vehicleSelect = $('#vehicleSelect');
    var selectedVehicleId = Number({{ (int) ($selectedVehicle ?? 0) }});

    var flashData = @json(session('route_duplicate'));
    if (flashData) {
      if (flashData.transportista_id) {
        carrierSelect.val(String(flashData.transportista_id));
      }
      selectedVehicleId = Number(flashData.transporte_id || 0);
      $('input[name="scheduled_date"]').val(flashData.scheduled_date || '');
    }

    function resetVehicles(message) {
      var text = message || 'Seleccionar...';
      vehicleSelect.html('<option value="">' + text + '</option>');
      vehicleSelect.trigger('change.select2');
    }

    function fetchVehicles(carrierId) {
      if (!carrierId) {
        selectedVehicleId = 0;
        resetVehicles('Seleccionar...');
        return;
      }

      resetVehicles('Cargando...');

      $.ajax({
        url: carriersRoute.replace('__carrier__', carrierId),
        method: 'GET',
        dataType: 'json',
      }).done(function (response) {
        var options = ['<option value="">Seleccionar...</option>'];
        var data = (response && response.data) ? response.data : [];

        if (!data.length) {
          options = ['<option value="">Sin transportes activos</option>'];
        } else {
          data.forEach(function (item) {
            var label = item.alias || 'Transporte #' + item.id;
            if (item.license_plate) {
              label += ' (' + item.license_plate + ')';
            }
            options.push('<option value="' + item.id + '">' + label + '</option>');
          });
        }

        vehicleSelect.html(options.join(''));
        if (selectedVehicleId) {
          vehicleSelect.val(String(selectedVehicleId));
        } else {
          vehicleSelect.val('');
        }
        vehicleSelect.trigger('change.select2');
        vehicleSelect.trigger('change');
        toggleSubmit();
      }).fail(function () {
        resetVehicles('Error al cargar transportes');
        nygAlert('No pudimos cargar los transportes para el transportista seleccionado.', 'error');
      });
    }

    carrierSelect.on('change', function () {
      selectedVehicleId = 0;
      fetchVehicles(this.value);
    });

    vehicleSelect.on('change', function () {
      selectedVehicleId = Number(this.value || 0);
    });

    fetchVehicles(carrierSelect.val());

    // Habilitar botón solo si hay transportista, transporte y fecha
    const scheduledInput = $('#scheduledDate');
    const submitBtn = $('#generateRouteBtn');
    const stopsBuilder = () => (window.addressesFormBuilders ? window.addressesFormBuilders['stopsWrapper'] : null);

    function toggleSubmit() {
      const hasCarrier = !!carrierSelect.val();
      const hasVehicle = !!vehicleSelect.val();
      const hasDate = !!scheduledInput.val();
      submitBtn.prop('disabled', !(hasCarrier && hasVehicle && hasDate));
    }

    carrierSelect.on('change', toggleSubmit);
    vehicleSelect.on('change', toggleSubmit);
    scheduledInput.on('input change', toggleSubmit);
    toggleSubmit();

    const depotCheckbox = document.getElementById('returnToDepot');
    const depotWrapper = document.getElementById('depotSelectWrapper');
    const toggleDepot = () => {
      if (!depotWrapper || !depotCheckbox) return;
      depotWrapper.classList.toggle('d-none', !depotCheckbox.checked);
    };
    depotCheckbox?.addEventListener('change', toggleDepot);
    toggleDepot();

    // Validar min 2 direcciones al enviar
    $('form').on('submit', function (e) {
      const builder = stopsBuilder();
      if (builder && typeof builder.syncFromDom === 'function') {
        builder.syncFromDom();
        if (!builder.addresses || builder.addresses.length < 2) {
          e.preventDefault();
          if (window.nygAlert) {
            window.nygAlert('La ruta necesita al menos dos direcciones.', 'warning');
          }
          return false;
        }
      }
      if (submitBtn.prop('disabled')) {
        e.preventDefault();
        if (window.nygAlert) {
          window.nygAlert('Seleccion? transportista, transporte y fecha.', 'warning');
        }
        return false;
      }
      return true;
    });
  })(jQuery);
</script>

<script>
  (function () {
    const mapEl = document.getElementById('stopsOverviewMap');
    if (!mapEl || !window.L) return;

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const map = window.L.map(mapEl, { attributionControl: false, zoomControl: false });
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 20,
    }).addTo(map);

    let markersLayer = window.L.featureGroup().addTo(map);

    const renderStopsMap = (addresses) => {
      markersLayer.clearLayers();
      const coords = [];
      (addresses || []).forEach((addr, index) => {
        const lat = parseFloat(addr.latitude ?? addr.lat);
        const lng = parseFloat(addr.longitude ?? addr.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const label = escapeHtml(addr.address || addr.label || `Parada ${index + 1}`);
        const marker = window.L.marker([lat, lng]);
        marker.bindPopup(
          `<div class="small">
            <div class="mb-2">${label}</div>
            <button type="button" class="btn btn-sm btn-outline-danger js-stop-remove" data-index="${index}">
              Eliminar punto
            </button>
          </div>`
        );
        marker.addTo(markersLayer);
        coords.push([lat, lng]);
      });

      if (coords.length) {
        map.fitBounds(markersLayer.getBounds(), { padding: [20, 20] });
      } else {
        map.setView([-34.6037, -58.3816], 5);
      }

      setTimeout(() => map.invalidateSize(), 0);
    };

    const stopsWrapper = document.getElementById('stopsWrapper');
    if (stopsWrapper) {
      stopsWrapper.addEventListener('addresses:updated', (event) => {
        renderStopsMap(event.detail?.addresses || []);
      });
    }

    setTimeout(() => {
      const builder = window.addressesFormBuilders ? window.addressesFormBuilders['stopsWrapper'] : null;
      if (builder) {
        const list = typeof builder.collectFromDom === 'function'
          ? builder.collectFromDom()
          : (builder.addresses || []);
        renderStopsMap(list);
      }
    }, 0);

    document.addEventListener('click', (event) => {
      const target = event.target;
      if (!target || !target.classList.contains('js-stop-remove')) return;
      const index = Number(target.getAttribute('data-index'));
      const builder = window.addressesFormBuilders ? window.addressesFormBuilders['stopsWrapper'] : null;
      if (!builder || !Number.isFinite(index)) return;
      builder.removeAddress(index);
      if (map) {
        map.closePopup();
      }
    });
  })();
</script>
@endpush