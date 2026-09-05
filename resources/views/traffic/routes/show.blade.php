@extends('layouts.app')

@php
  use App\Models\TrafficRouteStop;
  use Illuminate\Support\Facades\Storage;
  $authUser = auth()->user();
  $isTransportista = $authUser && $authUser->isTransportista();
  $assignedTransportistaId = $authUser ? $authUser->assignedTransportistaId() : null;
  $canTrack = $authUser
    && $authUser->isTransportista()
    && $assignedTransportistaId === $route->transportista_id;
  $totalStops = $route->stops->count();
  $completedStops = $route->stops->whereNotNull('completed_at')->count();
  $plannedStops = $route->stops->where('is_extra', false)->sortBy('sequence')->values();
  $extraStops = $route->stops->where('is_extra', true)->values();
  $canAddExtraStop = ($authUser && ! $isTransportista);
  $canReassign = ($authUser && ! $isTransportista);
  $congestionPriority = ['severe', 'heavy', 'moderate', 'low', 'unknown'];
  $resolveCongestionLevel = function ($values) use ($congestionPriority) {
      $normalized = collect((array) $values)
          ->map(fn ($value) => strtolower((string) $value))
          ->filter()
          ->values();

      foreach ($congestionPriority as $level) {
          if ($normalized->contains($level)) {
              return $level;
          }
      }

      return 'unknown';
  };
  $routeStatusDefinitions = [
      'planned' => ['label' => 'Planificada', 'color' => '#6b7280'],
      'in_progress' => ['label' => 'En progreso', 'color' => '#0ea5e9'],
      'completed' => ['label' => 'Completada', 'color' => '#16a34a'],
      'cancelled' => ['label' => 'Cancelada', 'color' => '#ef4444'],
  ];
  $routeStatusKey = $route->status ?? 'planned';
  $routeStatusMeta = $routeStatusDefinitions[$routeStatusKey] ?? $routeStatusDefinitions['planned'];
  if ($route->completed_at) {
      $routeStatusSubtitle = 'Completada ' . $route->completed_at->format('d/m/Y H:i');
  } elseif ($route->started_at) {
      $routeStatusSubtitle = 'Iniciada ' . $route->started_at->format('d/m/Y H:i');
  } elseif ($route->scheduled_date) {
      $routeStatusSubtitle = 'Programada para ' . $route->scheduled_date->format('d/m/Y');
  } else {
      $routeStatusSubtitle = 'Sin fecha asignada';
  }
  $hexToRgba = function (?string $hex, float $alpha = 1.0): string {
      $alpha = min(max($alpha, 0), 1);
      if (! $hex) {
          return sprintf('rgba(15, 23, 42, %.2f)', $alpha);
      }
      $value = ltrim($hex, '#');
      if (strlen($value) === 3) {
          $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
      }
      if (strlen($value) !== 6 || ! ctype_xdigit($value)) {
          return sprintf('rgba(15, 23, 42, %.2f)', $alpha);
      }
      return sprintf(
          'rgba(%d,%d,%d,%.2f)',
          hexdec(substr($value, 0, 2)),
          hexdec(substr($value, 2, 2)),
          hexdec(substr($value, 4, 2)),
          $alpha
      );
  };
@endphp

@section('title', 'Ruta ' . $route->code)

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Ruta {{ $route->code }}</h1>
      <p class="text-muted mb-0">
        <span class="carrier-color-dot" style="background: {{ $route->transportista->color ?? '#2563eb' }};"></span>
        {{ $route->transportista->name ?? 'Transportista' }} · {{ $route->transporte->alias ?? 'Transporte' }}
      </p>
      <div class="route-status-summary">
        <span class="badge route-status-badge" style="background: {{ $routeStatusMeta['color'] }}; color: #fff;">
          {{ $routeStatusMeta['label'] }}
        </span>
        <small class="text-muted mb-0">{{ $routeStatusSubtitle }}</small>
      </div>
    </div>
    <div class ="text-end"><p></p></div>
    <div class="page-actions route-responsive-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.routes.index') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver
      </a>
      <div class="btn-group">
        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fa-solid fa-file-export me-1"></i> Exportar
        </button>
        <ul class="dropdown-menu">
          <li>
            <a class="dropdown-item" href="{{ route('traffic.routes.print', $route) }}">
              <i class="fa-solid fa-file-pdf me-2"></i> PDF
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('traffic.routes.export', [$route, 'format' => 'xlsx']) }}">
              <i class="fa-solid fa-file-excel me-2"></i> Excel
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('traffic.routes.export', [$route, 'format' => 'csv']) }}">
              <i class="fa-solid fa-file-csv me-2"></i> CSV
            </a>
          </li>
        </ul>
      </div>
      @if($canReassign)
        <div class="btn-group">
          <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-truck me-1"></i> Transportista
          </button>
          <ul class="dropdown-menu">
            <li class="px-3 py-1">
              @if($route->sent_at)
                <span class="badge text-bg-success">Enviada</span>
              @else
                <span class="badge text-bg-secondary">No enviada</span>
              @endif
            </li>
            <li>
              <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#reassignCarrierModal">
                <i class="fa-solid fa-user-pen me-2"></i> Reasignar transportista
              </button>
            </li>
            <li>
              <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#carrierHistoryModal">
                <i class="fa-solid fa-clock-rotate-left me-2"></i> Historial transportistas
              </button>
            </li>
            @if($route->sent_at === null)
              <li>
                <form method="POST" action="{{ route('traffic.routes.send', $route) }}" class="d-inline">
                  @csrf
                  <button class="dropdown-item" type="submit">
                    <i class="fa-solid fa-paper-plane me-2"></i> Enviar ruta
                  </button>
                </form>
              </li>
            @endif
          </ul>
        </div>
      @endif
      @if($canTrack && ! $route->started_at)
        <form method="POST" action="{{ route('traffic.routes.start', $route) }}" class="d-inline">
          @csrf
          <button class="btn btn-outline-success" type="submit">
            <i class="fa-solid fa-play me-1"></i> Iniciar ruta
          </button>
        </form>
      @elseif($canTrack && $route->started_at && ! $route->completed_at)
        <span class="badge bg-success text-dark align-self-center">Ruta iniciada {{ optional($route->started_at)->format('d/m/Y H:i') }}</span>
      @elseif($canTrack && $route->completed_at)
        <span class="badge bg-primary text-white align-self-center">Ruta completada {{ optional($route->completed_at)->format('d/m/Y H:i') }}</span>
      @endif
      @if($canAddExtraStop || !($canTrack || $route->started_at))
        <div class="btn-group">
          <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-road me-1"></i> Ruta
          </button>
          <ul class="dropdown-menu">
            @if($canAddExtraStop)
              <li>
                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#adhocStopModal">
                  <i class="fa-solid fa-plus me-2"></i> Agregar parada extra
                </button>
              </li>
            @endif
            @unless($canTrack || $route->started_at)
              <li>
                <a class="dropdown-item" href="{{ route('traffic.routes.edit', $route) }}">
                  <i class="fa-solid fa-pen-to-square me-2"></i> Editar ruta
                </a>
              </li>
              <li>
                <form method="POST" action="{{ route('traffic.routes.regenerate', $route) }}" class="d-inline" data-confirm="?Recalcular la ruta con estas direcciones?">
                  @csrf
                  <button class="dropdown-item" type="submit">
                    <i class="fa-solid fa-rotate me-2"></i> Re-generar ruta
                  </button>
                </form>
              </li>
              <li>
                <form method="POST" action="{{ route('traffic.routes.duplicate', $route) }}" class="d-inline">
                  @csrf
                  <button class="dropdown-item" type="submit">
                    <i class="fa-solid fa-copy me-2"></i> Copiar y crear nueva
                  </button>
                </form>
              </li>
            @endunless
          </ul>
        </div>
      @endif
    </div>
</div>

@if($canAddExtraStop)
  <div class="modal fade" id="adhocStopModal" tabindex="-1" aria-labelledby="adhocStopModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="adhocStopModalLabel">Agregar parada extra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="{{ route('traffic.routes.extras.store', $route) }}">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Cliente *</label>
              <select class="form-select js-adhoc-client-select" name="party_id" data-placeholder="Seleccionar cliente" required>
                <option value="">Seleccionar...</option>
                @foreach($clients as $client)
                  <option value="{{ $client->id }}">{{ $client->business_name ?: $client->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Dirección *</label>
              <div class="position-relative js-adhoc-address-wrapper">
                <input type="text" name="address" class="form-control js-adhoc-address-input" autocomplete="off" required>
                <div class="list-group position-absolute w-100 js-adhoc-address-suggestions" style="z-index:1000; max-height:240px; overflow-y:auto; top:100%; left:0;"></div>
              </div>
              <input type="hidden" name="latitude" data-adhoc-lat>
              <input type="hidden" name="longitude" data-adhoc-lng>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Etiqueta</label>
                <input type="text" name="label" class="form-control" placeholder="Ej: Entrega rápida">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="contact_phone" class="form-control">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Nombre de contacto</label>
                <input type="text" name="contact_name" class="form-control">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-control" placeholder="Indicaciones opcionales">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar y agregar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif

@if($canReassign)
  <div class="modal fade" id="reassignCarrierModal" tabindex="-1" aria-labelledby="reassignCarrierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="reassignCarrierModalLabel">Reasignar transportista</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="{{ route('traffic.routes.reassign', $route) }}" id="reassignCarrierForm">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Transportista *</label>
              <select class="form-select" name="transportista_id" id="reassignCarrierSelect" required>
                <option value="">Seleccionar...</option>
                @foreach($transportistas as $transportista)
                  <option value="{{ $transportista->id }}">{{ $transportista->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Transporte *</label>
              <select class="form-select" name="transporte_id" id="reassignVehicleSelect" required>
                <option value="">Seleccionar...</option>
              </select>
              <div class="form-text">Debe pertenecer al transportista seleccionado.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-warning">Reasignar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="carrierHistoryModal" tabindex="-1" aria-labelledby="carrierHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="carrierHistoryModalLabel">Historial de transportistas</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Transportista</th>
                  <th>Transporte</th>
                  <th>Asignado</th>
                  <th>Paradas completadas</th>
                </tr>
              </thead>
              <tbody>
                @forelse($assignmentHistory as $entry)
                  <tr>
                    <td>{{ $entry['transportista']->name ?? 'N/D' }}</td>
                    <td>{{ $entry['transporte']->alias ?? 'N/D' }}</td>
                    <td>{{ optional($entry['assigned_at'])->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                    <td>{{ $entry['completed_stops'] ?? 0 }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">Sin historial disponible.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
@endif

<div class="row g-3 mb-4 route-kpis">
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Fecha</p>
          <h4 class="mb-0">{{ optional($route->scheduled_date)->format('d/m/Y') ?? 'Sin fecha' }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Distancia</p>
          <h4 class="mb-0">{{ $route->distance_km ? $route->distance_km . ' km' : 'n/d' }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Duración estimada</p>
          <h4 class="mb-0">{{ $route->duration_formatted ?? 'n/d' }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <p class="text-muted mb-1">Tránsito</p>
          <h4 class="mb-0">{{ $route->traffic_summary ?? 'No evaluado' }}</h4>
        </div>
      </div>
    </div>
</div>

@if($route->order)
  <div class="alert alert-secondary border-0 shadow-sm mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
      <div>
        <strong>Pedido {{ $route->order->order_number }}</strong>
        <div class="text-muted small">Cliente {{ $route->order->client_name }}</div>
      </div>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('traffic.orders.show', $route->order) }}">
        <i class="fa-solid fa-eye me-1"></i> Ver pedido
      </a>
    </div>
    <div class="row mt-3 g-3">
      <div class="col-md-4">
        <p class="text-muted small mb-1">Fecha del pedido</p>
        <strong>{{ optional($route->order->order_date)->format('d/m/Y') ?? 'Sin fecha' }}</strong>
      </div>
      <div class="col-md-4">
        <p class="text-muted small mb-1">Direcciones</p>
        <strong>{{ $route->order->addresses->count() }}</strong>
      </div>
      <div class="col-md-4">
        <p class="text-muted small mb-1">Estado</p>
        <span class="badge bg-info text-dark">{{ ucfirst($route->order->status) }}</span>
      </div>
    </div>
    @if($route->order->delivery_instructions)
      <p class="mt-3 mb-0 small text-muted">Instrucciones: {{ $route->order->delivery_instructions }}</p>
    @endif
  </div>
@endif

<div class="row g-4">
  <div class="col-lg-7">
    <div class="row g-4">
      <div class="col-12">
        <div class="card shadow-sm border-0 h-100 route-panel">
          <div class="card-header bg-white">
            <h2 class="h5 mb-0">Paradas planificadas</h2>
          </div>
          <div class="list-group list-group-flush route-stop-list">
            @foreach($plannedStops as $stop)
  @php
            $statusOptions = TrafficRouteStop::statusOptions();
                $currentStatus = $stop->status ?: TrafficRouteStop::STATUS_PENDING;
                $statusLabel = $statusOptions[$currentStatus] ?? 'Pendiente';
                $reasonBadge = null;
                $stopCompleted = (bool) $stop->completed_at;
                $cardClasses = 'list-group-item route-stop-card';
                $rowStyle = '';
                $isDelivered = $currentStatus === TrafficRouteStop::STATUS_ENTREGADO;
                $isFailed = $currentStatus === TrafficRouteStop::STATUS_NO_ENTREGADO;
                if ($isDelivered) {
                  $cardClasses .= ' route-stop-card--delivered';
                  $rowStyle = 'background: rgba(34, 197, 94, 0.12); border-color: rgba(34, 197, 94, 0.35);';
                } elseif ($isFailed) {
                  $cardClasses .= ' route-stop-card--no-entregado';
                  $rowStyle = 'background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); border-left: 4px solid rgba(239, 68, 68, 0.45);';
                } elseif ($stopCompleted) {
                  $rowStyle = 'background: #f8f9fa; border-color: rgba(0, 0, 0, 0.05);';
                }
                $recipientIsOwner = $stop->recipient_is_owner ?? true;
                $requiresDni = TrafficRouteStop::requiresRecipientDni($currentStatus, $recipientIsOwner);
                $selectedReason = $stop->delivery_reason_id;
                $hasStatus = $stop->events->count() > 0 || $currentStatus !== TrafficRouteStop::STATUS_PENDING;
                if ($currentStatus === TrafficRouteStop::STATUS_NO_ENTREGADO && $stop->deliveryReason) {
                  $reasonBadge = [
                    'name' => $stop->deliveryReason->name,
                    'color' => $stop->deliveryReason->color ?? '#6c757d',
                  ];
                }
                $lastEvent = $stop->events->last();
                $lastStatus = $lastEvent ? ($statusOptions[$lastEvent->status] ?? $lastEvent->status) : $statusLabel;
              @endphp
              <div class="{{ $cardClasses }}"
                   id="stopCard{{ $stop->id }}"
                   data-stop-card
                   data-stop-sequence="{{ $stop->sequence }}"
                   data-stop-completed="{{ $stopCompleted ? 1 : 0 }}"
                   style="{{ $rowStyle }}">
                <div class="d-flex justify-content-between">
                  <div>
                    <strong>#{{ $stop->sequence }} {{ $stop->label ?? 'Punto' }}</strong>
                  </div>
                  <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-light text-dark">{{ $statusLabel }}</span>
                    @if($reasonBadge)
                      <span class="badge text-white" style="background: {{ $reasonBadge['color'] }};">{{ $reasonBadge['name'] }}</span>
                    @endif
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#stopPhotos{{ $stop->id }}"
                            title="Ver fotos registradas">
                      <i class="fa-solid fa-camera"></i>
                      @if($stop->photos->count())
                        <span class="badge bg-primary text-white rounded-pill ms-1">{{ $stop->photos->count() }}</span>
                      @endif
                    </button>
                  </div>
                </div>
    <div>{{ $stop->address }}</div>
    @if($stop->contact_name)
      <div class="text-muted small">Contacto: {{ $stop->contact_name }} ({{ $stop->contact_phone ?: 'sin telefono' }})</div>
    @endif
    @if($stop->notes)
      <div class="text-muted small">Notas: {{ $stop->notes }}</div>
    @endif
    @if($stop->latitude && $stop->longitude)
      @php
        $googleUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $stop->latitude . ',' . $stop->longitude;
      @endphp
      <div class="mt-2">
        <a class="btn btn-sm btn-outline-primary" href="{{ $googleUrl }}" target="_blank" rel="noopener">
          <i class="fa-brands fa-google"></i> Abrir en Google Maps
        </a>
      </div>
    @endif
    <div class="text-muted small mt-1">
      Intento: {{ optional($stop->attempted_at)->format('d/m/Y H:i') ?? 'Sin registro' }} -
      Completado: {{ optional($stop->completed_at)->format('d/m/Y H:i') ?? 'Sin registro' }}
    </div>
    @if($stopCompleted)
      <div class="text-muted small mt-2">
        Visitado a las {{ optional($stop->completed_at)->format('H:i') }}
      </div>
      @if($stop->status === TrafficRouteStop::STATUS_ENTREGADO)
        <div class="text-muted small">Recibió: {{ $recipientIsOwner ? 'Titular' : 'Otra persona' }}{{ $stop->recipient_dni ? ' (DNI ' . $stop->recipient_dni . ')' : '' }}</div>
      @elseif($stop->status === TrafficRouteStop::STATUS_NO_ENTREGADO && $stop->deliveryReason)
        <div class="text-muted small">Motivo: {{ $stop->deliveryReason->name }}</div>
      @endif
    @endif
    @include('traffic.routes.partials.stop-photos', ['stop' => $stop, 'route' => $route])
    @if($canTrack && $route->started_at)
      @php
        $lastEvent = $stop->events->last();
        $statusOptions = \App\Models\TrafficRouteStop::statusOptions();
        $lastStatus = $lastEvent ? ($statusOptions[$lastEvent->status] ?? $lastEvent->status) : $statusLabel;
      @endphp
      <div class="mt-2 text-muted small">
        Elegí si se entregó o no, y completá los datos extra solo cuando aplique.
      </div>
      <form class="mt-3"
            method="POST"
            enctype="multipart/form-data"
            action="{{ route('traffic.routes.stops.update', ['route' => $route, 'stop' => $stop]) }}"
            data-stop-form
            data-stop-id="{{ $stop->id }}"
            data-stop-modal-target="#stopModal{{ $stop->id }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="{{ $currentStatus }}" data-stop-field="status">
        <input type="hidden" name="delivery_reason_id" value="{{ $selectedReason }}" data-stop-field="delivery_reason_id">
        <input type="hidden" name="recipient_is_owner" value="{{ $recipientIsOwner ? 1 : 0 }}" data-stop-field="recipient_is_owner">
        <input type="hidden" name="recipient_dni" value="{{ $stop->recipient_dni }}" data-stop-field="recipient_dni">
        <input type="hidden" name="recipient_name" value="{{ $stop->recipient_name }}" data-stop-field="recipient_name">
        <input type="hidden" name="status_notes" value="{{ $stop->status_notes }}" data-stop-field="status_notes">

          <div class="d-flex flex-wrap align-items-center gap-3">
            <div>
              <div class="small text-muted">Estado</div>
              <span class="badge text-bg-light" data-stop-summary="status">{{ $statusLabel }}</span>
            </div>
          <div>
            <div class="small text-muted">Motivo</div>
            <span class="badge text-bg-light" data-stop-summary="reason">
              {{ $currentStatus === TrafficRouteStop::STATUS_NO_ENTREGADO && $stop->deliveryReason ? $stop->deliveryReason->name : '—' }}
            </span>
          </div>
          <div>
            <div class="small text-muted">Receptor</div>
            <span class="badge text-bg-light" data-stop-summary="receiver">
              {{ $recipientIsOwner ? 'Titular' : 'Otra persona' }}{{ $stop->recipient_dni ? ' (DNI ' . $stop->recipient_dni . ')' : '' }}
            </span>
          </div>
          <button type="button"
                  class="btn btn-sm {{ $hasStatus ? 'btn-outline-warning' : 'btn-outline-primary' }} js-change-stop"
                  data-stop-target="#stopModal{{ $stop->id }}"
                  data-has-status="{{ $hasStatus ? 1 : 0 }}">
            Cambiar
          </button>
        </div>
      </form>

      <div class="modal fade" id="stopModal{{ $stop->id }}" tabindex="-1" aria-labelledby="stopModalLabel{{ $stop->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="stopModalLabel{{ $stop->id }}">Actualizar entrega</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-stop-modal>
              <div class="mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" data-stop-modal-status>
                  <option value="">Seleccionar...</option>
                  <option value="{{ TrafficRouteStop::STATUS_ENTREGADO }}">Entregado</option>
                  <option value="{{ TrafficRouteStop::STATUS_NO_ENTREGADO }}">No entregado</option>
                </select>
              </div>
              <div class="mb-3" data-stop-modal-reason-wrapper>
                <label class="form-label">Motivo de no entrega</label>
                <select class="form-select" data-stop-modal-reason>
                  <option value="">Seleccionar...</option>
                  @foreach($deliveryReasons as $reason)
                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                  @endforeach
                </select>
                <div class="form-text text-muted">Solo cuando no se entregó.</div>
              </div>
              <div class="mb-3" data-stop-modal-owner-wrapper>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" data-stop-modal-owner id="ownerModal{{ $stop->id }}">
                  <label class="form-check-label" for="ownerModal{{ $stop->id }}">Recibió el titular</label>
                </div>
              </div>
              <div class="mb-3" data-stop-modal-dni-wrapper>
                <label class="form-label">DNI receptor</label>
                <input type="text" class="form-control" data-stop-modal-dni>
                <div class="form-text text-muted">Siempre obligatorio.</div>
              </div>
              <div class="mb-3 d-none" data-stop-modal-recipient-name-wrapper>
                <label class="form-label">Nombre y apellido receptor</label>
                <input type="text" class="form-control" data-stop-modal-recipient-name>
                <div class="form-text text-muted">Solo si no es titular.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea class="form-control" rows="2" data-stop-modal-notes></textarea>
                <div class="form-text text-muted">Info adicional opcional.</div>
              </div>
              <div class="mb-3" data-stop-camera-wrapper>
                <label class="form-label">Fotos desde la cámara</label>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-stop-camera-toggle>
                    <i class="fa-solid fa-video-camera me-1"></i> Abrir cámara
                  </button>
                  <button type="button" class="btn btn-primary btn-sm" data-stop-camera-capture disabled>
                    <i class="fa-solid fa-camera me-1"></i> Capturar
                  </button>
                </div>
                <div class="ratio ratio-16x9 mt-3 d-none" data-stop-camera-preview>
                  <video autoplay playsinline muted data-stop-camera-video></video>
                </div>
                <div class="form-text text-danger mt-1 d-none" data-stop-camera-error></div>
                <div class="d-flex flex-wrap gap-2 mt-3" data-stop-camera-thumbs></div>
                <div class="form-text text-muted small mt-1">Las fotos se guardan junto al registro cuando aplicás los cambios.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-primary" data-stop-modal-apply>Aplicar</button>
            </div>
          </div>
        </div>
      </div>
    @endif
    <div class="mt-3">
      <button class="btn btn-outline-secondary btn-sm" type="button"
              data-bs-toggle="modal" data-bs-target="#stopHistory{{ $stop->id }}">
        Historial (último: {{ $lastStatus }})
      </button>
    </div>
  </div>
@endforeach
          </div>
        </div>
      </div>
      @if($extraStops->count())
        <div class="col-12">
          <div class="card shadow-sm border-0 h-100 mt-3">
            <div class="card-header bg-white">
              <h2 class="h5 mb-0">Paradas agregadas en ruta</h2>
            </div>
            <div class="list-group list-group-flush route-stop-list">
              @foreach($extraStops as $stop)
                @php
                  $statusOptions = TrafficRouteStop::statusOptions();
                  $currentStatus = $stop->status ?: TrafficRouteStop::STATUS_PENDING;
                  $statusLabel = $statusOptions[$currentStatus] ?? 'Pendiente';
                  $reasonBadge = null;
                  $stopCompleted = (bool) $stop->completed_at;
                  $rowStyle = $stopCompleted ? 'background: #f8f9fa; border-color: rgba(0, 0, 0, 0.05);' : '';
                  $recipientIsOwner = $stop->recipient_is_owner ?? true;
                  $hasStatus = $stop->events->count() > 0 || $currentStatus !== TrafficRouteStop::STATUS_PENDING;
                  $selectedReason = $stop->delivery_reason_id;
                  if ($currentStatus === TrafficRouteStop::STATUS_NO_ENTREGADO && $stop->deliveryReason) {
                    $reasonBadge = [
                      'name' => $stop->deliveryReason->name,
                      'color' => $stop->deliveryReason->color ?? '#6c757d',
                    ];
                  }
                @endphp
                <div class="list-group-item" style="{{ $rowStyle }}">
                  <div class="d-flex justify-content-between">
                    <strong>{{ $stop->label ?? 'Parada extra' }}</strong>
                  <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-light text-dark">{{ $statusLabel }}</span>
                    @if($reasonBadge)
                      <span class="badge text-white" style="background: {{ $reasonBadge['color'] }};">{{ $reasonBadge['name'] }}</span>
                    @endif
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#stopPhotos{{ $stop->id }}"
                            title="Ver fotos registradas">
                      <i class="fa-solid fa-camera"></i>
                      @if($stop->photos->count())
                        <span class="badge bg-primary text-white rounded-pill ms-1">{{ $stop->photos->count() }}</span>
                      @endif
                    </button>
                  </div>
                  </div>
                  <div>{{ $stop->address }}</div>
                  @if($stop->contact_name)
                    <div class="text-muted small">Contacto: {{ $stop->contact_name }} ({{ $stop->contact_phone ?: 'sin telefono' }})</div>
                  @endif
                  @if($stop->notes)
                    <div class="text-muted small">Notas: {{ $stop->notes }}</div>
                  @endif
                  @if($stop->latitude && $stop->longitude)
                    @php
                      $googleUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $stop->latitude . ',' . $stop->longitude;
                    @endphp
                    <div class="mt-2">
                      <a class="btn btn-sm btn-outline-primary" href="{{ $googleUrl }}" target="_blank" rel="noopener">
                        <i class="fa-brands fa-google"></i> Abrir en Google Maps
                      </a>
                    </div>
                  @endif
                  <div class="text-muted small mt-1">
                    Intento: {{ optional($stop->attempted_at)->format('d/m/Y H:i') ?? 'Sin registro' }} -
                    Completado: {{ optional($stop->completed_at)->format('d/m/Y H:i') ?? 'Sin registro' }}
                  </div>
    @if($stopCompleted)
      <div class="text-muted small mt-2">
        Visitado a las {{ optional($stop->completed_at)->format('H:i') }}
      </div>
      @if($stop->status === TrafficRouteStop::STATUS_ENTREGADO)
        <div class="text-muted small">Recibió: {{ $recipientIsOwner ? 'Titular' : 'Otra persona' }}{{ $stop->recipient_dni ? ' (DNI ' . $stop->recipient_dni . ')' : '' }}</div>
      @elseif($stop->status === TrafficRouteStop::STATUS_NO_ENTREGADO && $stop->deliveryReason)
        <div class="text-muted small">Motivo: {{ $stop->deliveryReason->name }}</div>
      @endif
    @endif
    @include('traffic.routes.partials.stop-photos', ['stop' => $stop, 'route' => $route])
    @if($canTrack)
                    @php
                      $lastEvent = $stop->events->last();
                      $statusOptions = \App\Models\TrafficRouteStop::statusOptions();
                      $lastStatus = $lastEvent ? ($statusOptions[$lastEvent->status] ?? $lastEvent->status) : $statusLabel;
                    @endphp
                    <div class="mt-2 text-muted small">
                      Elegí si se entregó o no, y completá los datos extra solo cuando aplique.
                    </div>
                    <form class="mt-3"
                          method="POST"
                          action="{{ route('traffic.routes.stops.update', ['route' => $route, 'stop' => $stop]) }}"
                          data-stop-form
                          data-stop-id="{{ $stop->id }}"
                          data-stop-modal-target="#stopModal{{ $stop->id }}">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="status" value="{{ $currentStatus }}" data-stop-field="status">
                      <input type="hidden" name="delivery_reason_id" value="{{ $selectedReason }}" data-stop-field="delivery_reason_id">
                      <input type="hidden" name="recipient_is_owner" value="{{ $recipientIsOwner ? 1 : 0 }}" data-stop-field="recipient_is_owner">
                      <input type="hidden" name="recipient_dni" value="{{ $stop->recipient_dni }}" data-stop-field="recipient_dni">
                      <input type="hidden" name="recipient_name" value="{{ $stop->recipient_name }}" data-stop-field="recipient_name">
                      <input type="hidden" name="status_notes" value="{{ $stop->status_notes }}" data-stop-field="status_notes">

                      <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                          <div class="small text-muted">Estado</div>
                          <span class="badge text-bg-light" data-stop-summary="status">{{ $statusLabel }}</span>
                        </div>
                        <div>
                          <div class="small text-muted">Motivo</div>
                          <span class="badge text-bg-light" data-stop-summary="reason">
                            {{ $currentStatus === TrafficRouteStop::STATUS_NO_ENTREGADO && $stop->deliveryReason ? $stop->deliveryReason->name : '—' }}
                          </span>
                        </div>
                        <div>
                          <div class="small text-muted">Receptor</div>
                          <span class="badge text-bg-light" data-stop-summary="receiver">
                            {{ $recipientIsOwner ? 'Titular' : 'Otra persona' }}{{ $stop->recipient_dni ? ' (DNI ' . $stop->recipient_dni . ')' : '' }}
                          </span>
                        </div>
                        <button type="button"
                                class="btn btn-sm {{ $hasStatus ? 'btn-outline-warning' : 'btn-outline-primary' }} js-change-stop"
                                data-stop-target="#stopModal{{ $stop->id }}"
                                data-has-status="{{ $hasStatus ? 1 : 0 }}">
                          Cambiar
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                data-bs-toggle="modal" data-bs-target="#stopHistory{{ $stop->id }}">
                          Historial (último: {{ $lastStatus }})
                      </button>
                    </div>
                    </form>

                    <div class="modal fade" id="stopModal{{ $stop->id }}" tabindex="-1" aria-labelledby="stopModalLabel{{ $stop->id }}" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="stopModalLabel{{ $stop->id }}">Actualizar entrega</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body" data-stop-modal>
                            <div class="mb-3">
                              <label class="form-label">Estado</label>
                              <select class="form-select" data-stop-modal-status>
                                <option value="">Seleccionar...</option>
                                <option value="{{ TrafficRouteStop::STATUS_ENTREGADO }}">Entregado</option>
                                <option value="{{ TrafficRouteStop::STATUS_NO_ENTREGADO }}">No entregado</option>
                              </select>
                            </div>
                            <div class="mb-3" data-stop-modal-reason-wrapper>
                              <label class="form-label">Motivo de no entrega</label>
                              <select class="form-select" data-stop-modal-reason>
                                <option value="">Seleccionar...</option>
                                @foreach($deliveryReasons as $reason)
                                  <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                              </select>
                              <div class="form-text text-muted">Solo cuando no se entregó.</div>
                            </div>
                            <div class="mb-3" data-stop-modal-owner-wrapper>
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" data-stop-modal-owner id="ownerModal{{ $stop->id }}">
                                <label class="form-check-label" for="ownerModal{{ $stop->id }}">Recibió el titular</label>
                              </div>
                            </div>
                            <div class="mb-3" data-stop-modal-dni-wrapper>
                              <label class="form-label">DNI receptor</label>
                              <input type="text" class="form-control" data-stop-modal-dni>
                              <div class="form-text text-muted">Solo si recibe otra persona.</div>
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Notas</label>
                              <textarea class="form-control" rows="2" data-stop-modal-notes></textarea>
                              <div class="form-text text-muted">Info adicional opcional.</div>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" data-stop-modal-apply>Aplicar</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @endif
      <div class="col-12">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <h2 class="h5 mb-0">Detalle de tráfico</h2>
            @if($route->congestion_level)
              <span class="badge text-bg-light">{{ \Illuminate\Support\Str::title($route->congestion_level) }}</span>
            @endif
          </div>
          @php
            $report = $route->traffic_report ?? [];
            $counts = $report['congestion_counts'] ?? [];
            $colorMap = [
              'low'      => 'bg-success',
              'moderate' => 'bg-warning text-dark',
              'heavy'    => 'bg-danger text-white',
              'severe'   => 'bg-danger text-white',
              'unknown'  => 'bg-primary text-white',
            ];
          @endphp
          <div class="card-body">
            @if($counts)
              <ul class="list-unstyled mb-3">
                @foreach($counts as $level => $count)
                  <li class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-capitalize">{{ $level }}</span>
                    <span class="badge {{ $colorMap[strtolower($level)] ?? 'bg-secondary' }}">{{ $count }}</span>
                  </li>
                @endforeach
              </ul>
              <p class="text-muted small mb-0">{{ $route->traffic_summary ?? 'Sin resumen' }}</p>
            @else
              <p class="text-muted mb-0">No se recibieron datos de congestión para esta ruta.</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    @php
      $legs = collect(data_get($route->raw_payload, 'trip.legs', []))->values();
      $orderedStops = $plannedStops;
    @endphp
    @if($orderedStops->count() > 1)
      <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white">
          <h2 class="h5 mb-0">Paso a paso</h2>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Orden</th>
                <th>Detalle</th>
                <th>Distancia aprox.</th>
                <th>Duración aprox.</th>
                <th>Tránsito</th>
              </tr>
            </thead>
            <tbody>
              @for($i = 0; $i < $orderedStops->count() - 1; $i++)
                @php
                  $from = $orderedStops[$i];
                  $to = $orderedStops[$i + 1];
                  $leg = $legs[$i] ?? [];
                  $distanceKm = isset($leg['distance']) ? round($leg['distance'] / 1000, 2) : null;
                  $durationMin = isset($leg['duration']) ? round($leg['duration'] / 60) : null;
                @endphp
                <tr>
                  <td class="fw-semibold">#{{ $i + 1 }} → #{{ $i + 2 }}</td>
                  <td>
                    <div class="small text-muted">Desde</div>
                    <div>{{ $from->address }}</div>
                    <div class="small text-muted mt-1">Hasta</div>
                    <div>{{ $to->address }}</div>
                  </td>
                  <td>{{ $distanceKm ? $distanceKm . ' km' : 'n/d' }}</td>
                  <td>{{ $durationMin ? $durationMin . ' min' : 'n/d' }}</td>
                  <td>
                  @php
                    $congestionLevel = ($resolveCongestionLevel)(data_get($route->raw_payload, "trip.legs.$i.annotation.congestion", []));
                    $badgeClasses = [
                      'low'      => 'bg-success',
                      'moderate' => 'bg-warning text-dark',
                      'heavy'    => 'bg-danger text-white',
                      'severe'   => 'bg-danger text-white',
                      'unknown'  => 'bg-primary text-white',
                    ];
                    $badgeClass = $badgeClasses[strtolower((string) $congestionLevel)] ?? 'bg-secondary';
                  @endphp
                    <span class="badge {{ $badgeClass }}">{{ ucfirst($congestionLevel) }}</span>
                  </td>
                </tr>
              @endfor
            </tbody>
          </table>
        </div>
      </div>
    @endif

  </div>
  <div class="col-lg-5">

    <div class="card shadow-sm border-0 mb-4 route-panel">
      <div class="card-header bg-white">
        <h2 class="h5 mb-0">Mapa</h2>
      </div>
      <div class="card-body p-0">
        <div id="routeMap" class="map-frame route-map"></div>
      </div>
    </div>
  </div>
</div>


<div class="row g-3 mb-4 route-kpis">
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <p class="text-muted mb-1">Duración real</p>
        <h4 class="mb-0">{{ $route->actual_duration_formatted ?? 'Pendiente' }}</h4>
        @if($route->delay_formatted)
          <small class="text-danger">Demora {{ $route->delay_formatted }}</small>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <p class="text-muted mb-1">Paradas completadas</p>
        <h4 class="mb-0">{{ $completedStops }} / {{ $totalStops }}</h4>
        <div class="progress mt-2" style="height:6px;">
          <div class="progress-bar" role="progressbar" style="width: {{ $totalStops ? ($completedStops / $totalStops) * 100 : 0 }}%;"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <p class="text-muted mb-1">Paradas restantes</p>
        <h4 class="mb-0">{{ max(0, $totalStops - $completedStops) }}</h4>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
  <style>
    .route-responsive-actions .btn {
      border-radius: 999px;
    }
    .route-status-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      align-items: center;
      margin-top: 0.35rem;
    }
    .route-status-summary .route-status-badge {
      font-size: 0.85rem;
      text-transform: none;
      border-radius: 999px;
      padding: 0.25rem 0.9rem;
    }
    .route-stop-card {
      transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .route-stop-card--no-entregado {
      padding-left: 1.3rem;
    }
    .stop-photo-thumb {
      position: relative;
      width: 80px;
      height: 80px;
    }
    .stop-photo-thumb img {
      border-radius: 10px;
    }
    .stop-photo-delete-form {
      position: absolute;
      top: 6px;
      right: 6px;
    }
    .stop-photo-delete-btn {
      padding: 0;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      border: 1px solid rgba(220, 38, 38, 0.5);
      background: rgba(255, 255, 255, 0.9);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #dc2626;
    }
    [data-stop-camera-preview] {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
      background: #000;
    }
    [data-stop-camera-preview] video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    [data-stop-camera-thumbs] {
      min-height: 74px;
    }
    .stop-camera-thumb {
      width: 72px;
      height: 72px;
      border-radius: 10px;
      overflow: hidden;
      border: 1px solid rgba(15, 23, 42, 0.1);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      position: relative;
    }
    .stop-camera-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .stop-camera-thumb__remove {
      position: absolute;
      top: 4px;
      right: 4px;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      border: none;
      background: rgba(0, 0, 0, 0.6);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.65rem;
    }
    @media (max-width: 768px) {
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      .page-header .page-actions {
        width: 100%;
        flex-wrap: wrap;
        gap: 8px;
      }
      .page-header .page-actions .btn,
      .page-header .page-actions .btn-group {
        width: 100%;
      }
      .route-stop-list .list-group-item {
        padding: 14px 16px;
      }
      .route-map {
        min-height: 320px;
      }
      .route-kpis .card-body h4 {
        font-size: 1.1rem;
      }
    }
  </style>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    #routeMap {
      min-height: 420px;
      border-radius: 0 0 0.5rem 0.5rem;
    }
    .route-map {
      min-height: 420px;
      border-radius: 0 0 14px 14px;
    }
    .route-kpis .card {
      border-radius: 16px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      border: 1px solid #e6edf3;
    }
    .route-kpis .card-body p {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 700;
    }
    .route-panel {
      border-radius: 18px;
      border: 1px solid #e6edf3;
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
    }
    .route-panel .card-header {
      border-bottom: 1px solid #edf2f7;
      border-radius: 18px 18px 0 0;
    }
    .route-stop-list .list-group-item {
      padding: 18px 20px;
    }
    .route-stop-list .list-group-item + .list-group-item {
      border-top: 1px solid #edf2f7;
    }
    .route-stop-list strong {
      font-size: 0.98rem;
    }
    .page-header .title-block p {
      font-size: 0.95rem;
    }
    .page-header .page-actions .btn-group .btn {
      border-radius: 999px;
    }
    .page-header .page-actions .btn {
      border-radius: 999px;
    }
    @media (max-width: 992px) {
      .route-map {
        min-height: 320px;
      }
      .route-kpis .card {
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
      }
    }
  </style>
@endpush

@push('scripts')
  @foreach($route->stops as $stop)
    @php
      $statusOptions = \App\Models\TrafficRouteStop::statusOptions();
    @endphp
    <div class="modal fade" id="stopHistory{{ $stop->id }}" tabindex="-1" aria-labelledby="stopHistoryLabel{{ $stop->id }}" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="stopHistoryLabel{{ $stop->id }}">Historial de parada #{{ $stop->sequence }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body p-0">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                    <th>Notas</th>
                    <th>Registrado por</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($stop->events->sortByDesc('happened_at') as $event)
                    <tr>
                      <td>{{ optional($event->happened_at)->format('d/m/Y H:i') ?? '-' }}</td>
                      <td>{{ $statusOptions[$event->status] ?? $event->status }}</td>
                      <td>
                        @if($event->deliveryReason)
                          <span class="badge text-white" style="background: {{ $event->deliveryReason->color ?? '#6c757d' }};">
                            {{ $event->deliveryReason->name }}
                          </span>
                        @else
                          <span class="text-muted">-</span>
                        @endif
                      </td>
                      <td class="text-muted small">{{ $event->status_notes ?: 'Sin notas' }}</td>
                      <td class="text-muted small">{{ optional($event->user)->name ?: 'N/D' }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5" class="text-center text-muted py-3">Sin eventos registrados.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="stopPhotos{{ $stop->id }}" tabindex="-1" aria-labelledby="stopPhotosLabel{{ $stop->id }}" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="stopPhotosLabel{{ $stop->id }}">Fotos de la parada #{{ $stop->sequence }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            @if($stop->photos->count())
              <div class="row g-3">
                @foreach($stop->photos as $photo)
                  <div class="col-6 col-md-4 position-relative">
                    <form method="POST"
                          action="{{ route('traffic.routes.stops.photos.destroy', ['route' => $route, 'stop' => $stop, 'photo' => $photo]) }}"
                          class="stop-photo-delete-form"
                          data-stop-photo-delete>
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger stop-photo-delete-btn" title="Eliminar foto">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                    <a class="d-block border rounded overflow-hidden shadow-sm bg-white"
                       style="padding:2px;"
                       href="{{ storage_image_url($photo->path) }}"
                       target="_blank"
                       rel="noreferrer"
                       title="{{ optional($photo->created_at)->format('d/m/Y H:i') }}{{ $photo->user ? ' · ' . $photo->user->name : '' }}">
                      <img src="{{ storage_image_url($photo->path) }}"
                           alt="Foto de entrega"
                           loading="lazy"
                           style="width:100%; height:160px; object-fit:cover;">
                    </a>
                    <div class="text-muted small mt-1">
                      {{ optional($photo->created_at)->format('d/m/Y H:i') ?? 'Sin fecha' }}
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="text-center text-muted py-4">
                <i class="fa-solid fa-camera" style="font-size: 1.5rem;"></i>
                <p class="mb-0">No hay fotos registradas aún.</p>
              </div>
            @endif
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      const mapboxToken = '{{ config('services.mapbox.token') }}';

      const setupClientSelect = () => {
        const el = document.querySelector('.js-adhoc-client-select');
        if (!el || !window.jQuery || !window.jQuery.fn.select2) return;
        window.jQuery(el).select2({
          dropdownParent: window.jQuery('#adhocStopModal'),
          width: '100%',
          placeholder: el.dataset.placeholder || 'Seleccionar...',
        });
      };

      const setupAdhocAddress = () => {
        if (!mapboxToken) return;
        const input = document.querySelector('.js-adhoc-address-input');
        const wrapper = document.querySelector('.js-adhoc-address-wrapper');
        const suggestions = document.querySelector('.js-adhoc-address-suggestions');
        const latInput = document.querySelector('[data-adhoc-lat]');
        const lngInput = document.querySelector('[data-adhoc-lng]');
        if (!input || !wrapper || !suggestions) return;

        const fetchSuggestions = async () => {
          const query = input.value.trim();
          suggestions.innerHTML = '';
          if (latInput) latInput.value = '';
          if (lngInput) lngInput.value = '';
          if (query.length < 3) return;

          const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json` +
            `?access_token=${mapboxToken}&country=AR&language=es&autocomplete=true&limit=5`;

          try {
            const response = await fetch(url);
            if (!response.ok) return;
            const data = await response.json();
            (data.features || []).forEach((feature) => {
              const item = document.createElement('button');
              item.type = 'button';
              item.className = 'list-group-item list-group-item-action';
              item.textContent = feature.place_name || '';
              item.addEventListener('click', () => {
                input.value = feature.place_name || '';
                const coords = feature.geometry?.coordinates;
                if (Array.isArray(coords) && coords.length === 2) {
                  if (latInput) latInput.value = coords[1];
                  if (lngInput) lngInput.value = coords[0];
                }
                suggestions.innerHTML = '';
              });
              suggestions.appendChild(item);
            });
          } catch (error) {
            console.error('Autocomplete error', error);
          }
        };

        input.addEventListener('input', fetchSuggestions);
        document.addEventListener('click', (event) => {
          if (!wrapper.contains(event.target)) {
            suggestions.innerHTML = '';
          }
        });
      };

      document.addEventListener('DOMContentLoaded', () => {
        setupClientSelect();
        setupAdhocAddress();
      });
    })();
  </script>
  <script>
    (function () {
      const modal = document.getElementById('reassignCarrierModal');
      const carrierSelect = document.getElementById('reassignCarrierSelect');
      const vehicleSelect = document.getElementById('reassignVehicleSelect');
      const form = document.getElementById('reassignCarrierForm');
      const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
      if (!modal || !carrierSelect || !vehicleSelect) return;

      const carrierRoute = "{{ route('traffic.transportistas.transportes', ['transportista' => '__carrier__']) }}";
      const currentCarrier = {{ (int) $route->transportista_id }};
      const currentVehicle = {{ (int) $route->transporte_id }};

      const renderVehicles = (items, selectedId) => {
        const options = ['<option value="">Seleccionar...</option>'];
        if (!items.length) {
          options.push('<option value="">Sin transportes activos</option>');
        } else {
          items.forEach((item) => {
            let label = item.alias || `Transporte #${item.id}`;
            if (item.license_plate) {
              label += ` (${item.license_plate})`;
            }
            options.push(`<option value="${item.id}">${label}</option>`);
          });
        }
        const html = options.join('');
        if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(vehicleSelect).data('select2')) {
          const $select = window.jQuery(vehicleSelect);
          $select.empty().append(html).trigger('change.select2');
          if (selectedId) {
            $select.val(String(selectedId)).trigger('change.select2');
          }
        } else {
          vehicleSelect.innerHTML = html;
          if (selectedId) {
            vehicleSelect.value = String(selectedId);
          }
        }
        vehicleSelect.disabled = !items.length;
        if (submitBtn) submitBtn.disabled = !items.length;
      };

      const resetVehicles = () => {
        const html = '<option value="">Seleccionar...</option>';
        if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(vehicleSelect).data('select2')) {
          const $select = window.jQuery(vehicleSelect);
          $select.empty().append(html).trigger('change.select2');
        } else {
          vehicleSelect.innerHTML = html;
        }
        vehicleSelect.disabled = true;
        if (submitBtn) submitBtn.disabled = true;
      };

      const fetchVehicles = async (carrierId, selectedId) => {
        const normalizedId = carrierId ? String(carrierId) : '';
        if (!normalizedId) {
          resetVehicles();
          return;
        }
        if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(vehicleSelect).data('select2')) {
          window.jQuery(vehicleSelect).empty().append('<option value="">Cargando...</option>').trigger('change.select2');
        } else {
          vehicleSelect.innerHTML = '<option value="">Cargando...</option>';
        }
        vehicleSelect.disabled = true;
        if (submitBtn) submitBtn.disabled = true;
        try {
          const response = await fetch(carrierRoute.replace('__carrier__', normalizedId));
          const data = response.ok ? await response.json() : null;
          renderVehicles((data && data.data) ? data.data : [], selectedId);
        } catch (error) {
          console.error(error);
          vehicleSelect.innerHTML = '<option value="">Error al cargar</option>';
          vehicleSelect.disabled = true;
          if (submitBtn) submitBtn.disabled = true;
        }
      };

      modal.addEventListener('show.bs.modal', () => {
        const value = currentCarrier ? String(currentCarrier) : '';
        if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(carrierSelect).data('select2')) {
          window.jQuery(carrierSelect).val(value).trigger('change.select2');
        } else {
          carrierSelect.value = value;
        }
        fetchVehicles(value, currentVehicle);
      });

      carrierSelect.addEventListener('change', () => {
        fetchVehicles(carrierSelect.value, null);
      });

      carrierSelect.addEventListener('input', () => {
        fetchVehicles(carrierSelect.value, null);
      });

      if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery(carrierSelect).on('select2:select', () => {
          fetchVehicles(carrierSelect.value, null);
        });
      }
    })();
  </script>
  <script>
    (function () {
      const STATUS_ENTREGADO = '{{ TrafficRouteStop::STATUS_ENTREGADO }}';
      const STATUS_NO_ENTREGADO = '{{ TrafficRouteStop::STATUS_NO_ENTREGADO }}';
      const cameraControllers = new Map();

      const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      const ensureVideoReady = (video) => {
        if (video.readyState >= 2) {
          return Promise.resolve();
        }

        return new Promise((resolve) => {
          video.addEventListener('loadedmetadata', () => resolve(), { once: true });
        });
      };

      const uploadCapturedStopPhotos = async (stopId, files) => {
        if (!stopId || !files.length) {
          return;
        }
        const form = document.querySelector(`[data-stop-photos-form][data-stop-id="${stopId}"]`);
        if (!form) {
          return;
        }
        const token = getCsrfToken();
        const payload = new FormData();
        payload.append('_token', token);
        files.forEach((file) => payload.append('photos[]', file));

        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
          },
          body: payload,
        });

        if (!response.ok) {
          throw new Error('No se pudieron subir las fotos. Intentá de nuevo.');
        }
      };

      const buildCameraController = (stopId, modalEl) => {
        const toggleBtn = modalEl.querySelector('[data-stop-camera-toggle]');
        const captureBtn = modalEl.querySelector('[data-stop-camera-capture]');
        const preview = modalEl.querySelector('[data-stop-camera-preview]');
        const video = modalEl.querySelector('[data-stop-camera-video]');
        const thumbs = modalEl.querySelector('[data-stop-camera-thumbs]');
        const errorEl = modalEl.querySelector('[data-stop-camera-error]');

        if (!toggleBtn || !captureBtn || !preview || !video || !thumbs || !errorEl) {
          return null;
        }

        let stream = null;
        const canvas = document.createElement('canvas');
        const capturedFiles = [];
        const thumbUrls = new Set();
        let captureIndex = 0;

        const clearThumbs = () => {
          thumbs.innerHTML = '';
          thumbUrls.forEach((url) => URL.revokeObjectURL(url));
          thumbUrls.clear();
        };

        const stopStream = () => {
          if (!stream) return;
          stream.getTracks().forEach((track) => track.stop());
          stream = null;
          video.srcObject = null;
        };

        const resetCamera = () => {
          stopStream();
          clearThumbs();
          capturedFiles.splice(0, capturedFiles.length);
          preview.classList.add('d-none');
          captureBtn.disabled = true;
          errorEl.classList.add('d-none');
          errorEl.textContent = '';
          toggleBtn.innerHTML = '<i class="fa-solid fa-video-camera me-1"></i> Abrir cámara';
        };

        const startCamera = async () => {
          try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
              throw new Error('La cámara no está disponible en este navegador.');
            }

            stream = await navigator.mediaDevices.getUserMedia({
              video: { facingMode: 'environment' },
              audio: false,
            });

            video.srcObject = stream;
            await ensureVideoReady(video);
            preview.classList.remove('d-none');
            captureBtn.disabled = false;
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
            toggleBtn.innerHTML = '<i class="fa-solid fa-square me-1"></i> Cerrar cámara';
          } catch (error) {
            stopStream();
            errorEl.classList.remove('d-none');
            errorEl.textContent = error.message || 'No se pudo acceder a la cámara.';
            throw error;
          }
        };

        const capturePhoto = async () => {
          if (!stream) {
            throw new Error('La cámara no está activa.');
          }
          await ensureVideoReady(video);
          canvas.width = video.videoWidth || 640;
          canvas.height = video.videoHeight || 480;
          const context = canvas.getContext('2d');
          context.drawImage(video, 0, 0, canvas.width, canvas.height);

          const blob = await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
              if (!blob) {
                reject(new Error('No se pudo generar la imagen.'));
                return;
              }
              resolve(blob);
            }, 'image/jpeg', 0.92);
          });

          const file = new File([blob], `stop-${stopId}-${Date.now()}.jpg`, { type: 'image/jpeg' });
          const captureId = `${stopId}-${Date.now()}-${captureIndex++}`;
          capturedFiles.push({ id: captureId, file });
          const url = URL.createObjectURL(blob);
          thumbUrls.add(url);
          const thumb = document.createElement('div');
          thumb.className = 'stop-camera-thumb';
          thumb.dataset.captureId = captureId;
          thumb.innerHTML = `
            <img src="${url}" alt="Foto capturada" loading="lazy">
            <button type="button" class="stop-camera-thumb__remove" title="Eliminar foto">
              <i class="fa-solid fa-trash"></i>
            </button>
          `;
          const removeBtn = thumb.querySelector('.stop-camera-thumb__remove');
          removeBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            const index = capturedFiles.findIndex((item) => item.id === captureId);
            if (index > -1) {
              capturedFiles.splice(index, 1);
            }
            thumb.remove();
            thumbUrls.delete(url);
            URL.revokeObjectURL(url);
          });
          thumbs.appendChild(thumb);
        };

        toggleBtn.addEventListener('click', async (event) => {
          event.preventDefault();
          if (stream) {
            resetCamera();
            return;
          }

          try {
            await startCamera();
          } catch (error) {
            console.error(error);
          }
        });

        captureBtn.addEventListener('click', async () => {
          try {
            await capturePhoto();
          } catch (error) {
            errorEl.classList.remove('d-none');
            errorEl.textContent = error.message || 'Error al capturar la imagen.';
            console.error(error);
          }
        });

        modalEl.addEventListener('hide.bs.modal', resetCamera);

        captureBtn.disabled = true;

          return {
            getCapturedFiles: () => capturedFiles.map((entry) => entry.file),
            reset: resetCamera,
            hasCapturedFiles: () => capturedFiles.length > 0,
          };
      };

      const ensureCameraController = (stopId, modalEl) => {
        if (!stopId || !modalEl) {
          return null;
        }
        if (cameraControllers.has(stopId)) {
          return cameraControllers.get(stopId);
        }
        const controller = buildCameraController(stopId, modalEl);
        cameraControllers.set(stopId, controller);
        return controller;
      };

      const applyBehaviors = () => {
        const forms = document.querySelectorAll('[data-stop-form]');

        forms.forEach((form) => {
          const stopId = form.getAttribute('data-stop-id');
          const hidden = {
            status: form.querySelector('[data-stop-field="status"]'),
            reason: form.querySelector('[data-stop-field="delivery_reason_id"]'),
            owner: form.querySelector('[data-stop-field="recipient_is_owner"]'),
            dni: form.querySelector('[data-stop-field="recipient_dni"]'),
            recipientName: form.querySelector('[data-stop-field="recipient_name"]'),
            notes: form.querySelector('[data-stop-field="status_notes"]'),
          };
          const summaries = {
            status: form.querySelector('[data-stop-summary="status"]'),
            reason: form.querySelector('[data-stop-summary="reason"]'),
            receiver: form.querySelector('[data-stop-summary="receiver"]'),
          };
          const modalSelector = form.getAttribute('data-stop-modal-target');
          const modal = modalSelector ? document.querySelector(modalSelector) : null;

          if (!modal) return;

          const modalEl = modal;
          const statusSelect = modalEl.querySelector('[data-stop-modal-status]');
          const reasonWrapper = modalEl.querySelector('[data-stop-modal-reason-wrapper]');
          const reasonSelect = modalEl.querySelector('[data-stop-modal-reason]');
          const ownerWrapper = modalEl.querySelector('[data-stop-modal-owner-wrapper]');
          const ownerCheckbox = modalEl.querySelector('[data-stop-modal-owner]');
          const dniWrapper = modalEl.querySelector('[data-stop-modal-dni-wrapper]');
          const dniInput = modalEl.querySelector('[data-stop-modal-dni]');
          const recipientNameWrapper = modalEl.querySelector('[data-stop-modal-recipient-name-wrapper]');
          const recipientNameInput = modalEl.querySelector('[data-stop-modal-recipient-name]');
          const notesInput = modalEl.querySelector('[data-stop-modal-notes]');
          const applyBtn = modalEl.querySelector('[data-stop-modal-apply]');
          const cameraController = ensureCameraController(stopId, modalEl);

          const syncVisibility = () => {
            const status = statusSelect.value || '';
            const delivered = status === STATUS_ENTREGADO;
            const notDelivered = status === STATUS_NO_ENTREGADO;
            const isOwner = ownerCheckbox.checked;

            reasonWrapper.classList.toggle('d-none', !notDelivered);
            reasonSelect.disabled = !notDelivered;
            ownerWrapper.classList.toggle('d-none', !delivered);
            if (dniWrapper && dniInput) {
              const allowDni = delivered;
              dniWrapper.classList.toggle('d-none', !allowDni);
              dniInput.disabled = !allowDni;
            }
            if (recipientNameWrapper && recipientNameInput) {
              const showName = delivered && !isOwner;
              recipientNameWrapper.classList.toggle('d-none', !showName);
              recipientNameInput.disabled = !showName;
            }
          };

          const setSummaries = () => {
            const statusText = statusSelect.options[statusSelect.selectedIndex]?.text || '—';
            const reasonText = reasonSelect.disabled || !reasonSelect.value
              ? '—'
              : reasonSelect.options[reasonSelect.selectedIndex]?.text || '—';
            const receiverText = ownerCheckbox.checked
              ? 'Titular'
              : `Otra persona${dniInput.value ? ' (DNI ' + dniInput.value + ')' : ''}`;

            summaries.status.textContent = statusText;
            summaries.reason.textContent = reasonText;
            summaries.receiver.textContent = receiverText;
          };

          const hydrateModal = () => {
            statusSelect.value = hidden.status.value || '';
            reasonSelect.value = hidden.reason.value || '';
            ownerCheckbox.checked = hidden.owner.value !== '0';
            dniInput.value = hidden.dni.value || '';
            if (recipientNameInput && hidden.recipientName) {
              recipientNameInput.value = hidden.recipientName.value || '';
            }
            notesInput.value = hidden.notes.value || '';
            syncVisibility();
            setSummaries();
          };

          const applyFromModal = async () => {
            hidden.status.value = statusSelect.value;
            hidden.reason.value = reasonSelect.value;
            hidden.owner.value = ownerCheckbox.checked ? '1' : '0';
            hidden.dni.value = dniInput.value;
            if (hidden.recipientName && recipientNameInput) {
              hidden.recipientName.value = recipientNameInput.value;
            }
            hidden.notes.value = notesInput.value;

            setSummaries();

            if (cameraController && cameraController.hasCapturedFiles()) {
              applyBtn.disabled = true;
              try {
                await uploadCapturedStopPhotos(stopId, cameraController.getCapturedFiles());
                cameraController.reset();
              } catch (error) {
                applyBtn.disabled = false;
                const message = error?.message || 'Error al subir las fotos.';
                if (typeof Swal !== 'undefined') {
                  Swal.fire({
                    icon: 'error',
                    title: 'Fotos',
                    text: message,
                  });
                } else {
                  alert(message);
                }
                return;
              }
            }

            sessionStorage.setItem('lastStopCardId', `stopCard${stopId}`);
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            modalInstance?.hide();
            form.submit();
          };

          modalEl.addEventListener('show.bs.modal', hydrateModal);
          statusSelect.addEventListener('change', () => {
            syncVisibility();
            setSummaries();
          });
          ownerCheckbox.addEventListener('change', () => {
            syncVisibility();
            setSummaries();
          });
          reasonSelect.addEventListener('change', setSummaries);

          if (window.jQuery) {
            const $status = window.jQuery(statusSelect);
            const $reason = window.jQuery(reasonSelect);
            $status.on('change.select2 select2:select', () => {
              syncVisibility();
              setSummaries();
            });
            $reason.on('change.select2 select2:select', setSummaries);
          }

          applyBtn.addEventListener('click', applyFromModal);

          hydrateModal();
        });
      };

      const scrollToLastStopCard = () => {
        const lastStopCardId = sessionStorage.getItem('lastStopCardId');
        if (!lastStopCardId) {
          return;
        }
        const element = document.getElementById(lastStopCardId);
        if (!element) {
          sessionStorage.removeItem('lastStopCardId');
          return;
        }
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        sessionStorage.removeItem('lastStopCardId');
      };

      const attachBehaviors = () => {
        applyBehaviors();
        scrollToLastStopCard();
      };

      if (document.readyState === 'complete' || document.readyState === 'interactive') {
        attachBehaviors();
      } else {
        document.addEventListener('DOMContentLoaded', attachBehaviors, { once: true });
      }
    })();
  </script>
  <script>
    (function () {
      const openModal = (selector) => {
        if (!selector) return;
        const el = document.querySelector(selector);
        if (!el) return;
        const instance = bootstrap.Modal.getOrCreateInstance(el);
        instance.show();
      };

      const attachHandlers = () => {
        const buttons = document.querySelectorAll('.js-change-stop');
        buttons.forEach((btn) => {
          btn.addEventListener('click', (event) => {
            event.preventDefault();
            const target = btn.getAttribute('data-stop-target');
            const hasStatus = btn.getAttribute('data-has-status') === '1';

            if (!hasStatus || typeof Swal === 'undefined') {
              openModal(target);
              return;
            }

            Swal.fire({
              title: 'Esta parada ya tiene estado',
              text: '¿Seguro que querés cambiarlo?',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, cambiar',
              cancelButtonText: 'Cancelar',
            }).then((result) => {
              if (result.isConfirmed) {
                openModal(target);
              }
            });
          });
        });
      };

      if (document.readyState === 'complete' || document.readyState === 'interactive') {
        attachHandlers();
      } else {
        document.addEventListener('DOMContentLoaded', attachHandlers, { once: true });
      }
    })();
  </script>


@php
  $carrierColor = $route->transportista->color ?? '#2563eb';
  $stopsArray = $route->stops->map(function ($stop) {
      return [
          'label'    => $stop->label,
          'sequence' => $stop->sequence,
          'lat'      => $stop->latitude,
          'lng'      => $stop->longitude,
          'is_extra' => (bool) $stop->is_extra,
          'status'   => $stop->status,
          'reason_color' => optional($stop->deliveryReason)->color,
          'carrier_color' => optional($stop->route->transportista)->color ?? '#2563eb',
      ];
  });

  $colorMap = [
      'low'      => '#2ecc71', // verde
      'moderate' => '#f1c40f', // amarillo
      'heavy'    => '#e74c3c', // rojo
      'severe'   => '#c0392b', // rojo fuerte
  ];

  $legs = collect(data_get($route->raw_payload, 'trip.legs', []));

  // Desde las steps y las anotaciones obtenemos cada tramo y su color
  $segments = collect();
  foreach ($legs as $leg) {
      $congestionLevels = collect(data_get($leg, 'annotation.congestion', []))
          ->map(fn ($value) => strtolower((string) $value))
          ->filter()
          ->values()
          ->all();

      $defaultLevel = count($congestionLevels) ? $congestionLevels[count($congestionLevels) - 1] : 'unknown';
      $congestionIndex = 0;

      foreach (data_get($leg, 'steps', []) as $step) {
          $coordinates = collect(data_get($step, 'geometry.coordinates', []))
              ->filter(fn ($pair) => is_array($pair) && count($pair) === 2)
              ->map(fn ($pair) => [$pair[1], $pair[0]])
              ->values()
              ->all();

          if (count($coordinates) < 2) {
              continue;
          }

          $pointCount = count($coordinates);
          for ($i = 0; $i < $pointCount - 1; $i++) {
              $level = $congestionLevels[$congestionIndex] ?? $defaultLevel;
              $color = $level === 'unknown'
                  ? $carrierColor
                  : ($colorMap[$level] ?? $carrierColor);

              $segments->push([
                  'latlngs' => [$coordinates[$i], $coordinates[$i + 1]],
                  'color'   => $color,
              ]);

              $congestionIndex++;
          }
      }
  }

  $segments = $segments->filter(fn ($segment) => !empty($segment['latlngs']));
@endphp




<script>
(function () {
  const segments = @json($segments);
  const stops = @json($stopsArray);
  const STATUS_ENTREGADO = '{{ TrafficRouteStop::STATUS_ENTREGADO }}';
  const STATUS_NO_ENTREGADO = '{{ TrafficRouteStop::STATUS_NO_ENTREGADO }}';

  const map = L.map('routeMap');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  if (segments.length) {
    const polylines = segments.map(function (segment) {
      return L.polyline(segment.latlngs, { color: segment.color, weight: 5 }).addTo(map);
    });
    const group = L.featureGroup(polylines);
    map.fitBounds(group.getBounds(), { padding: [20, 20] });
  } else if (stops.length) {
    const markers = stops
      .filter(stop => stop.lat && stop.lng)
      .map(stop => [stop.lat, stop.lng]);
    if (markers.length) {
      map.fitBounds(markers, { padding: [20, 20] });
    } else {
      map.setView([-34.6037, -58.3816], 5);
    }
  } else {
    map.setView([-34.6037, -58.3816], 5);
  }

  const markerHtml = (number, isExtra, status, carrierColor) => {
    const colors = {
      pending: 'rgba(148, 163, 184, 0.35)',
      delivered: 'rgba(34, 197, 94, 0.35)',
      failed: 'rgba(239, 68, 68, 0.35)',
    };
    let bg = colors.pending;
    let color = '#0f172a';

    if (isExtra) {
      bg = 'rgba(255, 255, 255, 0.7)';
      color = '#0f172a';
    } else if (status === STATUS_ENTREGADO) {
      bg = colors.delivered;
      color = '#0f172a';
    } else if (status === STATUS_NO_ENTREGADO) {
      bg = colors.failed;
      color = '#0f172a';
    }

    const border = carrierColor || '#2563eb';
    const ring = 'rgba(15, 23, 42, 0.12)';

    return `
    <div style="
      height:36px;width:36px;
      border-radius:50%;
      background:${bg};
      color:${color};
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:700;
      border:2px solid ${border};
      box-shadow:0 8px 16px ${ring};
      font-size:14px;
    ">
      ${isExtra ? '' : number}
    </div>`;
  };

  stops.forEach(stop => {
    if (!stop.lat || !stop.lng) return;
    const number = stop.sequence ?? '';
    const icon = L.divIcon({
        html: markerHtml(number, !!stop.is_extra, stop.status, stop.carrier_color),
        className: 'nyg-stop-marker',
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        popupAnchor: [0, -24],
    });
    L.marker([stop.lat, stop.lng], { icon })
      .addTo(map)
      .bindPopup(`#${stop.sequence} ${stop.label || ''}`);
  });
})();
</script>

@endpush


