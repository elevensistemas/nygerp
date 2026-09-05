@extends('layouts.app')
@section('title','Condiciones de pago')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Condiciones de pago</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('payment-terms.index') }}" class="d-flex">
      <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar condicion">
      <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-sm btn-primary" id="btnNewTerm" data-bs-toggle="modal" data-bs-target="#termModal">Nueva condicion</button>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Nombre</th>
        <th>Dias</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($terms as $term)
        <tr>
          <td>{{ $term->name }}</td>
          <td>
            @php $days = $term->days ?? []; @endphp
            {{ empty($days) ? 'Contado' : implode(', ', $days) }}
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $term->id }}">Editar</button>
            <form method="post" action="{{ route('payment-terms.destroy', $term) }}" class="d-inline" data-confirm="Eliminar condicion?">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-center text-muted py-4">No hay condiciones definidas.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $terms->links() }}</div>

<div class="modal fade" id="termModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('payment-terms.store') }}" id="termForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nueva condicion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Dias (separados por coma)</label>
            <input type="text" name="days" class="form-control" placeholder="Ej: 0,30,60">
            <div class="form-text">Dejar vacio para contado.</div>
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
  const modalEl = document.getElementById('termModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('termForm');
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

  document.getElementById('btnNewTerm').addEventListener('click', () => {
    form.reset();
    setFormAction("{{ route('payment-terms.store') }}", 'POST');
    titleEl.textContent = 'Nueva condicion';
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/payment-terms/${id}/edit`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/payment-terms/${id}`, 'PUT');
          titleEl.textContent = 'Editar condicion';
          form.querySelector('[name="name"]').value = data.name ?? '';
          const days = Array.isArray(data.days) ? data.days.join(',') : '';
          form.querySelector('[name="days"]').value = days;
          modal.show();
        });
    });
  });
});
</script>
@endpush
