@extends('layouts.app')

@section('title', 'Agenda de Vencimientos - Pago Choferes')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Pagos > Agenda de vencimientos</h1>
    
  </div>
  <div class="page-actions">
    <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.recibos.index') }}">Ir a recibos</a>
    <a class="btn btn-outline-primary" href="{{ route('pago-choferes.settings.index') }}">Configurar cronogramas</a>
  </div>
</div>

<div class="card card-body mb-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('pago-choferes.agenda.index', ['mes' => $prevMonth]) }}">
        <i class="fa-solid fa-chevron-left"></i>
      </a>
      <div class="fw-semibold px-2">{{ $currentMonth->translatedFormat('F Y') }}</div>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('pago-choferes.agenda.index', ['mes' => $nextMonth]) }}">
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
      <label class="form-label mb-0">Mes</label>
      <input type="month" name="mes" value="{{ $currentMonth->format('Y-m') }}" class="form-control form-control-sm" style="max-width: 170px;">
      <button class="btn btn-sm btn-outline-primary">Ver</button>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="table-responsive">
    <table class="table table-bordered align-middle mb-0">
      <thead>
        <tr>
          <th class="text-center">Lun</th>
          <th class="text-center">Mar</th>
          <th class="text-center">Mie</th>
          <th class="text-center">Jue</th>
          <th class="text-center">Vie</th>
          <th class="text-center">Sab</th>
          <th class="text-center">Dom</th>
        </tr>
      </thead>
      <tbody>
      @foreach($calendarWeeks as $week)
        <tr>
          @foreach($week as $day)
            @php
              $isCurrentMonth = $day->month === $currentMonth->month;
              $dayKey = $day->toDateString();
              $events = $eventsByDate[$dayKey] ?? [];
              $hasEvents = count($events) > 0;
            @endphp
            <td class="align-top {{ $isCurrentMonth ? '' : 'bg-light text-muted' }}" style="height: 130px; min-width: 145px;">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">{{ $day->format('d') }}</span>
                @if($hasEvents)
                  <span class="badge text-bg-warning">{{ count($events) }}</span>
                @endif
              </div>

              @if($hasEvents)
                <div class="d-grid gap-1">
                  @foreach($events as $event)
                    <div class="small border rounded p-1 {{ $event['tipo_periodo'] === 'quincenal' ? 'bg-warning-subtle border-warning-subtle' : 'bg-info-subtle border-info-subtle' }}">
                      <div class="fw-semibold">{{ $event['label'] }}</div>
                      <div>Vence: {{ $event['due_date']->format('d/m/Y') }}</div>
                      <div>Recibos: {{ $event['receipts_count'] }}</div>
                      <div>Total: $ {{ number_format((float) $event['receipts_total'], 2, ',', '.') }}</div>
                      @if($event['receipts_count'] > 0)
                        <details class="mt-1">
                          <summary class="text-primary" style="cursor: pointer;">Ver recibos</summary>
                          <div class="mt-1 d-grid gap-1">
                            @foreach($event['receipts'] as $recibo)
                              <a href="{{ route('pago-choferes.recibos.show', $recibo) }}" class="text-decoration-none">
                                #{{ $recibo->id }} {{ $recibo->displayName() }}
                                @if($recibo->transportista && $recibo->transportista->isNewDriver())
                                  <span class="new-driver-badge" style="font-size: 0.65rem; padding: 0.05rem 0.2rem; margin-left: 0.25rem;" title="Nuevo chofer ({{ $recibo->transportista->days_since_hired }} días dado de alta)"><i class="fa-solid fa-user-plus"></i> {{ $recibo->transportista->days_since_hired }} d</span>
                                @endif
                                | $ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}
                              </a>
                            @endforeach
                          </div>
                        </details>
                      @endif
                    </div>
                  @endforeach
                </div>
              @endif
            </td>
          @endforeach
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">Resumen de vencimientos del mes</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Regla</th>
          <th>Fecha de vencimiento</th>
          <th>Scope</th>
          <th class="text-end">Recibos pendientes</th>
          <th class="text-end">Total pendiente</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
      @forelse($monthEvents as $event)
        <tr>
          <td>{{ $event['label'] }}</td>
          <td>{{ $event['due_date']->format('d/m/Y') }}</td>
          <td>{{ $event['scope'] }}</td>
          <td class="text-end">{{ $event['receipts_count'] }}</td>
          <td class="text-end">$ {{ number_format((float) $event['receipts_total'], 2, ',', '.') }}</td>
          <td style="min-width: 280px;">
            @if($event['receipts_count'] > 0)
              <details>
                <summary class="text-primary" style="cursor: pointer;">Desplegar recibos</summary>
                <div class="mt-2 d-grid gap-1">
                  @foreach($event['receipts'] as $recibo)
                    <a href="{{ route('pago-choferes.recibos.show', $recibo) }}" class="text-decoration-none">
                      #{{ $recibo->id }} {{ $recibo->displayName() }}
                      @if($recibo->transportista && $recibo->transportista->isNewDriver())
                        <span class="new-driver-badge" style="font-size: 0.65rem; padding: 0.05rem 0.2rem; margin-left: 0.25rem;" title="Nuevo chofer ({{ $recibo->transportista->days_since_hired }} días dado de alta)"><i class="fa-solid fa-user-plus"></i> Nuevo ({{ $recibo->transportista->days_since_hired }} d)</span>
                      @endif
                      | {{ optional($recibo->periodo_desde)->format('d/m/Y') }} | $ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}
                    </a>
                  @endforeach
                </div>
              </details>
            @else
              <span class="text-muted">Sin recibos</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center text-muted">No hay reglas de vencimiento configuradas para este mes.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
