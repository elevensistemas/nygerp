@extends('layouts.app')

@section('title', 'Transportistas')

@section('content')
  @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.6/build/css/intlTelInput.css">
    <style>
      #zonesMap { height: 380px; border-radius: 0.5rem; }
      .iti { width: 100%; }
      .geocode-suggestions {
        position: relative;
      }
      .geocode-suggestions ul {
        position: absolute;
        z-index: 1000;
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
      .bulk-column {
        display: none;
        width: 48px;
        text-align: center;
      }
      .bulk-table.bulk-mode .bulk-column {
        display: table-cell;
      }
      .bulk-column .bulk-select {
        margin: 0 auto;
      }
    </style>
  @endpush
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Transportistas</h1>
      <p class="text-muted mb-0">Crea y vincula transportistas con los usuarios que tengan ese rol.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#carrierModal">
        <i class="fa-solid fa-plus me-1"></i> Nuevo transportista
      </button>
      <a class="btn btn-outline-success" href="{{ route('traffic.transportistas.export') }}">
        <i class="fa-solid fa-file-excel me-1"></i> Exportar Excel
      </a>
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#carrierImportModal">
        <i class="fa-solid fa-file-import me-1"></i> Importar
      </button>
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a Tráfico
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('warnings'))
    <div class="alert alert-warning">
      <ul class="mb-0">
        @foreach((array) session('warnings') as $warning)
          <li>{{ $warning }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('traffic.transportistas.index') }}" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Transportista</label>
          <select class="form-select" name="transportista_id" id="carrierFilterTransportista">
            <option value="">Todos</option>
            @foreach($transportistasFilter as $t)
              <option value="{{ $t->id }}" {{ (string) ($filters['transportista_id'] ?? '') === (string) $t->id ? 'selected' : '' }}>
                {{ $t->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Transporte</label>
          <select class="form-select" name="transporte_id" id="carrierFilterTransporte">
            <option value="">Todos</option>
            @foreach($transportesFilter as $tr)
              <option value="{{ $tr->id }}" data-transportista-id="{{ $tr->transportista_id }}"
                      {{ (string) ($filters['transporte_id'] ?? '') === (string) $tr->id ? 'selected' : '' }}>
                {{ $tr->alias }}{{ $tr->license_plate ? ' - ' . $tr->license_plate : '' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Usuario</label>
          <select class="form-select" name="user_id">
            <option value="">Todos</option>
            <option value="none" {{ ($filters['user_id'] ?? '') === 'none' ? 'selected' : '' }}>Sin usuario asignado</option>
            @foreach($users as $user)
              <option value="{{ $user->id }}" {{ (string) ($filters['user_id'] ?? '') === (string) $user->id ? 'selected' : '' }}>
                {{ $user->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Zonas</label>
          <select class="form-select" name="has_zones">
            <option value="">Todas</option>
            <option value="yes" {{ ($filters['has_zones'] ?? '') === 'yes' ? 'selected' : '' }}>Con zonas</option>
            <option value="no" {{ ($filters['has_zones'] ?? '') === 'no' ? 'selected' : '' }}>Sin zonas</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Transportes</label>
          <select class="form-select" name="has_transportes">
            <option value="">Todos</option>
            <option value="yes" {{ ($filters['has_transportes'] ?? '') === 'yes' ? 'selected' : '' }}>Con transportes</option>
            <option value="no" {{ ($filters['has_transportes'] ?? '') === 'no' ? 'selected' : '' }}>Sin transportes</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Dirección (buscar por zonas)</label>
          <div class="geocode-suggestions">
            <input class="form-control" type="text" name="address" id="carrierFilterAddress"
                   value="{{ $filters['address'] ?? '' }}" placeholder="Ej: Av. Corrientes 1234, CABA">
            <ul class="d-none" id="carrierAddressSuggestions"></ul>
          </div>
          <input type="hidden" name="address_lat" id="carrierFilterAddressLat" value="{{ $addressLat ?? '' }}">
          <input type="hidden" name="address_lng" id="carrierFilterAddressLng" value="{{ $addressLng ?? '' }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary w-100" type="submit">Filtrar</button>
          <a class="btn btn-outline-secondary" href="{{ route('traffic.transportistas.index') }}">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white">
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Listado</h2>
        <div class="d-flex gap-2">
          <button class="btn btn-primary btn-sm d-none" type="button" data-bs-toggle="modal" data-bs-target="#carrierModal">
            <i class="fa-solid fa-plus me-1"></i> Nuevo transportista
          </button>
          <button class="btn btn-outline-danger btn-sm" type="button" id="bulkModeToggleBtn">
            <i class="fa-solid fa-trash me-1"></i> Eliminar varios
          </button>
        </div>
      </div>
      <div id="bulkSelectionControls" class="d-none mt-3 border-top pt-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="small text-muted" id="bulkSelectedCount">0 seleccionados</span>
          <button class="btn btn-sm btn-outline-primary" type="button" id="bulkSelectAllBtn">Seleccionar todo</button>
          <button class="btn btn-sm btn-outline-secondary" type="button" id="bulkDeselectAllBtn">Deseleccionar todo</button>
          <button class="btn btn-sm btn-danger" type="button" id="bulkDeleteSubmitBtn" disabled>Eliminar seleccionados</button>
        </div>
      </div>
    </div>
    <div class="table-responsive">
      <table id="transportistasTable" class="table align-middle mb-0 bulk-table">
        <thead>
          <tr>
            <th class="bulk-column"></th>
            <th>Nombre</th>
            <th>Color</th>
            <th>Usuario</th>
            <th>Detalles</th>
            <th class="text-end">Transportes</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transportistas as $transportista)
            <tr class="{{ $transportista->isNewDriver() ? 'new-driver-row' : '' }}">
              <td class="bulk-column">
                <div class="form-check">
                  <input class="form-check-input bulk-select" type="checkbox" value="{{ $transportista->id }}" id="bulkSelect{{ $transportista->id }}">
                </div>
              </td>
              <td>
                <strong>{{ $transportista->name }}</strong>
                @if($transportista->isNewDriver())
                  <span class="new-driver-badge" title="Nuevo chofer ({{ $transportista->days_since_hired }} días dado de alta)"><i class="fa-solid fa-user-plus"></i> Nuevo ({{ $transportista->days_since_hired }} d)</span>
                @endif
                @if($transportista->isAdvanceBlocked())
                  <span class="badge bg-danger text-white" title="Adelantos suspendidos hasta {{ $transportista->advance_blocked_until->format('d/m/Y') }}"><i class="fa-solid fa-ban"></i> Adelantos Susp.</span>
                @endif
                <div class="text-muted small">{{ $transportista->business_name ?: 'Sin empresa' }}</div>
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input carrier-active-toggle"
                         type="checkbox"
                         role="switch"
                         id="carrierActiveToggle{{ $transportista->id }}"
                         data-id="{{ $transportista->id }}"
                         data-name="{{ $transportista->name }}"
                         {{ $transportista->is_active ? 'checked' : '' }}>
                  <label class="form-check-label small text-muted" for="carrierActiveToggle{{ $transportista->id }}" id="activeLabel{{ $transportista->id }}">
                    {{ $transportista->is_active ? 'Activo' : 'Inactivo' }}
                  </label>
                </div>
              </td>
              <td>
                <span class="carrier-color-dot" style="background: {{ $transportista->color ?? '#2563eb' }};"></span>
                <span class="text-muted small">{{ $transportista->color ?? '#2563eb' }}</span>
              </td>
              <td>
                @if($transportista->user)
                  <div>{{ $transportista->user->name }}</div>
                  <div class="text-muted small">{{ $transportista->user->email }}</div>
                @else
                  <span class="text-muted small">Sin usuario asignado</span>
                @endif
              </td>
              <td>
                <div>{{ $transportista->license_number ?: 'Licencia no registrada' }}</div>
                <div class="text-muted small">{{ $transportista->base_location ?: 'Sin base operacional' }}</div>
                <div class="text-muted small">
                  {{ optional($transportista->supplier)->name ?: 'Sin proveedor' }}
                  · Costo: {{ number_format($transportista->cost_efficiency ?? 0, 2) }}
                  · Ponderación: {{ number_format($transportista->performance_weight ?? 1, 2) }}
                </div>
                <div class="text-muted small">
                  Banco: {{ optional($transportista->bank)->name ?: 'Sin banco' }}
                  · Cuenta: {{ $transportista->account_number ?: '-' }}
                  · CBU: {{ $transportista->cbu ?: '-' }}
                </div>
                <div class="text-muted small">
                  Liquidacion: {{ optional($transportista->liquidationMeta)->tipo_periodo ? ucfirst(optional($transportista->liquidationMeta)->tipo_periodo) : 'Sin configurar' }}
                </div>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#zonesModal"
                        data-transportista-id="{{ $transportista->id }}"
                        data-transportista-name="{{ $transportista->name }}">
                  <i class="fa-solid fa-draw-polygon me-1"></i> Zonas
                </button>
                <button class="btn btn-sm btn-outline-info"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#transportesModal"
                        data-transportista-id="{{ $transportista->id }}"
                        data-transportista-name="{{ $transportista->name }}">
                  <i class="fa-solid fa-truck me-1"></i> Ver transportes
                </button>
                <button class="btn btn-sm btn-outline-warning"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#paymentMethodsModal"
                        data-transportista-id="{{ $transportista->id }}"
                        data-transportista-name="{{ $transportista->name }}">
                  <i class="fa-solid fa-building-columns me-1"></i> Medios de pago
                </button>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#carrierModal"
                        data-carrier='@json($transportista)'>
                  Editar
                </button>
                <form class="d-inline-block ms-1" method="POST"
                      action="{{ route('traffic.transportistas.destroy', $transportista) }}"
                      data-confirm="¿Eliminar transportista?">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">No hay transportistas cargados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $transportistas->withQueryString()->links() }}
    </div>
  </div>

  <div class="modal fade" id="carrierModal" tabindex="-1" aria-labelledby="carrierModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="carrierModalLabel">Nuevo transportista</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" id="carrierForm" action="{{ route('traffic.transportistas.store') }}">
          @csrf
          <input type="hidden" name="_method" value="POST" id="carrierMethod">
          <div class="modal-body">
            <div class="alert alert-danger d-none" id="carrierPhoneAlert"></div>
            <div class="row g-3">
              @if($users->count() > 0)
                <div class="col-md-6">
                  <label class="form-label">Usuario</label>
                  <select class="form-select @error('user_id') is-invalid @enderror" name="user_id" id="carrierUser">
                    <option value="">Usuario Nuevo</option>
                    @foreach($users as $user)
                      <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                  </select>
                  @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              @endif
              <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input class="form-control @error('name') is-invalid @enderror"
                       name="name"
                       id="carrierName"
                       value="{{ old('name') }}"
                       required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Razón social</label>
                <input class="form-control @error('business_name') is-invalid @enderror"
                       name="business_name"
                       id="carrierBusiness"
                       value="{{ old('business_name') }}">
                @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control @error('email') is-invalid @enderror"
                       name="email"
                       id="carrierEmail"
                       value="{{ old('email') }}"
                       type="email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Contacto</label>
                <input class="form-control @error('phone') is-invalid @enderror"
                       name="phone"
                       id="carrierPhone"
                       value="{{ old('phone') }}">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="invalid-feedback d-none" id="carrierPhoneClientError"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Cuit / CUIL</label>
                <input class="form-control @error('tax_id') is-invalid @enderror"
                       name="tax_id"
                       id="carrierTax"
                       value="{{ old('tax_id') }}">
                @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Número alternativo</label>
                <input class="form-control @error('phone_alt') is-invalid @enderror"
                       name="phone_alt"
                       id="carrierPhoneAlt"
                       value="{{ old('phone_alt') }}">
                @error('phone_alt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="invalid-feedback d-none" id="carrierPhoneAltClientError"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">DNI</label>
                <input class="form-control @error('dni') is-invalid @enderror"
                       name="dni"
                       id="carrierDni"
                       value="{{ old('dni') }}">
                @error('dni')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Fecha de nacimiento</label>
                <input class="form-control @error('birth_date') is-invalid @enderror"
                       name="birth_date"
                       id="carrierBirthDate"
                       type="date"
                       value="{{ old('birth_date') }}">
                @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Registro (vencimiento)</label>
                <input class="form-control @error('license_expires_at') is-invalid @enderror"
                       name="license_expires_at"
                       id="carrierLicenseExpires"
                       type="date"
                       value="{{ old('license_expires_at') }}">
                @error('license_expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Seguro personal</label>
                <input class="form-control @error('personal_insurance') is-invalid @enderror"
                       name="personal_insurance"
                       id="carrierInsurance"
                       value="{{ old('personal_insurance') }}">
                @error('personal_insurance')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Cert. de domicilio</label>
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input"
                         type="checkbox"
                         id="carrierAddressCert"
                         name="address_certificate"
                         value="1"
                         {{ old('address_certificate', false) ? 'checked' : '' }}>
                  <label class="form-check-label" for="carrierAddressCert">Presentado</label>
                </div>
                @error('address_certificate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Cert. antecedentes penales</label>
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input"
                         type="checkbox"
                         id="carrierCriminalCert"
                         name="criminal_record_certificate"
                         value="1"
                         {{ old('criminal_record_certificate', false) ? 'checked' : '' }}>
                  <label class="form-check-label" for="carrierCriminalCert">Presentado</label>
                </div>
                @error('criminal_record_certificate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Zona</label>
                <input class="form-control @error('base_location') is-invalid @enderror"
                       name="base_location"
                       id="carrierBase"
                       value="{{ old('base_location') }}">
                @error('base_location')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <input class="form-control @error('status') is-invalid @enderror"
                       name="status"
                       id="carrierStatus"
                       value="{{ old('status') }}">
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Condición</label>
                <input class="form-control @error('condition') is-invalid @enderror"
                       name="condition"
                       id="carrierCondition"
                       value="{{ old('condition') }}">
                @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Cliente</label>
                <input class="form-control @error('client_name') is-invalid @enderror"
                       name="client_name"
                       id="carrierClient"
                       value="{{ old('client_name') }}">
                @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Licencia</label>
                <input class="form-control @error('license_number') is-invalid @enderror"
                       name="license_number"
                       id="carrierLicense"
                       value="{{ old('license_number') }}">
                @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Banco</label>
                <select class="form-select @error('bank_id') is-invalid @enderror" name="bank_id" id="carrierBank">
                  <option value="">Sin banco</option>
                  @foreach($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                  @endforeach
                </select>
                @error('bank_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">CBU</label>
                <input class="form-control @error('cbu') is-invalid @enderror"
                       name="cbu"
                       id="carrierCbu"
                       value="{{ old('cbu') }}">
                @error('cbu')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Numero de cuenta</label>
                <input class="form-control @error('account_number') is-invalid @enderror"
                       name="account_number"
                       id="carrierAccountNumber"
                       value="{{ old('account_number') }}">
                @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Monotributo</label>
                <input class="form-control @error('monotributo') is-invalid @enderror"
                       name="monotributo"
                       id="carrierMonotributo"
                       value="{{ old('monotributo') }}">
                @error('monotributo')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Fecha de ingreso</label>
                <input class="form-control @error('hire_date') is-invalid @enderror"
                       name="hire_date"
                       id="carrierHireDate"
                       type="date"
                       value="{{ old('hire_date') }}">
                @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="mb-3 mt-3">
              <label class="form-label">Proveedor</label>
              <select class="form-select @error('supplier_id') is-invalid @enderror" name="supplier_id" id="carrierSupplier">
                <option value="">Sin proveedor</option>
                @foreach($suppliers as $supplier)
                  <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
              </select>
              @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Costo del transportista</label>
                <input class="form-control @error('cost_efficiency') is-invalid @enderror"
                       name="cost_efficiency"
                       id="carrierCost"
                       type="number"
                       step="0.01"
                       min="0"
                       value="{{ old('cost_efficiency', 0) }}">
                <div class="form-text">Costo real (por km o fijo según definas).</div>
                @error('cost_efficiency')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Ponderación de desempeño</label>
                <input class="form-control @error('performance_weight') is-invalid @enderror"
                       name="performance_weight"
                       id="carrierWeight"
                       type="number"
                       step="0.01"
                       min="0"
                       value="{{ old('performance_weight', 1) }}">
                <div class="form-text">Factor para priorizar transportistas.</div>
                @error('performance_weight')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Color</label>
              <input class="form-control form-control-color @error('color') is-invalid @enderror"
                     type="color"
                     name="color"
                     id="carrierColor"
                     value="{{ old('color', '#2563eb') }}">
              @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Notas</label>
              <textarea class="form-control @error('notes') is-invalid @enderror"
                        name="notes"
                        id="carrierNotes"
                        rows="3">{{ old('notes') }}</textarea>
              @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Tipo de liquidación</label>
                <select class="form-select no-select2 @error('liquidation_tipo_periodo') is-invalid @enderror" name="liquidation_tipo_periodo" id="carrierLiquidationTipoPeriodo">
                  <option value="">Por defecto (Configuración general)</option>
                  <option value="quincenal">Quincenal</option>
                  <option value="mensual">Mensual</option>
                </select>
                @error('liquidation_tipo_periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Permisos del portal</label>
                <select class="form-select no-select2 @error('portal_visibility') is-invalid @enderror" name="portal_visibility" id="carrierPortalVisibility">
                  <option value="all">Rutas y Liquidaciones</option>
                  <option value="routes_only">Solo Rutas</option>
                  <option value="settlements_only">Solo Liquidaciones</option>
                </select>
                @error('portal_visibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Suspender adelantos hasta</label>
                <input class="form-control @error('advance_blocked_until') is-invalid @enderror"
                       type="date"
                       name="advance_blocked_until"
                       id="carrierAdvanceBlockedUntil"
                       value="{{ old('advance_blocked_until') }}">
                <div class="form-text">El chofer no podrá solicitar adelantos hasta esta fecha.</div>
                @error('advance_blocked_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-check form-switch mb-3">
              <input class="form-check-input"
                     type="checkbox"
                     id="carrierActive"
                     name="is_active"
                     value="1"
                     {{ old('is_active', true) ? 'checked' : '' }}>
              <label class="form-check-label" for="carrierActive">Transportista activo</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="carrierSaveBtn">Guardar</button>
          </div>
        </form>
      </div>
    </div>
    <form id="transportistaBulkDeleteForm" method="POST" action="{{ route('traffic.transportistas.bulk.destroy') }}" class="d-none">
      @csrf
      @method('DELETE')
    </form>
  </div>

  <div class="modal fade" id="carrierImportModal" tabindex="-1" aria-labelledby="carrierImportModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="carrierImportModalLabel">Importar transportistas y transportes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" id="carrierImportForm" action="{{ route('traffic.transportistas.import') }}" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="phone_normalization" id="carrierImportPhoneNormalization" value="">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Modo de importación</label>
                <select class="form-select" name="mode" id="carrierImportMode">
                  <option value="single" selected>Un solo Excel (2 hojas)</option>
                  <option value="dual">Dos Excels (1 por entidad)</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Columna de match (transportistas)</label>
                <select class="form-select" name="transportistas_match_column" id="carrierImportMatchTransportistas">
                  <option value="">Selecciona columna</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Columna de match (transportes)</label>
                <select class="form-select" name="transportes_match_column" id="carrierImportMatchTransportes">
                  <option value="">Selecciona columna</option>
                </select>
              </div>
            </div>

            <div class="mt-3" id="carrierImportSingle">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Excel unico</label>
                  <input class="form-control" type="file" name="file" id="carrierImportFile" accept=".xlsx,.xls,.csv">
                </div>
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-light h-100">
                    <div class="small text-muted mb-2">Hojas asignadas</div>
                    <div class="d-flex flex-wrap gap-2">
                      <span class="badge text-bg-light" id="carrierImportAssignedTransportistas">Transportistas: -</span>
                      <span class="badge text-bg-light" id="carrierImportAssignedTransportes">Transportes: -</span>
                    </div>
                    <input type="hidden" name="transportistas_sheet" id="carrierImportSheetTransportistas">
                    <input type="hidden" name="transportes_sheet" id="carrierImportSheetTransportes">
                    <div class="form-text mt-2">Selecciona una hoja desde las pesta?as para asignarla.</div>
                  </div>
                </div>
                <div class="col-12">
                  <ul class="nav nav-tabs" id="carrierImportSingleTabs" role="tablist"></ul>
                  <div class="tab-content border border-top-0 rounded-bottom p-3" id="carrierImportSinglePanels">
                    <div class="text-muted">Carga un Excel para ver las hojas.</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-3 d-none" id="carrierImportDual">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Excel de transportistas</label>
                  <input class="form-control" type="file" name="transportistas_file" id="carrierImportFileTransportistas" accept=".xlsx,.xls,.csv">
                </div>
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-light h-100">
                    <div class="small text-muted mb-2">Hoja asignada (transportistas)</div>
                    <span class="badge text-bg-light" id="carrierImportAssignedTransportistasDual">-</span>
                    <input type="hidden" name="transportistas_sheet" id="carrierImportSheetTransportistasDual">
                  </div>
                </div>
                <div class="col-12">
                  <ul class="nav nav-tabs" id="carrierImportDualTabsTransportistas" role="tablist"></ul>
                  <div class="tab-content border border-top-0 rounded-bottom p-3" id="carrierImportDualPanelsTransportistas">
                    <div class="text-muted">Carga un Excel para ver las hojas.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Excel de transportes</label>
                  <input class="form-control" type="file" name="transportes_file" id="carrierImportFileTransportes" accept=".xlsx,.xls,.csv">
                </div>
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-light h-100">
                    <div class="small text-muted mb-2">Hoja asignada (transportes)</div>
                    <span class="badge text-bg-light" id="carrierImportAssignedTransportesDual">-</span>
                    <input type="hidden" name="transportes_sheet" id="carrierImportSheetTransportesDual">
                  </div>
                </div>
                <div class="col-12">
                  <ul class="nav nav-tabs" id="carrierImportDualTabsTransportes" role="tablist"></ul>
                  <div class="tab-content border border-top-0 rounded-bottom p-3" id="carrierImportDualPanelsTransportes">
                    <div class="text-muted">Carga un Excel para ver las hojas.</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="alert alert-info mt-3 mb-0">
              El Excel debe incluir encabezados en la primera fila. El match se hace por la columna indicada (ej: Chofer).
            </div>

            <div class="mt-3 d-none" id="carrierImportReport">
              <div class="border rounded-3 p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong class="small text-muted text-uppercase">Reporte de importación</strong>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="carrierImportReportHideBtn">Ocultar</button>
                </div>
                <div class="row g-2">
                  <div class="col-md-4">
                    <div class="small text-muted">Importados OK</div>
                    <div class="fw-semibold" id="carrierImportOkCount">-</div>
                  </div>
                  <div class="col-md-4">
                    <div class="small text-muted">Warnings (teléfono)</div>
                    <div class="fw-semibold" id="carrierImportPhoneWarnCount">-</div>
                  </div>
                  <div class="col-md-4">
                    <div class="small text-muted">Otros warnings</div>
                    <div class="fw-semibold" id="carrierImportOtherWarnCount">-</div>
                  </div>
                </div>
                <div class="table-responsive mt-3">
                  <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width: 80px;">Fila</th>
                        <th>Transportista</th>
                        <th style="width: 140px;">Campo</th>
                        <th>Valor original</th>
                        <th>Motivo</th>
                      </tr>
                    </thead>
                    <tbody id="carrierImportPhoneWarnTable">
                      <tr><td colspan="5" class="text-muted">Sin warnings de teléfono.</td></tr>
                    </tbody>
                  </table>
                </div>
                <div class="mt-3 d-none" id="carrierImportOtherWarnWrap">
                  <div class="small text-muted mb-1">Otros warnings</div>
                  <ul class="mb-0" id="carrierImportOtherWarnList"></ul>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Importar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal para gestionar zonas del transportista -->
  <div class="modal fade" id="zonesModal" tabindex="-1" aria-labelledby="zonesModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="zonesModalLabel">Zonas - <span id="zonesCarrierName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <div class="border rounded-3 p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <h6 class="mb-0">Zonas comunes</h6>
                    <div class="small text-muted">Asigna zonas ya creadas para este transportista.</div>
                  </div>
                  <a class="small text-decoration-none" href="{{ route('traffic.zones.index') }}" target="_blank" rel="noopener">Gestionar zonas</a>
                </div>
                <form id="sharedZonesForm">
                  @csrf
                  <div class="list-group border rounded mb-2 w-100" id="sharedZonesList" style="max-height: 300px; min-height: 200px; overflow:auto; width:100%;">
                    <div class="list-group-item text-muted">Cargando...</div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Guardar zonas comunes</button>
                    <small class="text-muted">Se dibujan en el mapa junto a las zonas propias.</small>
                  </div>
                </form>
              </div>
            </div>
            <div class="col-lg-4">
              <h6 class="mb-2">Zona</h6>
              <form id="zoneForm">
                @csrf
                <input type="hidden" name="zone_id" id="zoneId" value="">
                <div class="mb-2">
                  <label class="form-label">Nombre *</label>
                  <input class="form-control" name="name" id="zoneName" required>
                </div>
                <input type="hidden" name="type" id="zoneType" value="circle">
                <div class="mb-2 text-muted small">El tipo (círculo/polígono) se toma de lo que dibujes en el mapa.</div>
                <div class="row g-2 mb-2 circle-fields">
                  <div class="col-6">
                    <label class="form-label">Lat centro</label>
                    <input class="form-control" name="center_lat" id="zoneLat" type="number" step="0.000001">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Lng centro</label>
                    <input class="form-control" name="center_lng" id="zoneLng" type="number" step="0.000001">
                  </div>
                </div>
                <div class="mb-2">
                  <label class="form-label">Dirección base (geocoding)</label>
                  <div class="geocode-suggestions">
                    <input class="form-control" type="text" id="zoneAddress" placeholder="Ej: Av. Siempre Viva 123, CABA">
                    <ul class="d-none" id="zoneAddressSuggestions"></ul>
                  </div>
                  <small class="text-muted">Se usa para centrar y setear lat/lng.</small>
                </div>
                <div class="mb-2 circle-fields">
                  <label class="form-label">Radio (km)</label>
                  <input class="form-control" name="radius_km" id="zoneRadius" type="number" step="0.01" min="0">
                </div>
                <div class="mb-2 polygon-fields d-none">
                  <label class="form-label">Polígono (JSON de puntos)</label>
                  <textarea class="form-control" name="polygon" id="zonePolygon" rows="4" placeholder='[{"lat":-34.60,"lng":-58.38}, {"lat":-34.61,"lng":-58.40}]'></textarea>
                  <small class="text-muted">Puedes dibujar en el mapa y se completará automáticamente.</small>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-6">
                    <label class="form-label">Prioridad</label>
                    <select class="form-select" name="priority" id="zonePriority">
                      <option value="primary">Primaria</option>
                      <option value="secondary">Secundaria</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="form-label">Máx. paradas</label>
                    <input class="form-control" name="max_stops" id="zoneMaxStops" type="number" min="1">
                  </div>
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="zoneSoft" name="is_soft" value="1">
                  <label class="form-check-label" for="zoneSoft">Zona blanda (penaliza en lugar de prohibir)</label>
                </div>
                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary btn-sm">Guardar zona</button>
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="zoneResetBtn">Limpiar</button>
                </div>
              </form>

              <hr class="my-3 d-none">

              <div class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Zonas comunes</h6>
                  <a class="small text-decoration-none" href="{{ route('traffic.zones.index') }}" target="_blank" rel="noopener">Gestionar</a>
                </div>
                <form id="sharedZonesFormLegacy">
                  @csrf
                  <div id="sharedZonesListLegacy" class="list-group border rounded mb-2" style="max-height: 240px; overflow:auto;">
                    <div class="list-group-item text-muted">Cargando...</div>
                  </div>
                  <div class="d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Guardar zonas comunes</button>
                    <small class="text-muted">Asigna zonas ya creadas en <span class="fw-semibold">Traffic → Zonas</span>.</small>
                  </div>
                </form>
              </div>
            </div>
            <div class="col-lg-8">
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Mapa de zona</h6>
                  <small class="text-muted">Dibuja círculo o polígono, se completan los campos.</small>
                </div>
                <div id="zonesMap"></div>
              </div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Zonas cargadas</h6>
                <span class="text-muted small" id="zonesCount"></span>
              </div>
              <div id="zonesList" class="list-group list-group-flush border rounded">
                <div class="list-group-item text-muted">Sin zonas cargadas.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para listar y gestionar transportes del transportista -->
  <div class="modal fade" id="transportesModal" tabindex="-1" aria-labelledby="transportesModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="transportesModalLabel">Transportes - <span id="transportesCarrierName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <button class="btn btn-sm btn-primary" type="button" onclick="addTransporteToCarrier()">
              <i class="fa-solid fa-plus me-1"></i> Agregar transporte
            </button>
          </div>
          <div id="transportesList" class="list-group">
            <!-- Se cargará con JavaScript -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="paymentMethodsModal" tabindex="-1" aria-labelledby="paymentMethodsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="paymentMethodsModalLabel">Medios de pago - <span id="paymentMethodsCarrierName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="paymentMethodForm" class="row g-2 mb-3">
            @csrf
            <input type="hidden" id="paymentMethodId">
            <div class="col-md-4">
              <label class="form-label">Banco</label>
              <select class="form-select" id="paymentMethodBank">
                <option value="">Sin banco</option>
                @foreach($banks as $bank)
                  <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">CBU / CVU</label>
              <input type="text" class="form-control" id="paymentMethodCbu" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cuenta</label>
              <input type="text" class="form-control" id="paymentMethodAccount">
            </div>
            <div class="col-md-8">
              <label class="form-label">Descripcion</label>
              <input type="text" class="form-control" id="paymentMethodDescription" placeholder="Ej: Cuenta sueldo, Mercado Pago, etc.">
            </div>
            <div class="col-md-2">
              <label class="form-label d-block">Default</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="paymentMethodDefault" value="1">
              </div>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
              <button class="btn btn-primary w-100" type="submit" id="paymentMethodSaveBtn">
                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar
              </button>
            </div>
          </form>
          <div id="paymentMethodsList" class="list-group"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="paymentMethodResetBtn">Limpiar</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Componente reutilizable para modal de crear/editar transporte -->
  @component('components.traffic.transporte-modal', ['transportistas' => $transportistas, 'prefixId' => 'transporte', 'hideCarrier' => true])
  @endcomponent

@endsection

@push('components')
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.6/build/js/intlTelInput.min.js"></script>
  <script>
    const mapboxToken = "{{ config('services.mapbox.token') ?? env('MAPBOX_TOKEN') }}";
    const googleMapsKey = "{{ env('GOOGLE_MAPS_API_KEY') }}";
    const itiUtilsUrl = "https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.6/build/js/utils.js";
    window.carrierImportReport = null;
    @if(session()->has('import_report'))
    window.carrierImportReport = @json(session('import_report'));
    @endif

    (function () {
      const mapErrorToMessage = (errorCode) => {
        const code = Number(errorCode);
        const messages = {
          1: 'CÃ³digo de paÃ­s invÃ¡lido.',
          2: 'NÃºmero demasiado corto.',
          3: 'NÃºmero demasiado largo.',
          4: 'No es un nÃºmero.',
          0: 'NÃºmero invÃ¡lido.',
        };
        return messages[code] || 'NÃºmero invÃ¡lido.';
      };

      const initPhoneIti = (inputEl) => {
        if (!inputEl || !window.intlTelInput) return null;
        if (inputEl._nygIti) return inputEl._nygIti;
        const iti = window.intlTelInput(inputEl, {
          initialCountry: 'ar',
          utilsScript: itiUtilsUrl,
        });
        inputEl._nygIti = iti;
        return iti;
      };

      const validateAndNormalizeIti = async (iti) => {
        if (!iti) return { ok: true, e164: '', errorCode: null, errorMessage: '' };
        try {
          if (iti.promise) await iti.promise;
        } catch (e) {
          // ignore
        }
        const input = iti.telInput;
        const raw = String(input?.value ?? '').trim();
        if (!raw) {
          return { ok: true, e164: '', errorCode: null, errorMessage: '' };
        }
        iti.setNumber(raw);
        const isValid = typeof iti.isValidNumber === 'function' ? iti.isValidNumber() : true;
        if (isValid) {
          const e164 = window.intlTelInputUtils
            ? iti.getNumber(window.intlTelInputUtils.numberFormat.E164)
            : iti.getNumber();
          return { ok: true, e164: String(e164 || raw), errorCode: null, errorMessage: '' };
        }
        const errorCode = typeof iti.getValidationError === 'function' ? iti.getValidationError() : null;
        return { ok: false, e164: '', errorCode, errorMessage: mapErrorToMessage(errorCode) };
      };

      let validatorInput = null;
      let validatorIti = null;
      const getValidator = () => {
        if (validatorIti && validatorInput) return validatorIti;
        validatorInput = document.createElement('input');
        validatorInput.type = 'tel';
        validatorInput.style.position = 'absolute';
        validatorInput.style.left = '-99999px';
        validatorInput.style.top = '-99999px';
        document.body.appendChild(validatorInput);
        validatorIti = initPhoneIti(validatorInput);
        return validatorIti;
      };

      const validateAndNormalizeString = async (phoneString) => {
        const raw = String(phoneString ?? '').trim();
        if (!raw) return { ok: true, e164: '', errorCode: null, errorMessage: '' };
        const iti = getValidator();
        if (!iti) return { ok: true, e164: raw, errorCode: null, errorMessage: '' };
        iti.setNumber(raw);
        return validateAndNormalizeIti(iti);
      };

      window.nygPhone = {
        initPhoneIti,
        validateAndNormalizeIti,
        validateAndNormalizeString,
        mapErrorToMessage,
      };
    })();
    (function () {
      const modeSelect = document.getElementById('carrierImportMode');
      const singleWrap = document.getElementById('carrierImportSingle');
      const dualWrap = document.getElementById('carrierImportDual');
      if (!modeSelect || !singleWrap || !dualWrap) return;

      const toggleMode = () => {
        const isSingle = modeSelect.value === 'single';
        singleWrap.classList.toggle('d-none', !isSingle);
        dualWrap.classList.toggle('d-none', isSingle);
      };

      modeSelect.addEventListener('change', toggleMode);
      toggleMode();
    })();
    (function () {
      const transportistaSelect = document.getElementById('carrierFilterTransportista');
      const transporteSelect = document.getElementById('carrierFilterTransporte');
      if (!transportistaSelect || !transporteSelect) return;

      const updateTransportes = () => {
        const transportistaId = transportistaSelect.value;
        const options = Array.from(transporteSelect.options);
        options.forEach((opt) => {
          const ownerId = opt.dataset.transportistaId;
          if (!ownerId || !transportistaId) {
            opt.disabled = false;
            opt.hidden = false;
            return;
          }
          const matches = String(ownerId) === String(transportistaId);
          opt.disabled = !matches;
          opt.hidden = !matches;
        });

        const selected = transporteSelect.selectedOptions[0];
        if (selected && (selected.disabled || selected.hidden)) {
          transporteSelect.value = '';
        }
      };

      transportistaSelect.addEventListener('change', updateTransportes);
      updateTransportes();
    })();
    (function () {
      const addressInput = document.getElementById('carrierFilterAddress');
      const suggestions = document.getElementById('carrierAddressSuggestions');
      const latInput = document.getElementById('carrierFilterAddressLat');
      const lngInput = document.getElementById('carrierFilterAddressLng');
      if (!addressInput || !suggestions || !latInput || !lngInput) return;

      let geocodeTimer = null;

      const clearCoords = () => {
        latInput.value = '';
        lngInput.value = '';
      };

      const renderSuggestions = (items) => {
        if (!items.length) {
          suggestions.classList.add('d-none');
          suggestions.innerHTML = '';
          return;
        }
        suggestions.classList.remove('d-none');
        suggestions.innerHTML = items.map(item => (
          `<li data-lat="${item.lat}" data-lng="${item.lng}" data-name="${item.name}">${item.name}</li>`
        )).join('');
      };

      const geocodeAddress = async (query) => {
        if (!googleMapsKey) {
          renderSuggestions([{ lat: '', lng: '', name: 'Configura GOOGLE_MAPS_API_KEY' }]);
          return;
        }
        const url = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(query)}&key=${googleMapsKey}`;
        const res = await fetch(url);
        if (!res.ok) {
          renderSuggestions([]);
          return;
        }
        const data = await res.json();
        const results = data.results || [];
        const mapped = results.slice(0, 5).map(r => ({
          name: r.formatted_address,
          lat: r.geometry?.location?.lat,
          lng: r.geometry?.location?.lng,
        })).filter(r => r.lat !== undefined && r.lng !== undefined);
        renderSuggestions(mapped);
      };

      addressInput.addEventListener('input', (e) => {
        const val = e.target.value || '';
        clearCoords();
        if (geocodeTimer) clearTimeout(geocodeTimer);
        if (val.trim().length < 4) {
          suggestions.classList.add('d-none');
          return;
        }
        geocodeTimer = setTimeout(() => geocodeAddress(val.trim()), 400);
      });

      suggestions.addEventListener('click', (e) => {
        const target = e.target.closest('li');
        if (!target) return;
        addressInput.value = target.dataset.name || addressInput.value;
        latInput.value = target.dataset.lat || '';
        lngInput.value = target.dataset.lng || '';
        suggestions.classList.add('d-none');
      });
    })();
    (function () {
      const tabsSingle = document.getElementById('carrierImportSingleTabs');
      const panelsSingle = document.getElementById('carrierImportSinglePanels');
      const tabsDualTransportistas = document.getElementById('carrierImportDualTabsTransportistas');
      const panelsDualTransportistas = document.getElementById('carrierImportDualPanelsTransportistas');
      const tabsDualTransportes = document.getElementById('carrierImportDualTabsTransportes');
      const panelsDualTransportes = document.getElementById('carrierImportDualPanelsTransportes');

      const fileSingle = document.getElementById('carrierImportFile');
      const fileTransportistas = document.getElementById('carrierImportFileTransportistas');
      const fileTransportes = document.getElementById('carrierImportFileTransportes');

      const sheetTransportistasInput = document.getElementById('carrierImportSheetTransportistas');
      const sheetTransportesInput = document.getElementById('carrierImportSheetTransportes');
      const sheetTransportistasDualInput = document.getElementById('carrierImportSheetTransportistasDual');
      const sheetTransportesDualInput = document.getElementById('carrierImportSheetTransportesDual');

      const badgeTransportistas = document.getElementById('carrierImportAssignedTransportistas');
      const badgeTransportes = document.getElementById('carrierImportAssignedTransportes');
      const badgeTransportistasDual = document.getElementById('carrierImportAssignedTransportistasDual');
      const badgeTransportesDual = document.getElementById('carrierImportAssignedTransportesDual');

      const matchTransportistas = document.getElementById('carrierImportMatchTransportistas');
      const matchTransportes = document.getElementById('carrierImportMatchTransportes');

      const importFormatConfig = {
        transportistas: @json($transportistaImportConfig),
        transportes: @json($transporteImportConfig),
      };
      const importFormatLabels = {
        transportistas: @json($transportistaImportFieldLabels),
        transportes: @json($transporteImportFieldLabels),
      };

      const previewEmpty = '<div class="text-muted">Carga un Excel para ver las hojas.</div>';
      const maxRows = 8;
      const maxCols = 8;

      if (!window.XLSX) {
        return;
      }

      const readWorkbook = (file, cb) => {
        if (!file) {
          cb(null);
          return;
        }
        const reader = new FileReader();
        reader.onload = (event) => {
          const data = new Uint8Array(event.target.result);
          const workbook = window.XLSX.read(data, { type: 'array' });
          cb(workbook);
        };
        reader.onerror = () => cb(null);
        reader.readAsArrayBuffer(file);
      };

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const renderPreviewTable = (sheet) => {
        if (!sheet) {
          return '<div class="text-muted">Sin datos para previsualizar.</div>';
        }
        const rows = window.XLSX.utils.sheet_to_json(sheet, { header: 1, blankrows: false });
        const previewRows = rows.slice(0, maxRows);
        if (!previewRows.length) {
          return '<div class="text-muted">Sin datos para previsualizar.</div>';
        }
        const tableRows = previewRows.map((row) => {
          const cells = row.slice(0, maxCols).map((cell) => `<td>${escapeHtml(cell)}</td>`).join('');
          return `<tr>${cells}</tr>`;
        }).join('');
        return `<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>${tableRows}</tbody></table></div>`;
      };

      const extractHeaders = (sheet, headerRowIndex = 1) => {
        if (!sheet) return [];
        const rows = window.XLSX.utils.sheet_to_json(sheet, { header: 1, blankrows: false });
        const rowIndex = Math.max(1, headerRowIndex) - 1;
        const headerRow = rows[rowIndex] || [];
        return headerRow
          .map((value) => String(value ?? '').trim())
          .filter((value) => value !== '');
      };

      const getConfiguredMatchField = (isTransportistas) => (isTransportistas ? 'name' : 'driver_name');

      const readHeaderValue = (sheet, column, headerRowIndex) => {
        if (!sheet || !column) {
          return null;
        }
        const cleanColumn = String(column).trim().toUpperCase();
        if (!cleanColumn) {
          return null;
        }
        const row = Math.max(1, headerRowIndex);
        const cell = sheet[`${cleanColumn}${row}`];
        if (!cell) {
          return null;
        }
        const rawValue = cell.v ?? cell.w;
        if (rawValue === null || rawValue === undefined) {
          return null;
        }
        const normalized = String(rawValue).trim();
        return normalized === '' ? null : normalized;
      };

      const applyConfiguredMatchColumn = (sheet, config, headers, headerRowIndex, isTransportistas) => {
        if (!config || !config.field_mappings || !sheet) {
          return;
        }
        const matchField = getConfiguredMatchField(isTransportistas);
        const column = config.field_mappings?.[matchField]?.column;
        if (!column) {
          return;
        }
        const headerValue = readHeaderValue(sheet, column, headerRowIndex);
        if (!headerValue || !headers.includes(headerValue)) {
          return;
        }
        const selectEl = isTransportistas ? matchTransportistas : matchTransportes;
        if (selectEl) {
          selectEl.value = headerValue;
        }
      };

      const setMatchOptions = (selectEl, headers) => {
        if (!selectEl) return;
        const current = selectEl.value;
        const options = headers.map((header) => `<option value="${escapeHtml(header)}">${escapeHtml(header)}</option>`).join('');
        selectEl.innerHTML = '<option value="">Selecciona columna</option>' + options;
        if (current && headers.includes(current)) {
          selectEl.value = current;
        }
      };

      const setAssigned = (type, sheetName, headers) => {
        if (type === 'transportistas') {
          if (sheetTransportistasInput) sheetTransportistasInput.value = sheetName;
          if (badgeTransportistas) badgeTransportistas.textContent = `Transportistas: ${sheetName}`;
          setMatchOptions(matchTransportistas, headers);
        } else if (type === 'transportes') {
          if (sheetTransportesInput) sheetTransportesInput.value = sheetName;
          if (badgeTransportes) badgeTransportes.textContent = `Transportes: ${sheetName}`;
          setMatchOptions(matchTransportes, headers);
        } else if (type === 'transportistas_dual') {
          if (sheetTransportistasDualInput) sheetTransportistasDualInput.value = sheetName;
          if (badgeTransportistasDual) badgeTransportistasDual.textContent = sheetName;
          setMatchOptions(matchTransportistas, headers);
        } else if (type === 'transportes_dual') {
          if (sheetTransportesDualInput) sheetTransportesDualInput.value = sheetName;
          if (badgeTransportesDual) badgeTransportesDual.textContent = sheetName;
          setMatchOptions(matchTransportes, headers);
        }
      };

      const normalizeSheetName = (value) => {
        if (value === undefined || value === null) {
          return '';
        }
        return String(value)
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-zA-Z0-9]+/g, ' ')
          .trim()
          .toLowerCase();
      };

      const normalizeLabel = (value) => {
        if (value === undefined || value === null) {
          return '';
        }
        return String(value)
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .trim()
          .toLowerCase();
      };
      const findConfiguredSheetName = (workbook, config) => {
        if (!workbook || !config || !config.sheet) {
          return null;
        }
        const target = normalizeSheetName(config.sheet);
        if (!target) {
          return null;
        }
        return workbook.SheetNames.find((name) => normalizeSheetName(name) === target) || null;
      };

      const detectSheetByMatchColumn = (workbook, column, headerRowIndex, expectedLabel) => {
        if (!workbook || !column) {
          return null;
        }
        const normalizedExpected = normalizeLabel(expectedLabel);
        let fallbackSheet = null;
        for (const sheetName of workbook.SheetNames || []) {
          const sheet = workbook.Sheets[sheetName];
          if (!sheet) {
            continue;
          }
          const value = readHeaderValue(sheet, column, headerRowIndex);
          if (!value) {
            continue;
          }
          if (!fallbackSheet) {
            fallbackSheet = sheetName;
          }
          if (normalizedExpected && normalizeLabel(value) === normalizedExpected) {
            return sheetName;
          }
        }
        return fallbackSheet;
      };

      const autoAssignConfiguredSheet = (workbook, type) => {
        const isTransportistas = type === 'transportistas' || type === 'transportistas_dual';
        const config = isTransportistas ? importFormatConfig.transportistas : importFormatConfig.transportes;
        const matchField = getConfiguredMatchField(isTransportistas);
        const expectedLabel = (isTransportistas ? importFormatLabels.transportistas : importFormatLabels.transportes)[matchField] ?? '';
        const headerRowIndex = Math.max(1, (config?.start_row ?? 2) - 1);
        let sheetName = findConfiguredSheetName(workbook, config);
        if (!sheetName) {
          const column = config?.field_mappings?.[matchField]?.column ?? null;
          sheetName = detectSheetByMatchColumn(workbook, column, headerRowIndex, expectedLabel);
        }
        if (!sheetName) {
          return;
        }
        const sheet = workbook.Sheets[sheetName];
        if (!sheet) {
          return;
        }
        const headers = extractHeaders(sheet, headerRowIndex);
        setAssigned(type, sheetName, headers);
        applyConfiguredMatchColumn(sheet, config, headers, headerRowIndex, isTransportistas);
      };

      const buildTabs = (tabListEl, panelsEl, workbook, assignConfig) => {
        if (!tabListEl || !panelsEl) return;
        if (!workbook) {
          tabListEl.innerHTML = '';
          panelsEl.innerHTML = previewEmpty;
          return;
        }
        const sheetNames = workbook.SheetNames || [];
        if (!sheetNames.length) {
          tabListEl.innerHTML = '';
          panelsEl.innerHTML = previewEmpty;
          return;
        }
        tabListEl.innerHTML = '';
        panelsEl.innerHTML = '';
        sheetNames.forEach((name, index) => {
          const tabId = `${assignConfig.prefix}-tab-${index}`;
          const panelId = `${assignConfig.prefix}-panel-${index}`;
          const activeClass = index === 0 ? 'active' : '';
          tabListEl.insertAdjacentHTML('beforeend', `
            <li class="nav-item" role="presentation">
              <button class="nav-link ${activeClass}" id="${tabId}" data-bs-toggle="tab" type="button" role="tab" data-target="#${panelId}">${escapeHtml(name)}</button>
            </li>
          `);

          const sheet = workbook.Sheets[name];
          const preview = renderPreviewTable(sheet);
          const actions = assignConfig.actions.map((action) => (
            `<button type="button" class="btn btn-sm btn-outline-primary me-2" data-assign="${action.type}" data-sheet="${escapeHtml(name)}">${action.label}</button>`
          )).join('');

          panelsEl.insertAdjacentHTML('beforeend', `
            <div class="tab-pane ${activeClass}" id="${panelId}" role="tabpanel">
              <div class="d-flex flex-wrap gap-2 mb-2">
                ${actions}
              </div>
              ${preview}
            </div>
          `);
        });

        const tabs = Array.from(tabListEl.querySelectorAll('button[data-bs-toggle="tab"]'));
        const panels = Array.from(panelsEl.querySelectorAll('.tab-pane'));
        tabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-target');
            tabs.forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            panels.forEach((panel) => {
              panel.classList.toggle('active', `#${panel.id}` === target);
            });
          });
        });

        panelsEl.querySelectorAll('[data-assign]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const type = btn.dataset.assign;
            const sheetName = btn.dataset.sheet;
            const sheet = workbook.Sheets[sheetName];
            const headers = extractHeaders(sheet);
            setAssigned(type, sheetName, headers);
          });
        });
      };

      const refreshSingle = (workbook) => {
        buildTabs(tabsSingle, panelsSingle, workbook, {
          prefix: 'carrier-import-single',
          actions: [
            { type: 'transportistas', label: 'Usar para transportistas' },
            { type: 'transportes', label: 'Usar para transportes' },
          ],
        });
        autoAssignConfiguredSheet(workbook, 'transportistas');
        autoAssignConfiguredSheet(workbook, 'transportes');
      };

      const refreshDualTransportistas = (workbook) => {
        buildTabs(tabsDualTransportistas, panelsDualTransportistas, workbook, {
          prefix: 'carrier-import-dual-transportistas',
          actions: [
            { type: 'transportistas_dual', label: 'Usar hoja' },
          ],
        });
        autoAssignConfiguredSheet(workbook, 'transportistas_dual');
      };

      const refreshDualTransportes = (workbook) => {
        buildTabs(tabsDualTransportes, panelsDualTransportes, workbook, {
          prefix: 'carrier-import-dual-transportes',
          actions: [
            { type: 'transportes_dual', label: 'Usar hoja' },
          ],
        });
        autoAssignConfiguredSheet(workbook, 'transportes_dual');
      };

      if (fileSingle) {
        fileSingle.addEventListener('change', () => {
          readWorkbook(fileSingle.files?.[0], refreshSingle);
        });
      }
      if (fileTransportistas) {
        fileTransportistas.addEventListener('change', () => {
          readWorkbook(fileTransportistas.files?.[0], refreshDualTransportistas);
        });
      }
      if (fileTransportes) {
        fileTransportes.addEventListener('change', () => {
          readWorkbook(fileTransportes.files?.[0], refreshDualTransportes);
        });
      }
    })();

    (function () {
      const formEl = document.getElementById('carrierImportForm');
      const normalizationInput = document.getElementById('carrierImportPhoneNormalization');
      const reportEl = document.getElementById('carrierImportReport');
      const hideReportBtn = document.getElementById('carrierImportReportHideBtn');

      const okCountEl = document.getElementById('carrierImportOkCount');
      const phoneWarnCountEl = document.getElementById('carrierImportPhoneWarnCount');
      const otherWarnCountEl = document.getElementById('carrierImportOtherWarnCount');
      const phoneWarnTableEl = document.getElementById('carrierImportPhoneWarnTable');
      const otherWrapEl = document.getElementById('carrierImportOtherWarnWrap');
      const otherListEl = document.getElementById('carrierImportOtherWarnList');

      if (!hideReportBtn || !reportEl) return;
      hideReportBtn.addEventListener('click', () => reportEl.classList.add('d-none'));

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const normalizeString = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

      const normalizeMatchValue = (value) => normalizeString(value).trim().replace(/\s+/g, ' ');

      const renderReport = (report) => {
        if (!reportEl || !report) return;
        reportEl.classList.remove('d-none');

        const okCount = report.imported_ok ?? report.importedOk ?? '-';
        const phoneWarnings = report.phone_warnings ?? report.phoneWarnings ?? [];
        const otherWarnings = report.other_warnings ?? report.otherWarnings ?? [];

        if (okCountEl) okCountEl.textContent = String(okCount);
        if (phoneWarnCountEl) phoneWarnCountEl.textContent = String(phoneWarnings.length);
        if (otherWarnCountEl) otherWarnCountEl.textContent = String(otherWarnings.length);

        if (phoneWarnTableEl) {
          if (!phoneWarnings.length) {
            phoneWarnTableEl.innerHTML = '<tr><td colspan="5" class="text-muted">Sin warnings de teléfono.</td></tr>';
          } else {
            phoneWarnTableEl.innerHTML = phoneWarnings.map((w) => `
              <tr>
                <td>${escapeHtml(w.row ?? '-')}</td>
                <td>${escapeHtml(w.transportista ?? w.name ?? '-')}</td>
                <td>${escapeHtml(w.field ?? '-')}</td>
                <td>${escapeHtml(w.original ?? w.value ?? '')}</td>
                <td>${escapeHtml(w.reason ?? w.message ?? '')}</td>
              </tr>
            `).join('');
          }
        }

        if (otherWrapEl && otherListEl) {
          if (!otherWarnings.length) {
            otherWrapEl.classList.add('d-none');
            otherListEl.innerHTML = '';
          } else {
            otherWrapEl.classList.remove('d-none');
            otherListEl.innerHTML = otherWarnings.map((w) => `<li>${escapeHtml(w)}</li>`).join('');
          }
        }
      };

      // Si venimos de una importaciÃ³n, mostrar reporte y abrir modal
      if (window.carrierImportReport) {
        renderReport(window.carrierImportReport);
      }

      if (!formEl || !normalizationInput || !window.XLSX || !window.nygPhone) {
        return;
      }

      const readWorkbookAsync = (file) => new Promise((resolve) => {
        if (!file) return resolve(null);
        const reader = new FileReader();
        reader.onload = (event) => {
          try {
            const data = new Uint8Array(event.target.result);
            const workbook = window.XLSX.read(data, { type: 'array' });
            resolve(workbook);
          } catch (e) {
            resolve(null);
          }
        };
        reader.onerror = () => resolve(null);
        reader.readAsArrayBuffer(file);
      });

      const getTransportistasFileAndSheet = () => {
        const mode = document.getElementById('carrierImportMode')?.value || 'single';
        const fileSingle = document.getElementById('carrierImportFile')?.files?.[0] || null;
        const fileDual = document.getElementById('carrierImportFileTransportistas')?.files?.[0] || null;
        const sheetSingle = document.getElementById('carrierImportSheetTransportistas')?.value || '';
        const sheetDual = document.getElementById('carrierImportSheetTransportistasDual')?.value || '';
        return mode === 'dual'
          ? { file: fileDual, sheetName: sheetDual }
          : { file: fileSingle, sheetName: sheetSingle };
      };

      const detectColumnIndex = (headerRow, candidates) => {
        const normalized = (headerRow || []).map((h) => normalizeString(h).trim());
        for (const cand of candidates) {
          const idx = normalized.indexOf(normalizeString(cand).trim());
          if (idx >= 0) return idx;
        }
        return -1;
      };

      const buildPhoneNormalization = async () => {
        const { file, sheetName } = getTransportistasFileAndSheet();
        if (!file) {
          return { by_match: {}, warnings: [], meta: { error: 'no_file' } };
        }

        const workbook = await readWorkbookAsync(file);
        const sheet = workbook?.Sheets?.[sheetName] || workbook?.Sheets?.[workbook?.SheetNames?.[0]] || null;
        if (!sheet) {
          return { by_match: {}, warnings: [], meta: { error: 'no_sheet' } };
        }

        const rows = window.XLSX.utils.sheet_to_json(sheet, { header: 1, blankrows: false });
        const headerRow = rows[0] || [];

        const matchHeader = document.getElementById('carrierImportMatchTransportistas')?.value || 'Chofer';
        const matchIdx = detectColumnIndex(headerRow, [matchHeader, 'Chofer', 'Nombre', 'name']);
        const phoneIdx = detectColumnIndex(headerRow, ['Contacto', 'Telefono', 'Teléfono', 'Phone', 'Celular', 'WhatsApp']);
        const phoneAltIdx = detectColumnIndex(headerRow, ['Numero alternativo', 'Número alternativo', 'Telefono alternativo', 'Teléfono alternativo', 'Phone alt', 'phone_alt']);

        const by_match = {};
        const warnings = [];

        for (let i = 1; i < rows.length; i++) {
          const row = rows[i] || [];
          const matchValue = String(row[matchIdx] ?? '').trim();
          if (!matchValue) continue;
          const key = normalizeMatchValue(matchValue);
          const rowNumber = i + 1; // 1-based incluyendo header

          const phoneRaw = phoneIdx >= 0 ? String(row[phoneIdx] ?? '').trim() : '';
          const altRaw = phoneAltIdx >= 0 ? String(row[phoneAltIdx] ?? '').trim() : '';

          const entry = by_match[key] || { row: rowNumber, transportista: matchValue, phone: null, phone_alt: null };

          if (phoneRaw) {
            const res = await window.nygPhone.validateAndNormalizeString(phoneRaw);
            entry.phone = { ok: res.ok, e164: res.e164 || '', original: phoneRaw, errorCode: res.errorCode, reason: res.errorMessage || '' };
            if (!res.ok) {
              warnings.push({ row: rowNumber, transportista: matchValue, field: 'phone', original: phoneRaw, reason: res.errorMessage || '' });
            }
          }
          if (altRaw) {
            const res = await window.nygPhone.validateAndNormalizeString(altRaw);
            entry.phone_alt = { ok: res.ok, e164: res.e164 || '', original: altRaw, errorCode: res.errorCode, reason: res.errorMessage || '' };
            if (!res.ok) {
              warnings.push({ row: rowNumber, transportista: matchValue, field: 'phone_alt', original: altRaw, reason: res.errorMessage || '' });
            }
          }

          by_match[key] = entry;
        }

        return { by_match, warnings, meta: { matchHeader } };
      };

      let submitting = false;
      formEl.addEventListener('submit', async (e) => {
        if (submitting) return;
        e.preventDefault();
        submitting = true;
        try {
          const data = await buildPhoneNormalization();
          normalizationInput.value = JSON.stringify(data);
        } catch (err) {
          normalizationInput.value = '';
        }
        formEl.submit();
      });

      window.renderCarrierImportReport = renderReport;
    })();
    (function () {
      const modalEl = document.getElementById('carrierModal');
      if (!modalEl) return;

      const form = document.getElementById('carrierForm');
      const methodInput = document.getElementById('carrierMethod');
      const titleEl = document.getElementById('carrierModalLabel');

      const fields = {
        user_id: document.getElementById('carrierUser'),
        name: document.getElementById('carrierName'),
        business_name: document.getElementById('carrierBusiness'),
        email: document.getElementById('carrierEmail'),
        phone: document.getElementById('carrierPhone'),
        phone_alt: document.getElementById('carrierPhoneAlt'),
        tax_id: document.getElementById('carrierTax'),
        dni: document.getElementById('carrierDni'),
        birth_date: document.getElementById('carrierBirthDate'),
        license_number: document.getElementById('carrierLicense'),
        license_expires_at: document.getElementById('carrierLicenseExpires'),
        personal_insurance: document.getElementById('carrierInsurance'),
        address_certificate: document.getElementById('carrierAddressCert'),
        criminal_record_certificate: document.getElementById('carrierCriminalCert'),
        base_location: document.getElementById('carrierBase'),
        status: document.getElementById('carrierStatus'),
        condition: document.getElementById('carrierCondition'),
        client_name: document.getElementById('carrierClient'),
        bank_id: document.getElementById('carrierBank'),
        cbu: document.getElementById('carrierCbu'),
        account_number: document.getElementById('carrierAccountNumber'),
        monotributo: document.getElementById('carrierMonotributo'),
        hire_date: document.getElementById('carrierHireDate'),
        supplier_id: document.getElementById('carrierSupplier'),
        cost_efficiency: document.getElementById('carrierCost'),
        performance_weight: document.getElementById('carrierWeight'),
        color: document.getElementById('carrierColor'),
        notes: document.getElementById('carrierNotes'),
        is_active: document.getElementById('carrierActive'),
        liquidation_tipo_periodo: document.getElementById('carrierLiquidationTipoPeriodo'),
        portal_visibility: document.getElementById('carrierPortalVisibility'),
        advance_blocked_until: document.getElementById('carrierAdvanceBlockedUntil'),
      };

      const saveBtn = document.getElementById('carrierSaveBtn');
      const phoneAlertEl = document.getElementById('carrierPhoneAlert');
      const phoneClientErrorEl = document.getElementById('carrierPhoneClientError');
      const phoneAltClientErrorEl = document.getElementById('carrierPhoneAltClientError');

      let itiPhone = null;
      let itiPhoneAlt = null;
      const phoneState = {
        phone: true,
        phone_alt: true,
      };

      const showPhoneAlert = (message) => {
        if (!phoneAlertEl) return;
        if (!message) {
          phoneAlertEl.classList.add('d-none');
          phoneAlertEl.textContent = '';
          return;
        }
        phoneAlertEl.textContent = message;
        phoneAlertEl.classList.remove('d-none');
      };

      const setPhoneFieldError = (fieldKey, message) => {
        const inputEl = fields[fieldKey];
        const feedbackEl = fieldKey === 'phone' ? phoneClientErrorEl : phoneAltClientErrorEl;
        if (inputEl) inputEl.classList.toggle('is-invalid', !!message);
        if (feedbackEl) {
          feedbackEl.textContent = message || '';
          feedbackEl.classList.toggle('d-none', !message);
        }
        phoneState[fieldKey] = !message;
        if (saveBtn) {
          saveBtn.disabled = !(phoneState.phone && phoneState.phone_alt);
        }
      };

      const ensurePhoneIti = () => {
        if (window.nygPhone) {
          if (!itiPhone && fields.phone) itiPhone = window.nygPhone.initPhoneIti(fields.phone);
          if (!itiPhoneAlt && fields.phone_alt) itiPhoneAlt = window.nygPhone.initPhoneIti(fields.phone_alt);
        }
      };

      const validatePhoneField = async (fieldKey) => {
        ensurePhoneIti();
        const inputEl = fields[fieldKey];
        const iti = fieldKey === 'phone' ? itiPhone : itiPhoneAlt;
        if (!inputEl || !iti || !window.nygPhone) {
          setPhoneFieldError(fieldKey, '');
          return true;
        }
        const rawValue = String(inputEl.value || '').trim();
        const result = await window.nygPhone.validateAndNormalizeIti(iti);
        if (result.ok) {
          inputEl.value = result.e164 || rawValue;
          setPhoneFieldError(fieldKey, '');
          return true;
        }
        inputEl.value = rawValue;
        setPhoneFieldError(fieldKey, result.errorMessage || 'Teléfono inválido.');
        return false;
      };

      const clearForm = () => {
        form.action = "{{ route('traffic.transportistas.store') }}";
        methodInput.value = 'POST';
        titleEl.textContent = 'Nuevo transportista';
        showPhoneAlert('');
        setPhoneFieldError('phone', '');
        setPhoneFieldError('phone_alt', '');
        ensurePhoneIti();
        Object.entries(fields).forEach(([key, el]) => {
          if (!el) return;
          if (el.tagName === 'SELECT') {
            el.value = '';
          } else if (el.type === 'checkbox') {
            el.checked = key === 'is_active';
          } else {
            el.value = '';
          }
        });
        if (fields.cost_efficiency) fields.cost_efficiency.value = 0;
        if (fields.performance_weight) fields.performance_weight.value = 1;
        if (fields.color) fields.color.value = '#2563eb';
      };

      if (fields.phone) {
        fields.phone.addEventListener('blur', () => validatePhoneField('phone'));
        fields.phone.addEventListener('input', () => setPhoneFieldError('phone', ''));
      }
      if (fields.phone_alt) {
        fields.phone_alt.addEventListener('blur', () => validatePhoneField('phone_alt'));
        fields.phone_alt.addEventListener('input', () => setPhoneFieldError('phone_alt', ''));
      }

      form.addEventListener('submit', async (e) => {
        if (!window.nygPhone) {
          return;
        }
        e.preventDefault();
        showPhoneAlert('');
        const okPhone = await validatePhoneField('phone');
        const okAlt = await validatePhoneField('phone_alt');
        if (!okPhone || !okAlt) {
          showPhoneAlert('Corrige los teléfonos inválidos antes de guardar.');
          const firstInvalid = !okPhone ? fields.phone : fields.phone_alt;
          firstInvalid?.focus?.();
          return;
        }
        form.submit();
      });

      modalEl.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const carrier = button?.dataset?.carrier ? JSON.parse(button.dataset.carrier) : null;
        clearForm();

        if (carrier) {
          const formatDate = (value) => value ? String(value).slice(0, 10) : '';
          titleEl.textContent = 'Editar transportista';
          form.action = "{{ route('traffic.transportistas.index') }}/" + carrier.id;
          methodInput.value = 'PUT';
          // asegurar que el usuario actual aparezca en opciones
          if (fields.user_id && carrier.user && carrier.user.id) {
            const exists = Array.from(fields.user_id.options).some(opt => String(opt.value) === String(carrier.user.id));
            if (!exists) {
              const opt = document.createElement('option');
              opt.value = carrier.user.id;
              opt.textContent = `${carrier.user.name} (${carrier.user.email})`;
              fields.user_id.appendChild(opt);
            }
          }
          if (fields.user_id) {
            fields.user_id.value = carrier.user_id || '';
          }
          fields.name.value = carrier.name || '';
          fields.business_name.value = carrier.business_name || '';
          fields.email.value = carrier.email || '';
          fields.phone.value = carrier.phone || '';
          fields.phone_alt.value = carrier.phone_alt || '';
          fields.tax_id.value = carrier.tax_id || '';
          fields.dni.value = carrier.dni || '';
          fields.birth_date.value = formatDate(carrier.birth_date);
          fields.license_number.value = carrier.license_number || '';
          fields.license_expires_at.value = formatDate(carrier.license_expires_at);
          fields.personal_insurance.value = carrier.personal_insurance || '';
          fields.address_certificate.checked = !!carrier.address_certificate;
          fields.criminal_record_certificate.checked = !!carrier.criminal_record_certificate;
          fields.base_location.value = carrier.base_location || '';
          fields.status.value = carrier.status || '';
          fields.condition.value = carrier.condition || '';
          fields.client_name.value = carrier.client_name || '';
          fields.bank_id.value = carrier.bank_id || '';
          fields.cbu.value = carrier.cbu || '';
          fields.account_number.value = carrier.account_number || '';
          fields.monotributo.value = carrier.monotributo || '';
          fields.hire_date.value = formatDate(carrier.hire_date);
          fields.supplier_id.value = carrier.supplier_id || '';
          fields.cost_efficiency.value = carrier.cost_efficiency ?? 0;
          fields.performance_weight.value = carrier.performance_weight ?? 1;
          fields.color.value = carrier.color || '#2563eb';
          fields.notes.value = carrier.notes || '';
          fields.is_active.checked = !!carrier.is_active;
          if (fields.liquidation_tipo_periodo) fields.liquidation_tipo_periodo.value = carrier.liquidation_meta?.tipo_periodo || '';
          if (fields.portal_visibility) fields.portal_visibility.value = carrier.portal_visibility || 'all';
          if (fields.advance_blocked_until) fields.advance_blocked_until.value = formatDate(carrier.advance_blocked_until);
        }

        // Inicializar y normalizar telÃ©fonos si existen (E.164 o local)
        (async () => {
          ensurePhoneIti();
          if (itiPhone && fields.phone?.value) itiPhone.setNumber(fields.phone.value);
          if (itiPhoneAlt && fields.phone_alt?.value) itiPhoneAlt.setNumber(fields.phone_alt.value);
          await validatePhoneField('phone');
          await validatePhoneField('phone_alt');
        })();
      });
    })();

    // Zonas por transportista
    let zonesTransportistaId = null;
    const zonesModalEl = document.getElementById('zonesModal');
    const zonesListEl = document.getElementById('zonesList');
    const zonesCarrierNameEl = document.getElementById('zonesCarrierName');
    const zonesCountEl = document.getElementById('zonesCount');
    const zoneForm = document.getElementById('zoneForm');
    const zoneTypeSelect = document.getElementById('zoneType');
    const circleFields = document.querySelectorAll('.circle-fields');
    const polygonFields = document.querySelectorAll('.polygon-fields');
    const zoneResetBtn = document.getElementById('zoneResetBtn');
    const zonesMapEl = document.getElementById('zonesMap');
    const zoneAddressInput = document.getElementById('zoneAddress');
    const zoneAddressSuggestions = document.getElementById('zoneAddressSuggestions');
    const sharedZonesForm = document.getElementById('sharedZonesForm');
    const sharedZonesListEl = document.getElementById('sharedZonesList');
    let zonesMap = null;
    let zonesDrawnItems = null;
    let zonesDrawControl = null;
    let zoneCenterMarker = null;
    let geocodeTimer = null;
    let zonesCache = [];
    let sharedZonesCache = [];
    let zonesEditing = false;

    function toggleZoneFields(type) {
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
      document.getElementById('zoneId').value = '';
      toggleZoneFields('circle');
    }

    function clearZonesMap() {
      if (zonesDrawnItems) zonesDrawnItems.clearLayers();
      if (zoneCenterMarker && zonesMap) {
        zonesMap.removeLayer(zoneCenterMarker);
        zoneCenterMarker = null;
      }
    }

    function initZonesMap() {
      if (zonesMap || !zonesMapEl) return;
      zonesMap = L.map('zonesMap').setView([-34.6037, -58.3816], 9);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(zonesMap);
      zonesMap.setMinZoom(4);
      zonesMap.setMaxBounds(L.latLngBounds([
        [-55.0, -80.0],
        [-20.0, -50.0],
      ]));
      const defaultCenter = L.latLng(-34.6037, -58.3816);
      zonesMap.setView(defaultCenter, 10);
      const argentinaBounds = L.latLngBounds([
        [-34.61315, -58.37723],
        [-31.41695, -64.18340],
      ]);
      zonesMap.fitBounds(argentinaBounds.pad(0.5));

      zonesDrawnItems = new L.FeatureGroup();
      zonesMap.addLayer(zonesDrawnItems);

      zonesDrawControl = new L.Control.Draw({
        edit: {
          featureGroup: zonesDrawnItems
        },
        draw: {
          rectangle: false,
          marker: false,
          polyline: false,
          circlemarker: false,
          polygon: {
            allowIntersection: false,
            showArea: true
          },
          circle: {
            shapeOptions: { color: '#2563eb' }
          }
        }
      });
      zonesMap.addControl(zonesDrawControl);

      zonesMap.on(L.Draw.Event.CREATED, function (e) {
        zonesDrawnItems.clearLayers();
        zonesDrawnItems.addLayer(e.layer);
        applyShapeToForm(e.layer);
      });

      zonesMap.on(L.Draw.Event.EDITED, function (e) {
        const layers = e.layers;
        layers.eachLayer(layer => applyShapeToForm(layer));
      });

      zonesMap.on('click', function (e) {
        document.getElementById('zoneLat').value = e.latlng.lat.toFixed(6);
        document.getElementById('zoneLng').value = e.latlng.lng.toFixed(6);
        setCenterMarker(e.latlng, true);
      });
    }

    function applyShapeToForm(layer) {
      if (!layer) return;
      if (layer instanceof L.Circle) {
        const center = layer.getLatLng();
        const radiusKm = (layer.getRadius() / 1000).toFixed(3);
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
      const source = zone.source || 'carrier';
      let layer = null;
      const palette = {
        carrier: '#2563eb',
        'shared-assigned': '#0d6efd',
        'shared-available': '#14b8a6',
      };
      const color = palette[source] || palette.carrier;
      const fillOpacity = source.startsWith('shared') ? 0.12 : 0.15;

      if (zone.type === 'circle' && zone.center_lat && zone.center_lng && zone.radius_km) {
        layer = L.circle([zone.center_lat, zone.center_lng], {
          radius: zone.radius_km * 1000,
          color,
          fillColor: color,
          fillOpacity,
          weight: 2,
        });
      }
      if (zone.type === 'polygon' && Array.isArray(zone.polygon) && zone.polygon.length >= 3) {
        const coords = zone.polygon.map(p => [p.lat ?? p[0], p.lng ?? p[1]]);
        layer = L.polygon(coords, {
          color,
          fillColor: color,
          fillOpacity,
          weight: 2,
        });
      }
      if (layer && source === 'carrier') {
        layer.on('contextmenu', () => openZoneContextMenu(zone));
      }
      return layer;
      const argentinaBounds = L.latLngBounds([
        [-34.61315, -58.37723], // Buenos Aires
        [-31.41695, -64.18340], // Córdoba
      ]);
      zonesMap.fitBounds(argentinaBounds.pad(0.5));
    }

    function renderZoneShape(zone) {
      if (!zonesDrawnItems || !zonesMap) return;
      clearZonesMap();
      const layer = createZoneLayer(zone);
      if (layer) {
        zonesDrawnItems.addLayer(layer);
      }
      if (zone.type === 'circle' && zone.center_lat && zone.center_lng) {
        setCenterMarker({ lat: zone.center_lat, lng: zone.center_lng }, true);
      } else {
        zoneCenterMarker && zonesMap.removeLayer(zoneCenterMarker);
      }
    }

    function renderZonesMap(zones, { fitBounds = false } = {}) {
      if (!zonesDrawnItems || !zonesMap) return;
      clearZonesMap();
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

    function mapZonesForMap() {
      const specific = (zonesCache || []).map(z => ({ ...z, source: 'carrier' }));
      const shared = (sharedZonesCache || []).map(z => ({
        ...z,
        source: z.assigned ? 'shared-assigned' : 'shared-available',
      }));
      return specific.concat(shared);
    }

    function refreshZonesMap({ fitBounds = false } = {}) {
      if (zonesEditing) return;
      const zones = mapZonesForMap();
      renderZonesMap(zones, { fitBounds });
    }

    async function openZoneContextMenu(zone) {
      if (!window.Swal) {
        editZone(zone.id);
        return;
      }
      const result = await Swal.fire({
        icon: 'info',
        title: zone.name || 'Zona',
        text: 'Selecciona una acción',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Editar',
        denyButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
      });
      if (result.isConfirmed) {
        editZone(zone.id);
        return;
      }
      if (result.isDenied) {
        await deleteZone(zone.id, { skipConfirm: true });
      }
    }

    async function loadZones(transportistaId) {
      zonesListEl.innerHTML = '<div class="list-group-item text-muted">Cargando...</div>';
      zonesCountEl.textContent = '';
      try {
        const res = await fetch(`/traffic/transportistas/${transportistaId}/zones`);
        const json = await res.json();
        const zones = json.data || [];
        zonesCache = zones;
        zonesEditing = false;
        zonesCountEl.textContent = `${zones.length} zonas`;
        if (!zones.length) {
          zonesListEl.innerHTML = '<div class="list-group-item text-muted">Sin zonas cargadas.</div>';
          return;
        }
        refreshZonesMap({ fitBounds: true });
        zonesListEl.innerHTML = zones.map(z => {
          const coverage = z.type === 'circle'
            ? `Radio ${z.radius_km ?? '-'} km @ (${z.center_lat ?? '-'}, ${z.center_lng ?? '-'})`
            : `Polígono (${(z.polygon || []).length} puntos)`;
          const priority = z.priority === 'primary' ? 'Primaria' : 'Secundaria';
          const soft = z.is_soft ? '<span class="badge bg-warning text-dark ms-1">Blanda</span>' : '';
          const maxStops = z.max_stops ? ` | Máx: ${z.max_stops}` : '';
          return `
            <div class="list-group-item d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="fw-semibold">${z.name} <span class="badge bg-light text-dark border">${priority}</span>${soft}</div>
                <div class="text-muted small">${coverage}${maxStops}</div>
              </div>
              <div class="btn-group btn-group-sm ms-2">
                <button class="btn btn-outline-primary" onclick="editZone(${z.id})"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-outline-danger" onclick="deleteZone(${z.id})"><i class="fa-solid fa-trash"></i></button>
              </div>
            </div>
          `;
        }).join('');
      } catch (e) {
        zonesListEl.innerHTML = '<div class="list-group-item text-danger">Error al cargar zonas.</div>';
      }
    }

    async function loadSharedZones(transportistaId) {
      if (!sharedZonesListEl) return;
      sharedZonesListEl.innerHTML = '<div class="list-group-item text-muted">Cargando...</div>';
      try {
        const res = await fetch(`/traffic/transportistas/${transportistaId}/shared-zones`, {
          headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();
        const zones = json.data || [];
        sharedZonesCache = zones;
        if (!zones.length) {
          sharedZonesListEl.innerHTML = '<div class="list-group-item text-muted">No hay zonas comunes creadas.</div>';
          refreshZonesMap({ fitBounds: true });
          return;
        }
        sharedZonesListEl.innerHTML = zones.map(z => {
          const priority = z.priority === 'primary' ? 'Primaria' : 'Secundaria';
          const type = z.type === 'polygon' ? 'Polígono' : 'Círculo';
          return `
            <label class="list-group-item d-flex align-items-start gap-2">
              <input class="form-check-input mt-1" type="checkbox" name="zone_ids[]" value="${z.id}" ${z.assigned ? 'checked' : ''}>
              <span class="flex-grow-1">
                <span class="fw-semibold">${z.name}</span>
                <span class="badge bg-light text-dark border ms-2">${priority}</span>
                <span class="text-muted small ms-2">${type}</span>
              </span>
            </label>
          `;
        }).join('');
        refreshZonesMap({ fitBounds: false });
      } catch (e) {
        sharedZonesListEl.innerHTML = '<div class="list-group-item text-danger">Error al cargar zonas comunes.</div>';
      }
    }

    if (zonesModalEl) {
      zonesModalEl.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget;
        zonesTransportistaId = button?.dataset?.transportistaId;
        const carrierName = button?.dataset?.transportistaName || '';
        zonesCarrierNameEl.textContent = carrierName;
        resetZoneForm();
        initZonesMap();
        zonesCache = [];
        zonesEditing = false;
        clearZonesMap();
        if (zoneAddressInput) zoneAddressInput.value = '';
        if (zonesTransportistaId) {
          await loadZones(zonesTransportistaId);
          await loadSharedZones(zonesTransportistaId);
        } else if (sharedZonesListEl) {
          sharedZonesListEl.innerHTML = '<div class="list-group-item text-muted">Selecciona un transportista.</div>';
        }
        if (zonesMap) {
          setTimeout(() => zonesMap.invalidateSize(), 200);
        }
      });
    }

    if (zoneTypeSelect) {
      zoneTypeSelect.addEventListener('change', (e) => toggleZoneFields(e.target.value));
    }

    if (zoneResetBtn) {
      zoneResetBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resetZoneForm();
        zonesEditing = false;
        renderZonesMap(zonesCache, { fitBounds: false });
      });
    }

    if (sharedZonesForm) {
      sharedZonesForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!zonesTransportistaId) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const formData = new FormData(sharedZonesForm);
        const payload = new URLSearchParams();
        formData.forEach((value, key) => payload.append(key, value));
        const res = await fetch(`/traffic/transportistas/${zonesTransportistaId}/shared-zones`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          body: payload,
        });
        if (res.ok) {
          await loadSharedZones(zonesTransportistaId);
          if (window.nygAlert) window.nygAlert('Zonas comunes actualizadas', 'success');
        } else {
          if (window.nygAlert) window.nygAlert('No se pudieron actualizar las zonas comunes', 'error');
        }
      });
    }

    if (sharedZonesListEl) {
      sharedZonesListEl.addEventListener('change', (event) => {
        const input = event.target;
        if (!input || input.tagName !== 'INPUT' || input.type !== 'checkbox') return;
        const id = Number(input.value);
        if (!Number.isFinite(id)) return;
        const zone = (sharedZonesCache || []).find(z => Number(z.id) === id);
        if (zone) {
          zone.assigned = !!input.checked;
          refreshZonesMap({ fitBounds: false });
        }
      });
    }

    window.editZone = function (zoneId) {
      if (!zonesTransportistaId) return;
      const zones = zonesCache || [];
      const found = zones.find(z => Number(z.id) === Number(zoneId));
      if (!found) return;
      document.getElementById('zoneId').value = found.id;
      document.getElementById('zoneName').value = found.name || '';
      document.getElementById('zoneType').value = found.type || 'circle';
      document.getElementById('zoneLat').value = found.center_lat ?? '';
      document.getElementById('zoneLng').value = found.center_lng ?? '';
      document.getElementById('zoneRadius').value = found.radius_km ?? '';
      document.getElementById('zonePolygon').value = found.polygon ? JSON.stringify(found.polygon) : '';
      document.getElementById('zonePriority').value = found.priority || 'primary';
      document.getElementById('zoneMaxStops').value = found.max_stops ?? '';
      document.getElementById('zoneSoft').checked = !!found.is_soft;
      toggleZoneFields(found.type || 'circle');
      zonesEditing = true;
      renderZoneShape(found);
      zoneAddressInput.value = '';
    };

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

    window.deleteZone = async function (zoneId, { skipConfirm = false } = {}) {
      if (!zonesTransportistaId) return;
      if (!skipConfirm && window.nygConfirm) {
        const result = await window.nygConfirm({
          text: '¿Eliminar zona?',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
      }
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const res = await fetch(`/traffic/transportistas/${zonesTransportistaId}/zones/${zoneId}`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: new URLSearchParams({ _method: 'DELETE' }),
      });
      if (res.ok) {
        await loadZones(zonesTransportistaId);
      } else {
        if (window.nygAlert) {
          window.nygAlert('No se pudo eliminar la zona.', 'error');
        }
      }
    };

    if (zoneForm) {
      zoneForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!zonesTransportistaId) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const zoneId = document.getElementById('zoneId').value;
        const formData = new FormData(zoneForm);
        // normalizar radio a 2 decimales si viene
        const radius = parseFloat(formData.get('radius_km'));
        if (!isNaN(radius)) {
          formData.set('radius_km', radius.toFixed(2));
        }
        const payload = new URLSearchParams();
        formData.forEach((value, key) => {
          payload.append(key, value);
        });
        // refuerzo: token CSRF
        const tokenMeta = document.querySelector('meta[name="csrf-token"]')?.content;
        if (tokenMeta && !payload.get('_token')) {
          payload.append('_token', tokenMeta);
        }
        const method = zoneId ? 'PUT' : 'POST';
        if (zoneId) {
          payload.append('_method', 'PUT');
        }
        const res = await fetch(`/traffic/transportistas/${zonesTransportistaId}/zones${zoneId ? '/' + zoneId : ''}`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: payload,
        });
        if (res.ok) {
          resetZoneForm();
          await loadZones(zonesTransportistaId);
        } else {
          if (window.nygAlert) {
            window.nygAlert('Error al guardar la zona', 'error');
          }
        }
      });
    }

    // Variable global para el transportista actual en el modal de transportes
    let currentTransportistaId = null;
    let currentPaymentTransportistaId = null;
    let paymentMethodsCache = [];
    
    // Exponer a window para que el componente de transporte pueda acceder
    Object.defineProperty(window, 'currentTransportistaId', {
      get() { return currentTransportistaId; },
      set(val) { currentTransportistaId = val; }
    });

    // Manejo del modal de transportes
    const transportesModalEl = document.getElementById('transportesModal');
    const paymentMethodsModalEl = document.getElementById('paymentMethodsModal');
    if (transportesModalEl) {
      transportesModalEl.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget;
        currentTransportistaId = button?.dataset?.transportistaId;
        const carrierName = button?.dataset?.transportistaName;

        document.getElementById('transportesCarrierName').textContent = carrierName || '';

        if (currentTransportistaId) {
          await loadTransportes(currentTransportistaId);
        }
      });
    }

    const paymentMethodFields = {
      id: document.getElementById('paymentMethodId'),
      bank_id: document.getElementById('paymentMethodBank'),
      cbu: document.getElementById('paymentMethodCbu'),
      account_number: document.getElementById('paymentMethodAccount'),
      description: document.getElementById('paymentMethodDescription'),
      is_default: document.getElementById('paymentMethodDefault'),
    };

    const resetPaymentMethodForm = () => {
      paymentMethodFields.id.value = '';
      paymentMethodFields.bank_id.value = '';
      paymentMethodFields.cbu.value = '';
      paymentMethodFields.account_number.value = '';
      paymentMethodFields.description.value = '';
      paymentMethodFields.is_default.checked = false;
      document.getElementById('paymentMethodSaveBtn').innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar';
    };

    if (paymentMethodsModalEl) {
      paymentMethodsModalEl.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget;
        currentPaymentTransportistaId = button?.dataset?.transportistaId;
        const carrierName = button?.dataset?.transportistaName;
        document.getElementById('paymentMethodsCarrierName').textContent = carrierName || '';
        resetPaymentMethodForm();
        if (currentPaymentTransportistaId) {
          await loadPaymentMethods(currentPaymentTransportistaId);
        }
      });
    }

    document.getElementById('paymentMethodResetBtn')?.addEventListener('click', resetPaymentMethodForm);

    async function loadPaymentMethods(transportistaId) {
      try {
        const response = await fetch(`/traffic/transportistas/${transportistaId}/payment-methods`);
        const json = await response.json();
        const methods = json.data || [];
        paymentMethodsCache = methods;
        const listEl = document.getElementById('paymentMethodsList');

        if (!methods.length) {
          listEl.innerHTML = '<div class="alert alert-info">No hay medios de pago cargados para este transportista.</div>';
          return;
        }

        listEl.innerHTML = methods.map((method) => `
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="fw-semibold">${method.description || 'Medio sin descripcion'} ${method.is_default ? '<span class="badge bg-warning text-dark ms-1">DEFAULT</span>' : ''}</div>
                <div class="small text-muted">${method.bank_name || 'Sin banco'} · Cuenta: ${method.account_number || '-'} · CBU/CVU: ${method.cbu || '-'}</div>
              </div>
              <div class="btn-group btn-group-sm">
                ${!method.is_default ? `<button class="btn btn-outline-warning" type="button" onclick="setPaymentMethodDefault(${method.id})" title="Marcar como default"><i class="fa-solid fa-star"></i></button>` : ''}
                <button class="btn btn-outline-primary" type="button" onclick="editPaymentMethod(${method.id})" title="Editar"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-outline-danger" type="button" onclick="deletePaymentMethod(${method.id})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
              </div>
            </div>
          </div>
        `).join('');
      } catch (error) {
        console.error('Error loading payment methods:', error);
        document.getElementById('paymentMethodsList').innerHTML = '<div class="alert alert-danger">Error al cargar medios de pago.</div>';
      }
    }

    document.getElementById('paymentMethodForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!currentPaymentTransportistaId) return;

      const paymentMethodId = paymentMethodFields.id.value;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const payload = {
        bank_id: paymentMethodFields.bank_id.value || '',
        cbu: paymentMethodFields.cbu.value || '',
        account_number: paymentMethodFields.account_number.value || '',
        description: paymentMethodFields.description.value || '',
        is_default: paymentMethodFields.is_default.checked ? 1 : 0,
      };

      const url = paymentMethodId
        ? `/traffic/transportista-payment-methods/${paymentMethodId}`
        : `/traffic/transportistas/${currentPaymentTransportistaId}/payment-methods`;
      const method = paymentMethodId ? 'POST' : 'POST';
      if (paymentMethodId) {
        payload._method = 'PUT';
      }

      try {
        const response = await fetch(url, {
          method,
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });
        if (!response.ok) {
          throw new Error('No se pudo guardar el medio de pago.');
        }
        resetPaymentMethodForm();
        await loadPaymentMethods(currentPaymentTransportistaId);
        if (window.nygAlert) {
          window.nygAlert('Medio de pago guardado correctamente.', 'success');
        }
      } catch (error) {
        console.error('Error saving payment method:', error);
        if (window.nygAlert) {
          window.nygAlert('Error al guardar el medio de pago.', 'error');
        }
      }
    });

    window.editPaymentMethod = function (paymentMethodId) {
      const method = (paymentMethodsCache || []).find((entry) => Number(entry.id) === Number(paymentMethodId));
      if (!method) return;
      paymentMethodFields.id.value = method.id || '';
      paymentMethodFields.bank_id.value = method.bank_id || '';
      paymentMethodFields.cbu.value = method.cbu || '';
      paymentMethodFields.account_number.value = method.account_number || '';
      paymentMethodFields.description.value = method.description || '';
      paymentMethodFields.is_default.checked = !!method.is_default;
      document.getElementById('paymentMethodSaveBtn').innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Actualizar';
    };

    window.setPaymentMethodDefault = async function (paymentMethodId) {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(`/traffic/transportista-payment-methods/${paymentMethodId}/set-default`, {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
        });
        if (!response.ok) {
          throw new Error('No se pudo marcar como default.');
        }
        await loadPaymentMethods(currentPaymentTransportistaId);
      } catch (error) {
        console.error('Error setting payment method default:', error);
        if (window.nygAlert) {
          window.nygAlert('Error al marcar como default.', 'error');
        }
      }
    };

    window.deletePaymentMethod = async function (paymentMethodId) {
      const result = window.nygConfirm ? await window.nygConfirm({
        text: '¿Eliminar medio de pago?',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
      }) : { isConfirmed: confirm('¿Eliminar medio de pago?') };

      if (!result.isConfirmed) return;

      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(`/traffic/transportista-payment-methods/${paymentMethodId}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
        });
        if (!response.ok) {
          throw new Error('No se pudo eliminar el medio de pago.');
        }
        await loadPaymentMethods(currentPaymentTransportistaId);
      } catch (error) {
        console.error('Error deleting payment method:', error);
        if (window.nygAlert) {
          window.nygAlert('Error al eliminar el medio de pago.', 'error');
        }
      }
    };

    async function loadTransportes(transportistaId) {
      try {
        const response = await fetch(`/traffic/transportistas/${transportistaId}/transportes`);
        const json = await response.json();
        const transportes = json.data || [];
        const listEl = document.getElementById('transportesList');

        if (!transportes.length) {
          listEl.innerHTML = '<div class="alert alert-info">No hay transportes para este transportista.</div>';
          return;
        }

        listEl.innerHTML = transportes.map(t => `
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <h6 class="mb-1">${t.alias} ${t.is_default ? '<span class="badge bg-warning text-dark">DEFAULT</span>' : ''}</h6>
                <small class="text-muted">
                  Patente: ${t.license_plate || 'N/D'} | Tipo: ${t.type || 'N/D'} | Capacidad: ${t.capacity_kg ? t.capacity_kg + ' kg' : 'N/D'}
                </small>
              </div>
              <div class="btn-group btn-group-sm ms-2">
                ${!t.is_default ? `<button class="btn btn-outline-warning btn-sm" onclick="setAsDefault(${t.id})" title="Marcar como por defecto"><i class="fa-solid fa-star"></i></button>` : ''}
                <button class="btn btn-outline-primary btn-sm" onclick="editTransporte(${t.id})" title="Editar"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteTransporte(${t.id})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
              </div>
            </div>
          </div>
        `).join('');
      } catch (error) {
        console.error('Error loading transportes:', error);
        document.getElementById('transportesList').innerHTML = '<div class="alert alert-danger">Error al cargar transportes.</div>';
      }
    }

    function addTransporteToCarrier() {
      // Obtener nombre del transportista actual
      const carrierName = document.getElementById('transportesCarrierName').textContent;
      
      console.log('addTransporteToCarrier called');
      console.log('window.transporte_initWithTransportista:', window.transporte_initWithTransportista);
      console.log('currentTransportistaId:', currentTransportistaId);
      console.log('carrierName:', carrierName);
      
      // Usar la nueva función de inicialización
      if (window.transporte_initWithTransportista) {
        window.transporte_initWithTransportista(currentTransportistaId, carrierName);
      } else {
        console.error('transporte_initWithTransportista function not found');
      }
    }

    function editTransporte(transporteId) {
      console.log('editTransporte called with ID:', transporteId);
      console.log('window.transporte_initWithTransporte:', window.transporte_initWithTransporte);
      
      // Usar la nueva función de inicialización con transporte ID
      if (window.transporte_initWithTransporte) {
        window.transporte_initWithTransporte(transporteId);
      } else {
        console.error('transporte_initWithTransporte function not found');
      }
    }

    async function setAsDefault(transporteId) {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(`/traffic/transportes/${transporteId}/set-default`, {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
          },
        });
        const json = await response.json();
        if (json.ok) {
          // Recargar la lista
          if (currentTransportistaId) {
            await loadTransportes(currentTransportistaId);
          }
          // Mostrar mensaje
          if (window.nygAlert) {
            window.nygAlert('Transporte marcado como por defecto', 'success');
          }
        }
      } catch (error) {
        console.error('Error setting default:', error);
        if (window.nygAlert) {
          window.nygAlert('Error al marcar como por defecto', 'error');
        }
      }
    }

    async function deleteTransporte(transporteId) {
      if (window.nygConfirm) {
        const result = await window.nygConfirm({
          text: '¿Está seguro que desea eliminar este transporte?',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
      }
      
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const formData = new FormData();
      formData.append('_method', 'DELETE');
      
      fetch(`/traffic/transportes/${transporteId}`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: formData,
      })
      .then(res => {
        if (res.ok) {
          // Recargar la lista de transportes
          if (currentTransportistaId) {
            loadTransportes(currentTransportistaId);
          }
          if (window.nygAlert) {
            window.nygAlert('Transporte eliminado correctamente', 'success');
          }
        } else {
          if (window.nygAlert) {
            window.nygAlert('Error al eliminar el transporte', 'error');
          }
        }
      })
      .catch(err => {
        console.error('Error deleting transporte:', err);
        if (window.nygAlert) {
          window.nygAlert('Error al eliminar el transporte', 'error');
        }
      });
    }
  </script>
  <script>
    (() => {
      const table = document.getElementById('transportistasTable');
      const toggleBtn = document.getElementById('bulkModeToggleBtn');
      const controls = document.getElementById('bulkSelectionControls');
      const selectAllBtn = document.getElementById('bulkSelectAllBtn');
      const deselectAllBtn = document.getElementById('bulkDeselectAllBtn');
      const deleteBtn = document.getElementById('bulkDeleteSubmitBtn');
      const form = document.getElementById('transportistaBulkDeleteForm');
      const countLabel = document.getElementById('bulkSelectedCount');
      const transportistasIndexRoute = "{{ route('traffic.transportistas.index') }}";
      const checkboxes = Array.from(document.querySelectorAll('#transportistasTable input.bulk-select'));

      if (!table || !toggleBtn || !controls || !selectAllBtn || !deselectAllBtn || !deleteBtn || !form || !countLabel) {
        return;
      }

      let bulkMode = false;
      const selected = new Set();

      const updateControls = () => {
        const count = selected.size;
        countLabel.textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
        deleteBtn.disabled = count === 0;
      };

      const syncCheckboxes = (checked) => {
        checkboxes.forEach((checkbox) => {
          checkbox.checked = checked;
          if (checked) {
            selected.add(checkbox.value);
          } else {
            selected.delete(checkbox.value);
          }
        });
        updateControls();
      };

      const toggleBulkMode = () => {
        bulkMode = !bulkMode;
        table.classList.toggle('bulk-mode', bulkMode);
        controls.classList.toggle('d-none', !bulkMode);
        toggleBtn.classList.toggle('btn-danger', bulkMode);
        toggleBtn.classList.toggle('btn-outline-danger', !bulkMode);
        toggleBtn.innerHTML = bulkMode
          ? '<i class="fa-solid fa-xmark me-1"></i> Cancelar selección'
          : '<i class="fa-solid fa-trash me-1"></i> Eliminar varios';
        if (!bulkMode) {
          selected.clear();
          syncCheckboxes(false);
        }
      };

      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          if (checkbox.checked) {
            selected.add(checkbox.value);
          } else {
            selected.delete(checkbox.value);
          }
          updateControls();
        });
      });

      selectAllBtn.addEventListener('click', (event) => {
        event.preventDefault();
        checkboxes.forEach((checkbox) => {
          checkbox.checked = true;
          selected.add(checkbox.value);
        });
        updateControls();
      });

      deselectAllBtn.addEventListener('click', (event) => {
        event.preventDefault();
        checkboxes.forEach((checkbox) => {
          checkbox.checked = false;
        });
        selected.clear();
        updateControls();
      });

      toggleBtn.addEventListener('click', (event) => {
        event.preventDefault();
        toggleBulkMode();
      });

      const confirmBulkDelete = async () => {
        if (window.Swal) {
          const result = await Swal.fire({
            icon: 'warning',
            title: 'Eliminar transportistas',
            text: '¿Estás seguro que deseas eliminar los transportistas seleccionados? Esta acción no se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
          });
          return result.isConfirmed;
        }
        return confirm('¿Eliminar los transportistas seleccionados? Esta acción no se puede deshacer.');
      };

      deleteBtn.addEventListener('click', async (event) => {
        event.preventDefault();
        const ids = Array.from(selected);
        if (!ids.length) {
          return;
        }
        const confirmed = await confirmBulkDelete();
        if (!confirmed) {
          return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        try {
          deleteBtn.disabled = true;
          const response = await fetch(form.action, {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            },
            body: JSON.stringify({ ids }),
          });

          if (!response.ok) {
            throw new Error(response.statusText || 'Error al eliminar transportistas.');
          }

          const payload = await response.json().catch(() => ({}));
          const successMessage = payload.message || `${ids.length} transportista(s) eliminado(s).`;

          if (window.Swal) {
            await Swal.fire({
              icon: 'success',
              title: 'Transportistas eliminados',
              text: successMessage,
            });
          } else if (window.nygAlert) {
            window.nygAlert(successMessage, 'success');
          } else {
            alert(successMessage);
          }

          window.location.href = transportistasIndexRoute;
        } catch (error) {
          console.error('Error deleting transportistas:', error);
          const errorText = error?.message || 'Error al eliminar transportistas.';
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: errorText,
            });
          } else if (window.nygAlert) {
            window.nygAlert(errorText, 'error');
          } else {
            alert(errorText);
          }
        } finally {
          deleteBtn.disabled = selected.size === 0;
        }
      });
    })();
  </script>
  <script>
    document.querySelectorAll('.carrier-active-toggle').forEach(checkbox => {
      checkbox.addEventListener('change', async function() {
        const id = this.getAttribute('data-id');
        const label = document.getElementById('activeLabel' + id);
        const checked = this.checked;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        try {
          this.disabled = true;
          const url = "{{ route('traffic.transportistas.toggle-active', ':id') }}".replace(':id', id);
          const response = await fetch(url, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            }
          });
          
          if (!response.ok) {
            throw new Error('Error al actualizar el estado.');
          }
          
          const data = await response.json();
          if (data.ok) {
            label.textContent = data.is_active ? 'Activo' : 'Inactivo';
            if (window.nygAlert) {
              window.nygAlert(data.message, 'success');
            }
          } else {
            throw new Error(data.message || 'Error al actualizar el estado.');
          }
        } catch (error) {
          console.error(error);
          this.checked = !checked; // revert
          const errMsg = error?.message || 'Error al actualizar el estado.';
          if (window.nygAlert) {
            window.nygAlert(errMsg, 'error');
          } else {
            alert(errMsg);
          }
        } finally {
          this.disabled = false;
        }
      });
    });
  </script>
@endpush

