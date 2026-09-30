@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.portal.dashboard') }}">Mi Portal</a></li>
          <li class="breadcrumb-item active" aria-current="page">Mis Licencias y Ausencias</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Mis Solicitudes de Licencia</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.portal.calendar') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Mi Calendario</span>
      </a>
      <a href="{{ route('rrhh.portal.requests.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Nueva Solicitud</span>
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
      <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
      <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Listado de Mis Solicitudes --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Solicitud</th>
              <th>Tipo de Ausencia</th>
              <th>Período Solicitado</th>
              <th class="text-center">Días Computados</th>
              <th>Motivo Informado</th>
              <th class="text-center">Comprobante</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acción</th>
            </tr>
          </thead>
          <tbody>
            @forelse($requests as $req)
              <tr>
                <td class="ps-3">
                  <span class="badge bg-light text-dark border font-monospace">#{{ $req->id }}</span>
                  <div class="small text-muted">{{ $req->created_at->format('d/m/Y H:i') }}</div>
                </td>
                <td>
                  <span class="badge" style="background-color: {{ $req->leaveType->color ?? '#0d6efd' }}; color: #fff;">
                    {{ $req->leaveType->name }}
                  </span>
                  @if($req->is_half_day)
                    <span class="badge bg-secondary text-white small ms-1">Medio Día</span>
                  @endif
                </td>
                <td>
                  <div class="fw-semibold text-dark">
                    <i class="fa-regular fa-calendar text-primary me-1"></i>
                    {{ \Carbon\Carbon::parse($req->start_date)->format('d/m/Y') }}
                    @if($req->start_date != $req->end_date)
                      al {{ \Carbon\Carbon::parse($req->end_date)->format('d/m/Y') }}
                    @endif
                  </div>
                </td>
                <td class="text-center">
                  <span class="badge bg-primary fs-6">{{ $req->days_requested }} días</span>
                  <div class="text-muted small">{{ $req->leaveType->counts_as_working_days ? 'Hábiles' : 'Corridos' }}</div>
                </td>
                <td class="small text-muted">{{ Str::limit($req->reason, 40) ?? '-' }}</td>
                <td class="text-center">
                  @if($req->attachment_path)
                    <span class="badge bg-light text-info border"><i class="fa-solid fa-paperclip me-1"></i>Adjunto</span>
                  @else
                    <span class="text-muted small">-</span>
                  @endif
                </td>
                <td class="text-center">
                  @switch($req->status)
                    @case('pending')
                      <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>Pendiente</span>
                      @break
                    @case('approved')
                      <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Aprobada</span>
                      @break
                    @case('rejected')
                      <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada</span>
                      @if($req->rejection_reason)
                        <div class="small text-danger mt-1">{{ Str::limit($req->rejection_reason, 25) }}</div>
                      @endif
                      @break
                    @case('cancelled')
                      <span class="badge bg-secondary">Cancelada</span>
                      @break
                  @endswitch
                </td>
                <td class="text-end pe-3">
                  @if($req->status === 'pending')
                    <form action="{{ route('rrhh.portal.requests.cancel', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de cancelar esta solicitud de ausencia?');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar Solicitud">
                        <i class="fa-solid fa-ban me-1"></i>Cancelar
                      </button>
                    </form>
                  @else
                    <span class="text-muted small">-</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                  <i class="fa-regular fa-folder-open fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  Aún no has registrado solicitudes de ausencias.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($requests->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $requests->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
