@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Solicitudes de Licencias</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plane-departure text-primary me-2"></i>Gestión de Licencias y Vacaciones</h3>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="{{ asset('manuales/instructivo_rrhh.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo completo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
        <i class="fa-solid fa-circle-question fs-5"></i>
      </a>
      <a href="{{ route('rrhh.leave-requests.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-plus"></i>
        <span>Asignar Vacaciones / Licencia</span>
      </a>
      <a href="{{ route('rrhh.leave-requests.reports') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Reportes y CSV</span>
      </a>
      <a href="{{ route('rrhh.calendar') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Calendario General</span>
      </a>
      <a href="{{ route('rrhh.leave-balances.index') }}" class="btn btn-outline-success d-flex align-items-center gap-2">
        <i class="fa-solid fa-scale-balanced"></i>
        <span>Saldos</span>
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

  {{-- Filtros Avanzados --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.leave-requests.index') }}" class="row g-2 align-items-center">
        <div class="col-md-3 col-12">
          <select name="employee_id" class="form-select select2-filter">
            <option value="">Todos los Empleados</option>
            @foreach($employees as $emp)
              <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                {{ $emp->last_name }}, {{ $emp->first_name }} ({{ $emp->file_number }})
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 col-6">
          <select name="leave_type_id" class="form-select">
            <option value="">Todos los Tipos</option>
            @foreach($leaveTypes as $lt)
              <option value="{{ $lt->id }}" {{ request('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 col-6">
          <select name="status" class="form-select">
            <option value="">Todos los Estados</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendientes</option>
            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Aprobadas</option>
            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rechazadas</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Canceladas</option>
          </select>
        </div>

        <div class="col-md-2 col-6">
          <select name="department_id" class="form-select">
            <option value="">Todas las Áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['employee_id', 'leave_type_id', 'status', 'department_id', 'date_from', 'date_to']))
            <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Listado de Solicitudes --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Solicitud</th>
              <th>Colaborador</th>
              <th>Tipo de Ausencia</th>
              <th>Período Solicitado</th>
              <th class="text-center">Días Computados</th>
              <th class="text-center">Comprobante</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($leaveRequests as $req)
              <tr>
                <td class="ps-3">
                  <span class="badge bg-light text-dark border font-monospace">#{{ $req->id }}</span>
                  <div class="small text-muted">{{ $req->created_at->format('d/m/Y H:i') }}</div>
                </td>
                <td>
                  <div class="fw-bold text-dark">{{ $req->employee->full_name }}</div>
                  <div class="small text-muted">
                    <span class="badge bg-light text-secondary border me-1">{{ $req->employee->file_number }}</span>
                    {{ $req->employee->department->name ?? 'Sin Área' }}
                  </div>
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
                    <i class="fa-regular fa-calendar-days text-primary me-1"></i>
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
                <td class="text-center">
                  @if($req->attachment_path)
                    <a href="{{ route('rrhh.leave-requests.attachment', $req) }}" target="_blank" class="btn btn-sm btn-outline-info" title="Ver documento adjunto">
                      <i class="fa-solid fa-paperclip"></i>
                    </a>
                  @else
                    <span class="text-muted small">-</span>
                  @endif
                </td>
                <td class="text-center">
                  @switch($req->status)
                    @case('pendiente_manager')
                      <span class="badge bg-warning text-dark"><i class="fa-solid fa-user-clock me-1"></i>Pend. Responsable</span>
                      @break
                    @case('pendiente_rrhh')
                    @case('pendiente')
                    @case('pending')
                      <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>Pendiente RRHH</span>
                      @break
                    @case('aprobada')
                    @case('approved')
                      <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Aprobada</span>
                      @break
                    @case('rechazada')
                    @case('rejected')
                      <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Rechazada</span>
                      @break
                    @case('cancelada')
                    @case('cancelled')
                      <span class="badge bg-secondary"><i class="fa-solid fa-ban me-1"></i>Cancelada</span>
                      @break
                    @default
                      <span class="badge bg-light text-dark border">{{ ucfirst($req->status) }}</span>
                  @endswitch
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.leave-requests.show', $req) }}" class="btn btn-outline-primary" title="Ver detalles y gestionar">
                      <i class="fa-solid fa-eye"></i> Detalle
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                  <i class="fa-regular fa-folder-open fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  No se encontraron solicitudes de ausencias que coincidan con los filtros.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($leaveRequests->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $leaveRequests->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
