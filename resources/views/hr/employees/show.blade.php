@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.employees.index') }}">Colaboradores</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $employee->full_name }}</li>
      </ol>
    </nav>
  </div>

  {{-- Header de Ficha del Colaborador --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
          @if($employee->avatar_path)
            <img src="{{ asset('storage/' . $employee->avatar_path) }}" alt="{{ $employee->full_name }}" class="rounded-circle object-fit-cover shadow-sm" style="width: 72px; height: 72px;">
          @else
            <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold rounded-circle shadow-sm" style="width: 72px; height: 72px; font-size: 24px;">
              {{ strtoupper(mb_substr($employee->first_name, 0, 1) . mb_substr($employee->last_name, 0, 1)) }}
            </div>
          @endif
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <h3 class="fw-bold mb-0 text-dark">{{ $employee->full_name }}</h3>
              {!! $employee->status_badge !!}
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
              <span><i class="fa-solid fa-id-badge me-1"></i>Legajo: <strong class="text-dark">{{ $employee->file_number }}</strong></span>
              <span><i class="fa-solid fa-id-card me-1"></i>DNI: <strong class="text-dark">{{ $employee->dni }}</strong></span>
              <span><i class="fa-solid fa-sitemap me-1"></i>{{ $employee->department ? $employee->department->name : 'Sin área' }}</span>
              <span><i class="fa-solid fa-briefcase me-1"></i>{{ $employee->position ? $employee->position->name : 'Sin puesto' }}</span>
              <span><i class="fa-solid fa-building me-1"></i>{{ $employee->branch ? $employee->branch->name : 'Sin base' }}</span>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          @if($employee->status !== 'egresado')
            <a href="{{ route('rrhh.leave-requests.create', ['employee_id' => $employee->id]) }}" class="btn btn-primary d-flex align-items-center gap-2">
              <i class="fa-solid fa-plane-departure"></i>
              <span>Asignar Vacaciones</span>
            </a>
          @endif

          <a href="{{ route('rrhh.employees.edit', $employee) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="fa-solid fa-pen-to-square"></i>
            <span>Editar</span>
          </a>

          <button type="button" class="btn btn-outline-warning d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Cambiar Estado</span>
          </button>

          @if($employee->status !== 'egresado')
            <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#egresoModal">
              <i class="fa-solid fa-user-xmark"></i>
              <span>Registrar Egreso</span>
            </button>
          @endif
        </div>
      </div>
    </div>

    {{-- Tabs de Navegación --}}
    <div class="card-footer bg-white border-top p-0 px-4">
      <ul class="nav nav-tabs border-0" id="employeeShowTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ request('tab') !== 'legajo' && request('tab') !== 'auditoria' && request('tab') !== 'vacaciones' ? 'active' : '' }} fw-semibold py-3 border-0 border-bottom border-2" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-pane" type="button" role="tab">
            <i class="fa-solid fa-user text-primary me-2"></i>Ficha del Colaborador
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ request('tab') === 'vacaciones' ? 'active' : '' }} fw-semibold py-3 border-0 border-bottom border-2 position-relative" id="vacaciones-tab" data-bs-toggle="tab" data-bs-target="#vacaciones-pane" type="button" role="tab">
            <i class="fa-solid fa-plane-departure text-primary me-2"></i>Vacaciones y Licencias
            @if($vacationBalance && $vacationBalance->available_days > 0)
              <span class="badge bg-success ms-1">{{ $vacationBalance->available_days }}d disp.</span>
            @endif
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ request('tab') === 'legajo' ? 'active' : '' }} fw-semibold py-3 border-0 border-bottom border-2 position-relative" id="legajo-tab" data-bs-toggle="tab" data-bs-target="#legajo-pane" type="button" role="tab">
            <i class="fa-solid fa-folder-open text-primary me-2"></i>Legajo Digital
            @if($filesStats['total'] > 0)
              <span class="badge bg-secondary ms-1">{{ $filesStats['total'] }}</span>
            @endif
            @if($filesStats['expired'] > 0)
              <span class="badge bg-danger ms-1" title="Documentos vencidos">{{ $filesStats['expired'] }}</span>
            @endif
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ request('tab') === 'auditoria' ? 'active' : '' }} fw-semibold py-3 border-0 border-bottom border-2" id="audits-tab" data-bs-toggle="tab" data-bs-target="#audits-pane" type="button" role="tab">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Registro de Auditoría
          </button>
        </li>
      </ul>
    </div>
  </div>

  {{-- Contenido de las Pestañas --}}
  <div class="tab-content" id="employeeShowTabsContent">
    {{-- TAB 1: FICHA DEL COLABORADOR --}}
    <div class="tab-pane fade {{ request('tab') !== 'legajo' && request('tab') !== 'auditoria' ? 'show active' : '' }}" id="profile-pane" role="tabpanel">
      <div class="row g-3">
        {{-- Datos Personales y Contacto --}}
        <div class="col-lg-6 col-12">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i>Datos Personales y de Identificación</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Nombre Completo</span>
                  <span class="fw-semibold text-dark">{{ $employee->full_name }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">DNI / Documento</span>
                  <span class="fw-semibold text-dark">{{ $employee->dni }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">CUIL / CUIT</span>
                  <span class="fw-semibold text-dark">{{ $employee->cuil ?? '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Fecha de Nacimiento</span>
                  <span class="fw-semibold text-dark">
                    {{ $employee->birth_date ? $employee->birth_date->format('d/m/Y') . ' (' . $employee->birth_date->age . ' años)' : '-' }}
                  </span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Género</span>
                  <span class="fw-semibold text-dark">{{ ucfirst($employee->gender ?? '-') }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Estado Civil</span>
                  <span class="fw-semibold text-dark">{{ ucfirst($employee->marital_status ?? '-') }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Nacionalidad</span>
                  <span class="fw-semibold text-dark">{{ $employee->nationality ?? '-' }}</span>
                </div>
              </div>

              <hr class="my-3">

              <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-address-book text-primary me-2"></i>Contacto y Domicilio</h6>
              <div class="row g-3">
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Teléfono Celular</span>
                  <span class="fw-semibold text-dark">{{ $employee->phone ?? '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Correo Personal</span>
                  <span class="fw-semibold text-dark">{{ $employee->personal_email ?? '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Correo Corporativo</span>
                  <span class="fw-semibold text-dark">{{ $employee->work_email ?? '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Domicilio</span>
                  <span class="fw-semibold text-dark">
                    {{ $employee->address ?? '-' }}
                    @if($employee->city) , {{ $employee->city }} @endif
                    @if($employee->province) ({{ $employee->province }}) @endif
                  </span>
                </div>
              </div>

              <hr class="my-3">

              <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-phone-volume text-danger me-2"></i>Contacto de Emergencia</h6>
              <div class="row g-3">
                <div class="col-sm-4">
                  <span class="text-muted small d-block">Contacto</span>
                  <span class="fw-semibold text-dark">{{ $employee->emergency_contact_name ?? '-' }}</span>
                </div>
                <div class="col-sm-4">
                  <span class="text-muted small d-block">Teléfono</span>
                  <span class="fw-semibold text-dark">{{ $employee->emergency_contact_phone ?? '-' }}</span>
                </div>
                <div class="col-sm-4">
                  <span class="text-muted small d-block">Vínculo</span>
                  <span class="fw-semibold text-dark">{{ $employee->emergency_contact_relationship ?? '-' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Datos Laborales y Acceso --}}
        <div class="col-lg-6 col-12">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-briefcase text-primary me-2"></i>Información y Encuadre Laboral</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Número de Legajo</span>
                  <span class="badge bg-secondary font-monospace">{{ $employee->file_number }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Fecha de Ingreso Legal</span>
                  <span class="fw-semibold text-dark">{{ $employee->hire_date ? $employee->hire_date->format('d/m/Y') : '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Antigüedad p/ Vacaciones</span>
                  <span class="fw-semibold text-dark">
                    {{ $employee->vacation_seniority_date ? $employee->vacation_seniority_date->format('d/m/Y') : ($employee->hire_date ? $employee->hire_date->format('d/m/Y') . ' (Igual)' : '-') }}
                  </span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Antigüedad Computada</span>
                  <span class="fw-semibold text-primary">{{ $employee->seniority_formatted }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Tipo de Contrato</span>
                  <span class="fw-semibold text-dark">{{ ucfirst(str_replace('_', ' ', $employee->contract_type ?? 'indeterminado')) }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Área / Departamento</span>
                  <span class="fw-semibold text-dark">{{ $employee->department ? $employee->department->name : '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Puesto de Trabajo</span>
                  <span class="fw-semibold text-dark">{{ $employee->position ? $employee->position->name : '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Sucursal / Base Operativa</span>
                  <span class="fw-semibold text-dark">{{ $employee->branch ? $employee->branch->name : '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Responsable Directo</span>
                  <span class="fw-semibold text-dark">{{ $employee->manager ? $employee->manager->full_name : '-' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Convenio / Política</span>
                  <span class="fw-semibold text-dark">{{ $employee->agreement ? $employee->agreement->name : 'Fuera de convenio' }}</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted small d-block">Salario Bruto de Referencia</span>
                  <span class="fw-semibold text-dark">{{ $employee->salary ? '$ ' . number_format($employee->salary, 2, ',', '.') : '-' }}</span>
                </div>
                @if($employee->status === 'egresado')
                  <div class="col-12">
                    <div class="alert alert-secondary mb-0">
                      <div class="fw-bold text-danger"><i class="fa-solid fa-user-xmark me-1"></i>Colaborador Egresado</div>
                      <div>Fecha de Egreso: <strong>{{ $employee->termination_date ? $employee->termination_date->format('d/m/Y') : '-' }}</strong></div>
                      <div>Motivo: <em>{{ $employee->termination_reason ?? 'No especificado' }}</em></div>
                    </div>
                  </div>
                @endif
              </div>

              <hr class="my-3">

              <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-key text-primary me-2"></i>Acceso al Sistema ERP</h6>
              <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                @if($employee->user)
                  <div>
                    <div class="fw-bold text-dark"><i class="fa-solid fa-user-check text-success me-1"></i>{{ $employee->user->name }}</div>
                    <div class="text-muted small">{{ $employee->user->email }} | Rol: <span class="badge bg-secondary">{{ $employee->user->role ?? 'usuario' }}</span></div>
                  </div>
                  <form method="POST" action="{{ route('rrhh.employees.unlink-user', $employee) }}" onsubmit="return confirm('¿Desea desvincular la cuenta de usuario de este empleado?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desvincular usuario">
                      <i class="fa-solid fa-link-slash me-1"></i>Desvincular
                    </button>
                  </form>
                @else
                  <div class="text-muted">
                    <i class="fa-solid fa-user-slash me-1"></i>Sin cuenta de usuario asociada.
                  </div>
                  <a href="{{ route('rrhh.employees.edit', $employee) }}#access" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-link me-1"></i>Vincular Usuario
                  </a>
                @endif
              </div>

              @if($employee->notes)
                <hr class="my-3">
                <h6 class="fw-bold text-dark mb-2">Observaciones Internas</h6>
                <p class="text-muted small mb-0">{{ $employee->notes }}</p>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- TAB VACACIONES Y LICENCIAS --}}
    <div class="tab-pane fade {{ request('tab') === 'vacaciones' ? 'show active' : '' }}" id="vacaciones-pane" role="tabpanel">
      {{-- KPI Cards de Vacaciones --}}
      <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-primary border-4">
            <span class="text-muted small fw-semibold">Disponibles {{ $currentYear }}</span>
            <h3 class="fw-bold text-primary mb-0 mt-1">
              {{ $vacationBalance ? $vacationBalance->available_days : 0 }} <small class="fs-6 text-muted">días</small>
            </h3>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-info border-4">
            <span class="text-muted small fw-semibold">Asignados por Ley / Convenio</span>
            <h3 class="fw-bold text-info mb-0 mt-1">
              {{ $vacationBalance ? $vacationBalance->total_granted : 0 }} <small class="fs-6 text-muted">días</small>
            </h3>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-success border-4">
            <span class="text-muted small fw-semibold">Gozados / Aprobados</span>
            <h3 class="fw-bold text-success mb-0 mt-1">
              {{ $vacationBalance ? $vacationBalance->used_days : 0 }} <small class="fs-6 text-muted">días</small>
            </h3>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-warning border-4">
            <span class="text-muted small fw-semibold">En Trámite / Pendientes</span>
            <h3 class="fw-bold text-warning mb-0 mt-1">
              {{ $vacationBalance ? $vacationBalance->pending_days : 0 }} <small class="fs-6 text-muted">días</small>
            </h3>
          </div>
        </div>
      </div>

      {{-- Listado y Gestión de Licencias --}}
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plane-departure text-primary me-2"></i>Historial de Vacaciones y Licencias</h5>
            <small class="text-muted">Registro completo de solicitudes y permisos otorgados al colaborador.</small>
          </div>
          @if($employee->status !== 'egresado')
            <div class="d-flex gap-2">
              <a href="{{ route('rrhh.leave-requests.create', ['employee_id' => $employee->id]) }}" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="fa-solid fa-calendar-plus"></i>
                <span>Asignar Vacaciones / Licencia</span>
              </a>
              @if($vacationBalance)
                <a href="{{ route('rrhh.leave-balances.show', $vacationBalance) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                  <i class="fa-solid fa-sliders"></i>
                  <span>Ajustar Saldo</span>
                </a>
              @endif
            </div>
          @endif
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="min-height: 200px;">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Tipo de Ausencia</th>
                  <th>Período Solicitado</th>
                  <th class="text-center">Días Computados</th>
                  <th>Motivo / Justificación</th>
                  <th class="text-center">Estado</th>
                  <th class="text-end pe-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @forelse($leaveRequests as $req)
                  <tr>
                    <td class="ps-3">
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
                        {{ \Carbon\Carbon::parse($req->date_from)->format('d/m/Y') }}
                        @if($req->date_from != $req->date_to)
                          al {{ \Carbon\Carbon::parse($req->date_to)->format('d/m/Y') }}
                        @endif
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-light text-dark border font-monospace fs-6">{{ $req->days_count }} días</span>
                    </td>
                    <td>
                      <div class="small text-truncate" style="max-width: 250px;" title="{{ $req->reason }}">
                        {{ $req->reason ?? '-' }}
                      </div>
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
                      <a href="{{ route('rrhh.leave-requests.show', $req) }}" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                        <i class="fa-solid fa-eye me-1"></i>Ver
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                      <i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block text-secondary opacity-50"></i>
                      No se registran licencias ni vacaciones asignadas para este colaborador.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Resumen de Saldos por Tipo de Licencia --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Saldos del Período {{ $currentYear }}</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Tipo de Ausencia</th>
                  <th class="text-center">Asignados</th>
                  <th class="text-center">Ajustes (+/-)</th>
                  <th class="text-center">Gozados</th>
                  <th class="text-center">Pendientes</th>
                  <th class="text-center">Disponibles</th>
                  <th class="text-end pe-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @foreach($leaveTypes as $lt)
                  @php $b = $leaveBalances[$lt->id] ?? null; @endphp
                  <tr>
                    <td class="ps-3">
                      <span class="badge" style="background-color: {{ $lt->color ?? '#0d6efd' }}; color: #fff;">{{ $lt->name }}</span>
                    </td>
                    <td class="text-center">{{ $b ? $b->total_granted : ($lt->days_allowed_per_year ?? 'Sin límite') }}d</td>
                    <td class="text-center">{{ $b && $b->adjustment_days != 0 ? ($b->adjustment_days > 0 ? '+'.$b->adjustment_days : $b->adjustment_days).'d' : '-' }}</td>
                    <td class="text-center text-danger">{{ $b ? $b->used_days : 0 }}d</td>
                    <td class="text-center text-warning">{{ $b && $b->pending_days > 0 ? $b->pending_days.'d' : '-' }}</td>
                    <td class="text-center">
                      @if($b && $lt->deducts_from_balance)
                        <span class="badge bg-{{ $b->available_days > 0 ? 'success' : 'secondary' }} fs-6">
                          {{ $b->available_days }} días
                        </span>
                      @else
                        <span class="text-muted small">No limita cupo</span>
                      @endif
                    </td>
                    <td class="text-end pe-3">
                      @if($b)
                        <a href="{{ route('rrhh.leave-balances.show', $b) }}" class="btn btn-sm btn-outline-secondary">
                          <i class="fa-solid fa-sliders me-1"></i>Detalle Saldo
                        </a>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- TAB 2: LEGAJO DIGITAL --}}
    <div class="tab-pane fade {{ request('tab') === 'legajo' ? 'show active' : '' }}" id="legajo-pane" role="tabpanel">
      {{-- Tarjetas KPI de Vencimientos Documentales --}}
      <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3">
            <span class="text-muted small fw-semibold">Total Documentos</span>
            <h3 class="fw-bold text-dark mb-0 mt-1">{{ $filesStats['total'] }}</h3>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-success border-4">
            <span class="text-muted small fw-semibold">Documentos Vigentes</span>
            <h3 class="fw-bold text-success mb-0 mt-1">{{ $filesStats['valid'] }}</h3>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-warning border-4">
            <span class="text-muted small fw-semibold">Próximos a Vencer (&le;30d)</span>
            <h3 class="fw-bold text-warning mb-0 mt-1">{{ $filesStats['expiring_soon'] }}</h3>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="card border-0 shadow-sm text-center p-3 border-start border-danger border-4">
            <span class="text-muted small fw-semibold">Vencidos</span>
            <h3 class="fw-bold text-danger mb-0 mt-1">{{ $filesStats['expired'] }}</h3>
          </div>
        </div>
      </div>

      {{-- Listado de Documentos del Legajo --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-folder-tree text-primary me-2"></i>Documentación del Legajo Digital</h5>
            <small class="text-muted">Almacenamiento privado seguro con trazabilidad y auditoría de descargas.</small>
          </div>
          <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
            <i class="fa-solid fa-file-arrow-up"></i>
            <span>Adjuntar Documento</span>
          </button>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Categoría</th>
                  <th>Título del Documento</th>
                  <th>Archivo</th>
                  <th>Fecha Emisión</th>
                  <th>Vencimiento</th>
                  <th>Subido Por</th>
                  <th class="text-center">Estado</th>
                  <th class="text-end pe-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @forelse($employee->files as $file)
                  @php
                    $isExpired = $file->expiration_date && $file->expiration_date->lt($today);
                    $isExpiringSoon = $file->expiration_date && $file->expiration_date->gte($today) && $file->expiration_date->lte($expirationThreshold);
                    $isValid = $file->expiration_date && $file->expiration_date->gt($expirationThreshold);
                    $isNoExp = is_null($file->expiration_date);
                  @endphp
                  <tr class="{{ !$file->is_active ? 'opacity-50 bg-light' : '' }}">
                    <td class="ps-3">
                      <span class="badge bg-light text-dark border">{{ $file->category_label }}</span>
                    </td>
                    <td>
                      <div class="fw-bold text-dark">{{ $file->title }}</div>
                      @if($file->notes)
                        <div class="text-muted small">{{ Str::limit($file->notes, 60) }}</div>
                      @endif
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        @if(Str::contains($file->file_mime, 'pdf'))
                          <i class="fa-solid fa-file-pdf text-danger fa-lg"></i>
                        @elseif(Str::contains($file->file_mime, ['image', 'jpeg', 'png']))
                          <i class="fa-solid fa-file-image text-primary fa-lg"></i>
                        @elseif(Str::contains($file->file_mime, 'word'))
                          <i class="fa-solid fa-file-word text-info fa-lg"></i>
                        @else
                          <i class="fa-solid fa-file text-secondary fa-lg"></i>
                        @endif
                        <div>
                          <div class="small fw-semibold text-truncate" style="max-width: 160px;" title="{{ $file->file_name }}">
                            {{ $file->file_name }}
                          </div>
                          <span class="text-muted small">{{ $file->file_size_formatted }}</span>
                        </div>
                      </div>
                    </td>
                    <td>{{ $file->issue_date ? $file->issue_date->format('d/m/Y') : '-' }}</td>
                    <td>
                      @if($isExpired)
                        <span class="badge bg-danger text-white"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $file->expiration_date->format('d/m/Y') }} (Vencido)</span>
                      @elseif($isExpiringSoon)
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>{{ $file->expiration_date->format('d/m/Y') }} (Próximo)</span>
                      @elseif($isValid)
                        <span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>{{ $file->expiration_date->format('d/m/Y') }}</span>
                      @else
                        <span class="badge bg-secondary text-white">Sin Vencimiento</span>
                      @endif
                    </td>
                    <td>
                      <div class="small fw-semibold">{{ $file->uploader ? $file->uploader->name : 'Sistema' }}</div>
                      <small class="text-muted">{{ $file->created_at->format('d/m/Y H:i') }}</small>
                    </td>
                    <td class="text-center">
                      @if($file->is_active)
                        <span class="badge bg-success">Activo</span>
                      @else
                        <span class="badge bg-secondary">Anulado</span>
                      @endif
                    </td>
                    <td class="text-end pe-3">
                      <div class="btn-group btn-group-sm">
                        @if(in_array($file->file_mime, ['application/pdf', 'image/jpeg', 'image/png']))
                          <a href="{{ route('rrhh.employees.files.preview', [$employee, $file]) }}" target="_blank" class="btn btn-outline-primary" title="Previsualizar">
                            <i class="fa-solid fa-eye"></i>
                          </a>
                        @endif
                        <a href="{{ route('rrhh.employees.files.download', [$employee, $file]) }}" class="btn btn-outline-secondary" title="Descargar de forma segura">
                          <i class="fa-solid fa-download"></i>
                        </a>
                        @if($file->is_active)
                          <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidFileModal{{ $file->id }}" title="Anular documento">
                            <i class="fa-solid fa-ban"></i>
                          </button>
                        @endif
                      </div>

                      {{-- Modal Anulación de Archivo --}}
                      @if($file->is_active)
                        <div class="modal fade" id="voidFileModal{{ $file->id }}" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow text-start">
                              <form method="POST" action="{{ route('rrhh.employees.files.void', [$employee, $file]) }}">
                                @csrf
                                <div class="modal-header bg-danger text-white">
                                  <h5 class="modal-title fw-bold">Anular Documento del Legajo</h5>
                                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                  <p>¿Está seguro de anular el documento <strong>{{ $file->title }}</strong>?</p>
                                  <div class="mb-3">
                                    <label class="form-label fw-semibold">Motivo de Anulación <span class="text-danger">*</span></label>
                                    <textarea name="reason" class="form-control" rows="2" placeholder="Ej: Documento reemplazado, error en la carga..." required></textarea>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                  <button type="submit" class="btn btn-danger">Confirmar Anulación</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center py-5">
                      <div class="text-muted">
                        <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
                        <p class="mb-1 fw-semibold">No hay documentos registrados en este legajo</p>
                        <small>Haga clic en "Adjuntar Documento" para incorporar contratos, DNI o aptos médicos.</small>
                      </div>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- TAB 3: REGISTRO DE AUDITORÍA --}}
    <div class="tab-pane fade {{ request('tab') === 'auditoria' ? 'show active' : '' }}" id="audits-pane" role="tabpanel">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
          <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Historial de Auditoría de RR. HH.</h5>
          <small class="text-muted">Trazabilidad de modificaciones sobre la ficha del colaborador y sus archivos.</small>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Fecha y Hora</th>
                  <th>Usuario</th>
                  <th>Acción</th>
                  <th>Descripción</th>
                  <th>Dirección IP</th>
                </tr>
              </thead>
              <tbody>
                @forelse($auditLogs as $log)
                  <tr>
                    <td class="ps-3 font-monospace small">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>
                      <span class="fw-semibold text-dark">{{ $log->user ? $log->user->name : 'Sistema' }}</span>
                    </td>
                    <td>
                      <span class="badge bg-secondary font-monospace">{{ $log->action }}</span>
                    </td>
                    <td>
                      <span class="text-dark">{{ $log->description ?? '-' }}</span>
                    </td>
                    <td class="text-muted small font-monospace">{{ $log->ip_address ?? '-' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                      No hay registros de auditoría recientes para este colaborador.
                    </td>
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

{{-- MODAL ADJUNTAR DOCUMENTO AL LEGAJO --}}
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="{{ route('rrhh.employees.files.store', $employee) }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-arrow-up me-2"></i>Adjuntar Documento al Legajo Digital</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6 col-12">
              <label for="category" class="form-label fw-semibold">Categoría Documental <span class="text-danger">*</span></label>
              <select name="category" id="category" class="form-select" required>
                <option value="dni_documentos">Identificación Personal / DNI</option>
                <option value="curriculum">Currículum Vitae</option>
                <option value="contratos">Contratos y Anexos</option>
                <option value="apto_medico">Apto Médico / Exámenes de Salud</option>
                <option value="declaraciones_juradas">Declaraciones Juradas</option>
                <option value="certificados">Certificados y Títulos</option>
                <option value="capacitaciones">Capacitaciones y Cursos</option>
                <option value="datos_laborales">Documentación Laboral / Alta</option>
                <option value="egreso">Documentación de Egreso</option>
                <option value="otros">Otros Documentos</option>
              </select>
            </div>

            <div class="col-md-6 col-12">
              <label for="title" class="form-label fw-semibold">Título del Documento <span class="text-danger">*</span></label>
              <input type="text" name="title" id="title" class="form-control" placeholder="Ej: Apto Médico Periódico 2026" required>
            </div>

            <div class="col-md-6 col-12">
              <label for="issue_date" class="form-label fw-semibold">Fecha de Emisión</label>
              <input type="date" name="issue_date" id="issue_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>

            <div class="col-md-6 col-12">
              <label for="expiration_date" class="form-label fw-semibold">Fecha de Vencimiento (Opcional)</label>
              <input type="date" name="expiration_date" id="expiration_date" class="form-control">
              <small class="text-muted">Dejar vacío si no posee vencimiento</small>
            </div>

            <div class="col-12">
              <label for="document" class="form-label fw-semibold">Archivo a Adjuntar <span class="text-danger">*</span></label>
              <input type="file" name="document" id="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
              <small class="text-muted">Formatos admitidos: PDF, JPG, PNG, DOC, DOCX (Máximo 10 MB). El archivo se guardará en almacenamiento privado seguro.</small>
            </div>

            <div class="col-12">
              <label for="file_notes" class="form-label fw-semibold">Observaciones / Notas</label>
              <textarea name="notes" id="file_notes" rows="2" class="form-control" placeholder="Anotaciones sobre el documento..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Subir Documento</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- MODAL CAMBIO DE ESTADO --}}
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="{{ route('rrhh.employees.update-status', $employee) }}">
        @csrf
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold">Actualizar Estado Laboral</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Estado Actual</label>
            <div class="mb-2">{!! $employee->status_badge !!}</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nuevo Estado <span class="text-danger">*</span></label>
            <select name="status" class="form-select" required>
              <option value="activo" {{ $employee->status === 'activo' ? 'selected' : '' }}>Activo</option>
              <option value="licencia" {{ $employee->status === 'licencia' ? 'selected' : '' }}>En Licencia</option>
              <option value="suspendido" {{ $employee->status === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
              <option value="en_onboarding" {{ $employee->status === 'en_onboarding' ? 'selected' : '' }}>En Onboarding</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Estado</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- MODAL EGRESO --}}
@if($employee->status !== 'egresado')
  <div class="modal fade" id="egresoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form method="POST" action="{{ route('rrhh.employees.terminate', $employee) }}">
          @csrf
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-xmark me-2"></i>Registrar Egreso Laboral</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning small">
              El colaborador <strong>{{ $employee->full_name }}</strong> pasará a estado <strong>Egresado</strong>. Sus datos históricos y legajo digital permanecerán archivados.
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Fecha de Egreso <span class="text-danger">*</span></label>
              <input type="date" name="termination_date" class="form-control" value="{{ date('Y-m-d') }}" required min="{{ $employee->hire_date ? $employee->hire_date->format('Y-m-d') : '' }}">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Motivo de Egreso <span class="text-danger">*</span></label>
              <input type="text" name="termination_reason" class="form-control" placeholder="Ej: Renuncia, Fin de contrato, Despido..." required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger">Confirmar Egreso</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif
@endsection
