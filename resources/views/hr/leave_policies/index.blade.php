@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-types.index') }}">Tipos de Ausencia</a></li>
          <li class="breadcrumb-item active" aria-current="page">Políticas y Escalas</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i>Políticas y Escalas de Vacaciones</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.leave-types.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-tags"></i>
        <span>Ver Tipos</span>
      </a>
      <a href="{{ route('rrhh.leave-policies.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Nueva Política</span>
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
      <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Filtros --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.leave-policies.index') }}" class="row g-2 align-items-center">
        <div class="col-md-4 col-12">
          <select name="agreement_id" class="form-select">
            <option value="">Todos los Convenios / General</option>
            @foreach($agreements as $agr)
              <option value="{{ $agr->id }}" {{ request('agreement_id') == $agr->id ? 'selected' : '' }}>{{ $agr->name }} ({{ $agr->code }})</option>
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
          <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activas</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivas</option>
          </select>
        </div>
        <div class="col-md-3 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['agreement_id', 'leave_type_id', 'status']))
            <a href="{{ route('rrhh.leave-policies.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
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
              <th class="ps-3">Convenio Colectivo</th>
              <th>Tipo de Ausencia</th>
              <th class="text-center">Antigüedad Requerida</th>
              <th class="text-center">Días Otorgados</th>
              <th class="text-center">Cómputo</th>
              <th class="text-center">Traslado a Siguiente Año</th>
              <th class="text-center">Caducidad</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($policies as $policy)
              <tr>
                <td class="ps-3">
                  @if($policy->agreement)
                    <span class="badge bg-light text-dark border">{{ $policy->agreement->code }}</span>
                    <span class="fw-semibold ms-1">{{ $policy->agreement->name }}</span>
                  @else
                    <span class="badge bg-secondary">General (Todos los convenios)</span>
                  @endif
                </td>
                <td>
                  <span class="badge" style="background-color: {{ $policy->leaveType->color ?? '#0d6efd' }}; color: #fff;">{{ $policy->leaveType->code }}</span>
                  <span class="fw-semibold ms-1">{{ $policy->leaveType->name }}</span>
                </td>
                <td class="text-center">
                  @if($policy->max_seniority_years)
                    <span class="fw-bold">{{ $policy->min_seniority_years }}</span> a <span class="fw-bold">{{ $policy->max_seniority_years }}</span> años
                  @else
                    Más de <span class="fw-bold">{{ $policy->min_seniority_years }}</span> años
                  @endif
                </td>
                <td class="text-center">
                  <span class="badge bg-primary fs-6">{{ $policy->days_granted }} días</span>
                </td>
                <td class="text-center">
                  @if($policy->counts_as_working_days)
                    <span class="badge bg-info text-dark">Hábiles</span>
                  @else
                    <span class="badge bg-secondary">Corridos</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($policy->allows_carryover)
                    <span class="badge bg-success" title="Hasta {{ $policy->max_carryover_days ?? 'sin límite' }} días"><i class="fa-solid fa-check me-1"></i>Sí ({{ $policy->max_carryover_days ?? 'ilimitado' }}d)</span>
                  @else
                    <span class="badge bg-light text-muted border">No</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($policy->expiration_months)
                    <span class="small text-muted">{{ $policy->expiration_months }} meses</span>
                  @else
                    <span class="small text-muted">Fin de período</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($policy->is_active)
                    <span class="badge bg-success">Activa</span>
                  @else
                    <span class="badge bg-secondary">Inactiva</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.leave-policies.edit', $policy) }}" class="btn btn-outline-secondary" title="Editar política">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.leave-policies.destroy', $policy) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta política?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar política">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                  <i class="fa-solid fa-sliders fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  No se encontraron políticas configuradas.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($policies->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $policies->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
