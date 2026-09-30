@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Feriados y Días No Laborables</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-champagne-glasses text-primary me-2"></i>Feriados y Días No Laborables</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.calendar') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-day"></i>
        <span>Ver Calendario</span>
      </a>
      <a href="{{ route('rrhh.holidays.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Nuevo Feriado</span>
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
      <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Filtros por Año y Tipo --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.holidays.index') }}" class="row g-2 align-items-center">
        <div class="col-md-3 col-6">
          <div class="input-group">
            <span class="input-group-text bg-light fw-semibold">Año</span>
            <select name="year" class="form-select" onchange="this.form.submit()">
              @php $currentYear = (int)date('Y'); @endphp
              @for($y = $currentYear - 2; $y <= $currentYear + 3; $y++)
                <option value="{{ $y }}" {{ request('year', $selectedYear ?? $currentYear) == $y ? 'selected' : '' }}>{{ $y }}</option>
              @endfor
            </select>
          </div>
        </div>
        <div class="col-md-4 col-6">
          <select name="type" class="form-select">
            <option value="">Todos los tipos</option>
            <option value="national" {{ request('type') === 'national' ? 'selected' : '' }}>Nacional</option>
            <option value="provincial" {{ request('type') === 'provincial' ? 'selected' : '' }}>Provincial</option>
            <option value="bridge" {{ request('type') === 'bridge' ? 'selected' : '' }}>Feriado Puente / Turístico</option>
            <option value="custom" {{ request('type') === 'custom' ? 'selected' : '' }}>Día No Laborable Empresa</option>
          </select>
        </div>
        <div class="col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activos</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
          </select>
        </div>
        <div class="col-md-2 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['type', 'status']))
            <a href="{{ route('rrhh.holidays.index', ['year' => $selectedYear ?? date('Y')]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
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
              <th class="ps-3" style="width: 140px;">Fecha</th>
              <th>Día</th>
              <th>Descripción del Feriado</th>
              <th class="text-center">Tipo</th>
              <th class="text-center">Recurrente Anual</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($holidays as $holiday)
              @php
                $carbonDate = \Carbon\Carbon::parse($holiday->holiday_date);
                $dayName = ucfirst($carbonDate->locale('es')->isoFormat('dddd'));
              @endphp
              <tr>
                <td class="ps-3 fw-bold text-dark font-monospace">
                  <i class="fa-regular fa-calendar text-primary me-2"></i>{{ $carbonDate->format('d/m/Y') }}
                </td>
                <td class="text-capitalize text-muted">{{ $dayName }}</td>
                <td>
                  <div class="fw-bold text-dark">{{ $holiday->name }}</div>
                  @if($holiday->notes)
                    <div class="text-muted small">{{ $holiday->notes }}</div>
                  @endif
                </td>
                <td class="text-center">
                  @switch($holiday->type)
                    @case('national')
                      <span class="badge bg-primary">Nacional</span>
                      @break
                    @case('provincial')
                      <span class="badge bg-info text-dark">Provincial</span>
                      @break
                    @case('bridge')
                      <span class="badge bg-warning text-dark">Feriado Puente</span>
                      @break
                    @default
                      <span class="badge bg-secondary">Día Especial NyG</span>
                  @endswitch
                </td>
                <td class="text-center">
                  @if($holiday->is_recurring)
                    <span class="badge bg-success"><i class="fa-solid fa-repeat me-1"></i>Sí</span>
                  @else
                    <span class="badge bg-light text-muted border">No</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($holiday->is_active)
                    <span class="badge bg-success">Activo</span>
                  @else
                    <span class="badge bg-secondary">Inactivo</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('rrhh.holidays.edit', $holiday) }}" class="btn btn-outline-secondary" title="Editar feriado">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <form action="{{ route('rrhh.holidays.destroy', $holiday) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este feriado?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Eliminar feriado">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                  <i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block text-secondary opacity-50"></i>
                  No se encontraron feriados cargados para este año.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if(method_exists($holidays, 'hasPages') && $holidays->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $holidays->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
