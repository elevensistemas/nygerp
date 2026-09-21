@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Convenios y Políticas</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-contract text-primary me-2"></i>Convenios y Políticas Laborales</h3>
    </div>
    <a href="{{ route('rrhh.agreements.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
      <i class="fa-solid fa-circle-plus"></i>
      <span>Nuevo Convenio</span>
    </a>
  </div>

  {{-- Filtros --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.agreements.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o código..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Estado</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activos</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
          </select>
        </div>
        <div class="col-md-3 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('rrhh.agreements.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
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
              <th>Nombre del Convenio / Política</th>
              <th>Descripción</th>
              <th class="text-center">Colaboradores</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($agreements as $agreement)
              <tr>
                <td class="ps-3"><span class="badge bg-secondary font-monospace">{{ $agreement->code }}</span></td>
                <td>
                  <div class="fw-bold text-dark">{{ $agreement->name }}</div>
                </td>
                <td>
                  <span class="text-muted small">{{ $agreement->description ? Str::limit($agreement->description, 70) : '-' }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border">{{ $agreement->employees_count }}</span>
                </td>
                <td class="text-center">
                  @if($agreement->is_active)
                    <span class="badge bg-success">Activo</span>
                  @else
                    <span class="badge bg-secondary">Inactivo</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.agreements.edit', $agreement) }}" class="btn btn-outline-secondary" title="Editar convenio">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.agreements.toggle-status', $agreement) }}" method="POST" class="d-inline">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-outline-{{ $agreement->is_active ? 'warning' : 'success' }}" title="{{ $agreement->is_active ? 'Desactivar' : 'Activar' }}">
                        <i class="fa-solid fa-power-off"></i>
                      </button>
                    </form>
                    <form action="{{ route('rrhh.agreements.destroy', $agreement) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este convenio?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar" {{ $agreement->employees_count > 0 ? 'disabled' : '' }}>
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
                    <i class="fa-solid fa-file-contract fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-1 fw-semibold">No se encontraron convenios ni políticas</p>
                    <small>Cree un nuevo convenio haciendo clic en "Nuevo Convenio".</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($agreements->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $agreements->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
