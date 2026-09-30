@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Colaboradores</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i>Gestión de Colaboradores</h3>
    </div>
    <a href="{{ route('rrhh.employees.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
      <i class="fa-solid fa-user-plus"></i>
      <span>Nuevo Colaborador</span>
    </a>
  </div>

  {{-- Filtros y Búsqueda --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.employees.index') }}" class="row g-2 align-items-center">
        <div class="col-lg-3 col-md-6 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Nombre, DNI o Legajo..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="activo" {{ request('status') === 'activo' ? 'selected' : '' }}>Activo</option>
            <option value="licencia" {{ request('status') === 'licencia' ? 'selected' : '' }}>En Licencia</option>
            <option value="suspendido" {{ request('status') === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
            <option value="en_onboarding" {{ request('status') === 'en_onboarding' ? 'selected' : '' }}>Onboarding</option>
            <option value="egresado" {{ request('status') === 'egresado' ? 'selected' : '' }}>Egresado</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
          <select name="department_id" class="form-select">
            <option value="">Todas las áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <select name="position_id" class="form-select">
            <option value="">Todos los puestos</option>
            @foreach($positions as $pos)
              <option value="{{ $pos->id }}" {{ request('position_id') == $pos->id ? 'selected' : '' }}>{{ $pos->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <select name="branch_id" class="form-select">
            <option value="">Todas las bases</option>
            @foreach($branches as $branch)
              <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-1 col-md-4 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100" title="Filtrar"><i class="fa-solid fa-filter"></i></button>
          @if(request()->hasAny(['search', 'status', 'department_id', 'position_id', 'branch_id']))
            <a href="{{ route('rrhh.employees.index') }}" class="btn btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de Empleados --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive" style="min-height: 250px;">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Colaborador</th>
              <th>Legajo</th>
              <th>DNI / CUIL</th>
              <th>Área / Puesto</th>
              <th>Sucursal / Base</th>
              <th>Responsable</th>
              <th>Ingreso</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($employees as $employee)
              <tr>
                <td class="ps-3">
                  <div class="d-flex align-items-center gap-3">
                    @if($employee->avatar_path)
                      <img src="{{ asset('storage/' . $employee->avatar_path) }}" alt="{{ $employee->full_name }}" class="rounded-circle object-fit-cover" style="width: 40px; height: 40px;">
                    @else
                      <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold rounded-circle" style="width: 40px; height: 40px; font-size: 14px;">
                        {{ strtoupper(mb_substr($employee->first_name, 0, 1) . mb_substr($employee->last_name, 0, 1)) }}
                      </div>
                    @endif
                    <div>
                      <a href="{{ route('rrhh.employees.show', $employee) }}" class="fw-bold text-dark text-decoration-none">
                        {{ $employee->full_name }}
                      </a>
                      <div class="text-muted small">
                        @if($employee->user)
                          <span class="badge bg-light text-primary border"><i class="fa-solid fa-user-check me-1"></i>Con acceso</span>
                        @else
                          <span class="text-muted"><i class="fa-solid fa-user-slash me-1"></i>Sin usuario</span>
                        @endif
                      </div>
                    </div>
                  </div>
                </td>
                <td><span class="badge bg-secondary font-monospace">{{ $employee->file_number }}</span></td>
                <td>
                  <div class="fw-semibold">{{ $employee->dni }}</div>
                  @if($employee->cuil)
                    <div class="text-muted small">{{ $employee->cuil }}</div>
                  @endif
                </td>
                <td>
                  <div class="fw-semibold text-dark">{{ $employee->department ? $employee->department->name : '-' }}</div>
                  <div class="text-muted small">{{ $employee->position ? $employee->position->name : '-' }}</div>
                </td>
                <td>
                  <span class="text-dark">{{ $employee->branch ? $employee->branch->name : '-' }}</span>
                </td>
                <td>
                  @if($employee->manager)
                    <span class="small">{{ $employee->manager->full_name }}</span>
                  @else
                    <span class="text-muted small fst-italic">Sin asignar</span>
                  @endif
                </td>
                <td>
                  <div>{{ $employee->hire_date ? $employee->hire_date->format('d/m/Y') : '-' }}</div>
                  <small class="text-muted">{{ $employee->seniority_formatted }}</small>
                </td>
                <td class="text-center">
                  {!! $employee->status_badge !!}
                </td>
                <td class="text-end pe-3">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                      Acciones
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="z-index: 1065; min-width: 220px;">
                      <li>
                        <a class="dropdown-item" href="{{ route('rrhh.employees.show', $employee) }}">
                          <i class="fa-solid fa-folder-open text-primary me-2"></i>Ver Ficha / Legajo
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item" href="{{ route('rrhh.employees.edit', $employee) }}">
                          <i class="fa-solid fa-pen-to-square text-secondary me-2"></i>Editar Datos
                        </a>
                      </li>
                      @if($employee->status !== 'egresado')
                        <li>
                          <a class="dropdown-item text-primary" href="{{ route('rrhh.leave-requests.create', ['employee_id' => $employee->id]) }}">
                            <i class="fa-solid fa-plane-departure me-2"></i>Asignar Vacaciones
                          </a>
                        </li>
                      @endif
                      <li><hr class="dropdown-divider my-1"></li>
                      <li>
                        <button type="button" class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#statusModal{{ $employee->id }}">
                          <i class="fa-solid fa-arrows-rotate me-2"></i>Cambiar Estado
                        </button>
                      </li>
                      @if($employee->status !== 'egresado')
                        <li>
                          <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#terminateModal{{ $employee->id }}">
                            <i class="fa-solid fa-user-xmark me-2"></i>Registrar Egreso
                          </button>
                        </li>
                      @endif
                    </ul>
                  </div>

                  {{-- Modal Cambio de Estado --}}
                  <div class="modal fade" id="statusModal{{ $employee->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" action="{{ route('rrhh.employees.update-status', $employee) }}">
                          @csrf
                          <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold">Cambiar Estado Laboral</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body text-start">
                            <p class="mb-3">Modificar el estado de <strong>{{ $employee->full_name }}</strong>:</p>
                            <div class="mb-3">
                              <label class="form-label fw-semibold">Nuevo Estado</label>
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
                            <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  {{-- Modal Egreso --}}
                  @if($employee->status !== 'egresado')
                    <div class="modal fade" id="terminateModal{{ $employee->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                          <form method="POST" action="{{ route('rrhh.employees.terminate', $employee) }}">
                            @csrf
                            <div class="modal-header bg-danger text-white">
                              <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Registrar Egreso Laboral</h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-start">
                              <div class="alert alert-warning small">
                                El colaborador pasará a estado <strong>Egresado</strong>. No se eliminarán sus registros ni documentos históricos.
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
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center py-5">
                  <div class="text-muted">
                    <i class="fa-solid fa-users-slash fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-1 fw-semibold">No se encontraron colaboradores registrados</p>
                    <small>Agregue su primer colaborador haciendo clic en "Nuevo Colaborador".</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($employees->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $employees->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
