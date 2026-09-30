@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Saldos de Ausencias</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Saldos y Cupos de Licencias</h3>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('rrhh.leave-requests.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-plus"></i>
        <span>Asignar Vacaciones / Licencia</span>
      </a>
      <a href="{{ route('rrhh.leave-policies.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-sliders"></i>
        <span>Políticas de Vacaciones</span>
      </a>
      <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-plane-departure"></i>
        <span>Ver Solicitudes</span>
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

  {{-- Filtros --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.leave-balances.index') }}" class="row g-2 align-items-center">
        <div class="col-md-2 col-6">
          <select name="year" class="form-select" onchange="this.form.submit()">
            @php $currYear = (int)date('Y'); @endphp
            @for($y = $currYear - 2; $y <= $currYear + 1; $y++)
              <option value="{{ $y }}" {{ request('year', $selectedYear ?? $currYear) == $y ? 'selected' : '' }}>Año {{ $y }}</option>
            @endfor
          </select>
        </div>

        <div class="col-md-3 col-6">
          <select name="employee_id" class="form-select">
            <option value="">Todos los Empleados</option>
            @foreach($employees as $emp)
              <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                {{ $emp->full_name }} ({{ $emp->file_number }})
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3 col-6">
          <select name="leave_type_id" class="form-select">
            <option value="">Todos los Tipos</option>
            @foreach($leaveTypes as $lt)
              <option value="{{ $lt->id }}" {{ request('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }}</option>
            @endforeach
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

        <div class="col-md-2 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['employee_id', 'leave_type_id', 'department_id']))
            <a href="{{ route('rrhh.leave-balances.index', ['year' => $selectedYear ?? date('Y')]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de Saldos --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Colaborador</th>
              <th>Área</th>
              <th>Tipo de Licencia</th>
              <th class="text-center">Período</th>
              <th class="text-center">Asignados</th>
              <th class="text-center">Trasladados</th>
              <th class="text-center">Ajustes (+/-)</th>
              <th class="text-center">Aprobados</th>
              <th class="text-center">Comprometidos</th>
              <th class="text-center">Disponible</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($balances as $bal)
              <tr>
                <td class="ps-3">
                  <div class="fw-bold text-dark">{{ $bal->employee->full_name }}</div>
                  <span class="badge bg-light text-muted border small">{{ $bal->employee->file_number }}</span>
                </td>
                <td>{{ $bal->employee->department->name ?? 'Sin Área' }}</td>
                <td>
                  <span class="badge" style="background-color: {{ $bal->leaveType->color ?? '#0d6efd' }}; color: #fff;">{{ $bal->leaveType->name }}</span>
                </td>
                <td class="text-center font-monospace">{{ $bal->period_year }}</td>
                <td class="text-center fw-semibold">{{ $bal->assigned_days }}d</td>
                <td class="text-center text-muted">{{ $bal->transferred_days > 0 ? '+'.$bal->transferred_days.'d' : '-' }}</td>
                <td class="text-center">
                  @php $netAdj = $bal->positive_adjustments - $bal->negative_adjustments; @endphp
                  @if($netAdj > 0)
                    <span class="badge bg-success">+{{ $netAdj }}d</span>
                  @elseif($netAdj < 0)
                    <span class="badge bg-danger">{{ $netAdj }}d</span>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td class="text-center text-danger fw-semibold">{{ $bal->used_days }}d</td>
                <td class="text-center text-warning fw-semibold">{{ $bal->pending_days > 0 ? $bal->pending_days.'d' : '-' }}</td>
                <td class="text-center">
                  <span class="badge bg-{{ $bal->available_days > 0 ? 'primary' : ($bal->available_days == 0 ? 'secondary' : 'danger') }} fs-6">
                    {{ $bal->available_days }} días
                  </span>
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.leave-requests.create', ['employee_id' => $bal->employee_id, 'leave_type_id' => $bal->leave_type_id]) }}" class="btn btn-outline-primary" title="Asignar esta licencia">
                      <i class="fa-solid fa-plus me-1"></i>Asignar
                    </a>
                    <a href="{{ route('rrhh.leave-balances.show', $bal) }}" class="btn btn-outline-secondary" title="Ver detalle y realizar ajuste">
                      <i class="fa-solid fa-sliders"></i>
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="text-center py-4 text-muted">
                  <i class="fa-solid fa-scale-balanced fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  No se registraron saldos con los filtros aplicados.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($balances->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $balances->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
