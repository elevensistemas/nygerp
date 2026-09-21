@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Sucursales y Bases</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building text-primary me-2"></i>Sucursales y Bases Operativas</h3>
    </div>
    <a href="{{ route('rrhh.branches.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
      <i class="fa-solid fa-circle-plus"></i>
      <span>Nueva Sucursal / Base</span>
    </a>
  </div>

  {{-- Filtros --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.branches.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, código, ciudad o provincia..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Estado</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activas</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivas</option>
          </select>
        </div>
        <div class="col-md-3 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('rrhh.branches.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
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
              <th>Nombre de Sucursal</th>
              <th>Ubicación</th>
              <th>Teléfono / Contacto</th>
              <th>Responsable</th>
              <th class="text-center">Colaboradores</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($branches as $branch)
              <tr>
                <td class="ps-3"><span class="badge bg-secondary font-monospace">{{ $branch->code }}</span></td>
                <td>
                  <div class="fw-bold text-dark">{{ $branch->name }}</div>
                  @if($branch->address)
                    <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i>{{ $branch->address }}</div>
                  @endif
                </td>
                <td>
                  <div>{{ $branch->city ?? '-' }}</div>
                  <small class="text-muted">{{ $branch->province ?? '' }}</small>
                </td>
                <td>{{ $branch->phone ?? '<span class="text-muted small fst-italic">Sin teléfono</span>' }}</td>
                <td>{{ $branch->manager_name ?? '<span class="text-muted small fst-italic">Sin asignar</span>' }}</td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border">{{ $branch->employees_count }}</span>
                </td>
                <td class="text-center">
                  @if($branch->is_active)
                    <span class="badge bg-success">Activa</span>
                  @else
                    <span class="badge bg-secondary">Inactiva</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.branches.edit', $branch) }}" class="btn btn-outline-secondary" title="Editar sucursal">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.branches.toggle-status', $branch) }}" method="POST" class="d-inline">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-outline-{{ $branch->is_active ? 'warning' : 'success' }}" title="{{ $branch->is_active ? 'Desactivar' : 'Activar' }}">
                        <i class="fa-solid fa-power-off"></i>
                      </button>
                    </form>
                    <form action="{{ route('rrhh.branches.destroy', $branch) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta sucursal?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar" {{ $branch->employees_count > 0 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-5">
                  <div class="text-muted">
                    <i class="fa-solid fa-building fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-1 fw-semibold">No se encontraron sucursales o bases</p>
                    <small>Cree una nueva base haciendo clic en "Nueva Sucursal / Base".</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($branches->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $branches->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
