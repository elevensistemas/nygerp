@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
  {{-- Header Principal --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Calendario y Grilla de Ausencias</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark">
        <i class="fa-solid fa-chart-gantt text-primary me-2"></i>Planificación de Vacaciones y Licencias
      </h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.leave-types.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-sliders"></i>
        <span>Tipos de Licencia</span>
      </a>
      <a href="{{ route('rrhh.holidays.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-champagne-glasses"></i>
        <span>Feriados</span>
      </a>
      <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-plane-departure"></i>
        <span>Solicitudes</span>
      </a>
    </div>
  </div>

  {{-- Filtros y Selector de Vista --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.calendar') }}" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <div class="col-lg-3 col-md-4 col-6">
          <div class="input-group">
            <span class="input-group-text bg-light fw-semibold"><i class="fa-regular fa-calendar me-1"></i>Mes</span>
            <input type="month" name="month" class="form-control" value="{{ $selectedMonth ?? date('Y-m') }}" onchange="this.form.submit()">
          </div>
        </div>

        <div class="col-lg-3 col-md-4 col-6">
          <select name="position_id" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los Puestos</option>
            @foreach($allPositions as $pos)
              <option value="{{ $pos->id }}" {{ request('position_id') == $pos->id ? 'selected' : '' }}>
                {{ $pos->name }} {{ $pos->department ? '('.$pos->department->name.')' : '' }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-lg-2 col-md-4 col-6">
          <select name="department_id" class="form-select" onchange="this.form.submit()">
            <option value="">Todas las Áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-lg-2 col-md-4 col-6">
          <select name="leave_type_id" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los Tipos</option>
            @foreach($leaveTypes as $lt)
              <option value="{{ $lt->id }}" {{ request('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-lg-2 col-md-4 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['position_id', 'department_id', 'leave_type_id']))
            <a href="{{ route('rrhh.calendar', ['month' => $selectedMonth ?? date('Y-m'), 'view' => $viewMode]) }}" class="btn btn-outline-secondary" title="Limpiar filtros">
              <i class="fa-solid fa-rotate-left"></i>
            </a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Switcher de Vistas (Estilo ) --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div class="btn-group shadow-sm" role="group">
      <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['view' => 'matrix'])) }}" class="btn {{ $viewMode === 'matrix' ? 'btn-primary active' : 'btn-outline-primary' }}">
        <i class="fa-solid fa-chart-gantt me-1"></i>Grilla por Puesto
      </a>
      <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['view' => 'month'])) }}" class="btn {{ $viewMode === 'month' ? 'btn-primary active' : 'btn-outline-primary' }}">
        <i class="fa-regular fa-calendar-days me-1"></i>Calendario Mensual
      </a>
      <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['view' => 'list'])) }}" class="btn {{ $viewMode === 'list' ? 'btn-primary active' : 'btn-outline-primary' }}">
        <i class="fa-solid fa-list me-1"></i>Lista de Ausencias
      </a>
    </div>

    {{-- Navegación de Meses --}}
    @php
      $carbonMonth = \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth ?? date('Y-m'));
    @endphp
    <div class="d-flex align-items-center gap-2">
      <h5 class="fw-bold mb-0 text-dark text-capitalize me-2">
        <i class="fa-regular fa-calendar-check text-primary me-2"></i>{{ $carbonMonth->locale('es')->isoFormat('MMMM YYYY') }}
      </h5>
      <div class="btn-group btn-group-sm">
        <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['month' => $prevMonthDate->format('Y-m')])) }}" class="btn btn-outline-secondary">
          <i class="fa-solid fa-chevron-left"></i> Anterior
        </a>
        <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['month' => date('Y-m')])) }}" class="btn btn-outline-secondary">
          Hoy
        </a>
        <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['month' => $nextMonthDate->format('Y-m')])) }}" class="btn btn-outline-secondary">
          Siguiente <i class="fa-solid fa-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>

  {{-- Banner Alerta de Solapamientos --}}
  @if($totalOverlapsCount > 0)
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-3 p-3 rounded-3" role="alert">
      <i class="fa-solid fa-triangle-exclamation text-warning fs-3 me-3"></i>
      <div>
        <h6 class="fw-bold mb-1 text-dark">
          ⚠️ Solapamiento de Licencias / Vacaciones Detectado en {{ count($overlappingPositions) }} Puesto(s)
        </h6>
        <p class="mb-0 small text-secondary">
          Se identificaron <strong>{{ $totalOverlapsCount }} fecha(s)</strong> con 2 o más empleados ausentes simultáneamente en el mismo puesto ({{ implode(', ', $overlappingPositions) }}). Verifique la cobertura operativa en la grilla inferior.
        </p>
      </div>
    </div>
  @else
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-3 p-2 px-3 rounded-3" role="alert">
      <i class="fa-solid fa-circle-check text-success me-2 fs-5"></i>
      <span class="small fw-semibold text-dark">Sin solapamientos críticos detectados para los puestos seleccionados en este mes.</span>
    </div>
  @endif

  {{-- ==================== VISTA 1: GRILLA SAP / ODOO POR PUESTO ==================== --}}
  @if($viewMode === 'matrix')
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-table-cells text-primary me-2"></i>Matriz Mensual </h6>
          <small class="text-muted"></small>
        </div>
        {{-- Leyenda de Colores (Contraíble por defecto) --}}
        <div>
          <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 py-1 px-3 shadow-xs" 
                  type="button" 
                  data-bs-toggle="collapse" 
                  data-bs-target="#leaveLegendCollapse" 
                  aria-expanded="false" 
                  aria-controls="leaveLegendCollapse">
            <i class="fa-solid fa-palette text-warning"></i>
            <span class="fw-semibold">Ver Leyenda de Colores</span>
            <i class="fa-solid fa-chevron-down ms-1 small"></i>
          </button>
        </div>
      </div>

      {{-- Panel de Leyenda Expandible (Contraído por defecto) --}}
      <div class="collapse border-bottom bg-light" id="leaveLegendCollapse">
        <div class="p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold small text-dark"><i class="fa-solid fa-tags me-1 text-primary"></i>Referencia de Tipos de Licencias y Códigos en la Grilla:</span>
            <small class="text-muted">Haga clic en cualquier casilla de la grilla para cargar vacaciones o ausencias dinámicamente</small>
          </div>
          <div class="row g-2">
            @foreach($leaveTypes as $lt)
              <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                <div class="d-flex align-items-center gap-2 p-1.5 bg-white rounded border shadow-xs">
                  <span class="rounded-circle d-inline-block flex-shrink-0" style="width: 14px; height: 14px; background-color: {{ $lt->color ?? '#0d6efd' }};"></span>
                  <span class="badge bg-secondary font-monospace" style="font-size: 0.68rem;">{{ $lt->code }}</span>
                  <span class="text-dark fw-semibold small text-truncate" style="font-size: 0.78rem;" title="{{ $lt->name }}">{{ $lt->name }}</span>
                </div>
              </div>
            @endforeach
            <div class="col-lg-3 col-md-4 col-sm-6 col-12">
              <div class="d-flex align-items-center gap-2 p-1.5 bg-warning-subtle rounded border border-warning shadow-xs">
                <span class="badge bg-warning text-dark">⚠️</span>
                <span class="text-dark fw-semibold small" style="font-size: 0.78rem;">Solapamiento en Puesto</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 650px;">
          <table class="table table-bordered table-sm align-middle mb-0 matrix-table" style="font-size: 0.82rem; border-collapse: separate; border-spacing: 0;">
            <thead class="bg-light text-center sticky-top border-bottom" style="z-index: 10;">
              <tr>
                <th class="ps-3 text-start align-middle sticky-col text-dark fw-bold" style="min-width: 240px; position: sticky; left: 0; background-color: #f8fafc; z-index: 12; border-bottom: 2px solid #cbd5e1;">
                  Puesto / Colaborador
                </th>
                @foreach($daysInMonthPeriod as $day)
                  @php
                    $dStr = $day->toDateString();
                    $isWeekend = $day->isWeekend();
                    $isToday = $dStr === date('Y-m-d');
                    $hasHoliday = isset($holidaysByDate[$dStr]);
                  @endphp
                  <th class="text-center align-middle p-1" style="min-width: 38px; width: 38px; border-bottom: 2px solid #cbd5e1; {{ $isToday ? 'background-color: #fef3c7; color: #92400e; font-weight: bold;' : ($hasHoliday ? 'background-color: #fef2f2; color: #991b1b;' : ($isWeekend ? 'background-color: #f1f5f9; color: #64748b;' : 'background-color: #f8fafc; color: #334155;')) }}">
                    <div>{{ $day->format('d') }}</div>
                    <div style="font-size: 0.68rem; font-weight: 600; opacity: 0.85;">
                      {{ mb_substr($day->locale('es')->shortDayName, 0, 2) }}
                    </div>
                  </th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              {{-- Recorrer Puestos de Trabajo --}}
              @forelse($positions as $pos)
                @php
                  $posEmployees = $pos->employees;
                  $hasPosOverlaps = isset($positionOverlaps[$pos->id]);
                @endphp

                @if($posEmployees->count() > 0 || request()->filled('position_id'))
                  {{-- Fila Cabecera del Puesto --}}
                  <tr class="table-light">
                    <td class="ps-3 fw-bold text-dark sticky-col" style="position: sticky; left: 0; background-color: #f8f9fa; z-index: 5;">
                      <div class="d-flex align-items-center justify-content-between">
                        <div>
                          <i class="fa-solid fa-briefcase text-primary me-2"></i>
                          <span>{{ $pos->name }}</span>
                          <span class="badge bg-secondary rounded-pill ms-1" title="Cantidad de colaboradores en este puesto">{{ $posEmployees->count() }}</span>
                        </div>
                        @if($hasPosOverlaps)
                          <span class="badge bg-warning text-dark me-2" title="Solapamiento de licencias detectado en este puesto">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Solapamiento
                          </span>
                        @endif
                      </div>
                    </td>
                    @foreach($daysInMonthPeriod as $day)
                      @php
                        $dStr = $day->toDateString();
                        $overlap = $positionOverlaps[$pos->id][$dStr] ?? null;
                        $isWeekend = $day->isWeekend();
                      @endphp
                      <td class="text-center p-0 {{ $isWeekend ? 'bg-light' : '' }} {{ $overlap ? 'bg-warning-subtle border-warning' : '' }}">
                        @if($overlap)
                          <span class="badge bg-warning text-dark w-100 py-1" style="font-size: 0.68rem;" title="⚠️ {{ $overlap['count'] }} ausencias en este puesto el {{ $day->format('d/m/Y') }}">
                            {{ $overlap['count'] }} ⚠️
                          </span>
                        @endif
                      </td>
                    @endforeach
                  </tr>

                  {{-- Recorrer Empleados del Puesto --}}
                  @foreach($posEmployees as $emp)
                    <tr>
                      <td class="ps-3 sticky-col bg-white" style="position: sticky; left: 0; z-index: 5; white-space: nowrap;">
                        <div class="d-flex align-items-center gap-2 py-1 emp-quick-trigger"
                             style="cursor: pointer;"
                             onclick="openEmployeeQuickViewModal({{ json_encode([
                               'id' => $emp->id,
                               'full_name' => $emp->full_name,
                               'first_name' => $emp->first_name,
                               'last_name' => $emp->last_name,
                               'file_number' => $emp->file_number ?? '-',
                               'dni' => $emp->dni ?? '-',
                               'cuil' => $emp->cuil ?? '-',
                               'phone' => $emp->phone ?? '-',
                               'work_email' => $emp->work_email ?? $emp->personal_email ?? '-',
                               'birth_date' => $emp->birth_date ? $emp->birth_date->format('d/m/Y') : '-',
                               'position_name' => $emp->position->name ?? 'Sin Puesto',
                               'department_name' => $emp->department->name ?? 'Sin Área',
                               'branch_name' => $emp->branch->name ?? 'Sin Base',
                               'hire_date' => $emp->hire_date ? $emp->hire_date->format('d/m/Y') : '-',
                               'vacation_seniority_date' => $emp->vacation_seniority_date ? $emp->vacation_seniority_date->format('d/m/Y') : ($emp->hire_date ? $emp->hire_date->format('d/m/Y') . ' (Igual)' : '-'),
                               'seniority_formatted' => $emp->seniority_formatted,
                               'status' => $emp->status,
                               'avatar_url' => $emp->avatar_path ? asset('storage/' . $emp->avatar_path) : null,
                               'profile_url' => route('rrhh.employees.show', $emp->id),
                             ]) }})"
                             title="Clic para ver ficha rápida de {{ $emp->full_name }}">
                          <div class="avatar-circle bg-dark text-white fw-bold rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.70rem;">
                            {{ substr($emp->first_name, 0, 1) }}{{ substr($emp->last_name, 0, 1) }}
                          </div>
                          <span class="fw-bold text-dark text-truncate" style="max-width: 140px; font-size: 0.82rem;">{{ $emp->last_name }}, {{ $emp->first_name }}</span>
                          <span class="badge bg-light text-muted border font-monospace px-1" style="font-size: 0.68rem;">{{ $emp->file_number ?? '-' }}</span>
                        </div>
                      </td>

                      @foreach($daysInMonthPeriod as $day)
                        @php
                          $dStr = $day->toDateString();
                          $dayLeaves = $leavesByDate[$dStr] ?? [];
                          $empLeave = null;
                          foreach($dayLeaves as $l) {
                              if($l->employee_id == $emp->id) {
                                  $empLeave = $l;
                                  break;
                              }
                          }
                          $isWeekend = $day->isWeekend();
                          $hasHoliday = isset($holidaysByDate[$dStr]);
                          $isPosOverlap = isset($positionOverlaps[$pos->id][$dStr]);
                        @endphp

                        <td class="text-center p-1 align-middle position-relative cell-hoverable {{ $isWeekend ? 'bg-light' : '' }} {{ $hasHoliday ? 'bg-danger-subtle' : '' }} {{ $isPosOverlap && $empLeave ? 'border-warning border-2' : '' }}" 
                            style="height: 34px; cursor: pointer;"
                            @if(!$empLeave) onclick="openQuickLeaveModal({{ $emp->id }}, '{{ addslashes($emp->full_name) }}', '{{ $dStr }}')" @endif
                            title="{{ !$empLeave ? 'Clic para cargar vacaciones/licencia el '.$day->format('d/m/Y') : '' }}">
                          @if($empLeave)
                            @php
                              $badgeBg = $empLeave->leaveType->color ?? '#0d6efd';
                              $isPending = $empLeave->status === 'pendiente';
                            @endphp
                            <div class="rounded-1 py-1 px-1 text-white fw-bold text-truncate shadow-xs" 
                                 style="background-color: {{ $badgeBg }}; font-size: 0.70rem; line-height: 1.1; opacity: {{ $isPending ? '0.75' : '1' }}; border: {{ $isPending ? '1px dashed #fff' : 'none' }};"
                                 data-bs-toggle="tooltip"
                                 data-bs-html="true"
                                 title="<strong>{{ $emp->full_name }}</strong><br>{{ $empLeave->leaveType->name }} ({{ ucfirst($empLeave->status) }})<br>Desde: {{ \Carbon\Carbon::parse($empLeave->date_from)->format('d/m/Y') }}<br>Hasta: {{ \Carbon\Carbon::parse($empLeave->date_to)->format('d/m/Y') }}{{ $isPosOverlap ? '<br><span class=\'text-warning\'>⚠️ Solapamiento con otro colaborador del puesto</span>' : '' }}">
                              @if($isPosOverlap)<i class="fa-solid fa-triangle-exclamation me-1"></i>@endif
                              {{ $empLeave->leaveType->code ?? Str::limit($empLeave->leaveType->name, 4, '') }}
                            </div>
                          @elseif($hasHoliday)
                            <i class="fa-solid fa-star text-danger" style="font-size: 0.65rem;" title="Feriado: {{ $holidaysByDate[$dStr][0]->name }}"></i>
                          @endif
                        </td>
                      @endforeach
                    </tr>
                  @endforeach
                @endif
              @empty
                <tr>
                  <td colspan="{{ count($daysInMonthPeriod) + 1 }}" class="text-center py-4 text-muted">
                    No se encontraron puestos de trabajo activos registrados.
                  </td>
                </tr>
              @endforelse

              {{-- Empleados sin Puesto Asignado --}}
              @if($unassignedEmployees->count() > 0)
                <tr class="table-light">
                  <td class="ps-3 fw-bold text-muted sticky-col" style="position: sticky; left: 0; background-color: #f8f9fa; z-index: 5;">
                    <i class="fa-solid fa-user-gear me-2"></i>Sin Puesto Asignado ({{ $unassignedEmployees->count() }})
                  </td>
                  @foreach($daysInMonthPeriod as $day)
                    <td class="bg-light"></td>
                  @endforeach
                </tr>
                @foreach($unassignedEmployees as $emp)
                  <tr>
                    <td class="ps-3 sticky-col bg-white" style="position: sticky; left: 0; z-index: 5; white-space: nowrap;">
                      <div class="d-flex align-items-center gap-2 py-1 emp-quick-trigger"
                           style="cursor: pointer;"
                           onclick="openEmployeeQuickViewModal({{ json_encode([
                             'id' => $emp->id,
                             'full_name' => $emp->full_name,
                             'first_name' => $emp->first_name,
                             'last_name' => $emp->last_name,
                             'file_number' => $emp->file_number ?? '-',
                             'dni' => $emp->dni ?? '-',
                             'cuil' => $emp->cuil ?? '-',
                             'phone' => $emp->phone ?? '-',
                             'work_email' => $emp->work_email ?? $emp->personal_email ?? '-',
                             'birth_date' => $emp->birth_date ? $emp->birth_date->format('d/m/Y') : '-',
                             'position_name' => 'Sin Puesto Asignado',
                             'department_name' => $emp->department->name ?? 'Sin Área',
                             'branch_name' => $emp->branch->name ?? 'Sin Base',
                             'hire_date' => $emp->hire_date ? $emp->hire_date->format('d/m/Y') : '-',
                             'vacation_seniority_date' => $emp->vacation_seniority_date ? $emp->vacation_seniority_date->format('d/m/Y') : ($emp->hire_date ? $emp->hire_date->format('d/m/Y') . ' (Igual)' : '-'),
                             'seniority_formatted' => $emp->seniority_formatted,
                             'status' => $emp->status,
                             'avatar_url' => $emp->avatar_path ? asset('storage/' . $emp->avatar_path) : null,
                             'profile_url' => route('rrhh.employees.show', $emp->id),
                           ]) }})"
                           title="Clic para ver ficha rápida de {{ $emp->full_name }}">
                        <div class="avatar-circle bg-secondary text-white fw-bold rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.70rem;">
                          {{ substr($emp->first_name, 0, 1) }}{{ substr($emp->last_name, 0, 1) }}
                        </div>
                        <span class="fw-bold text-dark text-truncate" style="max-width: 140px; font-size: 0.82rem;">{{ $emp->last_name }}, {{ $emp->first_name }}</span>
                        <span class="badge bg-light text-muted border font-monospace px-1" style="font-size: 0.68rem;">{{ $emp->file_number ?? '-' }}</span>
                      </div>
                    </td>
                    @foreach($daysInMonthPeriod as $day)
                      @php
                        $dStr = $day->toDateString();
                        $dayLeaves = $leavesByDate[$dStr] ?? [];
                        $empLeave = null;
                        foreach($dayLeaves as $l) {
                            if($l->employee_id == $emp->id) {
                                $empLeave = $l;
                                break;
                            }
                        }
                        $isWeekend = $day->isWeekend();
                        $hasHoliday = isset($holidaysByDate[$dStr]);
                      @endphp
                      <td class="text-center p-1 align-middle position-relative cell-hoverable {{ $isWeekend ? 'bg-light' : '' }}" 
                          style="height: 34px; cursor: pointer;"
                          @if(!$empLeave) onclick="openQuickLeaveModal({{ $emp->id }}, '{{ addslashes($emp->full_name) }}', '{{ $dStr }}')" @endif
                          title="{{ !$empLeave ? 'Clic para cargar vacaciones/licencia el '.$day->format('d/m/Y') : '' }}">
                        @if($empLeave)
                          <div class="rounded-1 py-1 px-1 text-white fw-bold text-truncate shadow-xs" 
                               style="background-color: {{ $empLeave->leaveType->color ?? '#0d6efd' }}; font-size: 0.70rem; line-height: 1.1;">
                            {{ $empLeave->leaveType->code ?? Str::limit($empLeave->leaveType->name, 4, '') }}
                          </div>
                        @elseif($hasHoliday)
                          <i class="fa-solid fa-star text-danger" style="font-size: 0.65rem;" title="Feriado: {{ $holidaysByDate[$dStr][0]->name }}"></i>
                        @endif
                      </td>
                    @endforeach
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>

  {{-- ==================== VISTA 2: CALENDARIO MENSUAL TRADICIONAL ==================== --}}
  @elseif($viewMode === 'month')
    @php
      $daysInMonth = $carbonMonth->daysInMonth;
      $startOfWeek = $carbonMonth->copy()->startOfMonth()->dayOfWeekIso;
    @endphp

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-regular fa-calendar-days text-primary me-2"></i>Vista de Calendario Mensual</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered mb-0 calendar-table" style="min-width: 800px; table-layout: fixed;">
            <thead class="table-light text-center">
              <tr>
                <th style="width: 14.28%;">Lunes</th>
                <th style="width: 14.28%;">Martes</th>
                <th style="width: 14.28%;">Miércoles</th>
                <th style="width: 14.28%;">Jueves</th>
                <th style="width: 14.28%;">Viernes</th>
                <th style="width: 14.28%; color: #e02d1b;">Sábado</th>
                <th style="width: 14.28%; color: #e02d1b;">Domingo</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                @php
                  $dayCounter = 1;
                  $currentCell = 1;
                @endphp

                @for($i = 1; $i < $startOfWeek; $i++)
                  <td class="bg-light text-muted p-2" style="height: 110px; vertical-align: top;"></td>
                  @php $currentCell++; @endphp
                @endfor

                @while($dayCounter <= $daysInMonth)
                  @php
                    $dateStr = sprintf('%04d-%02d-%02d', $carbonMonth->year, $carbonMonth->month, $dayCounter);
                    $isToday = $dateStr === date('Y-m-d');
                    $dayHolidays = $holidaysByDate[$dateStr] ?? [];
                    $dayLeaves = $leavesByDate[$dateStr] ?? [];
                    $isWeekend = ($currentCell % 7 == 6 || $currentCell % 7 == 0);
                  @endphp

                  <td class="p-2 position-relative {{ $isToday ? 'bg-light-primary border-primary border-2' : ($isWeekend ? 'bg-light' : '') }}" style="height: 110px; vertical-align: top;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span class="fw-bold {{ $isToday ? 'badge bg-primary rounded-circle' : ($isWeekend ? 'text-muted' : 'text-dark') }}" style="{{ $isToday ? 'width: 24px; height: 24px; line-height: 18px;' : '' }}">
                        {{ $dayCounter }}
                      </span>
                      @if(count($dayHolidays) > 0)
                        <i class="fa-solid fa-champagne-glasses text-danger small" title="Feriado"></i>
                      @endif
                    </div>

                    @foreach($dayHolidays as $h)
                      <div class="badge bg-danger text-white text-truncate w-100 mb-1 text-start" title="{{ $h->name }}">
                        <i class="fa-solid fa-star me-1 small"></i>{{ Str::limit($h->name, 16) }}
                      </div>
                    @endforeach

                    @foreach(array_slice($dayLeaves, 0, 3) as $l)
                      <div class="badge text-truncate w-100 mb-1 text-start" style="background-color: {{ $l->leaveType->color ?? '#0d6efd' }}; color: #fff;" title="{{ $l->employee->full_name }} ({{ $l->leaveType->name }})">
                        <i class="fa-solid fa-user me-1 small"></i>{{ Str::limit($l->employee->last_name . ' ' . substr($l->employee->first_name, 0, 1) . '.', 14) }}
                      </div>
                    @endforeach

                    @if(count($dayLeaves) > 3)
                      <div class="badge bg-secondary text-white w-100 text-center small">
                        +{{ count($dayLeaves) - 3 }} más
                      </div>
                    @endif
                  </td>

                  @if($currentCell % 7 == 0 && $dayCounter < $daysInMonth)
                    </tr><tr>
                  @endif

                  @php
                    $dayCounter++;
                    $currentCell++;
                  @endphp
                @endwhile

                @while(($currentCell - 1) % 7 != 0)
                  <td class="bg-light text-muted p-2" style="height: 110px; vertical-align: top;"></td>
                  @php $currentCell++; @endphp
                @endwhile
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  {{-- ==================== VISTA 3: LISTADO DETALLADO ==================== --}}
  @else
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list text-primary me-2"></i>Detalle de Ausencias Registradas en el Mes</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Colaborador</th>
                <th>Puesto de Trabajo</th>
                <th>Área</th>
                <th>Tipo de Ausencia</th>
                <th>Desde</th>
                <th>Hasta</th>
                <th class="text-center">Días Computables</th>
                <th class="text-center">Estado</th>
                <th class="text-end pe-3">Acción</th>
              </tr>
            </thead>
            <tbody>
              @forelse($monthLeaves as $leave)
                <tr>
                  <td class="ps-3">
                    <div class="fw-bold text-dark">{{ $leave->employee->full_name }}</div>
                    <span class="badge bg-light text-muted border small">Leg. {{ $leave->employee->file_number ?? '-' }}</span>
                  </td>
                  <td>{{ $leave->employee->position->name ?? 'Sin Puesto' }}</td>
                  <td>{{ $leave->employee->department->name ?? 'Sin Área' }}</td>
                  <td>
                    <span class="badge" style="background-color: {{ $leave->leaveType->color ?? '#0d6efd' }}; color: #fff;">
                      {{ $leave->leaveType->name }}
                    </span>
                  </td>
                  <td>{{ \Carbon\Carbon::parse($leave->date_from)->format('d/m/Y') }}</td>
                  <td>{{ \Carbon\Carbon::parse($leave->date_to)->format('d/m/Y') }}</td>
                  <td class="text-center fw-bold text-primary">{{ $leave->days_count }}</td>
                  <td class="text-center">
                    @if($leave->status === 'aprobada')
                      <span class="badge bg-success">Aprobada</span>
                    @elseif($leave->status === 'pendiente')
                      <span class="badge bg-warning text-dark">Pendiente</span>
                    @else
                      <span class="badge bg-secondary">{{ ucfirst($leave->status) }}</span>
                    @endif
                  </td>
                  <td class="text-end pe-3">
                    <a href="{{ route('rrhh.leave-requests.show', $leave) }}" class="btn btn-sm btn-outline-secondary">
                      <i class="fa-solid fa-eye me-1"></i>Ver
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" class="text-center py-4 text-muted">
                    No hay solicitudes de ausencia registradas en el mes seleccionado.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
</div>

{{-- Modal Carga Rápida de Ausencia / Vacaciones --}}
<div class="modal fade" id="quickLeaveModal" tabindex="-1" aria-labelledby="quickLeaveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="{{ route('rrhh.leave-requests.store') }}" id="quickLeaveForm">
        @csrf
        <input type="hidden" name="employee_id" id="quick_employee_id">
        <input type="hidden" name="auto_approve" value="1">

        <div class="modal-header bg-light py-3 border-bottom">
          <h6 class="modal-title fw-bold text-dark" id="quickLeaveModalLabel">
            <i class="fa-solid fa-calendar-plus text-warning me-2"></i>Cargar Ausencia / Vacaciones
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body p-4">
          {{-- Info Colaborador --}}
          <div class="card bg-light border-0 p-3 mb-3">
            <div class="d-flex align-items-center">
              <div class="avatar-circle bg-dark text-white fw-bold rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="fa-solid fa-user"></i>
              </div>
              <div>
                <div class="fw-bold text-dark fs-6" id="quick_employee_name_display">-</div>
                <div class="small text-muted">Carga directa desde la grilla por puesto</div>
              </div>
            </div>
          </div>

          {{-- Tipo de Licencia --}}
          <div class="mb-3">
            <label for="quick_leave_type_id" class="form-label fw-semibold">Tipo de Licencia / Ausencia <span class="text-danger">*</span></label>
            <select name="leave_type_id" id="quick_leave_type_id" class="form-select" required>
              <option value="">-- Seleccione el tipo --</option>
              @foreach($leaveTypes as $lt)
                <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->code }})</option>
              @endforeach
            </select>
          </div>

          {{-- Fechas Desde / Hasta --}}
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label for="quick_date_from" class="form-label fw-semibold">Fecha Desde <span class="text-danger">*</span></label>
              <input type="date" name="date_from" id="quick_date_from" class="form-control" required>
            </div>
            <div class="col-6">
              <label for="quick_date_to" class="form-label fw-semibold">Fecha Hasta <span class="text-danger">*</span></label>
              <input type="date" name="date_to" id="quick_date_to" class="form-control" required>
            </div>
          </div>

          {{-- Observaciones / Motivo --}}
          <div class="mb-2">
            <label for="quick_reason" class="form-label fw-semibold">Motivo / Observación</label>
            <textarea name="reason" id="quick_reason" class="form-control" rows="2" placeholder="Motivo o detalle administrativo de la ausencia (opcional)..."></textarea>
          </div>
        </div>

        <div class="modal-footer bg-light py-2 border-top">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="fa-solid fa-check me-1"></i>Guardar Ausencia
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal Ficha Rápida del Colaborador --}}
<div class="modal fade" id="employeeQuickViewModal" tabindex="-1" aria-labelledby="employeeQuickViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white py-3">
        <div class="d-flex align-items-center gap-3">
          <div id="emp_quick_avatar_container">
            <div id="emp_quick_initials" class="avatar-circle bg-warning text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px; font-size: 1.25rem;">
              -
            </div>
            <img id="emp_quick_photo" src="" class="rounded-circle d-none object-fit-cover shadow-sm" style="width: 50px; height: 50px;" alt="Foto del Colaborador">
          </div>
          <div>
            <h5 class="fw-bold mb-0 text-white" id="emp_quick_full_name">-</h5>
            <div class="small text-white-50">
              <span id="emp_quick_position_name">-</span> &bull; Legajo: <span class="font-monospace text-warning" id="emp_quick_file_number">-</span>
            </div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body p-0">
        {{-- Pestañas con Reserva / Discreción de Datos --}}
        <ul class="nav nav-tabs nav-fill bg-light border-bottom px-3 pt-2" id="empQuickTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold small py-2" id="tab-emp-personal" data-bs-toggle="tab" data-bs-target="#emp-personal-pane" type="button" role="tab">
              <i class="fa-solid fa-user me-1 text-primary"></i>Datos Personales
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold small py-2" id="tab-emp-laboral" data-bs-toggle="tab" data-bs-target="#emp-laboral-pane" type="button" role="tab">
              <i class="fa-solid fa-briefcase me-1 text-primary"></i>Información Laboral
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold small py-2" id="tab-emp-contact" data-bs-toggle="tab" data-bs-target="#emp-contact-pane" type="button" role="tab">
              <i class="fa-solid fa-address-book me-1 text-primary"></i>Contacto
            </button>
          </li>
        </ul>

        <div class="tab-content p-4" id="empQuickTabsContent">
          {{-- TAB 1: DATOS PERSONALES --}}
          <div class="tab-pane fade show active" id="emp-personal-pane" role="tabpanel">
            <div class="row g-3">
              <div class="col-sm-6">
                <span class="text-muted small d-block">DNI / Documento</span>
                <span class="fw-bold text-dark font-monospace" id="emp_quick_dni">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">CUIL / CUIT</span>
                <span class="fw-bold text-dark font-monospace" id="emp_quick_cuil">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Fecha de Nacimiento</span>
                <span class="fw-semibold text-dark" id="emp_quick_birth_date">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Estado Laboral</span>
                <span class="badge bg-success" id="emp_quick_status">Activo</span>
              </div>
            </div>
          </div>

          {{-- TAB 2: INFORMACIÓN LABORAL --}}
          <div class="tab-pane fade" id="emp-laboral-pane" role="tabpanel">
            <div class="row g-3">
              <div class="col-sm-6">
                <span class="text-muted small d-block">Puesto de Trabajo</span>
                <span class="fw-bold text-dark" id="emp_quick_pos_display">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Área / Departamento</span>
                <span class="fw-semibold text-dark" id="emp_quick_dept_display">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Sucursal / Base</span>
                <span class="fw-semibold text-dark" id="emp_quick_branch_display">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Fecha de Ingreso Legal</span>
                <span class="fw-semibold text-dark" id="emp_quick_hire_date">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Antigüedad p/ Vacaciones</span>
                <span class="fw-semibold text-dark" id="emp_quick_vacation_date">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Antigüedad Computada</span>
                <span class="fw-bold text-primary" id="emp_quick_seniority">-</span>
              </div>
            </div>
          </div>

          {{-- TAB 3: CONTACTO --}}
          <div class="tab-pane fade" id="emp-contact-pane" role="tabpanel">
            <div class="row g-3">
              <div class="col-sm-6">
                <span class="text-muted small d-block">Teléfono de Contacto</span>
                <span class="fw-semibold text-dark" id="emp_quick_phone">-</span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small d-block">Correo Electrónico</span>
                <span class="fw-semibold text-dark" id="emp_quick_email">-</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light py-2 border-top justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        <a id="emp_quick_full_profile_btn" href="#" class="btn btn-primary px-3">
          <i class="fa-solid fa-folder-open me-1"></i>Ver Legajo Completo
        </a>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  .cell-hoverable:hover {
    background-color: rgba(255, 193, 7, 0.25) !important;
    transition: background-color 0.15s ease;
  }
  .emp-quick-trigger:hover {
    background-color: rgba(15, 23, 42, 0.05);
    border-radius: 6px;
  }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });

  function openQuickLeaveModal(empId, empName, dateStr) {
    document.getElementById('quick_employee_id').value = empId;
    document.getElementById('quick_employee_name_display').textContent = empName;
    document.getElementById('quick_date_from').value = dateStr;
    document.getElementById('quick_date_to').value = dateStr;
    
    var modalEl = document.getElementById('quickLeaveModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  function openEmployeeQuickViewModal(empData) {
    document.getElementById('emp_quick_full_name').textContent = empData.full_name;
    document.getElementById('emp_quick_position_name').textContent = empData.position_name;
    document.getElementById('emp_quick_file_number').textContent = empData.file_number;
    document.getElementById('emp_quick_dni').textContent = empData.dni;
    document.getElementById('emp_quick_cuil').textContent = empData.cuil;
    document.getElementById('emp_quick_birth_date').textContent = empData.birth_date;
    document.getElementById('emp_quick_pos_display').textContent = empData.position_name;
    document.getElementById('emp_quick_dept_display').textContent = empData.department_name;
    document.getElementById('emp_quick_branch_display').textContent = empData.branch_name;
    document.getElementById('emp_quick_hire_date').textContent = empData.hire_date;
    document.getElementById('emp_quick_vacation_date').textContent = empData.vacation_seniority_date;
    document.getElementById('emp_quick_seniority').textContent = empData.seniority_formatted;
    document.getElementById('emp_quick_phone').textContent = empData.phone;
    document.getElementById('emp_quick_email').textContent = empData.work_email;
    document.getElementById('emp_quick_full_profile_btn').href = empData.profile_url;

    var initials = ((empData.first_name || '').charAt(0) + (empData.last_name || '').charAt(0)).toUpperCase();
    document.getElementById('emp_quick_initials').textContent = initials;

    var photoEl = document.getElementById('emp_quick_photo');
    var initialsEl = document.getElementById('emp_quick_initials');
    if (empData.avatar_url) {
      photoEl.src = empData.avatar_url;
      photoEl.classList.remove('d-none');
      initialsEl.classList.add('d-none');
    } else {
      photoEl.classList.add('d-none');
      initialsEl.classList.remove('d-none');
    }

    var modalEl = document.getElementById('employeeQuickViewModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
</script>
@endpush
@endsection
