@props([
    'prefixId' => 'carrier',
    'users' => [],
    'suppliers' => [],
])

<div class="modal fade" id="{{ $prefixId }}Modal" tabindex="-1" aria-labelledby="{{ $prefixId }}ModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $prefixId }}ModalLabel">Nuevo transportista</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="{{ $prefixId }}Form" action="{{ route('traffic.transportistas.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST" id="{{ $prefixId }}Method">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Usuario</label>
              <select class="form-select @error('user_id') is-invalid @enderror" 
                      name="user_id" 
                      id="{{ $prefixId }}User">
                <option value="">Sin usuario asignado</option>
                @foreach($users as $user)
                  @php $alreadyAssigned = $user->transportistaProfile; @endphp
                  @if(!$alreadyAssigned)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                  @endif
                @endforeach
              </select>
              @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre *</label>
              <input class="form-control @error('name') is-invalid @enderror"
                     name="name"
                     id="{{ $prefixId }}Name"
                     required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label">Razón social</label>
              <input class="form-control @error('business_name') is-invalid @enderror"
                     name="business_name"
                     id="{{ $prefixId }}Business">
              @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input class="form-control @error('email') is-invalid @enderror"
                     name="email"
                     id="{{ $prefixId }}Email"
                     type="email">
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input class="form-control @error('phone') is-invalid @enderror"
                     name="phone"
                     id="{{ $prefixId }}Phone">
              @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Cuit / DNI</label>
              <input class="form-control @error('tax_id') is-invalid @enderror"
                     name="tax_id"
                     id="{{ $prefixId }}Tax">
              @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label">Licencia</label>
              <input class="form-control @error('license_number') is-invalid @enderror"
                     name="license_number"
                     id="{{ $prefixId }}License">
              @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Base operativa</label>
              <input class="form-control @error('base_location') is-invalid @enderror"
                     name="base_location"
                     id="{{ $prefixId }}Base">
              @error('base_location')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de liquidacion</label>
              <select class="form-select @error('liquidation_tipo_periodo') is-invalid @enderror"
                      name="liquidation_tipo_periodo"
                      id="{{ $prefixId }}LiquidationPeriod">
                <option value="">Sin configurar</option>
                <option value="quincenal">Quincenal</option>
                <option value="mensual">Mensual</option>
              </select>
              <div class="form-text">Se usa para agrupar recibos importados por fecha.</div>
              @error('liquidation_tipo_periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label class="form-label">Proveedor</label>
            <select class="form-select @error('supplier_id') is-invalid @enderror" 
                    name="supplier_id" 
                    id="{{ $prefixId }}Supplier">
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
                     id="{{ $prefixId }}Cost"
                     type="number"
                     step="0.01"
                     min="0">
              <div class="form-text">Costo real (por km o fijo según definas).</div>
              @error('cost_efficiency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Ponderación de desempeño</label>
              <input class="form-control @error('performance_weight') is-invalid @enderror"
                     name="performance_weight"
                     id="{{ $prefixId }}Weight"
                     type="number"
                     step="0.01"
                     min="0">
              <div class="form-text">Factor para priorizar transportistas.</div>
              @error('performance_weight')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Color</label>
            <input class="form-control form-control-color @error('color') is-invalid @enderror"
                   type="color"
                   name="color"
                   id="{{ $prefixId }}Color"
                   value="#2563eb">
            @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea class="form-control @error('notes') is-invalid @enderror"
                      name="notes"
                      id="{{ $prefixId }}Notes"
                      rows="3"></textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input"
                   type="checkbox"
                   id="{{ $prefixId }}Active"
                   name="is_active"
                   value="1"
                   checked>
            <label class="form-check-label" for="{{ $prefixId }}Active">Transportista activo</label>
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
    (function () {
      const prefixId = '{{ $prefixId }}';
      const modalEl = document.getElementById(prefixId + 'Modal');
      if (!modalEl) return;

      const form = document.getElementById(prefixId + 'Form');
      const methodInput = document.getElementById(prefixId + 'Method');
      const titleEl = document.getElementById(prefixId + 'ModalLabel');

      const fields = {
        user_id: document.getElementById(prefixId + 'User'),
        name: document.getElementById(prefixId + 'Name'),
        business_name: document.getElementById(prefixId + 'Business'),
        email: document.getElementById(prefixId + 'Email'),
        phone: document.getElementById(prefixId + 'Phone'),
        tax_id: document.getElementById(prefixId + 'Tax'),
        license_number: document.getElementById(prefixId + 'License'),
        base_location: document.getElementById(prefixId + 'Base'),
        liquidation_tipo_periodo: document.getElementById(prefixId + 'LiquidationPeriod'),
        supplier_id: document.getElementById(prefixId + 'Supplier'),
        cost_efficiency: document.getElementById(prefixId + 'Cost'),
        performance_weight: document.getElementById(prefixId + 'Weight'),
        color: document.getElementById(prefixId + 'Color'),
        notes: document.getElementById(prefixId + 'Notes'),
        is_active: document.getElementById(prefixId + 'Active'),
      };

      window[prefixId + '_clearForm'] = function() {
        form.action = "{{ route('traffic.transportistas.store') }}";
        methodInput.value = 'POST';
        titleEl.textContent = 'Nuevo transportista';
        Object.entries(fields).forEach(([key, el]) => {
          if (!el) return;
          if (el.tagName === 'SELECT') {
            el.value = '';
          } else if (el.type === 'checkbox') {
            el.checked = true;
          } else {
            el.value = '';
          }
        });
        if (fields.cost_efficiency) fields.cost_efficiency.value = 0;
        if (fields.performance_weight) fields.performance_weight.value = 1;
        if (fields.color) fields.color.value = '#2563eb';
      };

      window[prefixId + '_loadCarrier'] = function(carrier) {
        titleEl.textContent = 'Editar transportista';
        form.action = "/traffic/transportistas/" + carrier.id;
        methodInput.value = 'PUT';

        // Agregar usuario actual si está asignado
        if (carrier.user_id && carrier.user) {
          const exists = Array.from(fields.user_id.options).some(opt => String(opt.value) === String(carrier.user_id));
          if (!exists) {
            const opt = document.createElement('option');
            opt.value = carrier.user_id;
            opt.textContent = `${carrier.user.name} (${carrier.user.email})`;
            fields.user_id.appendChild(opt);
          }
        }

        fields.user_id.value = carrier.user_id || '';
        fields.name.value = carrier.name || '';
        fields.business_name.value = carrier.business_name || '';
        fields.email.value = carrier.email || '';
        fields.phone.value = carrier.phone || '';
        fields.tax_id.value = carrier.tax_id || '';
        fields.license_number.value = carrier.license_number || '';
        fields.base_location.value = carrier.base_location || '';
        fields.liquidation_tipo_periodo.value = carrier.liquidation_meta?.tipo_periodo || '';
        fields.supplier_id.value = carrier.supplier_id || '';
        fields.cost_efficiency.value = carrier.cost_efficiency ?? 0;
        fields.performance_weight.value = carrier.performance_weight ?? 1;
        fields.color.value = carrier.color || '#2563eb';
        fields.notes.value = carrier.notes || '';
        fields.is_active.checked = !!carrier.is_active;
      };

      window[prefixId + '_showModal'] = function() {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      };

      window[prefixId + '_hideModal'] = function() {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      };

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

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
          
          // Recargar la página
          window.location.reload();
        } catch (error) {
          console.error('Error saving carrier:', error);
          if (window.nygAlert) {
            window.nygAlert('Error al guardar el transportista', 'error');
          }
        }
      });

      modalEl.addEventListener('show.bs.modal', (event) => {
        window[prefixId + '_clearForm']();
      });
    })();
  </script>
@endpush
