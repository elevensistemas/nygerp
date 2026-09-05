@extends('layouts.app')

@section('title', 'Motivos de no entrega')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Motivos de no entrega</h1>
      <p class="text-muted mb-0">Administra las opciones que ve el transportista cuando no puede entregar.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#reasonModal">
        <i class="fa-solid fa-plus me-1"></i> Nuevo motivo
      </button>
      <a class="btn btn-outline-secondary" href="{{ route('traffic.dashboard') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a tráfico
      </a>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white">
      <h2 class="h5 mb-0">Listado</h2>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Orden</th>
            <th>Nombre</th>
            <th>Color</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reasons as $reason)
            <tr>
              <td><span class="badge text-bg-light">{{ $reason->order_index }}</span></td>
              <td>{{ $reason->name }}</td>
              <td>
                <span class="d-inline-flex align-items-center gap-2">
                  <span style="display:inline-block;width:18px;height:18px;border-radius:4px;background: {{ $reason->color }}; border: 1px solid #dee2e6;"></span>
                  <span class="text-muted">{{ $reason->color }}</span>
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary"
                        type="button"
                        data-reason='@json($reason)'
                        data-bs-toggle="modal"
                        data-bs-target="#reasonModal">
                  Editar
                </button>
                <form class="d-inline-block ms-1"
                      method="POST"
                      action="{{ route('traffic.delivery-reasons.destroy', $reason) }}"
                      data-confirm="¿Eliminar motivo?">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="text-center text-muted py-4">No hay motivos cargados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      {{ $reasons->withQueryString()->links() }}
    </div>
  </div>

  <div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="reasonModalLabel">Nuevo motivo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" id="reasonForm" action="{{ route('traffic.delivery-reasons.store') }}">
          @csrf
          <input type="hidden" name="_method" value="POST" id="reasonMethod">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Nombre *</label>
              <input class="form-control @error('name') is-invalid @enderror"
                     name="name"
                     id="reasonName"
                     value="{{ old('name') }}"
                     required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Orden *</label>
              <input class="form-control @error('order_index') is-invalid @enderror"
                     type="number"
                     min="0"
                     name="order_index"
                     id="reasonOrder"
                     value="{{ old('order_index', 0) }}"
                     required>
              @error('order_index')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text text-muted">Más bajo = aparece antes en el listado.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Color *</label>
              <input class="form-control form-control-color @error('color') is-invalid @enderror"
                     type="color"
                     name="color"
                     id="reasonColor"
                     value="{{ old('color', '#6c757d') }}"
                     required>
              @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
@endsection

@push('scripts')
  <script>
    (function () {
      const modalEl = document.getElementById('reasonModal');
      if (!modalEl) return;
      const form = document.getElementById('reasonForm');
      const methodInput = document.getElementById('reasonMethod');
      const nameInput = document.getElementById('reasonName');
      const orderInput = document.getElementById('reasonOrder');
      const colorInput = document.getElementById('reasonColor');
      const titleEl = document.getElementById('reasonModalLabel');

      modalEl.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const reason = button?.dataset?.reason ? JSON.parse(button.dataset.reason) : null;

        if (reason) {
          titleEl.textContent = 'Editar motivo';
          form.action = "{{ route('traffic.delivery-reasons.index') }}/" + reason.id;
          methodInput.value = 'PUT';
          nameInput.value = reason.name || '';
          orderInput.value = reason.order_index ?? 0;
          colorInput.value = reason.color || '#6c757d';
        } else {
          titleEl.textContent = 'Nuevo motivo';
          form.action = "{{ route('traffic.delivery-reasons.store') }}";
          methodInput.value = 'POST';
          nameInput.value = '';
          orderInput.value = 0;
          colorInput.value = '#6c757d';
        }
      });
    })();
  </script>
@endpush
