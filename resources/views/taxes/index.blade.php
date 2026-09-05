@extends('layouts.app')
@section('title','Impuestos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Impuestos (Percepciones y Retenciones)</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('taxes.index') }}" class="d-flex">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar impuesto">
      <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-sm btn-primary" id="btnNewTax" data-bs-toggle="modal" data-bs-target="#taxModal">Nuevo impuesto</button>
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
        <th>Codigo</th>
        <th>Nombre</th>
        <th>Tipo</th>
        <th>Aplica en</th>
        <th>Base</th>
        <th class="text-end">Alicuota (%)</th>
        <th>Cuenta</th>
        <th class="text-center">Activo</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($taxes as $tax)
        <tr>
          <td>{{ $tax->code }}</td>
          <td>{{ $tax->name }}</td>
          <td>{{ ucfirst($tax->type) }}</td>
          <td>{{ $tax->applies_on === 'invoice' ? 'Factura' : 'Pago' }}</td>
          <td>{{ $tax->base === 'gross' ? 'Bruto' : 'Neto' }}</td>
          <td class="text-end">{{ number_format($tax->rate, 4, ',', '.') }}</td>
          <td>{{ optional($tax->account)->code }} {{ optional($tax->account)->name }}</td>
          <td class="text-center">
            <span class="badge {{ $tax->active ? 'bg-success' : 'bg-secondary' }}">
              {{ $tax->active ? 'Si' : 'No' }}
            </span>
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $tax->id }}">Editar</button>
            <form method="post" action="{{ route('taxes.destroy', $tax) }}" class="d-inline" data-confirm="Eliminar impuesto?">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="text-center text-muted py-4">No hay impuestos cargados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $taxes->links() }}</div>

<div class="modal fade" id="taxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="{{ route('taxes.store') }}" id="taxForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nuevo impuesto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Codigo</label>
              <input type="text" name="code" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Nombre</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select name="type" class="form-select" required>
                <option value="perception">Percepcion</option>
                <option value="retention">Retencion</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Aplica en</label>
              <select name="applies_on" class="form-select" required>
                <option value="invoice">Factura</option>
                <option value="payment">Pago</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Base</label>
              <select name="base" class="form-select" required>
                <option value="net">Neto</option>
                <option value="gross">Bruto</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Alicuota (%)</label>
              <input type="number" step="0.0001" min="0" name="rate" class="form-control" value="0" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cuenta contable</label>
              <select name="account_id" class="form-select" required>
                <option value="">Seleccionar</option>
                @foreach($accounts as $account)
                  <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12 form-check mt-2">
              <input type="checkbox" class="form-check-input" id="taxActive" name="active" value="1" checked>
              <label class="form-check-label" for="taxActive">Activo</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('taxModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('taxForm');
  const titleEl = modalEl.querySelector('.modal-title');

  const setFormAction = (action, method = 'POST') => {
    form.action = action;
    let methodField = form.querySelector('input[name="_method"]');
    if (method === 'POST') {
      if (methodField) methodField.remove();
    } else {
      if (!methodField) {
        methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        form.prepend(methodField);
      }
      methodField.value = method;
    }
  };

  document.getElementById('btnNewTax').addEventListener('click', () => {
    form.reset();
    setFormAction("{{ route('taxes.store') }}", 'POST');
    titleEl.textContent = 'Nuevo impuesto';
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/taxes/${id}`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/taxes/${id}`, 'PUT');
          titleEl.textContent = 'Editar impuesto';
          for (const [key, value] of Object.entries(data)) {
            const field = form.querySelector(`[name="${key}"]`);
            if (!field) continue;
            if (field.type === 'checkbox') {
              field.checked = Boolean(value);
            } else {
              field.value = value ?? '';
            }
          }
          modal.show();
        });
    });
  });
});
</script>
@endpush

