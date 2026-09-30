@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-balances.index') }}">Saldos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Saldo #{{ $balance->id }}</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Gestión de Saldo: {{ $balance->employee->full_name }}</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.leave-balances.index', ['year' => $balance->period_year]) }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver a Saldos
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
    {{-- Resumen del Saldo --}}
    <div class="col-lg-4 col-12">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i>Datos del Colaborador</h6>
        </div>
        <div class="card-body p-3">
          <div class="fw-bold text-dark fs-5">{{ $balance->employee->full_name }}</div>
          <div class="text-muted small mb-2">Legajo: {{ $balance->employee->file_number }} | DNI: {{ $balance->employee->dni }}</div>
          <div class="text-muted small">Área: {{ $balance->employee->department->name ?? 'Sin asignar' }}</div>
          <div class="text-muted small">Puesto: {{ $balance->employee->position->name ?? 'Sin asignar' }}</div>
          <div class="text-muted small">Convenio: {{ $balance->employee->agreement->name ?? 'Sin convenio' }}</div>
          <hr class="my-2">
          <div class="d-flex justify-content-between">
            <span class="text-muted">Tipo de Licencia:</span>
            <span class="badge" style="background-color: {{ $balance->leaveType->color ?? '#0d6efd' }}; color: #fff;">{{ $balance->leaveType->name }}</span>
          </div>
          <div class="d-flex justify-content-between mt-2">
            <span class="text-muted">Año / Período:</span>
            <span class="fw-bold font-monospace">{{ $balance->period_year }}</span>
          </div>
        </div>
      </div>

      {{-- Estado de la Cuenta de Días --}}
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calculator text-primary me-2"></i>Desglose de Días</h6>
        </div>
        <div class="card-body p-3">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Días Otorgados / Base:</span>
            <span class="fw-semibold">{{ $balance->assigned_days }} d</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Días Trasladados:</span>
            <span class="fw-semibold text-info">+{{ $balance->transferred_days }} d</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Ajustes Positivos:</span>
            <span class="fw-semibold text-success">+{{ $balance->positive_adjustments }} d</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Ajustes Negativos:</span>
            <span class="fw-semibold text-danger">-{{ $balance->negative_adjustments }} d</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Días Aprobados (Consumidos):</span>
            <span class="fw-semibold text-dark">-{{ $balance->used_days }} d</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Días Pendientes (Comprometidos):</span>
            <span class="fw-semibold text-warning">-{{ $balance->pending_days }} d</span>
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark fs-6">Saldo Disponible:</span>
            <span class="badge bg-primary fs-5">{{ $balance->available_days }} días</span>
          </div>
        </div>
      </div>

      {{-- Formulario para Ajuste Manual --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i>Nuevo Ajuste Manual</h6>
        </div>
        <div class="card-body p-3">
          <form action="{{ route('rrhh.leave-balances.adjust', $balance) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold">Tipo de Ajuste <span class="text-danger">*</span></label>
              <select name="type" class="form-select" required>
                <option value="positive">Ajuste Positivo (+) Agregar Días</option>
                <option value="negative">Ajuste Negativo (-) Descontar Días</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Cantidad de Días <span class="text-danger">*</span></label>
              <input type="number" name="days" class="form-control" min="0.5" step="0.5" max="100" required placeholder="ej. 2 o 5">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Motivo Obligatorio <span class="text-danger">*</span></label>
              <textarea name="reason" class="form-control" rows="3" required placeholder="Justificación administrativa detallada para la auditoría..." minlength="5"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold">
              <i class="fa-solid fa-floppy-disk me-1"></i>Registrar Ajuste Manual
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- Columna Derecha: Historial de Ajustes y Solicitudes del Período --}}
    <div class="col-lg-8 col-12">
      {{-- Historial de Ajustes Manuales con Auditoría --}}
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Historial de Ajustes Manuales (Auditoría)</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Fecha y Hora</th>
                  <th>Tipo / Cantidad</th>
                  <th class="text-center">Saldo Ant.</th>
                  <th class="text-center">Saldo Post.</th>
                  <th>Motivo</th>
                  <th>Usuario</th>
                </tr>
              </thead>
              <tbody>
                @forelse($adjustments as $adj)
                  <tr>
                    <td class="ps-3 font-monospace small text-muted">{{ $adj->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                      @if($adj->adjustment_type === 'positive')
                        <span class="badge bg-success">+{{ $adj->days }} días</span>
                      @else
                        <span class="badge bg-danger">-{{ $adj->days }} días</span>
                      @endif
                    </td>
                    <td class="text-center font-monospace">{{ $adj->previous_balance }}d</td>
                    <td class="text-center font-monospace fw-bold">{{ $adj->new_balance }}d</td>
                    <td class="small">{{ $adj->reason }}</td>
                    <td>
                      <div class="fw-semibold small text-dark">{{ $adj->user->name ?? 'Admin' }}</div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center py-3 text-muted">No se registran ajustes manuales para este período.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Solicitudes de Ausencia del Período --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Solicitudes de Licencia en {{ $balance->period_year }}</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Solicitud</th>
                  <th>Fechas</th>
                  <th class="text-center">Días</th>
                  <th class="text-center">Estado</th>
                  <th class="text-end pe-3">Acción</th>
                </tr>
              </thead>
              <tbody>
                @forelse($leaveRequests as $req)
                  <tr>
                    <td class="ps-3 font-monospace small">#{{ $req->id }}</td>
                    <td>
                      {{ \Carbon\Carbon::parse($req->start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($req->end_date)->format('d/m/Y') }}
                    </td>
                    <td class="text-center fw-bold text-primary">{{ $req->days_requested }}d</td>
                    <td class="text-center">
                      @switch($req->status)
                        @case('pending')
                          <span class="badge bg-warning text-dark">Pendiente</span>
                          @break
                        @case('approved')
                          <span class="badge bg-success">Aprobada</span>
                          @break
                        @case('rejected')
                          <span class="badge bg-danger">Rechazada</span>
                          @break
                        @case('cancelled')
                          <span class="badge bg-secondary">Cancelada</span>
                          @break
                      @endswitch
                    </td>
                    <td class="text-end pe-3">
                      <a href="{{ route('rrhh.leave-requests.show', $req) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-eye"></i>
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center py-3 text-muted">No se registran solicitudes para este tipo en este período.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
