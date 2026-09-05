@extends('layouts.app')

@section('title', 'Detalle Liquidacion')

@push('styles')
<style>
  .receipt-shell {
    background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
    border: 1px solid #e6ebf2;
    border-radius: 18px;
    box-shadow: 0 22px 40px rgba(15, 23, 42, 0.08);
    overflow: hidden;
  }
  .receipt-head {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(130deg, #f8fafc, #eef4ff);
    color: #0f172a;
    border-bottom: 1px solid #dbe4ef;
  }
  .receipt-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    opacity: 0.75;
  }
  .receipt-id {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
  }
  .receipt-meta-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e6ebf2;
    background: #ffffff;
  }
  .receipt-meta-card {
    border: 1px solid #e8edf4;
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    background: #fbfcff;
  }
  .receipt-meta-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    margin-bottom: 0.22rem;
  }
  .receipt-meta-value {
    font-size: 0.98rem;
    font-weight: 700;
    color: #0f172a;
  }
  .receipt-layout {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
    gap: 1rem;
    padding: 1rem 1.25rem 1.25rem;
  }
  @media (max-width: 1100px) {
    .receipt-layout {
      grid-template-columns: 1fr;
    }
  }
  .receipt-panel {
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
  }
  .receipt-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0.95rem;
    border-bottom: 1px solid #e6ebf2;
    background: #f8fafc;
  }
  .receipt-panel-title {
    font-size: 0.86rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #475569;
    font-weight: 800;
    margin: 0;
  }
  .badge-state {
    font-size: 0.74rem;
    padding: 0.38rem 0.7rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .metric {
    display: inline-block;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    font-size: 0.76rem;
    color: #334155;
    margin-right: 0.35rem;
    margin-bottom: 0.25rem;
  }
  .status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .status-confirmado {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
  }
  .status-disputado {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
  }
  .status-pendiente {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
  }
</style>
@endpush

@section('content')
@php
  $stateClass = 'bg-secondary';
  if ($recibo->estado === \App\Models\ReciboChofer::ESTADO_PAGADO) {
      $stateClass = 'bg-success';
  } elseif ($recibo->estado === \App\Models\ReciboChofer::ESTADO_EN_PLANILLA) {
      $stateClass = 'bg-info text-dark';
  } elseif ($recibo->estado === \App\Models\ReciboChofer::ESTADO_PENDIENTE_PAGO) {
      $stateClass = 'bg-primary';
  }
@endphp

<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Conciliacion #{{ $recibo->id }}</h1>
    <p class="text-muted mb-0">Revisa dia por dia los items liquidados y confirma o reporta discrepancias.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-outline-warning" id="openContactRequestModal">
      Solicitar contacto por faltantes
    </button>
    <a class="btn btn-outline-secondary" href="{{ route('portal-choferes.liquidaciones.index') }}">Volver al listado</a>
  </div>
</div>

@if($recibo->driver_contact_request_comment)
  <div class="alert alert-warning">
    <div class="fw-semibold">Solicitud de contacto enviada</div>
    <div class="small">
      {{ $recibo->driver_contact_request_date ? $recibo->driver_contact_request_date->format('d/m/Y H:i') . ' · ' : '' }}
      {{ $recibo->driver_contact_request_comment }}
    </div>
  </div>
@endif

<div class="receipt-shell mb-3">
  <div class="receipt-head d-flex flex-wrap justify-content-between gap-3">
    <div>
      <div class="receipt-kicker">Liquidacion</div>
      <div class="receipt-id">#{{ $recibo->id }}</div>
    </div>
    <div class="text-end">
      <div class="receipt-kicker">Totales</div>
      <div class="money-main fw-bold fs-4">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</div>
      <div class="mt-2">
        <span class="badge {{ $stateClass }} badge-state">{{ str_replace('_', ' ', $recibo->estado) }}</span>
      </div>
    </div>
  </div>

  <div class="receipt-meta-grid">
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Transportista</div>
      <div class="receipt-meta-value">{{ $recibo->displayName() ?: '-' }}</div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Tipo periodo</div>
      <div class="receipt-meta-value">{{ strtoupper((string) $recibo->tipo_periodo) }}</div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Rango de fechas</div>
      <div class="receipt-meta-value">{{ optional($recibo->periodo_desde)->format('d/m/Y') }} - {{ optional($recibo->periodo_hasta)->format('d/m/Y') }}</div>
    </div>
  </div>

  <div class="receipt-layout">
    <div class="receipt-panel">
      <div class="receipt-panel-head">
        <h2 class="receipt-panel-title">Detalle de conceptos liquidados</h2>
        <div class="small text-muted">{{ $recibo->items->count() }} item(s)</div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle table-hover">
          <thead class="bg-light">
            <tr>
              <th>Fecha</th>
              <th>Concepto</th>
              <th>Zona</th>
              <th class="text-end">Importe</th>
              <th class="text-center" style="width: 15%">Estado (Chofer)</th>
              <th class="text-end" style="width: 20%">Accion</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recibo->items as $item)
              @php
                $meta = (array) ($item->meta ?? []);
                $zonaName = $item->zona ?: data_get($meta, 'zona') ?: data_get($meta, 'raw.zona');
                $routeDate = data_get($meta, 'fecha');
              @endphp
              <tr>
                <td class="small text-nowrap">{{ $routeDate ? \Carbon\Carbon::parse($routeDate)->format('d/m/Y') : '-' }}</td>
                <td class="fw-medium">{{ $item->concepto }}</td>
                <td>
                  <div>{{ $zonaName ?: '-' }}</div>
                </td>
                <td class="text-end fw-bold">
                  $ {{ number_format((float) $item->importe, 2, ',', '.') }}
                </td>
                <td class="text-center">
                  @if($item->driver_status === 'confirmado')
                    <div class="status-badge status-confirmado"> Confirmado</div>
                  @elseif($item->driver_status === 'disputado')
                    <div class="status-badge status-disputado"> Discrepancia</div>
                    @if($item->driver_comment)
                      <div class="small text-muted mt-1 fst-italic view-comment-trigger text-decoration-underline" role="button" data-comment="{{ htmlspecialchars($item->driver_comment) }}">Ver comentario</div>
                    @endif
                  @else
                    <div class="status-badge status-pendiente"> Pendiente</div>
                  @endif
                  @if($item->driver_status_date)
                    <div class="small text-muted" style="font-size:0.65rem;">{{ $item->driver_status_date->format('d/m/y H:i') }}</div>
                  @endif
                </td>
                <td class="text-end">
                  <div class="d-flex gap-2 justify-content-end align-items-center">
                    <form method="POST" action="{{ route('portal-choferes.liquidaciones.items.status.update', $item) }}">
                      @csrf
                      <input type="hidden" name="status" value="confirmado">
                      <button type="submit" class="btn btn-sm {{ $item->driver_status === 'confirmado' ? 'btn-success' : 'btn-outline-success' }}" title="Aceptar valor de esta linea">
                        <i class="fa-solid fa-check"></i> Confirmar
                      </button>
                    </form>

                    <button type="button" class="btn btn-sm {{ $item->driver_status === 'disputado' ? 'btn-warning' : 'btn-outline-warning' }} btn-disputar"
                            data-item-id="{{ $item->id }}"
                            data-current-comment="{{ $item->driver_comment }}"
                            title="Informar discrepancia en el calculo de esta linea">
                      <i class="fa-solid fa-triangle-exclamation"></i> Discrepancia
                    </button>
                    
                    @if($item->logs->count() > 0)
                      <button type="button" class="btn btn-sm btn-link text-muted p-0 ms-1 btn-view-logs" data-logs="{{ json_encode($item->logs->map(function($log) { return ['date' => $log->created_at->format('d/m/Y H:i'), 'status' => $log->status, 'comment' => $log->comment]; })) }}" title="Ver historial">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex flex-column gap-3">
      <div class="receipt-panel">
        <div class="receipt-panel-head">
          <h2 class="receipt-panel-title">Factura de Liquidación</h2>
        </div>
        <div class="p-3">
          @php
            $adjuntos = is_array($recibo->facturas_adjuntas) ? $recibo->facturas_adjuntas : [];
            // Compatibilidad para vieja factura única
            if (count($adjuntos) === 0 && !empty($recibo->factura_pdf_path)) {
                $adjuntos[] = [
                    'path' => $recibo->factura_pdf_path,
                    'nombre' => $recibo->factura_pdf_nombre,
                    'uploaded_at' => null
                ];
            }
          @endphp
          @if(count($adjuntos) > 0)
            @foreach($adjuntos as $index => $factura)
              <div class="d-flex align-items-center justify-content-between mb-3 bg-light rounded p-2 border">
                <div class="d-flex align-items-center gap-3">
                  <div class="text-danger fs-3 ms-2">
                    <i class="fa-solid fa-file-pdf"></i>
                  </div>
                  <div class="text-truncate">
                    <div class="fw-bold">{{ $factura['nombre'] ?? 'Factura Adjunta' }}</div>
                    @if(!empty($factura['uploaded_at']))
                      <div class="small text-muted">{{ \Carbon\Carbon::parse($factura['uploaded_at'])->format('d/m/Y H:i') }}</div>
                    @endif
                  </div>
                </div>
                <div class="d-flex gap-2 me-2">
                  <a href="{{ route('portal-choferes.liquidaciones.factura.download', ['recibo' => $recibo, 'index' => $index]) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Descargar">
                    <i class="fa-solid fa-download"></i>
                  </a>
                  @if($recibo->estado !== \App\Models\ReciboChofer::ESTADO_PAGADO)
                    <form method="POST" action="{{ route('portal-choferes.liquidaciones.factura.delete', $recibo) }}" class="d-inline" data-confirm="¿Seguro que deseas eliminar esta factura?">
                      @csrf
                      @method('DELETE')
                      <input type="hidden" name="index" value="{{ $index }}">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  @endif
                </div>
              </div>
            @endforeach
            
            @if($recibo->estado !== \App\Models\ReciboChofer::ESTADO_PAGADO)
              <hr class="text-muted opacity-25">
              <p class="small text-muted mb-2">Puedes adjuntar más facturas si es necesario:</p>
              <form method="POST" action="{{ route('portal-choferes.liquidaciones.factura.store', $recibo) }}" enctype="multipart/form-data">
                @csrf
                <div class="input-group input-group-sm mb-2">
                  <input type="file" class="form-control" name="facturas[]" accept="application/pdf" multiple required>
                  <button class="btn btn-outline-secondary" type="submit">Subir</button>
                </div>
              </form>
            @endif
          @else
            @if($recibo->estado === \App\Models\ReciboChofer::ESTADO_PAGADO)
              <div class="alert alert-info py-2 small mb-0 text-center">
                La liquidación ya fue pagada.
              </div>
            @else
              <p class="small text-muted mb-3">Sube el comprobante fiscal correspondiente a esta liquidación.</p>
              <form method="POST" action="{{ route('portal-choferes.liquidaciones.factura.store', $recibo) }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                @csrf
                <input type="file" class="form-control form-control-sm" name="facturas[]" accept="application/pdf" multiple required>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                  <i class="fa-solid fa-cloud-arrow-up me-2"></i> Subir PDF(s)
                </button>
              </form>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Discrepancia -->
<div class="modal fade" id="discrepanciaModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="discrepanciaForm" method="POST" action="" class="modal-content">
      @csrf
      <input type="hidden" name="status" value="disputado">
      <div class="modal-header">
        <h5 class="modal-title">Informar Discrepancia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Por favor, ingresa el motivo por el cual hay una discrepancia con el monto/cantidad en esta linea. Asegurate de brindar la informacion que creas correcta (ej. cantidad de paradas correctas, si fue doble recorrido, etc).</p>
        <div class="mb-3">
          <label class="form-label">Comentario <span class="text-danger">*</span></label>
          <textarea name="comment" id="discrepanciaComment" class="form-control" rows="3" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-warning">Enviar Discrepancia</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Solicitud de Contacto -->
<div class="modal fade" id="contactRequestModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="contactRequestForm" method="POST" action="{{ route('portal-choferes.liquidaciones.contact-request.store', $recibo) }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Solicitar contacto por faltantes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Usa esta opción si faltan días, rutas o liquidaciones completas y necesitas que administración revise el período.</p>
        <div class="mb-3">
          <label class="form-label">Detalle del faltante <span class="text-danger">*</span></label>
          <textarea name="comment" id="contactRequestComment" class="form-control" rows="4" required>{{ old('comment', $recibo->driver_contact_request_comment) }}</textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-warning">Enviar solicitud</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Historial Logs -->
<div class="modal fade" id="logsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Historial de Confirmaciones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <ul class="list-group list-group-flush" id="logsContainer"></ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const btnDisputar = document.querySelectorAll('.btn-disputar');
    const formDisputa = document.getElementById('discrepanciaForm');
    const commentDisputa = document.getElementById('discrepanciaComment');
    const modalDiscrepancia = new bootstrap.Modal(document.getElementById('discrepanciaModal'));
    const openContactRequestModalBtn = document.getElementById('openContactRequestModal');
    const modalContactRequest = new bootstrap.Modal(document.getElementById('contactRequestModal'));
    const disputeActionTemplate = @json(url('/portal-choferes/liquidaciones/items/__ITEM__/estado'));

    btnDisputar.forEach(btn => {
      btn.addEventListener('click', function () {
        const itemId = this.dataset.itemId;
        const currentComment = this.dataset.currentComment;
        formDisputa.action = disputeActionTemplate.replace('__ITEM__', itemId);
        commentDisputa.value = currentComment || '';
        modalDiscrepancia.show();
      });
    });

    if (openContactRequestModalBtn) {
      openContactRequestModalBtn.addEventListener('click', function () {
        modalContactRequest.show();
      });
    }

    // View comment quick trigger
    document.querySelectorAll('.view-comment-trigger').forEach(el => {
      el.addEventListener('click', function() {
        Swal.fire({
          title: 'Comentario de Discrepancia',
          text: this.dataset.comment,
          icon: 'info'
        });
      });
    });

    // Logs modal
    const btnViewLogs = document.querySelectorAll('.btn-view-logs');
    const logsModal = new bootstrap.Modal(document.getElementById('logsModal'));
    const logsContainer = document.getElementById('logsContainer');

    btnViewLogs.forEach(btn => {
      btn.addEventListener('click', function () {
        const logs = JSON.parse(this.dataset.logs);
        logsContainer.innerHTML = '';
        if (logs.length === 0) {
          logsContainer.innerHTML = '<li class="list-group-item text-center text-muted">No hay historial</li>';
        } else {
          logs.forEach(log => {
            const badgeClass = log.status === 'confirmado' ? 'bg-success' : 'bg-warning text-dark';
            const logHTML = `
              <li class="list-group-item d-flex justify-content-between align-items-start">
                <div class="ms-2 me-auto">
                  <div class="fw-bold"><span class="badge ${badgeClass}">${log.status}</span></div>
                  ${log.comment ? `<div class="small mt-1 text-muted fst-italic">"${log.comment}"</div>` : ''}
                </div>
                <span class="text-muted small">${log.date}</span>
              </li>
            `;
            logsContainer.innerHTML += logHTML;
          });
        }
        logsModal.show();
      });
    });
  });
</script>
@endpush
