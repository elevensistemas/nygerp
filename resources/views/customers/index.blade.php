@extends('layouts.app')
@section('title','Clientes')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Clientes</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('customers.index') }}" class="d-flex">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar por nombre o CUIT">
      <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" id="btnNewCustomer">
      Nuevo cliente
    </button>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Nombre</th>
        <th>Fantasia</th>
        <th>CUIT</th>
        <th>IVA</th>
        <th>Telefonos</th>
        <th>Email</th>
        <th>Ciudad</th>
        <th class="text-center">Activo</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($customers as $customer)
        <tr>
          <td>{{ $customer->name }}</td>
          <td>{{ $customer->business_name }}</td>
          <td>{{ $customer->tax_id }}</td>
          <td>{{ strtoupper(str_replace('_',' ', $customer->iva_condition ?? '')) }}</td>
          <td>
            <div>{{ $customer->phone }}</div>
            <div class="text-muted small">{{ $customer->mobile }}</div>
          </td>
          <td>
            <div>{{ $customer->email }}</div>
            @if($customer->email_secondary)<div class="text-muted small">{{ $customer->email_secondary }}</div>@endif
          </td>
          <td>{{ $customer->city }}</td>
          <td class="text-center">
            <span class="badge {{ $customer->active ? 'bg-success' : 'bg-secondary' }}">
              {{ $customer->active ? 'Si' : 'No' }}
            </span>
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $customer->id }}">Editar</button>
            <form method="post" action="{{ route('customers.destroy', $customer) }}" class="d-inline" data-confirm="Eliminar cliente?">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron clientes.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $customers->links() }}</div>

<!-- Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="{{ route('customers.store') }}" id="customerForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nuevo cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre / Razon social</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre de fantasia</label>
              <input type="text" name="business_name" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">CUIT / DNI</label>
              <input type="text" name="tax_id" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Condicion IVA</label>
              <select name="iva_condition" class="form-select">
                <option value="">Seleccionar</option>
                <option value="responsable_inscripto">Responsable Inscripto</option>
                <option value="monotributo">Monotributo</option>
                <option value="exento">Exento</option>
                <option value="consumidor_final">Consumidor Final</option>
                <option value="no_residente">No residente</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Ingresos Brutos</label>
              <input type="text" name="iibb_number" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email principal</label>
              <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email alternativo</label>
              <input type="email" name="email_secondary" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Telefono</label>
              <input type="text" name="phone" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Celular</label>
              <input type="text" name="mobile" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Codigo Postal</label>
              <input type="text" name="postal_code" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Direccion</label>
              <input type="text" name="address" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Ciudad</label>
              <input type="text" name="city" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Provincia</label>
              <input type="text" name="province" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Alias bancario</label>
              <input type="text" name="bank_alias" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">CBU</label>
              <input type="text" name="bank_cbu" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Notas</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 form-check mt-2">
              <input type="checkbox" class="form-check-input" id="customerActive" name="active" value="1" checked>
              <label class="form-check-label" for="customerActive">Activo</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="customerSubmitBtn">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('customerModal');
  const form = document.getElementById('customerForm');
  const modal = new bootstrap.Modal(modalEl);
  const titleEl = modalEl.querySelector('.modal-title');

  const setFormAction = (action, method = 'POST') => {
    form.action = action;
    const methodField = form.querySelector('input[name="_method"]');
    if (method === 'POST') {
      if (methodField) methodField.remove();
    } else {
      if (!methodField) {
        form.insertAdjacentHTML('afterbegin', '<input type="hidden" name="_method" value="' + method + '">');
      } else {
        methodField.value = method;
      }
    }
  };

  document.getElementById('btnNewCustomer').addEventListener('click', () => {
    form.reset();
    setFormAction("{{ route('customers.store') }}", 'POST');
    titleEl.textContent = 'Nuevo cliente';
    modal.show();
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/customers/${id}/edit`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/customers/${id}`, 'PUT');
          titleEl.textContent = 'Editar cliente';
          Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
              if (input.type === 'checkbox') {
                input.checked = Boolean(data[key]);
              } else {
                input.value = data[key] ?? '';
              }
            }
          });
          modal.show();
        });
    });
  });
});
</script>
@endpush
