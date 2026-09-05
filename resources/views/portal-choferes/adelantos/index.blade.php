@extends('layouts.app')

@section('title', 'Portal Choferes - Mis Adelantos')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-3">
  <div class="title-block">
    <h1 class="h3 mb-1">Mis Adelantos</h1>
    <p class="text-muted mb-0">Solicita adelantos de pago y consulta el estado de tus solicitudes.</p>
  </div>
  <div>
    @if($isBlocked || $hasRequestedThisMonth)
      <button class="btn btn-primary" disabled title="No disponible en este momento">
        <i class="fa-solid fa-hand-holding-dollar me-1"></i> Solicitar Adelanto
      </button>
    @else
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestAdvanceModal">
        <i class="fa-solid fa-hand-holding-dollar me-1"></i> Solicitar Adelanto
      </button>
    @endif
  </div>
</div>

@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if($hasRequestedThisMonth && !$isBlocked)
  <div class="alert alert-info">
    <i class="fa-solid fa-circle-info me-1"></i> Ya has realizado una solicitud de adelanto durante este mes calendario.
  </div>
@endif

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 text-center">
      <thead>
        <tr>
          <th>Fecha Pedido</th>
          <th>Monto Pedido</th>
          <th>Comentario</th>
          <th>Estado</th>
          <th>Monto Aprobado</th>
          <th>Fecha Resolución</th>
          <th>Observaciones Admin</th>
          <th>Liquidado En</th>
        </tr>
      </thead>
      <tbody>
      @forelse($requests as $req)
        <tr>
          <td>{{ optional($req->fecha_pedido)->format('d/m/Y') }}</td>
          <td class="fw-bold">$ {{ number_format((float) $req->monto_pedido, 2, ',', '.') }}</td>
          <td>
            @if($req->comentario_chofer)
              <span class="text-muted small" title="{{ $req->comentario_chofer }}">{{ Str::limit($req->comentario_chofer, 30) }}</span>
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
              <div class="d-flex flex-column align-items-center gap-1">
                <span class="badge bg-info text-dark" title="Oferta original: $ {{ number_format((float) $req->monto_pedido, 2, ',', '.') }}"><i class="fa-solid fa-comments-dollar"></i> Contraoferta: $ {{ number_format((float) $req->monto_aprobado, 2, ',', '.') }}</span>
                <div class="d-flex gap-1">
                  <form method="POST" action="{{ route('portal-choferes.adelantos.accept-counter', $req) }}">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-success py-0 px-2 small" style="font-size: 0.75rem;">Aceptar</button>
                  </form>
                  <form method="POST" action="{{ route('portal-choferes.adelantos.reject-counter', $req) }}">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-danger py-0 px-2 small" style="font-size: 0.75rem;">Rechazar</button>
                  </form>
                </div>
              </div>
            @endif
          </td>
          <td class="fw-bold text-success">
            @if($req->estado === \App\Models\DriverAdvanceRequest::ESTADO_APROBADO || $req->estado === \App\Models\DriverAdvanceRequest::ESTADO_CONTRAOFERTADO)
              $ {{ number_format((float) $req->monto_aprobado, 2, ',', '.') }}
            @else
              -
            @endif
          </td>
          <td>{{ $req->fecha_resolucion ? $req->fecha_resolucion->format('d/m/Y H:i') : '-' }}</td>
          <td>
            @if($req->observaciones_admin)
              <span class="text-muted small" title="{{ $req->observaciones_admin }}">{{ Str::limit($req->observaciones_admin, 30) }}</span>
            @else
              -
            @endif
          </td>
          <td>
            @if($req->recibo_chofer_id)
              <a href="{{ route('portal-choferes.liquidaciones.show', $req->recibo_chofer_id) }}" class="badge bg-light text-dark border decoration-none">
                Recibo #{{ $req->recibo_chofer_id }}
              </a>
            @else
              <span class="text-muted small">Pendiente de descuento</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No has registrado solicitudes de adelanto todavía.</td>
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

{{-- MODAL SOLICITUD --}}
@if(!$isBlocked && !$hasRequestedThisMonth)
<div class="modal fade" id="requestAdvanceModal" tabindex="-1" aria-labelledby="requestAdvanceModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="requestAdvanceModalLabel"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Nueva Solicitud de Adelanto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="{{ route('portal-choferes.adelantos.store') }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Monto Solicitado *</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="monto_pedido" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
            </div>
            <div class="form-text">Indica el monto obligatorio que necesitas solicitar.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Comentario u observaciones (opcional)</label>
            <textarea name="comentario_chofer" class="form-control" rows="3" placeholder="Ej: Motivo del adelanto..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection
