@php
  $prefixId = $prefixId ?? 'transporte';
  $transportistas = $transportistas ?? collect();
  $hideCarrier = $hideCarrier ?? false;
  $transportistaId = $transportistaId ?? null;
  $transporteId = $transporteId ?? null;
  $transportista = null;
  $transporte = null;
  
  // Cargar transportista si se proporciona el ID
  if ($transportistaId) {
    $transportista = \App\Models\Transportista::find($transportistaId);
  }
  
  // Cargar transporte si se proporciona el ID
  if ($transporteId) {
    $transporte = \App\Models\Transporte::find($transporteId);
    if ($transporte && $transporte->transportista_id) {
      $transportista = \App\Models\Transportista::find($transporte->transportista_id);
    }
  }
@endphp

<div class="modal fade" id="{{ $prefixId }}Modal" tabindex="-1" aria-labelledby="{{ $prefixId }}ModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $prefixId }}ModalLabel">
          @if($transporte)
            Editar transporte
            @if($transportista)
              - {{ $transportista->name }}
            @endif
          @elseif($transportista)
            Nuevo transporte - {{ $transportista->name }}
          @else
            Nuevo transporte
          @endif
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="{{ $prefixId }}Form" action="{{ route('traffic.transportes.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST" id="{{ $prefixId }}Method">
        <input type="hidden" name="transportista_id" id="{{ $prefixId }}CarrierValue" @if($transportista) value="{{ $transportista->id }}" @endif>
        <div class="modal-body">
          <div class="mb-3" id="{{ $prefixId }}CarrierGroup" @if($hideCarrier || $transportista) style="display:none;" @endif>
            <label class="form-label">Transportista *</label>
            <select class="form-select @error('transportista_id') is-invalid @enderror"
                    id="{{ $prefixId }}Carrier">
              <option value="">Seleccionar...</option>
              @foreach($transportistas as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
              @endforeach
            </select>
            @error('transportista_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Alias / Identificador *</label>
            <input class="form-control @error('alias') is-invalid @enderror"
                   name="alias"
                   id="{{ $prefixId }}Alias"
                   required>
            @error('alias')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Chofer</label>
              <input class="form-control @error('driver_name') is-invalid @enderror"
                     name="driver_name"
                     id="{{ $prefixId }}Driver">
              @error('driver_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Titular</label>
              <input class="form-control @error('owner_name') is-invalid @enderror"
                     name="owner_name"
                     id="{{ $prefixId }}Owner">
              @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Estado</label>
              <input class="form-control @error('status') is-invalid @enderror"
                     name="status"
                     id="{{ $prefixId }}Status">
              @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Tipo</label>
              <select class="form-select @error('type') is-invalid @enderror"
                      name="type"
                      id="{{ $prefixId }}Type">
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
              <label class="form-label">Patente</label>
              <input class="form-control @error('license_plate') is-invalid @enderror"
                     name="license_plate"
                     id="{{ $prefixId }}Plate">
              @error('license_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Color de la unidad</label>
              <input class="form-control @error('unit_color') is-invalid @enderror"
                     name="unit_color"
                     id="{{ $prefixId }}Color">
              @error('unit_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Marca</label>
              <input class="form-control @error('brand') is-invalid @enderror"
                     name="brand"
                     id="{{ $prefixId }}Brand">
              @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Modelo</label>
              <input class="form-control @error('model') is-invalid @enderror"
                     name="model"
                     id="{{ $prefixId }}Model">
              @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Versión</label>
              <input class="form-control @error('version') is-invalid @enderror"
                     name="version"
                     id="{{ $prefixId }}Version">
              @error('version')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Año</label>
              <input class="form-control @error('year') is-invalid @enderror"
                     type="number"
                     name="year"
                     id="{{ $prefixId }}Year">
              @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Nº de chasis</label>
              <input class="form-control @error('chassis_number') is-invalid @enderror"
                     name="chassis_number"
                     id="{{ $prefixId }}Chassis">
              @error('chassis_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Combustible</label>
              <input class="form-control @error('fuel_type') is-invalid @enderror"
                     name="fuel_type"
                     id="{{ $prefixId }}Fuel">
              @error('fuel_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Cédula verde / azul</label>
              <input class="form-control @error('registration_card') is-invalid @enderror"
                     name="registration_card"
                     id="{{ $prefixId }}Registration">
              @error('registration_card')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">VTV</label>
              <input class="form-control @error('vtv') is-invalid @enderror"
                     name="vtv"
                     id="{{ $prefixId }}Vtv">
              @error('vtv')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Seguro</label>
              <input class="form-control @error('insurance') is-invalid @enderror"
                     name="insurance"
                     id="{{ $prefixId }}Insurance">
              @error('insurance')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Satelital</label>
              <input class="form-control @error('satellite') is-invalid @enderror"
                     name="satellite"
                     id="{{ $prefixId }}Satellite">
              @error('satellite')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Cant. de puertas</label>
              <input class="form-control @error('doors_count') is-invalid @enderror"
                     type="number"
                     min="0"
                     name="doors_count"
                     id="{{ $prefixId }}Doors">
              @error('doors_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Cap. tanque</label>
              <input class="form-control @error('tank_capacity') is-invalid @enderror"
                     name="tank_capacity"
                     id="{{ $prefixId }}Tank">
              @error('tank_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Prom. consumo</label>
              <input class="form-control @error('fuel_consumption_avg') is-invalid @enderror"
                     name="fuel_consumption_avg"
                     id="{{ $prefixId }}Consumption">
              @error('fuel_consumption_avg')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Capacidad (kg)</label>
              <input class="form-control @error('capacity_kg') is-invalid @enderror"
                     type="number"
                     name="capacity_kg"
                     id="{{ $prefixId }}Capacity">
              @error('capacity_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Largo (cm)</label>
              <input class="form-control @error('length_cm') is-invalid @enderror"
                     type="number" step="0.01" min="0"
                     name="length_cm"
                     id="{{ $prefixId }}Length">
              @error('length_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Ancho (cm)</label>
              <input class="form-control @error('width_cm') is-invalid @enderror"
                     type="number" step="0.01" min="0"
                     name="width_cm"
                     id="{{ $prefixId }}Width">
              @error('width_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Alto (cm)</label>
              <input class="form-control @error('height_cm') is-invalid @enderror"
                     type="number" step="0.01" min="0"
                     name="height_cm"
                     id="{{ $prefixId }}Height">
              @error('height_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Volumen (m³)</label>
              <input class="form-control @error('volume_m3') is-invalid @enderror"
                     type="number" step="0.001" min="0"
                     name="volume_m3"
                     id="{{ $prefixId }}Volume">
              @error('volume_m3')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Identificador GPS / interno</label>
            <input class="form-control @error('tracking_identifier') is-invalid @enderror"
                   name="tracking_identifier"
                   id="{{ $prefixId }}Tracking">
            @error('tracking_identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea class="form-control @error('notes') is-invalid @enderror"
                      rows="3"
                      name="notes"
                      id="{{ $prefixId }}Notes"></textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input"
                   type="checkbox"
                   id="{{ $prefixId }}Active"
                   name="is_active"
                   value="1"
                   checked>
            <label class="form-check-label" for="{{ $prefixId }}Active">Transporte activo</label>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input"
                   type="checkbox"
                   id="{{ $prefixId }}Default"
                   name="is_default"
                   value="1">
            <label class="form-check-label" for="{{ $prefixId }}Default">Marcar como por defecto</label>
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

@push('scripts')
  <script>
    console.log('=== TRANSPORTE MODAL COMPONENT LOADING ===');
    console.log('prefixId will be: transporte');
    
    (function () {
      const prefixId = '{{ $prefixId }}';
      console.log('prefixId:', prefixId);
      const modalEl = document.getElementById(prefixId + 'Modal');
      console.log('Modal element found:', !!modalEl);
      if (!modalEl) {
        console.error('Modal element not found with ID:', prefixId + 'Modal');
        return;
      }

      const form = document.getElementById(prefixId + 'Form');
      const methodInput = document.getElementById(prefixId + 'Method');
      const titleEl = document.getElementById(prefixId + 'ModalLabel');

      const fields = {
        transportista_id: document.getElementById(prefixId + 'Carrier'),
        alias: document.getElementById(prefixId + 'Alias'),
        driver_name: document.getElementById(prefixId + 'Driver'),
        owner_name: document.getElementById(prefixId + 'Owner'),
        status: document.getElementById(prefixId + 'Status'),
        license_plate: document.getElementById(prefixId + 'Plate'),
        type: document.getElementById(prefixId + 'Type'),
        unit_color: document.getElementById(prefixId + 'Color'),
        brand: document.getElementById(prefixId + 'Brand'),
        model: document.getElementById(prefixId + 'Model'),
        version: document.getElementById(prefixId + 'Version'),
        year: document.getElementById(prefixId + 'Year'),
        chassis_number: document.getElementById(prefixId + 'Chassis'),
        fuel_type: document.getElementById(prefixId + 'Fuel'),
        registration_card: document.getElementById(prefixId + 'Registration'),
        vtv: document.getElementById(prefixId + 'Vtv'),
        insurance: document.getElementById(prefixId + 'Insurance'),
        satellite: document.getElementById(prefixId + 'Satellite'),
        doors_count: document.getElementById(prefixId + 'Doors'),
        tank_capacity: document.getElementById(prefixId + 'Tank'),
        fuel_consumption_avg: document.getElementById(prefixId + 'Consumption'),
        capacity_kg: document.getElementById(prefixId + 'Capacity'),
        length_cm: document.getElementById(prefixId + 'Length'),
        width_cm: document.getElementById(prefixId + 'Width'),
        height_cm: document.getElementById(prefixId + 'Height'),
        volume_m3: document.getElementById(prefixId + 'Volume'),
        tracking_identifier: document.getElementById(prefixId + 'Tracking'),
        notes: document.getElementById(prefixId + 'Notes'),
        is_active: document.getElementById(prefixId + 'Active'),
        is_default: document.getElementById(prefixId + 'Default'),
      };
      
      console.log('Fields loaded:', {
        carrier: !!fields.transportista_id,
        alias: !!fields.alias,
        license_plate: !!fields.license_plate,
        type: !!fields.type,
        brand: !!fields.brand,
        model: !!fields.model,
        year: !!fields.year,
        capacity_kg: !!fields.capacity_kg,
        tracking_identifier: !!fields.tracking_identifier,
        notes: !!fields.notes,
        is_active: !!fields.is_active,
        is_default: !!fields.is_default,
      });

      window[prefixId + '_clearForm'] = function() {
        form.action = "{{ route('traffic.transportes.store') }}";
        methodInput.value = 'POST';
        titleEl.textContent = 'Nuevo transporte';
        
        const carrierValue = document.getElementById(prefixId + 'CarrierValue');
        
        // Limpiar campos
        Object.entries(fields).forEach(([key, el]) => {
          if (!el) return;
          if (el.tagName === 'SELECT') {
            el.value = '';
          } else if (el.type === 'checkbox') {
            el.checked = el.id === (prefixId + 'Active') ? true : false;
          } else {
            el.value = '';
          }
        });
        
        // Limpiar hidden
        if (carrierValue) {
          carrierValue.value = '';
        }
      };

      window[prefixId + '_loadTransporte'] = function(transporte, hideCarrier) {
        console.log('_loadTransporte called with:', {id: transporte.id, alias: transporte.alias, hideCarrier});
        
        titleEl.textContent = 'Editar transporte';
        form.action = "/traffic/transportes/" + transporte.id;
        methodInput.value = 'PUT';

        const carrierValue = document.getElementById(prefixId + 'CarrierValue');
        const carrierGroup = document.getElementById(prefixId + 'CarrierGroup');
        const carrierSelect = fields.transportista_id;
        
        console.log('carrierSelect element:', !!carrierSelect);
        console.log('fields.alias element:', !!fields.alias);

        if (hideCarrier) {
          if (carrierGroup) carrierGroup.style.display = 'none';
          if (carrierValue) carrierValue.value = transporte.transportista_id || '';
        } else {
          if (carrierGroup) carrierGroup.style.display = 'block';
          if (transporte.transportista_id) {
            const exists = Array.from(carrierSelect.options).some(opt => String(opt.value) === String(transporte.transportista_id));
            if (!exists) {
              const opt = document.createElement('option');
              opt.value = transporte.transportista_id;
              opt.textContent = transporte.transportista_name || `Transportista #${transporte.transportista_id}`;
              carrierSelect.appendChild(opt);
            }
          }
          carrierSelect.value = transporte.transportista_id ? String(transporte.transportista_id) : '';
          if (carrierValue) carrierValue.value = transporte.transportista_id || '';
        }

        fields.alias.value = transporte.alias || '';
        fields.driver_name.value = transporte.driver_name || '';
        fields.owner_name.value = transporte.owner_name || '';
        fields.status.value = transporte.status || '';
        fields.license_plate.value = transporte.license_plate || '';
        fields.type.value = transporte.type || '';
        fields.unit_color.value = transporte.unit_color || '';
        fields.brand.value = transporte.brand || '';
        fields.model.value = transporte.model || '';
        fields.version.value = transporte.version || '';
        fields.year.value = transporte.year || '';
        fields.chassis_number.value = transporte.chassis_number || '';
        fields.fuel_type.value = transporte.fuel_type || '';
        fields.registration_card.value = transporte.registration_card || '';
        fields.vtv.value = transporte.vtv || '';
        fields.insurance.value = transporte.insurance || '';
        fields.satellite.value = transporte.satellite || '';
        fields.doors_count.value = transporte.doors_count || '';
        fields.tank_capacity.value = transporte.tank_capacity || '';
        fields.fuel_consumption_avg.value = transporte.fuel_consumption_avg || '';
        fields.capacity_kg.value = transporte.capacity_kg || '';
        fields.length_cm.value = transporte.length_cm || '';
        fields.width_cm.value = transporte.width_cm || '';
        fields.height_cm.value = transporte.height_cm || '';
        fields.volume_m3.value = transporte.volume_m3 || '';
        fields.tracking_identifier.value = transporte.tracking_identifier || '';
        fields.notes.value = transporte.notes || '';
        fields.is_active.checked = !!transporte.is_active;
        fields.is_default.checked = !!transporte.is_default;
      };

      window[prefixId + '_showModal'] = function() {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      };

      window[prefixId + '_hideModal'] = function() {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      };

      // Inicializar con transportista ID
      window[prefixId + '_initWithTransportista'] = function(transportistaId, transportistaName) {
        window[prefixId + '_clearForm']();
        
        // Actualizar título y hidden field
        titleEl.textContent = 'Nuevo transporte - ' + transportistaName;
        const carrierValue = document.getElementById(prefixId + 'CarrierValue');
        const carrierGroup = document.getElementById(prefixId + 'CarrierGroup');
        
        if (carrierValue) {
          carrierValue.value = transportistaId;
        }
        if (carrierGroup) {
          carrierGroup.style.display = 'none';
        }

        // Pre-rellenar chofer y titular con el nombre del transportista
        if (fields.driver_name) {
          fields.driver_name.value = transportistaName || '';
        }
        if (fields.owner_name) {
          fields.owner_name.value = transportistaName || '';
        }
        
        window[prefixId + '_showModal']();
      };

      // Inicializar con transporte ID para editar
      window[prefixId + '_initWithTransporte'] = async function(transporteId) {
        try {
          const response = await fetch('/traffic/transportes/' + transporteId + '/edit', {
            headers: {
              'Accept': 'application/json',
            }
          });
          
          if (!response.ok) {
            if (window.nygAlert) {
              window.nygAlert('Error al cargar el transporte', 'error');
            }
            return;
          }
          
          const data = await response.json();
          const transporte = data.data;
          
          // Actualizar el título con el nombre del transportista
          let title = 'Editar transporte';
          if (transporte.transportista_name) {
            title += ' - ' + transporte.transportista_name;
          }
          titleEl.textContent = title;
          
          // Cargar los datos
          window[prefixId + '_loadTransporte'](transporte, true);
          
          window[prefixId + '_showModal']();
        } catch (error) {
          console.error('Error loading transporte:', error);
          if (window.nygAlert) {
            window.nygAlert('Error al cargar el transporte', 'error');
          }
        }
      };

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validar que transportista_id esté presente
        const carrierValue = document.getElementById(prefixId + 'CarrierValue');
        const carrierGroup = document.getElementById(prefixId + 'CarrierGroup');
        const carrierSelect = fields.transportista_id;
        const isHidden = carrierGroup && carrierGroup.style.display === 'none';

        // Determinar de dónde obtener el valor
        const visibleValue = carrierSelect.value;
        const hiddenValue = carrierValue?.value;
        const finalValue = isHidden ? hiddenValue : visibleValue;

        if (!finalValue) {
          if (window.nygAlert) {
            window.nygAlert('Debe seleccionar un transportista', 'warning');
          }
          return;
        }

        // Actualizar el hidden field con el valor final
        if (carrierValue) {
          carrierValue.value = finalValue;
        }

        const formData = new FormData(form);
        let action = form.action;
        const method = methodInput.value;

        // Si es PUT, agregar el método HTTP override
        if (method === 'PUT') {
          formData.append('_method', 'PUT');
        }

        try {
          const response = await fetch(action, {
            method: 'POST', // Laravel espera POST con _method override
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
              'Accept': 'application/json',
            },
            body: formData,
          });

          if (!response.ok) {
            const json = await response.json();
            if (window.nygAlert) {
              window.nygAlert('Error: ' + (json.message || 'Error al guardar'), 'error');
            }
            return;
          }

          const json = await response.json();
          window[prefixId + '_hideModal']();
          
          // Recargar la lista de transportes si estamos en el modal de transportistas
          if (window.currentTransportistaId && window.loadTransportes) {
            await window.loadTransportes(window.currentTransportistaId);
          }
          
          if (window.nygAlert) {
            window.nygAlert('Transporte guardado correctamente', 'success');
          }
        } catch (error) {
          console.error('Error saving transporte:', error);
          if (window.nygAlert) {
            window.nygAlert('Error al guardar el transporte', 'error');
          }
        }
      });

      // No limpiar automáticamente al abrir el modal
      // Las funciones _initWithTransportista y _initWithTransporte ya se encargan
      // de preparar el formulario según sea necesario
    })();
  </script>
@endpush
