@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.portal.dashboard') }}">Mi Portal</a></li>
          <li class="breadcrumb-item active" aria-current="page">Mi Calendario</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Mi Calendario de Ausencias</h3>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('rrhh.portal.requests.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Solicitar Ausencia</span>
      </a>
    </div>
  </div>

  {{-- Selector de Mes --}}
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
        <a href="{{ route('rrhh.portal.calendar', ['month' => $carbonMonth->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-outline-secondary">
          <i class="fa-solid fa-chevron-left"></i> Mes Anterior
        </a>
        <a href="{{ route('rrhh.portal.calendar', ['month' => date('Y-m')]) }}" class="btn btn-outline-secondary">
          Hoy
        </a>
        <a href="{{ route('rrhh.portal.calendar', ['month' => $carbonMonth->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-outline-secondary">
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

              {{-- Celdas previas --}}
              @for($i = 1; $i < $startOfWeek; $i++)
                <td class="bg-light text-muted p-2" style="height: 100px; vertical-align: top;"></td>
                @php $currentCell++; @endphp
              @endfor

              {{-- Días del Mes --}}
              @while($dayCounter <= $daysInMonth)
                @php
                  $dateStr = sprintf('%04d-%02d-%02d', $carbonMonth->year, $carbonMonth->month, $dayCounter);
                  $isToday = $dateStr === date('Y-m-d');
                  $dayHolidays = $holidaysByDate[$dateStr] ?? [];
                  $dayLeaves = $myLeavesByDate[$dateStr] ?? [];
                  $isWeekend = ($currentCell % 7 == 6 || $currentCell % 7 == 0);
                @endphp

                <td class="p-2 position-relative {{ $isToday ? 'bg-light-primary border-primary border-2' : ($isWeekend ? 'bg-light' : '') }}" style="height: 100px; vertical-align: top;">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold {{ $isToday ? 'badge bg-primary rounded-circle' : ($isWeekend ? 'text-muted' : 'text-dark') }}">
                      {{ $dayCounter }}
                    </span>
                    @if(count($dayHolidays) > 0)
                      <i class="fa-solid fa-champagne-glasses text-danger small" title="Feriado"></i>
                    @endif
                  </div>

                  @foreach($dayHolidays as $h)
                    <div class="badge bg-danger text-white text-truncate w-100 mb-1 text-start" title="{{ $h->name }}">
                      <i class="fa-solid fa-star me-1 small"></i>{{ Str::limit($h->name, 16) }}
                    </div>
                  @endforeach

                  @foreach($dayLeaves as $l)
                    <div class="badge text-truncate w-100 mb-1 text-start" style="background-color: {{ $l->leaveType->color ?? '#0d6efd' }}; color: #fff;" title="{{ $l->leaveType->name }} ({{ $l->status }})">
                      <i class="fa-solid fa-clock me-1 small"></i>{{ $l->leaveType->name }}
                    </div>
                  @endforeach
                </td>

                @if($currentCell % 7 == 0 && $dayCounter < $daysInMonth)
                  </tr><tr>
                @endif

                @php
                  $dayCounter++;
                  $currentCell++;
                @endphp
              @endwhile

              {{-- Celdas finales --}}
              @while(($currentCell - 1) % 7 != 0)
                <td class="bg-light text-muted p-2" style="height: 100px; vertical-align: top;"></td>
                @php $currentCell++; @endphp
              @endwhile
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
