@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-requests.index') }}">Solicitudes</a></li>
          <li class="breadcrumb-item active" aria-current="page">Reportes de Ausencias</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Reportes de Ausencias y Licencias</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.leave-requests.export-csv', request()->query()) }}" class="btn btn-success d-flex align-items-center gap-2">
        <i class="fa-solid fa-file-csv"></i>
        <span>Exportar CSV</span>
      </a>
      <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver a Solicitudes
      </a>
    </div>
  </div>

  {{-- KPIs de Reporte --}}
  <div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Total Solicitudes</div>
          <div class="fs-4 fw-bold text-dark">{{ $stats['total'] ?? $requests->count() }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Días Aprobados (Tomados)</div>
          <div class="fs-4 fw-bold text-success">{{ $stats['approved_days'] ?? 0 }} días</div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Pendientes de Resolución</div>
          <div class="fs-4 fw-bold text-warning">{{ $stats['pending_count'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Rechazadas / Canceladas</div>
          <div class="fs-4 fw-bold text-secondary">{{ $stats['rejected_cancelled_count'] ?? 0 }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filtros del Reporte --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.leave-requests.reports') }}" class="row g-2 align-items-center">
        <div class="col-md-3 col-12">
          <label class="small text-muted mb-1">Empleado</label>
          <select name="employee_id" class="form-select">
            <option value="">Todos los Empleados</option>
            @foreach($employees as $emp)
              <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }} ({{ $emp->file_number }})</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 col-6">
          <label class="small text-muted mb-1">Área / Depto</label>
          <select name="department_id" class="form-select">
            <option value="">Todas las Áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 col-6">
          <label class="small text-muted mb-1">Tipo de Ausencia</label>
          <select name="leave_type_id" class="form-select">
            <option value="">Todos los Tipos</option>
            @foreach($leaveTypes as $lt)
              <option value="{{ $lt->id }}" {{ request('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 col-6">
          <label class="small text-muted mb-1">Desde</label>
          <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>

        <div class="col-md-2 col-6">
          <label class="small text-muted mb-1">Hasta</label>
          <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>

        <div class="col-md-1 col-12 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100" title="Aplicar filtros"><i class="fa-solid fa-filter"></i></button>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de Resultados --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">ID</th>
              <th>Colaborador</th>
              <th>Área</th>
              <th>Tipo de Ausencia</th>
              <th>Desde</th>
              <th>Hasta</th>
              <th class="text-center">Días</th>
              <th class="text-center">Estado</th>
              <th>Motivo</th>
            </tr>
          </thead>
          <tbody>
            @forelse($requests as $req)
              <tr>
                <td class="ps-3 font-monospace small">#{{ $req->id }}</td>
                <td>
                  <div class="fw-bold text-dark">{{ $req->employee->full_name }}</div>
                  <span class="badge bg-light text-muted border small">{{ $req->employee->file_number }}</span>
                </td>
                <td>{{ $req->employee->department->name ?? 'Sin Área' }}</td>
                <td>
                  <span class="badge" style="background-color: {{ $req->leaveType->color ?? '#0d6efd' }}; color: #fff;">{{ $req->leaveType->name }}</span>
                </td>
                <td>{{ \Carbon\Carbon::parse($req->start_date)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($req->end_date)->format('d/m/Y') }}</td>
                <td class="text-center fw-bold text-primary">{{ $req->days_requested }}</td>
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
                <td class="small text-muted">{{ Str::limit($req->reason, 40) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                  No hay registros para los criterios de búsqueda especificados.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
