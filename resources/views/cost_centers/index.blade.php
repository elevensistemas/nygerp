@extends('layouts.app')
@section('title','Centros de costo')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Centros de costo</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('cost-centers.index') }}" class="d-flex">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar centro">
      <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-sm btn-primary" id="btnNewCenter" data-bs-toggle="modal" data-bs-target="#centerModal">Nuevo centro</button>
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
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($centers as $center)
        <tr>
          <td>{{ $center->code }}</td>
          <td>{{ $center->name }}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $center->id }}">Editar</button>
            <form method="post" action="{{ route('cost-centers.destroy', $center) }}" class="d-inline" data-confirm="Eliminar centro?">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-center text-muted py-4">No hay centros cargados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $centers->links() }}</div>

<div class="modal fade" id="centerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('cost-centers.store') }}" id="centerForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nuevo centro</h5>
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
  const modalEl = document.getElementById('centerModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('centerForm');
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

  document.getElementById('btnNewCenter').addEventListener('click', () => {
    form.reset();
    setFormAction("{{ route('cost-centers.store') }}", 'POST');
    titleEl.textContent = 'Nuevo centro';
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/cost-centers/${id}/edit`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/cost-centers/${id}`, 'PUT');
          titleEl.textContent = 'Editar centro';
          form.querySelector('[name="code"]').value = data.code ?? '';
          form.querySelector('[name="name"]').value = data.name ?? '';
          modal.show();
        });
    });
  });
});
</script>
@endpush
