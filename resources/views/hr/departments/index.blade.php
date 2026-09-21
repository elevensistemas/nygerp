@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Áreas y Departamentos</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sitemap text-primary me-2"></i>Áreas y Departamentos</h3>
    </div>
    <a href="{{ route('rrhh.departments.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
      <i class="fa-solid fa-circle-plus"></i>
      <span>Nueva Área</span>
    </a>
  </div>

  {{-- Filtros y Búsqueda --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.departments.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o código..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activas</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivas</option>
          </select>
        </div>
        <div class="col-md-4 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('rrhh.departments.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Listado --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Código</th>
              <th>Nombre del Área</th>
              <th>Área Superior</th>
              <th>Responsable</th>
              <th class="text-center">Colaboradores Activos</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($departments as $department)
              <tr>
                <td class="ps-3"><span class="badge bg-secondary font-monospace">{{ $department->code }}</span></td>
                <td>
                  <div class="fw-bold text-dark">{{ $department->name }}</div>
                  @if($department->description)
                    <div class="text-muted small">{{ Str::limit($department->description, 60) }}</div>
                  @endif
                </td>
                <td>{{ $department->parent ? $department->parent->name : '<span class="text-muted fst-italic">Principal</span>' }}</td>
                <td>
                  @if($department->manager)
                    <div class="d-flex align-items-center gap-2">
                      <i class="fa-solid fa-user-tie text-muted"></i>
                      <span>{{ $department->manager->full_name }}</span>
                    </div>
                  @else
                    <span class="text-muted small fst-italic">Sin asignar</span>
                  @endif
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border">{{ $department->active_employees_count }}</span>
                </td>
                <td class="text-center">
                  @if($department->is_active)
                    <span class="badge bg-success">Activa</span>
                  @else
                    <span class="badge bg-secondary">Inactiva</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.departments.edit', $department) }}" class="btn btn-outline-secondary" title="Editar área">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.departments.toggle-status', $department) }}" method="POST" class="d-inline">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-outline-{{ $department->is_active ? 'warning' : 'success' }}" title="{{ $department->is_active ? 'Desactivar' : 'Activar' }}">
                        <i class="fa-solid fa-power-off"></i>
                      </button>
                    </form>
                    <form action="{{ route('rrhh.departments.destroy', $department) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta área?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar" {{ $department->active_employees_count > 0 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-5">
                  <div class="text-muted">
                    <i class="fa-solid fa-sitemap fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-1 fw-semibold">No se encontraron áreas o departamentos</p>
                    <small>Cree una nueva área haciendo clic en "Nueva Área".</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($departments->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $departments->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
