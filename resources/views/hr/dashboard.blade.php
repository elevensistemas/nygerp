@extends('layouts.app')

@section('title', 'Panel de Recursos Humanos - NyG ERP')

@push('styles')
<style>
  .hr-kpi-card {
    border-radius: var(--nyg-radius);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #ffffff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
  }
  .hr-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
  }
  .hr-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: rgba(15, 23, 42, 0.06);
    color: #0f172a;
    transition: all 0.2s ease;
  }
  .hr-kpi-icon.brand-gold {
    background: rgba(245, 158, 11, 0.14);
    color: #b45309;
  }
  .hr-kpi-icon.brand-navy {
    background: rgba(15, 23, 42, 0.08);
    color: #0f172a;
  }
  .hr-kpi-icon.brand-accent {
    background: rgba(37, 99, 235, 0.1);
    color: #1d4ed8;
  }
  .hr-empty-state {
    padding: 2.5rem 1rem;
    text-align: center;
    color: #6c757d;
  }
  .hr-empty-state i {
    font-size: 2.5rem;
    color: #cbd5e1;
    margin-bottom: 0.75rem;
  }
  .hr-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--nyg-ink);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .hr-avatar-sm {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #111827;
    color: var(--nyg-mustard);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
  }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

  {{-- Cabecera del Panel --}}
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 fw-bold mb-1">
        <i class="fa-solid fa-users-gear text-primary me-2"></i>Recursos Humanos
      </h1>
      <p class="text-muted small mb-0">Panel central de gestión de colaboradores, ausencias, legajos y documentación laboral.</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();">
        <i class="fa-solid fa-arrows-rotate me-1"></i>Actualizar
      </button>
      <span class="badge bg-light text-dark border px-3 py-2">
        <i class="fa-regular fa-calendar me-1"></i>{{ \Carbon\Carbon::now()->isoFormat('D [de] MMMM, YYYY') }}
      </span>
    </div>
  </div>

  {{-- Tarjetas KPI Principales (4 columnas) --}}
  <div class="row g-3 mb-4">
    
    {{-- Total Empleados Activos --}}
    <div class="col-xl-3 col-md-6 col-12">
      <div class="card hr-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Colaboradores Activos</div>
            <div class="h2 fw-bold mb-0 mt-1">{{ $stats['total_active_employees'] }}</div>
            <div class="text-muted small mt-1">
              <span class="fw-semibold">{{ $stats['total_employees'] }}</span> registrados en total
            </div>
          </div>
          <div class="hr-kpi-icon brand-gold">
            <i class="fa-solid fa-user-group"></i>
          </div>
        </div>
      </div>
    </div>

    {{-- Ausentes Hoy --}}
    <div class="col-xl-3 col-md-6 col-12">
      <div class="card hr-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Ausencias Hoy</div>
            <div class="h2 fw-bold mb-0 mt-1 {{ $stats['absent_today'] > 0 ? 'text-warning' : 'text-dark' }}">
              {{ $stats['absent_today'] }}
            </div>
            <div class="text-muted small mt-1">
              Personas con licencia activa hoy
            </div>
          </div>
          <div class="hr-kpi-icon brand-navy">
            <i class="fa-solid fa-calendar-xmark"></i>
          </div>
        </div>
      </div>
    </div>

    {{-- Solicitudes de Licencia Pendientes --}}
    <div class="col-xl-3 col-md-6 col-12">
      <div class="card hr-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Licencias Pendientes</div>
            <div class="h2 fw-bold mb-0 mt-1 {{ $stats['pending_leaves'] > 0 ? 'text-dark' : 'text-muted' }}">
              {{ $stats['pending_leaves'] }}
            </div>
            <div class="text-muted small mt-1">
              Esperando revisión / aprobación
            </div>
          </div>
          <div class="hr-kpi-icon brand-gold">
            <i class="fa-solid fa-clock-rotate-left"></i>
          </div>
        </div>
      </div>
    </div>

    {{-- Documentos por Firmar --}}
    <div class="col-xl-3 col-md-6 col-12">
      <div class="card hr-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold text-uppercase">Firmas Pendientes</div>
            <div class="h2 fw-bold mb-0 mt-1 {{ $stats['pending_documents'] > 0 ? 'text-dark' : 'text-muted' }}">
              {{ $stats['pending_documents'] }}
            </div>
            <div class="text-muted small mt-1">
              <span class="text-dark fw-semibold">{{ $stats['signed_documents'] }}</span> firmados conforme
            </div>
          </div>
          <div class="hr-kpi-icon brand-navy">
            <i class="fa-solid fa-file-signature"></i>
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- Fila Secundaria de Métricas Rápidas --}}
  <div class="row g-3 mb-4">
    <div class="col-md-4 col-12">
      <div class="card p-3 bg-light border-0">
        <div class="d-flex align-items-center gap-3">
          <div class="hr-kpi-icon brand-navy">
            <i class="fa-solid fa-user-plus"></i>
          </div>
          <div>
            <div class="fw-bold fs-5 mb-0">{{ $stats['active_onboardings'] }}</div>
            <div class="text-muted small">Procesos de Onboarding en curso</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-12">
      <div class="card p-3 bg-light border-0">
        <div class="d-flex align-items-center gap-3">
          <div class="hr-kpi-icon brand-gold">
            <i class="fa-solid fa-sitemap"></i>
          </div>
          <div>
            <div class="fw-bold fs-5 mb-0">{{ $stats['departments_count'] }}</div>
            <div class="text-muted small">Áreas / Sectores de la empresa</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-12">
      <div class="card p-3 bg-light border-0">
        <div class="d-flex align-items-center gap-3">
          <div class="hr-kpi-icon brand-navy">
            <i class="fa-solid fa-briefcase"></i>
          </div>
          <div>
            <div class="fw-bold fs-5 mb-0">{{ $stats['positions_count'] }}</div>
            <div class="text-muted small">Puestos de trabajo definidos</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Bloques Principales de Información --}}
  <div class="row g-3 mb-4">

    {{-- Columna Izquierda: Solicitudes Pendientes & Cartelera --}}
    <div class="col-lg-8 col-12">

      {{-- Solicitudes de Licencia Pendientes --}}
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
          <span class="hr-section-title">
            <i class="fa-solid fa-envelope-open-text text-warning"></i> Solicitudes de Licencia Pendientes
          </span>
          <span class="badge bg-warning text-dark">{{ $stats['pending_leaves'] }} pendientes</span>
        </div>
        <div class="card-body p-0">
          @if($recentPendingLeaves->count() > 0)
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th class="ps-3">Colaborador</th>
                    <th>Tipo</th>
                    <th>Período</th>
                    <th>Días</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($recentPendingLeaves as $leave)
                    <tr>
                      <td class="ps-3">
                        <div class="d-flex align-items-center gap-2">
                          <div class="hr-avatar-sm">
                            {{ strtoupper(substr($leave->employee->first_name ?? 'C', 0, 1)) }}
                          </div>
                          <div>
                            <div class="fw-semibold">{{ $leave->employee->full_name ?? 'Colaborador' }}</div>
                            <div class="text-muted small">{{ $leave->employee->department->name ?? 'Área' }}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="badge" style="background-color: {{ $leave->leaveType->color ?? '#6c757d' }}; color: #fff;">
                          {{ $leave->leaveType->name ?? 'Licencia' }}
                        </span>
                      </td>
                      <td>
                        <div class="small fw-semibold">{{ $leave->date_from->format('d/m/Y') }} al {{ $leave->date_to->format('d/m/Y') }}</div>
                      </td>
                      <td>
                        <span class="fw-bold">{{ $leave->days_count }}</span> d
                      </td>
                      <td>
                        {!! $leave->status_badge !!}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="hr-empty-state">
              <i class="fa-solid fa-clipboard-check"></i>
              <div class="fw-semibold">No hay solicitudes de licencia pendientes</div>
              <p class="small text-muted mb-0">Todas las solicitudes recibidas se encuentran procesadas.</p>
            </div>
          @endif
        </div>
      </div>

      {{-- Comunicados y Cartelera Reciente --}}
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
          <span class="hr-section-title">
            <i class="fa-solid fa-bullhorn text-primary"></i> Cartelera y Novedades Internas
          </span>
        </div>
        <div class="card-body">
          @if($recentBulletins->count() > 0)
            <div class="row g-3">
              @foreach($recentBulletins as $item)
                <div class="col-md-6 col-12">
                  <div class="card h-100 border p-3 bg-light">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      {!! $item->category_badge !!}
                      <span class="text-muted small">
                        {{ $item->published_at ? $item->published_at->format('d/m/Y') : '-' }}
                      </span>
                    </div>
                    <div class="fw-bold mb-1">{{ $item->title }}</div>
                    <p class="text-muted small mb-0">{{ Str::limit($item->summary ?? strip_tags($item->content), 90) }}</p>
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <div class="hr-empty-state">
              <i class="fa-solid fa-newspaper"></i>
              <div class="fw-semibold">No hay comunicados publicados</div>
              <p class="small text-muted mb-0">Las novedades y comunicados de la empresa se mostrarán aquí.</p>
            </div>
          @endif
        </div>
      </div>

    </div>

    {{-- Columna Derecha: Cumpleaños, Eventos, Onboardings y Auditoría --}}
    <div class="col-lg-4 col-12">

      {{-- Cumpleaños del Mes --}}
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
          <span class="hr-section-title">
            <i class="fa-solid fa-cake-candles text-danger"></i> Cumpleaños del Mes
          </span>
          <span class="badge bg-danger">{{ $birthdaysThisMonth->count() }}</span>
        </div>
        <div class="card-body p-0">
          @if($birthdaysThisMonth->count() > 0)
            <ul class="list-group list-group-flush">
              @foreach($birthdaysThisMonth as $bdayEmp)
                <li class="list-group-item d-flex align-items-center justify-content-between px-3 py-2">
                  <div class="d-flex align-items-center gap-2">
                    <div class="hr-avatar-sm">
                      {{ strtoupper(substr($bdayEmp->first_name, 0, 1)) }}
                    </div>
                    <div>
                      <div class="fw-semibold small">{{ $bdayEmp->full_name }}</div>
                      <div class="text-muted small">{{ $bdayEmp->department->name ?? 'Área' }}</div>
                    </div>
                  </div>
                  <span class="badge bg-light text-danger border">
                    {{ $bdayEmp->birth_date ? $bdayEmp->birth_date->format('d') . ' de ' . $bdayEmp->birth_date->isoFormat('MMMM') : '' }}
                  </span>
                </li>
              @endforeach
            </ul>
          @else
            <div class="hr-empty-state py-3">
              <i class="fa-solid fa-cake-candles"></i>
              <div class="fw-semibold small">Sin cumpleaños registrados este mes</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Onboardings en Proceso --}}
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
          <span class="hr-section-title">
            <i class="fa-solid fa-user-plus text-info"></i> Onboardings Activos
          </span>
          <span class="badge bg-info text-dark">{{ $stats['active_onboardings'] }}</span>
        </div>
        <div class="card-body p-0">
          @if($recentOnboardings->count() > 0)
            <ul class="list-group list-group-flush">
              @foreach($recentOnboardings as $ob)
                <li class="list-group-item px-3 py-2">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-semibold small">{{ $ob->candidate_name }}</span>
                    {!! $ob->status_badge !!}
                  </div>
                  <div class="text-muted small mb-2">{{ $ob->position->name ?? 'Puesto' }} · {{ $ob->department->name ?? 'Área' }}</div>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height: 6px;">
                      <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $ob->progress_percentage }}%;" aria-valuenow="{{ $ob->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span class="small text-muted fw-bold">{{ $ob->progress_percentage }}%</span>
                  </div>
                </li>
              @endforeach
            </ul>
          @else
            <div class="hr-empty-state py-3">
              <i class="fa-solid fa-user-check"></i>
              <div class="fw-semibold small">Sin procesos de ingreso activos</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Auditoría Reciente --}}
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
          <span class="hr-section-title">
            <i class="fa-solid fa-shield-halved text-secondary"></i> Registro de Auditoría
          </span>
        </div>
        <div class="card-body p-0">
          @if($recentAudits->count() > 0)
            <ul class="list-group list-group-flush">
              @foreach($recentAudits as $log)
                <li class="list-group-item px-3 py-2">
                  <div class="d-flex align-items-center justify-content-between">
                    <span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;">{{ $log->action }}</span>
                    <span class="text-muted" style="font-size: 0.72rem;">{{ $log->created_at->diffForHumans() }}</span>
                  </div>
                  <div class="small fw-semibold mt-1">{{ $log->description ?? $log->entity_type }}</div>
                  <div class="text-muted" style="font-size: 0.72rem;">Por: {{ $log->user->name ?? 'Sistema' }}</div>
                </li>
              @endforeach
            </ul>
          @else
            <div class="hr-empty-state py-3">
              <i class="fa-solid fa-shield"></i>
              <div class="fw-semibold small">Sin registros de auditoría recientes</div>
            </div>
          @endif
        </div>
      </div>

    </div>

  </div>

</div>
@endsection
