@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-requests.index') }}">Solicitudes</a></li>
          <li class="breadcrumb-item active" aria-current="page">Solicitud #{{ $leaveRequest->id }}</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark">
        <i class="fa-solid fa-file-invoice text-primary me-2"></i>Detalle de Solicitud #{{ $leaveRequest->id }}
      </h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver al Listado
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

  <div class="row g-3">
    {{-- Columna Izquierda: Información de la Solicitud y Empleado --}}
    <div class="col-lg-8 col-12">
      {{-- Card de la Solicitud --}}
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 text-dark">
            <span class="badge" style="background-color: {{ $leaveRequest->leaveType->color ?? '#0d6efd' }}; color: #fff;">
              {{ $leaveRequest->leaveType->name }}
            </span>
          </h5>
          <div>
            @switch($leaveRequest->status)
              @case('pending')
                <span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-hourglass-half me-1"></i>Pendiente de Aprobación</span>
                @break
              @case('approved')
                <span class="badge bg-success fs-6"><i class="fa-solid fa-circle-check me-1"></i>Aprobada</span>
                @break
              @case('rejected')
                <span class="badge bg-danger fs-6"><i class="fa-solid fa-circle-xmark me-1"></i>Rechazada</span>
                @break
              @case('cancelled')
                <span class="badge bg-secondary fs-6"><i class="fa-solid fa-ban me-1"></i>Cancelada</span>
                @break
            @endswitch
          </div>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="text-muted small text-uppercase fw-bold">Colaborador</label>
              <div class="fw-bold fs-6 text-dark">{{ $leaveRequest->employee->full_name }}</div>
              <div class="text-muted small">Legajo: <span class="fw-semibold text-dark">{{ $leaveRequest->employee->file_number }}</span> | DNI: {{ $leaveRequest->employee->dni }}</div>
              <div class="text-muted small">Área: {{ $leaveRequest->employee->department->name ?? 'Sin asignar' }} | Puesto: {{ $leaveRequest->employee->position->name ?? 'Sin asignar' }}</div>
            </div>

            <div class="col-md-6">
              <label class="text-muted small text-uppercase fw-bold">Responsable Directo (Manager)</label>
              @if($leaveRequest->employee->manager)
                <div class="fw-semibold text-dark"><i class="fa-solid fa-user-tie text-muted me-1"></i>{{ $leaveRequest->employee->manager->full_name }}</div>
                <div class="text-muted small">{{ $leaveRequest->employee->manager->email ?? '' }}</div>
              @else
                <div class="text-muted small fst-italic">Sin manager asignado (Aprobación directa por RR. HH.)</div>
              @endif
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-3">
              <label class="text-muted small text-uppercase fw-bold">Fecha Desde</label>
              <div class="fw-bold text-dark fs-6">{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('d/m/Y') }}</div>
              <div class="text-muted small text-capitalize">{{ \Carbon\Carbon::parse($leaveRequest->start_date)->locale('es')->isoFormat('dddd') }}</div>
            </div>

            <div class="col-md-3">
              <label class="text-muted small text-uppercase fw-bold">Fecha Hasta</label>
              <div class="fw-bold text-dark fs-6">{{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('d/m/Y') }}</div>
              <div class="text-muted small text-capitalize">{{ \Carbon\Carbon::parse($leaveRequest->end_date)->locale('es')->isoFormat('dddd') }}</div>
            </div>

            <div class="col-md-3">
              <label class="text-muted small text-uppercase fw-bold">Días Computados</label>
              <div class="fw-bold text-primary fs-5">{{ $leaveRequest->days_requested }} días</div>
              <div class="badge bg-light text-dark border">{{ $leaveRequest->leaveType->counts_as_working_days ? 'Días Hábiles' : 'Días Corridos' }}</div>
            </div>

            <div class="col-md-3">
              <label class="text-muted small text-uppercase fw-bold">Modalidad</label>
              <div>
                @if($leaveRequest->is_half_day)
                  <span class="badge bg-info text-dark"><i class="fa-solid fa-circle-half-stroke me-1"></i>Medio Día (0.5)</span>
                @else
                  <span class="badge bg-light text-dark border">Jornada Completa</span>
                @endif
              </div>
            </div>

            <div class="col-12">
              <label class="text-muted small text-uppercase fw-bold">Motivo Informado por el Colaborador</label>
              <div class="p-3 bg-light rounded border text-dark">
                {{ $leaveRequest->reason ?? 'Sin motivo especificado.' }}
              </div>
            </div>

            @if($leaveRequest->attachment_path)
              <div class="col-12">
                <label class="text-muted small text-uppercase fw-bold">Documentación / Comprobante Adjunto</label>
                <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-paperclip text-primary fs-5"></i>
                    <div>
                      <div class="fw-semibold text-dark">{{ $leaveRequest->attachment_name ?? 'Comprobante_Adjunto' }}</div>
                      <div class="text-muted small">Almacenamiento seguro no público</div>
                    </div>
                  </div>
                  <a href="{{ route('rrhh.leave-requests.attachment', $leaveRequest) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-download me-1"></i>Descargar Comprobante
                  </a>
                </div>
              </div>
            @endif

            @if($leaveRequest->rejection_reason)
              <div class="col-12">
                <div class="alert alert-danger mb-0">
                  <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Motivo del Rechazo / Cancelación:</h6>
                  <p class="mb-0">{{ $leaveRequest->rejection_reason }}</p>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>

      {{-- Trazabilidad y Workflow de Aprobación --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-timeline text-primary me-2"></i>Historial de Trazabilidad y Aprobaciones</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Fecha y Hora</th>
                  <th>Nivel / Acción</th>
                  <th>Usuario</th>
                  <th>Comentario</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                @forelse($leaveRequest->approvalLogs as $log)
                  <tr>
                    <td class="ps-3 small font-monospace text-muted">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>
                      @switch($log->action)
                        @case('created')
                          <span class="badge bg-secondary"><i class="fa-solid fa-plus me-1"></i>Creada</span>
                          @break
                        @case('approved')
                          <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Aprobada ({{ $log->level }})</span>
                          @break
                        @case('rejected')
                          <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada ({{ $log->level }})</span>
                          @break
                        @case('cancelled')
                          <span class="badge bg-dark"><i class="fa-solid fa-ban me-1"></i>Cancelada</span>
                          @break
                        @default
                          <span class="badge bg-light text-dark border">{{ $log->action }}</span>
                      @endswitch
                    </td>
                    <td>
                      <div class="fw-semibold text-dark">{{ $log->user->name ?? 'Sistema' }}</div>
                      <div class="small text-muted">{{ $log->user->email ?? '' }}</div>
                    </td>
                    <td class="small">{{ $log->comments ?? '-' }}</td>
                    <td class="small text-muted font-monospace">{{ $log->ip_address ?? '-' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center py-3 text-muted">No hay registros de trazabilidad adicionales.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Columna Derecha: Estado de Saldo y Acciones Administrativas --}}
    <div class="col-lg-4 col-12">
      {{-- Card de Saldo del Empleado --}}
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Saldo en {{ $leaveRequest->leaveType->name }}</h6>
        </div>
        <div class="card-body p-3">
          @if($balance)
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Período / Año:</span>
              <span class="fw-bold text-dark">{{ $balance->period_year }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Días Asignados:</span>
              <span class="fw-semibold text-dark">{{ $balance->assigned_days }} días</span>
            </div>
            @if($balance->transferred_days > 0)
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Días Trasladados:</span>
                <span class="fw-semibold text-info">+{{ $balance->transferred_days }} días</span>
              </div>
            @endif
            @if($balance->positive_adjustments > 0)
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Ajustes Positivos:</span>
                <span class="fw-semibold text-success">+{{ $balance->positive_adjustments }} días</span>
              </div>
            @endif
            @if($balance->negative_adjustments > 0)
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Ajustes Negativos:</span>
                <span class="fw-semibold text-danger">-{{ $balance->negative_adjustments }} días</span>
              </div>
            @endif
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Días Aprobados (Consumidos):</span>
              <span class="fw-semibold text-dark">{{ $balance->used_days }} días</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Días Comprometidos (Pendientes):</span>
              <span class="fw-semibold text-warning">{{ $balance->pending_days }} días</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-bold text-dark">Saldo Disponible:</span>
              <span class="badge bg-primary fs-6">{{ $balance->available_days }} días</span>
            </div>
          @else
            <div class="text-muted small fst-italic py-2 text-center">
              Tipo sin descuento de saldo anual asignado.
            </div>
          @endif
        </div>
      </div>

      {{-- Card de Acciones --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-gavel text-primary me-2"></i>Gestión de la Solicitud</h6>
        </div>
        <div class="card-body p-3">
          @if($leaveRequest->status === 'pending')
            <div class="d-grid gap-2">
              {{-- Botón Aprobar --}}
              <form action="{{ route('rrhh.leave-requests.approve', $leaveRequest) }}" method="POST" onsubmit="return confirm('¿Confirmar la aprobación definitiva de esta solicitud de ausencia?');">
                @csrf
                <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                  <i class="fa-solid fa-check me-1"></i>Aprobar Solicitud
                </button>
              </form>

              {{-- Botón Rechazar (Abre Modal) --}}
              <button type="button" class="btn btn-danger w-100 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="fa-solid fa-xmark me-1"></i>Rechazar Solicitud
              </button>
            </div>
          @elseif($leaveRequest->status === 'approved')
            <div class="alert alert-success small mb-3">
              <i class="fa-solid fa-circle-check me-1"></i>Esta solicitud está aprobada.
            </div>
            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelModal">
              <i class="fa-solid fa-ban me-1"></i>Cancelación Administrativa
            </button>
          @else
            <div class="alert alert-secondary small mb-0 text-center">
              Solicitud finalizada con estado: <span class="fw-bold text-uppercase">{{ $leaveRequest->status }}</span>.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Modal Rechazar --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('rrhh.leave-requests.reject', $leaveRequest) }}" method="POST">
        @csrf
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="rejectModalLabel"><i class="fa-solid fa-triangle-exclamation me-2"></i>Rechazar Solicitud</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted">Por favor indique el motivo del rechazo. Este comentario quedará registrado en la auditoría y será notificado al colaborador.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Motivo del Rechazo <span class="text-danger">*</span></label>
            <textarea name="comments" class="form-control" rows="3" placeholder="Detalle el motivo obligatorio..." required minlength="5"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger"><i class="fa-solid fa-ban me-1"></i>Confirmar Rechazo</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal Cancelar Aprobada --}}
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('rrhh.leave-requests.cancel', $leaveRequest) }}" method="POST">
        @csrf
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title" id="cancelModalLabel"><i class="fa-solid fa-ban me-2"></i>Cancelación Administrativa</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted">Al cancelar una solicitud previamente aprobada, se reintegrarán automáticamente los <strong>{{ $leaveRequest->days_requested }} días</strong> al saldo disponible del colaborador.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Motivo de la Cancelación <span class="text-danger">*</span></label>
            <textarea name="comments" class="form-control" rows="3" placeholder="Indique la causa administrativa..." required minlength="5"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-dark"><i class="fa-solid fa-arrow-rotate-left me-1"></i>Confirmar Cancelación y Reintegro</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
