@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Calendario General</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Calendario General de Ausencias y Feriados</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.holidays.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-champagne-glasses"></i>
        <span>Feriados</span>
      </a>
      <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-plane-departure"></i>
        <span>Solicitudes</span>
      </a>
    </div>
  </div>

  {{-- Filtros del Calendario --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.calendar') }}" class="row g-2 align-items-center">
        <div class="col-md-3 col-6">
          <div class="input-group">
            <span class="input-group-text bg-light fw-semibold">Mes</span>
            <input type="month" name="month" class="form-control" value="{{ $selectedMonth ?? date('Y-m') }}" onchange="this.form.submit()">
          </div>
        </div>

        <div class="col-md-3 col-6">
          <select name="department_id" class="form-select">
            <option value="">Todas las Áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
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

        <div class="col-md-3 col-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          @if(request()->hasAny(['department_id', 'leave_type_id', 'employee_id']))
            <a href="{{ route('rrhh.calendar', ['month' => $selectedMonth ?? date('Y-m')]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Grilla del Mes --}}
  @php
    $carbonMonth = \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth ?? date('Y-m'));
    $daysInMonth = $carbonMonth->daysInMonth;
    $startOfWeek = $carbonMonth->copy()->startOfMonth()->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
  @endphp

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
      <h5 class="fw-bold mb-0 text-dark text-capitalize">
        <i class="fa-regular fa-calendar-check text-primary me-2"></i>{{ $carbonMonth->locale('es')->isoFormat('MMMM YYYY') }}
      </h5>
      <div class="btn-group btn-group-sm">
        <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['month' => $carbonMonth->copy()->subMonth()->format('Y-m')])) }}" class="btn btn-outline-secondary">
          <i class="fa-solid fa-chevron-left"></i> Mes Anterior
        </a>
        <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['month' => date('Y-m')])) }}" class="btn btn-outline-secondary">
          Hoy
        </a>
        <a href="{{ route('rrhh.calendar', array_merge(request()->query(), ['month' => $carbonMonth->copy()->addMonth()->format('Y-m')])) }}" class="btn btn-outline-secondary">
          Mes Siguiente <i class="fa-solid fa-chevron-right"></i>
        </a>
      </div>
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

              {{-- Celdas vacías antes del día 1 --}}
              @for($i = 1; $i < $startOfWeek; $i++)
                <td class="bg-light text-muted p-2" style="height: 110px; vertical-align: top;"></td>
                @php $currentCell++; @endphp
              @endfor

              {{-- Días del Mes --}}
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

                  {{-- Feriados del día --}}
                  @foreach($dayHolidays as $h)
                    <div class="badge bg-danger text-white text-truncate w-100 mb-1 text-start" title="{{ $h->name }}">
                      <i class="fa-solid fa-star me-1 small"></i>{{ Str::limit($h->name, 18) }}
                    </div>
                  @endforeach

                  {{-- Ausencias del día --}}
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

              {{-- Celdas vacías al final --}}
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

  {{-- Listado Detallado del Mes --}}
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom">
      <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list text-primary me-2"></i>Detalle de Ausencias en este Mes</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Colaborador</th>
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
                  <span class="badge bg-light text-muted border small">{{ $leave->employee->file_number }}</span>
                </td>
                <td>{{ $leave->employee->department->name ?? 'Sin Área' }}</td>
                <td>
                  <span class="badge" style="background-color: {{ $leave->leaveType->color ?? '#0d6efd' }}; color: #fff;">
                    {{ $leave->leaveType->name }}
                  </span>
                </td>
                <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y') }}</td>
                <td class="text-center fw-bold text-primary">{{ $leave->days_requested }}</td>
                <td class="text-center">
                  @if($leave->status === 'approved')
                    <span class="badge bg-success">Aprobada</span>
                  @else
                    <span class="badge bg-warning text-dark">Pendiente</span>
                  @endif
                </td>
                <td class="text-end pe-3">
                  <a href="{{ route('rrhh.leave-requests.show', $leave) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-eye"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-3 text-muted">
                  No hay ausencias programadas en el mes seleccionado.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
