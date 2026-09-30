@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Tipos de Ausencia</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-tags text-primary me-2"></i>Tipos de Ausencia y Licencias</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.leave-policies.index') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-sliders"></i>
        <span>Políticas de Licencias</span>
      </a>
      <a href="{{ route('rrhh.leave-types.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Nuevo Tipo</span>
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
      <form method="GET" action="{{ route('rrhh.leave-types.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Buscar por código, nombre o descripción..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activos</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
          </select>
        </div>
        <div class="col-md-3 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('rrhh.leave-types.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
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
              <th class="ps-3" style="width: 80px;">Color</th>
              <th>Código</th>
              <th>Nombre del Tipo</th>
              <th class="text-center">Cómputo</th>
              <th class="text-center">Descuenta Saldo</th>
              <th class="text-center">Aprobación</th>
              <th class="text-center">Comprobante</th>
              <th class="text-center">Medio Día</th>
              <th class="text-center">Anticipación</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($leaveTypes as $type)
              <tr>
                <td class="ps-3">
                  <span class="d-inline-block rounded-circle shadow-sm" style="width: 22px; height: 22px; background-color: {{ $type->color ?? '#0d6efd' }}; border: 2px solid #fff;" title="{{ $type->color }}"></span>
                </td>
                <td><span class="badge bg-secondary font-monospace">{{ $type->code }}</span></td>
                <td>
                  <div class="fw-bold text-dark">{{ $type->name }}</div>
                  @if($type->description)
                    <div class="text-muted small">{{ Str::limit($type->description, 50) }}</div>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->counts_as_working_days)
                    <span class="badge bg-info text-dark" title="Excluye fines de semana y feriados"><i class="fa-solid fa-business-time me-1"></i>Hábiles</span>
                  @else
                    <span class="badge bg-secondary" title="Incluye sábados, domingos y feriados"><i class="fa-solid fa-calendar-days me-1"></i>Corridos</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->deducts_from_balance)
                    <span class="badge bg-danger"><i class="fa-solid fa-minus me-1"></i>Sí</span>
                    @if($type->allows_negative_balance)
                      <span class="badge bg-warning text-dark small" title="Permite saldo negativo"><i class="fa-solid fa-triangle-exclamation"></i> Neg.</span>
                    @endif
                  @else
                    <span class="badge bg-light text-muted border">No</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->requires_approval)
                    <span class="badge bg-primary">Requerida</span>
                  @else
                    <span class="badge bg-light text-muted border">Directa</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->requires_attachment)
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-paperclip me-1"></i>Obligatorio</span>
                  @else
                    <span class="badge bg-light text-muted border">Opcional</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->allows_half_day)
                    <span class="badge bg-success"><i class="fa-solid fa-circle-half-stroke me-1"></i>Permitido</span>
                  @else
                    <span class="badge bg-light text-muted border">No</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->min_anticipation_days > 0)
                    <span class="badge bg-light text-dark border">{{ $type->min_anticipation_days }} días</span>
                  @else
                    <span class="text-muted small">0 días</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($type->is_active)
                    <span class="badge bg-success">Activo</span>
                  @else
                    <span class="badge bg-secondary">Inactivo</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.leave-types.edit', $type) }}" class="btn btn-outline-secondary" title="Editar tipo">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.leave-types.toggle-status', $type) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-outline-{{ $type->is_active ? 'warning' : 'success' }}" title="{{ $type->is_active ? 'Desactivar' : 'Activar' }}">
                        <i class="fa-solid fa-{{ $type->is_active ? 'ban' : 'check' }}"></i>
                      </button>
                    </form>
                    <form action="{{ route('rrhh.leave-types.destroy', $type) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este tipo de ausencia?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar tipo">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="text-center py-4 text-muted">
                  <i class="fa-solid fa-tags fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  No se encontraron tipos de ausencia configurados.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($leaveTypes->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $leaveTypes->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
