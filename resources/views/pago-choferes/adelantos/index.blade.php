@extends('layouts.app')

@section('title', 'Pago a Choferes - Solicitudes de Adelanto')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-3">
  <div class="title-block">
    <h1 class="h3 mb-1">Solicitudes de Adelanto</h1>
    <p class="text-muted mb-0">Bandeja de entrada para revisar, aprobar, rechazar y realizar contraofertas de adelantos a choferes.</p>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="GET" class="card card-body mb-3">
  <div class="row g-2">
    <div class="col-md-3">
      <label class="form-label">Transportista</label>
      <select name="transportista_id" class="form-select no-select2">
        <option value="">Todos</option>
        @foreach($transportistas as $t)
          <option value="{{ $t->id }}" {{ (string)$t->id === ($filters['transportista_id'] ?? '') ? 'selected' : '' }}>{{ $t->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select no-select2">
        <option value="">Todos</option>
        <option value="pendiente" {{ ($filters['estado'] ?? '') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
        <option value="aprobado" {{ ($filters['estado'] ?? '') === 'aprobado' ? 'selected' : '' }}>Aprobado</option>
        <option value="rechazado" {{ ($filters['estado'] ?? '') === 'rechazado' ? 'selected' : '' }}>Rechazado</option>
        <option value="contraofertado" {{ ($filters['estado'] ?? '') === 'contraofertado' ? 'selected' : '' }}>Contraofertado</option>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <div class="w-100 d-flex gap-1">
        <button class="btn btn-primary flex-grow-1" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.adelantos.index') }}">Limpiar</a>
      </div>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 text-center">
      <thead>
        <tr>
          <th>Chofer / Transportista</th>
          <th>Fecha Pedido</th>
          <th>Monto Pedido</th>
          <th>Comentario Chofer</th>
          <th>Estado</th>
          <th>Monto Aprobado</th>
          <th>Fecha Res.</th>
          <th>Resuelto Por</th>
          <th>Liquidado En</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      @forelse($requests as $req)
        <tr class="{{ $req->estado === 'pendiente' ? 'table-warning-row' : '' }}">
          <td>
            <strong>{{ $req->transportista->name }}</strong>
            @if($req->transportista->isNewDriver())
              <span class="new-driver-badge" title="Nuevo chofer ({{ $req->transportista->days_since_hired }} días dado de alta)"><i class="fa-solid fa-user-plus"></i> Nuevo ({{ $req->transportista->days_since_hired }} d)</span>
            @endif
          </td>
          <td>{{ optional($req->fecha_pedido)->format('d/m/Y') }}</td>
          <td class="fw-bold">$ {{ number_format((float) $req->monto_pedido, 2, ',', '.') }}</td>
          <td>
            @if($req->comentario_chofer)
              <span class="text-muted small" title="{{ $req->comentario_chofer }}">{{ Str::limit($req->comentario_chofer, 25) }}</span>
            @else
              -
            @endif
          </td>
          <td>
            @if($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_PENDIENTE)
              <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock"></i> Pendiente</span>
            @elseif($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_APROBADO)
              <span class="badge bg-success"><i class="fa-solid fa-circle-check"></i> Aprobado</span>
            @elseif($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_RECHAZADO)
              <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark"></i> Rechazado</span>
            @elseif($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_CONTRAOFERTADO)
              <span class="badge bg-info text-dark" title="Esperando respuesta del chofer"><i class="fa-solid fa-comments-dollar"></i> Contraofertado</span>
            @endif
          </td>
          <td class="fw-bold">
            @if($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_APROBADO || $req->estado === \App\Models\DriverAdvanceRequest::ESTADO_CONTRAOFERTADO)
              $ {{ number_format((float) $req->monto_aprobado, 2, ',', '.') }}
            @else
              -
            @endif
          </td>
          <td>{{ $req->fecha_resolucion ? $req->fecha_resolucion->format('d/m/Y H:i') : '-' }}</td>
          <td>{{ $req->resolvedBy ? $req->resolvedBy->name : '-' }}</td>
          <td>
            @if($req->recibo_chofer_id)
              <span class="badge bg-light text-dark border">Recibo #{{ $req->recibo_chofer_id }}</span>
            @else
              <span class="text-muted small">Pendiente de descuento</span>
            @endif
          </td>
          <td class="text-end">
            @if($req->recibo_chofer_id === null && ($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_PENDIENTE || $req->estado === \App\Models\DriverAdvanceRequest::ESTADO_CONTRAOFERTADO || $req->estado === \App\Models\DriverAdvanceRequest::ESTADO_APROBADO))
              <button class="btn btn-sm btn-outline-primary"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#resolveModal"
                      data-req-id="{{ $req->id }}"
                      data-req-name="{{ $req->transportista->name }}"
                      data-req-amount="{{ $req->monto_pedido }}"
                      data-req-comment="{{ $req->comentario_chofer ?? 'Sin comentarios.' }}"
                      data-req-state="{{ $req->estado }}"
                      data-req-approved="{{ $req->monto_aprobado }}">
                Resolver
              </button>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="text-center text-muted py-4">No se encontraron solicitudes de adelantos en la bandeja.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @if($requests->hasPages())
    <div class="card-footer bg-white border-top-0 pt-3">
      {{ $requests->links() }}
    </div>
  @endif
</div>

{{-- MODAL RESOLUCIÓN --}}
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resolveModalLabel"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Resolver Solicitud de Adelanto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="resolveForm">
        @csrf
        <div class="modal-body">
          <div class="mb-3 bg-light p-3 rounded">
            <div><strong>Chofer:</strong> <span id="modalReqName"></span></div>
            <div><strong>Monto Solicitado:</strong> <span class="fw-bold text-primary" id="modalReqAmount"></span></div>
            <div class="mt-2"><strong>Comentario del Chofer:</strong></div>
            <p class="text-muted small mb-0" id="modalReqComment"></p>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Resolución</label>
            <select name="action" class="form-select no-select2" id="modalResolveAction" required>
              <option value="aprobar">Aprobar por el monto total solicitado</option>
              <option value="contraofertar">Ofrecer contraoferta de monto menor</option>
              <option value="rechazar">Rechazar solicitud</option>
            </select>
          </div>

          <div class="mb-3 d-none" id="contraofertaGroup">
            <label class="form-label fw-bold text-info">Monto Contraoferta *</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="monto_contraoferta" class="form-control" placeholder="0.00" step="0.01" min="0.01" id="montoContraofertaInput">
            </div>
            <div class="form-text text-info">Indica el monto menor ofrecido. El chofer podrá aceptar o rechazar esta oferta.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Observaciones / Motivos (opcional)</label>
            <textarea name="observaciones_admin" class="form-control" rows="3" placeholder="Comentarios u observaciones para el chofer..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Resolución</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const resolveModalEl = document.getElementById('resolveModal');
    const resolveForm = document.getElementById('resolveForm');
    const modalReqName = document.getElementById('modalReqName');
    const modalReqAmount = document.getElementById('modalReqAmount');
    const modalReqComment = document.getElementById('modalReqComment');
    const modalResolveAction = document.getElementById('modalResolveAction');
    const contraofertaGroup = document.getElementById('contraofertaGroup');
    const montoContraofertaInput = document.getElementById('montoContraofertaInput');

    if (resolveModalEl) {
      resolveModalEl.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const id = button.dataset.reqId;
        const name = button.dataset.reqName;
        const amount = parseFloat(button.dataset.reqAmount);
        const comment = button.dataset.reqComment;
        const state = button.dataset.reqState;
        const approvedAmount = parseFloat(button.dataset.reqApproved || 0);

        // Configurar formulario
        resolveForm.action = `/pago-choferes/adelantos/${id}/resolver`;

        // Datos del chofer
        modalReqName.textContent = name;
        modalReqAmount.textContent = `$ ${amount.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        modalReqComment.textContent = comment;

        // Resetear campos
        modalResolveAction.value = 'aprobar';
        contraofertaGroup.classList.add('d-none');
        montoContraofertaInput.removeAttribute('required');
        montoContraofertaInput.value = '';

        if (state === 'contraofertado') {
          modalResolveAction.value = 'contraofertar';
          contraofertaGroup.classList.remove('d-none');
          montoContraofertaInput.setAttribute('required', 'required');
          montoContraofertaInput.value = approvedAmount > 0 ? approvedAmount : '';
        }
      });
    }

    if (modalResolveAction) {
      modalResolveAction.addEventListener('change', (e) => {
        if (e.target.value === 'contraofertar') {
          contraofertaGroup.classList.remove('d-none');
          montoContraofertaInput.setAttribute('required', 'required');
          montoContraofertaInput.focus();
        } else {
          contraofertaGroup.classList.add('d-none');
          montoContraofertaInput.removeAttribute('required');
          montoContraofertaInput.value = '';
        }
      });
    }
  });
</script>
@endpush
@endsection
