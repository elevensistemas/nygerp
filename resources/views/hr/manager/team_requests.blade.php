@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
          <li class="breadcrumb-item active" aria-current="page">Gestión de Equipo</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i>Solicitudes de Mi Equipo</h3>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ asset('manuales/instructivo_rrhh.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo completo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
        <i class="fa-solid fa-circle-question fs-5"></i>
      </a>
      <a href="{{ route('rrhh.manager.calendar') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Calendario del Equipo</span>
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

  {{-- KPIs del Equipo --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Colaboradores a Cargo</div>
          <div class="fs-4 fw-bold text-dark">{{ $stats['team_count'] ?? $directReports->count() }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Pendientes de Mi Aprobación</div>
          <div class="fs-4 fw-bold text-warning">{{ $stats['pending_count'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Ausentes Hoy en Mi Equipo</div>
          <div class="fs-4 fw-bold text-info">{{ $stats['absent_today_count'] ?? 0 }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Listado de Solicitudes del Equipo --}}
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom">
      <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-inbox text-primary me-2"></i>Bandeja de Aprobaciones</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Solicitud</th>
              <th>Colaborador</th>
              <th>Tipo de Ausencia</th>
              <th>Período</th>
              <th class="text-center">Días</th>
              <th>Motivo</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acción</th>
            </tr>
          </thead>
          <tbody>
            @forelse($requests as $req)
              <tr>
                <td class="ps-3">
                  <span class="badge bg-light text-dark border font-monospace">#{{ $req->id }}</span>
                  <div class="small text-muted">{{ $req->created_at->format('d/m/Y') }}</div>
                </td>
                <td>
                  <div class="fw-bold text-dark">{{ $req->employee->full_name }}</div>
                  <span class="badge bg-light text-muted border small">{{ $req->employee->file_number }}</span>
                </td>
                <td>
                  <span class="badge" style="background-color: {{ $req->leaveType->color ?? '#0d6efd' }}; color: #fff;">
                    {{ $req->leaveType->name }}
                  </span>
                  @if($req->is_half_day)
                    <span class="badge bg-secondary text-white small">1/2 Día</span>
                  @endif
                </td>
                <td>
                  <div class="fw-semibold text-dark">
                    {{ \Carbon\Carbon::parse($req->start_date)->format('d/m/Y') }}
                    @if($req->start_date != $req->end_date)
                      al {{ \Carbon\Carbon::parse($req->end_date)->format('d/m/Y') }}
                    @endif
                  </div>
                </td>
                <td class="text-center fw-bold text-primary">{{ $req->days_requested }}d</td>
                <td class="small text-muted">{{ Str::limit($req->reason, 45) ?? '-' }}</td>
                <td class="text-center">
                  @switch($req->status)
                    @case('pendiente')
                    @case('pending')
                      <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>Pendiente</span>
                      @break
                    @case('aprobada')
                    @case('approved')
                      <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Aprobada</span>
                      @break
                    @case('rechazada')
                    @case('rejected')
                      <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada</span>
                      @break
                    @case('cancelada')
                    @case('cancelled')
                      <span class="badge bg-secondary">Cancelada</span>
                      @break
                    @default
                      <span class="badge bg-light text-dark">{{ ucfirst($req->status) }}</span>
                  @endswitch
                </td>
                <td class="text-end pe-3">
                  @if(in_array($req->status, ['pendiente', 'pending']))
                    <div class="btn-group btn-group-sm">
                      <form action="{{ route('rrhh.manager.requests.approve', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Aprobar solicitud de {{ $req->employee->full_name }}?');">
                        @csrf
                        <button type="submit" class="btn btn-success" title="Aprobar Solicitud">
                          <i class="fa-solid fa-check me-1"></i>Aprobar
                        </button>
                      </form>
                      <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $req->id }}" title="Rechazar">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    </div>

                    {{-- Modal Rechazo Manager --}}
                    <div class="modal fade text-start" id="rejectModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{ route('rrhh.manager.requests.reject', $req) }}" method="POST">
                            @csrf
                            <div class="modal-header bg-danger text-white">
                              <h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>Rechazar Solicitud #{{ $req->id }}</h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <p class="text-muted">Por favor indique el motivo del rechazo para {{ $req->employee->full_name }}:</p>
                              <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo del Rechazo <span class="text-danger">*</span></label>
                                <textarea name="comments" class="form-control" rows="3" required placeholder="Justificación requerida..." minlength="5"></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                              <button type="submit" class="btn btn-danger"><i class="fa-solid fa-ban me-1"></i>Rechazar Solicitud</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  @else
                    <span class="text-muted small">Resuelta</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                  <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  No hay solicitudes pendientes o registradas en tu equipo.
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
