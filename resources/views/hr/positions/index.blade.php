@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Puestos de Trabajo</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-briefcase text-primary me-2"></i>Puestos y Cargos</h3>
    </div>
    <a href="{{ route('rrhh.positions.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
      <i class="fa-solid fa-circle-plus"></i>
      <span>Nuevo Puesto</span>
    </a>
  </div>

  {{-- Filtros --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.positions.index') }}" class="row g-2 align-items-center">
        <div class="col-md-4 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Buscar puesto o código..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3 col-6">
          <select name="department_id" class="form-select">
            <option value="">Todas las áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                {{ $dept->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 col-6">
          <select name="status" class="form-select">
            <option value="">Estado</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activos</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
          </select>
        </div>
        <div class="col-md-3 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['search', 'department_id', 'status']))
            <a href="{{ route('rrhh.positions.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Código</th>
              <th>Nombre del Puesto</th>
              <th>Área / Departamento</th>
              <th class="text-center">Colaboradores</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($positions as $position)
              <tr>
                <td class="ps-3"><span class="badge bg-secondary font-monospace">{{ $position->code }}</span></td>
                <td>
                  <div class="fw-bold text-dark">{{ $position->name }}</div>
                  @if($position->description)
                    <div class="text-muted small">{{ Str::limit($position->description, 60) }}</div>
                  @endif
                </td>
                <td>
                  @if($position->department)
                    <span class="badge bg-light text-dark border">{{ $position->department->name }}</span>
                  @else
                    <span class="text-muted small fst-italic">General / Sin área</span>
                  @endif
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border">{{ $position->active_employees_count }}</span>
                </td>
                <td class="text-center">
                  @if($position->is_active)
                    <span class="badge bg-success">Activo</span>
                  @else
                    <span class="badge bg-secondary">Inactivo</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.positions.edit', $position) }}" class="btn btn-outline-secondary" title="Editar puesto">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.positions.toggle-status', $position) }}" method="POST" class="d-inline">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-outline-{{ $position->is_active ? 'warning' : 'success' }}" title="{{ $position->is_active ? 'Desactivar' : 'Activar' }}">
                        <i class="fa-solid fa-power-off"></i>
                      </button>
                    </form>
                    <form action="{{ route('rrhh.positions.destroy', $position) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este puesto?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar" {{ $position->active_employees_count > 0 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center py-5">
                  <div class="text-muted">
                    <i class="fa-solid fa-briefcase fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-1 fw-semibold">No se encontraron puestos de trabajo</p>
                    <small>Cree un nuevo puesto haciendo clic en "Nuevo Puesto".</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($positions->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $positions->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
