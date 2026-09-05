@extends('layouts.app')
@section('title','Cuentas contables')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Cuentas contables</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('accounts.index') }}" class="d-flex">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar cuenta">
      <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-sm btn-primary" id="btnNewAccount" data-bs-toggle="modal" data-bs-target="#accountModal">Nueva cuenta</button>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Codigo</th>
        <th>Nombre</th>
        <th>Tipo</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($accounts as $account)
        <tr>
          <td>{{ $account->code }}</td>
          <td>{{ $account->name }}</td>
          <td>{{ ucfirst($account->type) }}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $account->id }}">Editar</button>
            <form method="post" action="{{ route('accounts.destroy', $account) }}" class="d-inline" data-confirm="Eliminar cuenta?">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-center text-muted py-4">No hay cuentas cargadas.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $accounts->links() }}</div>

<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('accounts.store') }}" id="accountForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nueva cuenta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Codigo</label>
            <input type="text" name="code" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select" required>
              <option value="asset">Activo</option>
              <option value="liability">Pasivo</option>
              <option value="equity">Patrimonio</option>
              <option value="income">Ingreso</option>
              <option value="expense">Gasto</option>
            </select>
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
document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('accountModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('accountForm');
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

  document.getElementById('btnNewAccount').addEventListener('click', () => {
    form.reset();
    setFormAction("{{ route('accounts.store') }}", 'POST');
    titleEl.textContent = 'Nueva cuenta';
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/accounts/${id}/edit`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/accounts/${id}`, 'PUT');
          titleEl.textContent = 'Editar cuenta';
          form.querySelector('[name="code"]').value = data.code ?? '';
          form.querySelector('[name="name"]').value = data.name ?? '';
          form.querySelector('[name="type"]').value = data.type ?? 'asset';
          modal.show();
        });
    });
  });
});
</script>
@endpush
