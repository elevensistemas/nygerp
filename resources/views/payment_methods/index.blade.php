@extends('layouts.app')
@section('title','Medios de pago')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Medios de pago</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('payment-methods.index') }}" class="d-flex">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar medio">
      <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-sm btn-primary" id="btnNewMethod" data-bs-toggle="modal" data-bs-target="#methodModal">Nuevo medio</button>
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
        <th>Cuenta</th>
        <th>Descripcion</th>
        <th class="text-center">Activo</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($methods as $method)
        <tr>
          <td>{{ $method->code }}</td>
          <td>{{ $method->name }}</td>
          <td>{{ optional($method->account)->code }} {{ optional($method->account)->name }}</td>
          <td>{{ $method->description }}</td>
          <td class="text-center">
            <span class="badge {{ $method->active ? 'bg-success' : 'bg-secondary' }}">
              {{ $method->active ? 'Si' : 'No' }}
            </span>
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $method->id }}">Editar</button>
            <form method="post" action="{{ route('payment-methods.destroy', $method) }}" class="d-inline" data-confirm="Eliminar medio de pago?">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No hay medios de pago cargados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $methods->links() }}</div>

<div class="modal fade" id="methodModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="{{ route('payment-methods.store') }}" id="methodForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nuevo medio de pago</h5>
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
              <label class="form-label">Cuenta contable</label>
              <select name="account_id" class="form-select" required>
                <option value="">Seleccionar</option>
                @foreach($accounts as $account)
                  <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Descripcion</label>
              <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 form-check mt-2">
              <input type="checkbox" class="form-check-input" id="methodActive" name="active" value="1" checked>
              <label class="form-check-label" for="methodActive">Activo</label>
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
  const modalEl = document.getElementById('methodModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('methodForm');
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

  document.getElementById('btnNewMethod').addEventListener('click', () => {
    form.reset();
    setFormAction("{{ route('payment-methods.store') }}", 'POST');
    titleEl.textContent = 'Nuevo medio de pago';
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/payment-methods/${id}`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/payment-methods/${id}`, 'PUT');
          titleEl.textContent = 'Editar medio de pago';
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

