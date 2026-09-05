@extends('layouts.app')

@section('title', 'Bancos')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 mb-1">Bancos</h1>
    <p class="text-muted mb-0">Mantenimiento de bancos para datos bancarios de transportistas.</p>
  </div>
  <div class="d-flex gap-2">
    <form method="GET" action="{{ route('traffic.banks.index') }}" class="d-flex">
      <input class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Buscar banco">
      <button class="btn btn-outline-secondary ms-2">Buscar</button>
    </form>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#bankModal" id="btnNewBank">
      Nuevo banco
    </button>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Nombre</th>
          <th class="text-center">Activo</th>
          <th class="text-center">Transportistas</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($banks as $bank)
          <tr>
            <td>{{ $bank->name }}</td>
            <td class="text-center">
              <span class="badge {{ $bank->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $bank->is_active ? 'Si' : 'No' }}</span>
            </td>
            <td class="text-center">{{ (int) $bank->transportistas_count }}</td>
            <td class="text-end">
              <button
                class="btn btn-sm btn-outline-primary btn-edit-bank"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#bankModal"
                data-bank='@json($bank)'>
                Editar
              </button>
              <form method="POST" action="{{ route('traffic.banks.destroy', $bank) }}" class="d-inline" data-confirm="¿Eliminar banco?">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">No hay bancos cargados.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body">
    {{ $banks->links() }}
  </div>
</div>

<div class="modal fade" id="bankModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('traffic.banks.store') }}" id="bankForm">
        @csrf
        <input type="hidden" name="_method" value="POST" id="bankMethod">
        <div class="modal-header">
          <h5 class="modal-title" id="bankModalTitle">Nuevo banco</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="name" id="bankName" required>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="bankIsActive" value="1" checked>
            <label class="form-check-label" for="bankIsActive">Activo</label>
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
  (function () {
    const form = document.getElementById('bankForm');
    const methodInput = document.getElementById('bankMethod');
    const titleEl = document.getElementById('bankModalTitle');
    const nameEl = document.getElementById('bankName');
    const isActiveEl = document.getElementById('bankIsActive');
    const newBtn = document.getElementById('btnNewBank');

    if (!form || !methodInput || !titleEl || !nameEl || !isActiveEl) return;

    const resetForm = () => {
      form.action = "{{ route('traffic.banks.store') }}";
      methodInput.value = 'POST';
      titleEl.textContent = 'Nuevo banco';
      nameEl.value = '';
      isActiveEl.checked = true;
    };

    if (newBtn) {
      newBtn.addEventListener('click', resetForm);
    }

    document.querySelectorAll('.btn-edit-bank').forEach((btn) => {
      btn.addEventListener('click', () => {
        const bank = btn.dataset.bank ? JSON.parse(btn.dataset.bank) : null;
        if (!bank) return;
        form.action = "{{ route('traffic.banks.index') }}/" + bank.id;
        methodInput.value = 'PUT';
        titleEl.textContent = 'Editar banco';
        nameEl.value = bank.name || '';
        isActiveEl.checked = !!bank.is_active;
      });
    });
  })();
</script>
@endpush

