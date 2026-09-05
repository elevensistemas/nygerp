@extends('layouts.app')

@section('title', 'Depósitos')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Depósitos</h1>
      <p class="text-muted mb-0">Ubicaciones propias para regreso a depósito.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#locationModal">
        <i class="fa-solid fa-plus me-1"></i> Nuevo depósito
      </button>
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h2 class="h5 mb-0">Listado</h2>
      <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#locationModal">
        <i class="fa-solid fa-plus me-1"></i> Nuevo depósito
      </button>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Dirección</th>
            <th>Coordenadas</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($locations as $location)
            <tr>
              <td>{{ $location->name }}</td>
              <td>{{ $location->address }}</td>
              <td class="text-muted small">
                @if($location->latitude && $location->longitude)
                  {{ $location->latitude }}, {{ $location->longitude }}
                @else
                  <span class="text-warning">Sin coordenadas</span>
                @endif
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#locationModal"
                        data-location='@json($location)'>
                  Editar
                </button>
                <form class="d-inline-block ms-1" method="POST"
                      action="{{ route('traffic.locations.destroy', $location) }}"
                      data-confirm="¿Eliminar depósito?">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">No hay depósitos cargados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $locations->withQueryString()->links() }}
    </div>
  </div>

  <div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="locationModalLabel">Nuevo depósito</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" id="locationForm" action="{{ route('traffic.locations.store') }}">
          @csrf
          <input type="hidden" name="_method" value="POST" id="locationMethod">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Nombre *</label>
              <input class="form-control @error('name') is-invalid @enderror" name="name" id="locName" required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label">Dirección *</label>
              <div class="position-relative">
                <input class="form-control @error('address') is-invalid @enderror js-loc-address" name="address" id="locAddress" autocomplete="off" required>
                <div class="list-group position-absolute w-100 js-loc-address-suggestions" style="z-index:1000; max-height:240px; overflow-y:auto; top:100%; left:0;"></div>
              </div>
              @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Latitud</label>
                <input class="form-control @error('latitude') is-invalid @enderror" name="latitude" id="locLat" step="0.000001" type="number">
                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Longitud</label>
                <input class="form-control @error('longitude') is-invalid @enderror" name="longitude" id="locLng" step="0.000001" type="number">
                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
            <div class="mb-3 mt-3">
              <label class="form-label">Notas</label>
              <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" id="locNotes" rows="2"></textarea>
              @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="locActive" name="is_active" value="1" checked>
              <label class="form-check-label" for="locActive">Depósito activo</label>
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
      const modalEl = document.getElementById('locationModal');
      if (!modalEl) return;
      const form = document.getElementById('locationForm');
      const methodInput = document.getElementById('locationMethod');
      const titleEl = document.getElementById('locationModalLabel');
      const fields = {
        name: document.getElementById('locName'),
        address: document.getElementById('locAddress'),
        latitude: document.getElementById('locLat'),
        longitude: document.getElementById('locLng'),
        notes: document.getElementById('locNotes'),
        is_active: document.getElementById('locActive'),
      };

      const clearForm = () => {
        form.action = "{{ route('traffic.locations.store') }}";
        methodInput.value = 'POST';
        titleEl.textContent = 'Nuevo depósito';
        Object.values(fields).forEach((el) => {
          if (!el) return;
          if (el.type === 'checkbox') {
            el.checked = true;
          } else {
            el.value = '';
          }
        });
      };

      modalEl.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const location = button?.dataset?.location ? JSON.parse(button.dataset.location) : null;
        clearForm();
        if (location) {
          titleEl.textContent = 'Editar depósito';
          form.action = "{{ route('traffic.locations.index') }}/" + location.id;
          methodInput.value = 'PUT';
          fields.name.value = location.name || '';
          fields.address.value = location.address || '';
          fields.latitude.value = location.latitude ?? '';
          fields.longitude.value = location.longitude ?? '';
          fields.notes.value = location.notes || '';
          fields.is_active.checked = !!location.is_active;
        }
      });
    })();
  </script>
  <script>
    (function () {
      const mapboxToken = '{{ config('services.mapbox.token') }}';
      if (!mapboxToken) return;

      const addressInput = document.querySelector('.js-loc-address');
      const suggestions = document.querySelector('.js-loc-address-suggestions');
      const latInput = document.getElementById('locLat');
      const lngInput = document.getElementById('locLng');

      const clearSuggestions = () => {
        if (suggestions) suggestions.innerHTML = '';
      };

      const fetchSuggestions = async () => {
        if (!addressInput || !suggestions) return;
        const query = addressInput.value.trim();
        clearSuggestions();
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        if (query.length < 3) return;

        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json` +
          `?access_token=${mapboxToken}&country=AR&language=es&autocomplete=true&limit=5`;

        try {
          const response = await fetch(url);
          if (!response.ok) return;
          const data = await response.json();
          (data.features || []).forEach((feature) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.textContent = feature.place_name || '';
            item.addEventListener('click', () => {
              addressInput.value = feature.place_name || '';
              const coords = feature.geometry?.coordinates;
              if (Array.isArray(coords) && coords.length === 2) {
                if (latInput) latInput.value = coords[1];
                if (lngInput) lngInput.value = coords[0];
              }
              clearSuggestions();
            });
            suggestions.appendChild(item);
          });
        } catch (error) {
          clearSuggestions();
        }
      };

      addressInput?.addEventListener('input', fetchSuggestions);
      document.addEventListener('click', (e) => {
        if (suggestions && !suggestions.contains(e.target) && e.target !== addressInput) {
          clearSuggestions();
        }
      });
    })();
  </script>
@endpush
