@extends('layouts.app')

@section('title', 'Transportes')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Transportes</h1>
      <p class="text-muted mb-0">Vehículos y unidades asignadas a cada transportista.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#vehicleModal">
        <i class="fa-solid fa-plus me-1"></i> Nuevo transporte
      </button>
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a tráfico
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <h2 class="h5 mb-0">Listado</h2>
      <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#vehicleModal">
        <i class="fa-solid fa-plus me-1"></i> Nuevo transporte
      </button>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Alias</th>
            <th>Transportista</th>
            <th>Detalles</th>
            <th class="text-center" style="width: 60px;"><i class="fa-solid fa-star" title="Por defecto"></i></th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transportes as $transporte)
            <tr>
              <td>
                <strong>{{ $transporte->alias }}</strong><br>
                <small class="text-muted">{{ $transporte->license_plate ?: 'sin patente' }}</small>
              </td>
              <td>
                <span class="carrier-color-dot" style="background: {{ $transporte->transportista->color ?? '#2563eb' }};"></span>
                {{ $transporte->transportista->name ?? 'N/D' }}
              </td>
              <td>
                <div>{{ \App\Models\Transporte::paymentVehicleTypes()[$transporte->type] ?? $transporte->type ?: 'Tipo no definido' }}</div>
                <small class="text-muted">
                  Capacidad: {{ $transporte->capacity_kg ? $transporte->capacity_kg . ' kg' : 'n/d' }}
                  @php
                    $dims = array_filter([
                      $transporte->length_cm ? rtrim(rtrim(number_format($transporte->length_cm, 2, '.', ''), '0'), '.') : null,
                      $transporte->width_cm ? rtrim(rtrim(number_format($transporte->width_cm, 2, '.', ''), '0'), '.') : null,
                      $transporte->height_cm ? rtrim(rtrim(number_format($transporte->height_cm, 2, '.', ''), '0'), '.') : null,
                    ]);
                    $volume = $transporte->volume_m3 ? rtrim(rtrim(number_format($transporte->volume_m3, 3, '.', ''), '0'), '.') : null;
                  @endphp
                  @if(count($dims) === 3)
                    · Dimensiones: {{ implode(' x ', $dims) }} cm
                  @endif
                  @if($volume)
                    · Volumen: {{ $volume }} m³
                  @endif
                </small>
              </td>
              <td class="text-center">
                @if($transporte->is_default)
                  <span class="badge bg-warning text-dark" title="Transporte por defecto"><i class="fa-solid fa-star"></i></span>
                @endif
              </td>
              <td class="text-end">
                @php
                  $vehiclePayload = json_encode([
                    'id' => $transporte->id,
                    'transportista_id' => $transporte->transportista_id,
                    'transportista_name' => optional($transporte->transportista)->name,
                    'alias' => $transporte->alias,
                    'license_plate' => $transporte->license_plate,
                    'type' => $transporte->type,
                    'brand' => $transporte->brand,
                    'model' => $transporte->model,
                    'year' => $transporte->year,
                    'capacity_kg' => $transporte->capacity_kg,
                    'length_cm' => $transporte->length_cm,
                    'width_cm' => $transporte->width_cm,
                    'height_cm' => $transporte->height_cm,
                    'volume_m3' => $transporte->volume_m3,
                    'tracking_identifier' => $transporte->tracking_identifier,
                    'notes' => $transporte->notes,
                    'is_active' => $transporte->is_active,
                    'is_default' => $transporte->is_default,
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                @endphp
                <button class="btn btn-sm btn-outline-primary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#vehicleModal"
                        data-vehicle='{{ $vehiclePayload }}'>
                  Editar
                </button>
                <form class="d-inline-block ms-1"
                      method="POST"
                      action="{{ route('traffic.transportes.destroy', $transporte) }}"
                      data-confirm="¿Eliminar transporte?">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">No hay transportes cargados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $transportes->withQueryString()->links() }}
    </div>
  </div>

  <div class="modal fade" id="vehicleModal" tabindex="-1" aria-labelledby="vehicleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="vehicleModalLabel">Nuevo transporte</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" id="vehicleForm" action="{{ route('traffic.transportes.store') }}">
          @csrf
          <input type="hidden" name="_method" value="POST" id="vehicleMethod">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Transportista *</label>
              <select class="form-select @error('transportista_id') is-invalid @enderror"
                      name="transportista_id"
                      id="vehicleCarrier"
                      required>
                <option value="">Seleccionar...</option>
                @foreach($transportistas as $transportista)
                  <option value="{{ $transportista->id }}">{{ $transportista->name }}</option>
                @endforeach
              </select>
              @error('transportista_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Alias / Identificador *</label>
              <input class="form-control @error('alias') is-invalid @enderror"
                     name="alias"
                     id="vehicleAlias"
                     required>
              @error('alias')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Patente</label>
                <input class="form-control @error('license_plate') is-invalid @enderror"
                       name="license_plate"
                       id="vehiclePlate">
                @error('license_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Tipo</label>
                <select class="form-select @error('type') is-invalid @enderror"
                        name="type"
                        id="vehicleType">
                  <option value="">Seleccionar...</option>
                  @foreach(\App\Models\Transporte::paymentVehicleTypes() as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                  @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Marca</label>
                <input class="form-control @error('brand') is-invalid @enderror"
                       name="brand"
                       id="vehicleBrand">
                @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Modelo</label>
                <input class="form-control @error('model') is-invalid @enderror"
                       name="model"
                       id="vehicleModel">
                @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Año</label>
                <input class="form-control @error('year') is-invalid @enderror"
                       type="number"
                       name="year"
                       id="vehicleYear">
                @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Capacidad (kg)</label>
                <input class="form-control @error('capacity_kg') is-invalid @enderror"
                       type="number"
                       name="capacity_kg"
                       id="vehicleCapacity">
                @error('capacity_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Largo (cm)</label>
                <input class="form-control @error('length_cm') is-invalid @enderror"
                       type="number" step="0.01" min="0"
                       name="length_cm"
                       id="vehicleLength">
                @error('length_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Ancho (cm)</label>
                <input class="form-control @error('width_cm') is-invalid @enderror"
                       type="number" step="0.01" min="0"
                       name="width_cm"
                       id="vehicleWidth">
                @error('width_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Alto (cm)</label>
                <input class="form-control @error('height_cm') is-invalid @enderror"
                       type="number" step="0.01" min="0"
                       name="height_cm"
                       id="vehicleHeight">
                @error('height_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Volumen (m³)</label>
                <input class="form-control @error('volume_m3') is-invalid @enderror"
                       type="number" step="0.001" min="0"
                       name="volume_m3"
                       id="vehicleVolume">
                @error('volume_m3')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Identificador GPS / interno</label>
              <input class="form-control @error('tracking_identifier') is-invalid @enderror"
                     name="tracking_identifier"
                     id="vehicleTracking">
              @error('tracking_identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Notas</label>
              <textarea class="form-control @error('notes') is-invalid @enderror"
                        rows="3"
                        name="notes"
                        id="vehicleNotes"></textarea>
              @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-check form-switch mb-3">
              <input class="form-check-input"
                     type="checkbox"
                     id="vehicleActive"
                     name="is_active"
                     value="1"
                     checked>
              <label class="form-check-label" for="vehicleActive">Transporte activo</label>
            </div>

            <div class="form-check form-switch">
              <input class="form-check-input"
                     type="checkbox"
                     id="vehicleDefault"
                     name="is_default"
                     value="1">
              <label class="form-check-label" for="vehicleDefault">
                <strong>Marcar como transporte por defecto para este transportista</strong>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const modalEl = document.getElementById('vehicleModal');
      if (!modalEl) return;

      const form = document.getElementById('vehicleForm');
      const methodInput = document.getElementById('vehicleMethod');
      const titleEl = document.getElementById('vehicleModalLabel');

      const fields = {
        transportista_id: document.getElementById('vehicleCarrier'),
        alias: document.getElementById('vehicleAlias'),
        license_plate: document.getElementById('vehiclePlate'),
        type: document.getElementById('vehicleType'),
        brand: document.getElementById('vehicleBrand'),
        model: document.getElementById('vehicleModel'),
        year: document.getElementById('vehicleYear'),
        capacity_kg: document.getElementById('vehicleCapacity'),
        length_cm: document.getElementById('vehicleLength'),
        width_cm: document.getElementById('vehicleWidth'),
        height_cm: document.getElementById('vehicleHeight'),
        volume_m3: document.getElementById('vehicleVolume'),
        tracking_identifier: document.getElementById('vehicleTracking'),
        notes: document.getElementById('vehicleNotes'),
        is_active: document.getElementById('vehicleActive'),
        is_default: document.getElementById('vehicleDefault'),
      };

      const clearForm = () => {
        form.action = "{{ route('traffic.transportes.store') }}";
        methodInput.value = 'POST';
        titleEl.textContent = 'Nuevo transporte';
        Object.values(fields).forEach((el) => {
          if (!el) return;
          if (el.tagName === 'SELECT') {
            el.value = '';
          } else if (el.type === 'checkbox') {
            el.checked = el.id === 'vehicleActive' ? true : false;
          } else {
            el.value = '';
          }
        });
      };

      modalEl.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const vehicle = button?.dataset?.vehicle ? JSON.parse(button.dataset.vehicle) : null;
        clearForm();

          if (vehicle) {
            titleEl.textContent = 'Editar transporte';
            form.action = "{{ route('traffic.transportes.index') }}/" + vehicle.id;
            methodInput.value = 'PUT';

            if (vehicle.transportista_id) {
              const exists = Array.from(fields.transportista_id.options).some(opt => String(opt.value) === String(vehicle.transportista_id));
              if (!exists) {
                const opt = document.createElement('option');
                opt.value = vehicle.transportista_id;
                opt.textContent = vehicle.transportista_name || `Transportista #${vehicle.transportista_id}`;
                fields.transportista_id.appendChild(opt);
              }
            }

          fields.transportista_id.value = vehicle.transportista_id ? String(vehicle.transportista_id) : '';
          fields.transportista_id.dispatchEvent(new Event('change'));
          fields.alias.value = vehicle.alias || '';
          fields.license_plate.value = vehicle.license_plate || '';
          fields.type.value = vehicle.type || '';
          fields.brand.value = vehicle.brand || '';
          fields.model.value = vehicle.model || '';
          fields.year.value = vehicle.year || '';
          fields.capacity_kg.value = vehicle.capacity_kg || '';
          fields.length_cm.value = vehicle.length_cm || '';
          fields.width_cm.value = vehicle.width_cm || '';
          fields.height_cm.value = vehicle.height_cm || '';
          fields.volume_m3.value = vehicle.volume_m3 || '';
          fields.tracking_identifier.value = vehicle.tracking_identifier || '';
          fields.notes.value = vehicle.notes || '';
          fields.is_active.checked = !!vehicle.is_active;
          fields.is_default.checked = !!vehicle.is_default;
        }
      });
    })();
  </script>
@endpush
