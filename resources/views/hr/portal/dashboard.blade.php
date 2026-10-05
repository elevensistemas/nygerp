@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
          <li class="breadcrumb-item active" aria-current="page">Portal del Empleado</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark">
        <i class="fa-solid fa-user-check text-primary me-2"></i>Hola, {{ $employee->first_name }}!
      </h3>
      <p class="text-muted mb-0 small">Legajo: <strong>{{ $employee->file_number }}</strong> | Área: <strong>{{ $employee->department->name ?? 'General' }}</strong></p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ asset('manuales/instructivo_rrhh.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo completo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
        <i class="fa-solid fa-circle-question fs-5"></i>
      </a>
      <a href="{{ route('rrhh.portal.calendar') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Mi Calendario</span>
      </a>
      <a href="{{ route('rrhh.portal.requests.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Nueva Solicitud</span>
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

  {{-- Tarjetas de Saldos Disponibles --}}
  <div class="row g-3 mb-4">
    @forelse($balances as $bal)
      <div class="col-md-4 col-sm-6 col-12">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid {{ $bal->leaveType->color ?? '#0d6efd' }} !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <span class="badge" style="background-color: {{ $bal->leaveType->color ?? '#0d6efd' }}; color: #fff;">{{ $bal->leaveType->name }}</span>
                <div class="text-muted small mt-1">Período {{ $bal->period_year }}</div>
              </div>
              <div class="text-end">
                <div class="fs-3 fw-bold text-primary">{{ $bal->available_days }}</div>
                <div class="small text-muted">días libres</div>
              </div>
            </div>
            <div class="progress" style="height: 6px;">
              @php
                $totalBase = max(1, $bal->assigned_days + $bal->transferred_days + $bal->positive_adjustments);
                $pctUsed = min(100, round(($bal->used_days / $totalBase) * 100));
                $pctPending = min(100, round(($bal->pending_days / $totalBase) * 100));
              @endphp
              <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pctUsed }}%" title="Tomados: {{ $bal->used_days }}d"></div>
              <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pctPending }}%" title="Pendientes: {{ $bal->pending_days }}d"></div>
            </div>
            <div class="d-flex justify-content-between text-muted small mt-2">
              <span>Asignados: {{ $bal->assigned_days }}d</span>
              <span>Tomados: {{ $bal->used_days }}d</span>
              @if($bal->pending_days > 0)
                <span class="text-warning fw-semibold">Comprometidos: {{ $bal->pending_days }}d</span>
              @endif
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-3 text-muted text-center">
            No tienes saldos de vacaciones cargados para el período actual. Contacta a RR. HH.
          </div>
        </div>
      </div>
    @endforelse
  </div>

  <div class="row g-3">
    {{-- Solicitudes Recientes --}}
    <div class="col-lg-8 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Mis Solicitudes Recientes</h5>
          <a href="{{ route('rrhh.portal.requests') }}" class="btn btn-sm btn-outline-primary">Ver Todas</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Tipo</th>
                  <th>Período</th>
                  <th class="text-center">Días</th>
                  <th class="text-center">Estado</th>
                  <th class="text-end pe-3">Acción</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentRequests as $req)
                  <tr>
                    <td class="ps-3">
                      <span class="badge" style="background-color: {{ $req->leaveType->color ?? '#0d6efd' }}; color: #fff;">
                        {{ $req->leaveType->name }}
                      </span>
                      @if($req->is_half_day)
                        <span class="badge bg-secondary text-white small ms-1">1/2 Día</span>
                      @endif
                    </td>
                    <td>
                      <div class="fw-semibold text-dark">
                        {{ \Carbon\Carbon::parse($req->start_date)->format('d/m/Y') }}
                        @if($req->start_date != $req->end_date)
                          al {{ \Carbon\Carbon::parse($req->end_date)->format('d/m/Y') }}
                        @endif
                      </div>
                      <div class="small text-muted">{{ $req->created_at->format('d/m/Y H:i') }}</div>
                    </td>
                    <td class="text-center fw-bold text-primary">{{ $req->days_requested }}d</td>
                    <td class="text-center">
                      @switch($req->status)
                        @case('pending')
                          <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>Pendiente</span>
                          @break
                        @case('approved')
                          <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Aprobada</span>
                          @break
                        @case('rejected')
                          <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada</span>
                          @break
                        @case('cancelled')
                          <span class="badge bg-secondary">Cancelada</span>
                          @break
                      @endswitch
                    </td>
                    <td class="text-end pe-3">
                      @if($req->status === 'pending')
                        <form action="{{ route('rrhh.portal.requests.cancel', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de cancelar esta solicitud pendiente?');">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar Solicitud">
                            <i class="fa-solid fa-trash-can"></i> Cancelar
                          </button>
                        </form>
                      @else
                        <span class="text-muted small">-</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                      No has realizado solicitudes de ausencias aún.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Próxima Ausencia y Feriados --}}
    <div class="col-lg-4 col-12">
      {{-- Próxima ausencia confirmada --}}
      @if($nextLeave)
        <div class="card border-0 shadow-sm mb-3 bg-light-primary border-primary border">
          <div class="card-body p-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="fa-solid fa-suitcase-rolling text-primary fs-4"></i>
              <h6 class="fw-bold mb-0 text-dark">Próxima Ausencia Programada</h6>
            </div>
            <div class="fw-bold text-dark fs-6">{{ $nextLeave->leaveType->name }}</div>
            <div class="text-muted small">
              Del {{ \Carbon\Carbon::parse($nextLeave->start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($nextLeave->end_date)->format('d/m/Y') }} ({{ $nextLeave->days_requested }} días)
            </div>
          </div>
        </div>
      @endif

      {{-- Próximos Feriados --}}
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-champagne-glasses text-primary me-2"></i>Próximos Feriados</h6>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            @forelse($upcomingHolidays as $holiday)
              <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                <div>
                  <div class="fw-semibold text-dark small">{{ $holiday->name }}</div>
                  <div class="text-muted text-capitalize" style="font-size: 0.75rem;">
                    {{ \Carbon\Carbon::parse($holiday->holiday_date)->locale('es')->isoFormat('dddd D [de] MMMM') }}
                  </div>
                </div>
                <span class="badge bg-light text-primary border font-monospace small">
                  {{ \Carbon\Carbon::parse($holiday->holiday_date)->format('d/m') }}
                </span>
              </li>
            @empty
              <li class="list-group-item text-muted small text-center py-3">No hay feriados próximos este mes.</li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
