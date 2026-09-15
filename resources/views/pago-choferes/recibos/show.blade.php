@extends('layouts.app')

@section('title', 'Detalle Recibo Chofer')

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
    grid-template-columns: minmax(0, 1.7fr) minmax(300px, 1fr);
    gap: 1rem;
    padding: 1rem 1.25rem 1.25rem;
  }
  .receipt-layout.receipt-layout-single {
    grid-template-columns: minmax(0, 1fr);
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
  .receipt-toggle {
    border: 0;
    background: transparent;
    color: #475569;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0;
  }
  .receipt-list {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .receipt-list li {
    padding: 0.6rem 0.95rem;
    border-top: 1px solid #eef2f8;
  }
  .receipt-list li:first-child {
    border-top: 0;
  }
  .receipt-list .label {
    color: #64748b;
    font-size: 0.78rem;
  }
  .receipt-list .value {
    font-weight: 700;
    color: #111827;
  }
  .badge-state {
    font-size: 0.74rem;
    padding: 0.38rem 0.7rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .table-detail thead th {
    white-space: nowrap;
  }
  .table-detail tbody td {
    vertical-align: top;
  }
  .table-detail .line-actions {
    white-space: nowrap;
    width: 1%;
  }
  .table-detail .row-editor {
    min-width: 140px;
  }
  .table-detail .icon-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .detail-add-row {
    display: none;
  }
  .detail-add-row.is-visible {
    display: table-row;
  }
  .detail-add-row td {
    background: #f8fafc;
  }
  .table-detail .metric {
    display: inline-flex;
    gap: 0.35rem;
    align-items: center;
    font-size: 0.76rem;
    color: #64748b;
    padding: 0.16rem 0.48rem;
    border: 1px solid #dbe4ef;
    border-radius: 999px;
    margin: 0.1rem 0.1rem 0 0;
    background: #f8fafc;
  }
  .table-detail .rule-formula-row td {
    background: #fbfdff;
    border-top: 0;
    padding-top: 0;
  }
  .table-detail .rule-formula-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    width: 100%;
    padding: 0.15rem 0 0.4rem;
  }
  .table-detail .rule-formula-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .table-detail .rule-formula-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid #dbe4ef;
    background: #ffffff;
    color: #334155;
    font-size: 0.8rem;
    white-space: normal;
  }
  .table-detail .rule-formula-pill.rule-formula-base {
    border-color: #93c5fd;
    color: #1d4ed8;
  }
  .table-detail .rule-formula-pill.rule-formula-modifier {
    border-color: #facc15;
    color: #854d0e;
  }
  .money-main {
    font-size: 1.55rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
  }
  .note-box {
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 0.7rem 0.8rem;
    background: #f8fafc;
    color: #334155;
    font-size: 0.9rem;
  }
  @media (max-width: 1100px) {
    .receipt-layout {
      grid-template-columns: 1fr;
    }
  }
</style>
@endpush

@section('content')
@php
  $stateClass = [
      \App\Models\ReciboChofer::ESTADO_CARGADO => 'text-bg-secondary',
      \App\Models\ReciboChofer::ESTADO_EN_PLANILLA => 'text-bg-info',
      \App\Models\ReciboChofer::ESTADO_PENDIENTE_PAGO => 'text-bg-warning',
      \App\Models\ReciboChofer::ESTADO_PAGADO => 'text-bg-success',
      \App\Models\ReciboChofer::ESTADO_ANULADO => 'text-bg-danger',
  ][$recibo->estado] ?? 'text-bg-light';

  $firstItemMeta = data_get($recibo->items->first(), 'meta', []);
  $sheetName = data_get($firstItemMeta, 'zona_hoja')
      ?: data_get($firstItemMeta, 'sheet')
      ?: $recibo->source_sheet;
  $currentUser = auth()->user();
  $canUpdateReceipt = $currentUser ? $currentUser->can('update', $recibo) : false;
  $showAppliedRules = $currentUser && ! $currentUser->isTransportista();
  $canRecalculateReceipt = $canUpdateReceipt
      && $recibo->estado === \App\Models\ReciboChofer::ESTADO_CARGADO
      && $recibo->origen === 'excel_trafico'
      && ! $recibo->planillaLinks()->exists();
  $hasSidebarContent = ! $canUpdateReceipt && ! empty($recibo->observaciones);
  $transportista = $recibo->transportista;
  $liquidationMeta = optional($transportista)->liquidationMeta;
  $defaultPaymentMethod = optional($transportista)->defaultPaymentMethod;
@endphp

<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Detalle de recibo #{{ $recibo->id }}</h1>
    <p class="text-muted mb-0">Visualizacion de liquidacion y control administrativo del comprobante.</p>
  </div>
  <div class="page-actions d-flex align-items-center gap-2">
    <a href="{{ asset('manuales/instructivo_pago_choferes.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
      <i class="fa-solid fa-circle-question fs-5"></i>
    </a>
    <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.recibos.index') }}">Volver</a>
    <a class="btn btn-outline-primary" target="_blank" href="{{ route('pago-choferes.recibos.print', $recibo) }}">Imprimir</a>
    @if($canRecalculateReceipt)
      <form method="POST" action="{{ route('pago-choferes.recibos.recalculate', $recibo) }}" data-confirm="Se recalcularan las lineas importadas con el motor actual. Las lineas manuales se conservaran. Continuar?">
        @csrf
        <button class="btn btn-primary">Recalcular</button>
      </form>
    @endif
    @can('anular', $recibo)
      @if($recibo->estado !== \App\Models\ReciboChofer::ESTADO_ANULADO)
        <form method="POST" action="{{ route('pago-choferes.recibos.anular', $recibo) }}" data-confirm="Se anulara el recibo. Continuar?">
          @csrf
          <button class="btn btn-outline-warning">Anular</button>
        </form>
      @endif
    @endcan
    @can('viewAny', \App\Models\ReciboChofer::class)
      @if($recibo->estado === \App\Models\ReciboChofer::ESTADO_CARGADO && $recibo->origen === 'excel_trafico' && ! $recibo->planillaLinks()->exists())
        <form method="POST" action="{{ route('pago-choferes.recibos.destroy', $recibo) }}" data-confirm="Se eliminara el recibo importado. Continuar?">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger">Eliminar</button>
        </form>
      @endif
    @endcan
    @can('update', $recibo)
      <button type="button" class="btn btn-primary js-btn-edit">Editar</button>
      <button type="button" class="btn btn-success js-btn-save d-none">Guardar</button>
      <button type="button" class="btn btn-outline-secondary js-btn-cancel d-none">Cancelar</button>
    @endcan
  </div>
</div>

<div class="receipt-shell mb-3">
  <div class="receipt-head d-flex flex-wrap justify-content-between gap-3">
    <div>
      <div class="receipt-kicker">Recibo de liquidacion</div>
      <div class="receipt-id">#{{ $recibo->id }}</div>
    </div>
    <div class="text-end">
      <div class="receipt-kicker">Importe total</div>
      <div class="money-main">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</div>
      <div class="mt-2">
        <span class="badge {{ $stateClass }} badge-state">{{ str_replace('_', ' ', $recibo->estado) }}</span>
      </div>
    </div>
  </div>

  @if($recibo->driver_contact_request_comment)
    <div class="alert alert-warning rounded-0 border-0 border-top border-bottom mb-0">
      <div class="fw-semibold">Solicitud de contacto del chofer</div>
      <div class="small">
        {{ $recibo->driver_contact_request_date ? $recibo->driver_contact_request_date->format('d/m/Y H:i') . ' · ' : '' }}
        {{ $recibo->driver_contact_request_comment }}
      </div>
    </div>
  @endif

  <div class="receipt-meta-grid">
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Transportista</div>
      <div class="receipt-meta-value">{{ $recibo->displayName() ?: '-' }}</div>
      <div class="small text-muted mt-1">
        DNI: {{ $transportista && $transportista->dni ? $transportista->dni : '-' }}
        · Tel: {{ $transportista && $transportista->phone ? $transportista->phone : '-' }}
        · Registro: {{ $transportista && $transportista->license_expires_at ? $transportista->license_expires_at->format('d/m/Y') : '-' }}
      </div>
      <div class="small text-muted">
        Cuenta default: {{ optional(optional($defaultPaymentMethod)->bank)->name ?: 'Sin banco' }}
        · {{ optional($defaultPaymentMethod)->account_number ?: '-' }}
        · {{ optional($defaultPaymentMethod)->cbu ?: '-' }}
      </div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Tipo periodo</div>
      <div class="receipt-meta-value">{{ strtoupper((string) $recibo->tipo_periodo) }}</div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Periodo</div>
      <div class="receipt-meta-value">{{ optional($recibo->periodo_desde)->format('d/m/Y') }} - {{ optional($recibo->periodo_hasta)->format('d/m/Y') }}</div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Fecha emision</div>
      <div class="receipt-meta-value">{{ optional($recibo->fecha_emision)->format('d/m/Y') ?: '-' }}</div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Plaza</div>
      <div class="receipt-meta-value">{{ $recibo->plaza ?: '-' }}</div>
    </div>
    <div class="receipt-meta-card">
      <div class="receipt-meta-label">Hoja origen</div>
      <div class="receipt-meta-value">{{ $sheetName ?: '-' }}</div>
      <div class="small text-muted mt-1">
        Titular: {{ $liquidationMeta && $liquidationMeta->titular ? $liquidationMeta->titular : '-' }}
        · Patente: {{ $liquidationMeta && $liquidationMeta->patente ? $liquidationMeta->patente : '-' }}
      </div>
    </div>
  </div>

  <div class="receipt-layout{{ $hasSidebarContent ? '' : ' receipt-layout-single' }}">
    <div class="d-flex flex-column gap-3">
      <div class="receipt-panel">
        <div class="receipt-panel-head">
          <h2 class="receipt-panel-title">Datos de facturacion</h2>
          <button
            class="receipt-toggle"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#billingPanel"
            aria-expanded="false"
            aria-controls="billingPanel"
          >
            Desplegar / contraer
          </button>
        </div>
        <div class="collapse" id="billingPanel">
          <ul class="receipt-list">
            <li>
              <div class="label">Factura referencia</div>
              <div class="value">{{ $recibo->factura_ref ?: '-' }}</div>
            </li>
            <li>
              <div class="label">Fecha factura</div>
              <div class="value">{{ optional($recibo->factura_fecha)->format('d/m/Y') ?: '-' }}</div>
            </li>
            <li>
              <div class="label">Archivo PDF</div>
              <div class="value">
                @if($recibo->factura_pdf_path)
                  <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('pago-choferes.recibos.factura.download', $recibo) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                      <i class="fa-solid fa-file-pdf"></i> {{ $recibo->factura_pdf_nombre ?: 'Ver archivo' }}
                    </a>
                  </div>
                @else
                  <form method="POST" action="{{ route('pago-choferes.recibos.update', $recibo) }}" enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0" style="max-width: 350px;">
                    @csrf
                    @method('PUT')
                    <input type="file" name="factura_pdf" class="form-control form-control-sm" accept="application/pdf" required>
                    <button class="btn btn-sm btn-primary text-nowrap" type="submit">Subir PDF</button>
                  </form>
                @endif
              </div>
            </li>
            <li>
              <div class="label">Origen</div>
              <div class="value">{{ $recibo->origen ?: '-' }}</div>
            </li>
            <li>
              <div class="label">Clave origen</div>
              <div class="value">{{ $recibo->source_key ?: '-' }}</div>
            </li>
          </ul>
        </div>
      </div>

      <div class="receipt-panel">
        <div class="receipt-panel-head">
          <h2 class="receipt-panel-title">Detalle de conceptos liquidados</h2>
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">{{ $recibo->items->count() }} item(s)</span>
            @can('update', $recibo)
              <button
                type="button"
                class="btn btn-sm btn-outline-primary icon-btn d-none"
                id="toggleAddItemRow"
                title="Agregar linea"
                aria-label="Agregar linea"
              >
                <i class="fa-solid fa-plus"></i>
              </button>
            @endcan
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-detail mb-0 align-middle">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Zona</th>
                <th>Modelo</th>
                <th class="text-end">Cantidad</th>
                <th>Datos operativos</th>
                <th class="text-end">Importe</th>
                @can('update', $recibo)
                  <th></th>
                @endcan
              </tr>
            </thead>
            <tbody>
              @can('update', $recibo)
                <tr class="detail-add-row" id="detailAddRow">
                  <td class="small text-nowrap">-</td>
                  <td>
                    <form id="item-create-form" method="POST" action="{{ route('pago-choferes.items.store', $recibo) }}">
                      @csrf
                    </form>
                    <select form="item-create-form" name="concepto" class="form-select form-select-sm row-editor js-concept-select" data-placeholder="Seleccionar concepto" required>
                      <option value=""></option>
                      @foreach($conceptOptions as $conceptOption)
                        <option value="{{ $conceptOption->name }}">{{ $conceptOption->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td>-</td>
                  <td>-</td>
                  <td class="text-end">
                    <div class="js-cantidad-container">
                      <input form="item-create-form" type="number" step="0.001" name="cantidad" class="form-control form-control-sm text-end row-editor js-cantidad-input" placeholder="1">
                      <span class="js-cantidad-placeholder text-muted" style="display: none;">-</span>
                    </div>
                  </td>
                  <td>
                    <span class="small text-muted">Linea manual</span>
                  </td>
                  <td class="text-end">
                    <input form="item-create-form" type="number" step="0.01" name="importe" class="form-control form-control-sm text-end row-editor" placeholder="0,00" required>
                  </td>
                  <td class="text-end line-actions">
                    <div class="d-flex gap-1 justify-content-end">
                      <button form="item-create-form" class="btn btn-sm btn-outline-primary icon-btn" type="submit" title="Guardar linea" aria-label="Guardar linea">
                        <i class="fa-solid fa-floppy-disk"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-secondary icon-btn" type="button" id="cancelAddItemRow" title="Cancelar" aria-label="Cancelar">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @endcan
              @forelse($recibo->items as $item)
                @php
                  $meta = (array) ($item->meta ?? []);
                  $zonaName = $item->zona
                    ?: data_get($meta, 'zona')
                    ?: data_get($meta, 'raw.zona');
                  $routeDate = data_get($meta, 'fecha');
                  $itemModel = data_get($meta, 'modelo');
                  $itemVehicleType = data_get($meta, 'vehicle_type');
                  $pricingBreakdown = (array) data_get($meta, 'pricing_breakdown', []);
                  $appliedBaseRule = (array) data_get($pricingBreakdown, 'applied_base_rule', []);
                  $appliedModifiers = collect(data_get($pricingBreakdown, 'applied_modifiers', []))->filter(fn ($modifier) => is_array($modifier));
                  $appliedRuleEntries = collect();
                  if (! empty($appliedBaseRule)) {
                    $appliedRuleEntries->push([
                      'kind' => 'Base',
                      'css' => 'rule-formula-base',
                      'name' => data_get($appliedBaseRule, 'name', 'Regla base'),
                      'amount' => (float) data_get($appliedBaseRule, 'calculated_amount', 0),
                    ]);
                  }
                  foreach ($appliedModifiers as $modifier) {
                    $appliedRuleEntries->push([
                      'kind' => 'Modifier',
                      'css' => 'rule-formula-modifier',
                      'name' => data_get($modifier, 'name', 'Regla modifier'),
                      'amount' => (float) data_get($modifier, 'calculated_amount', 0),
                    ]);
                  }
                @endphp
                @can('update', $recibo)
                  @php $editFormId = 'item-update-' . $item->id; @endphp
                  <!-- Fila de Vista -->
                  <tr class="js-row-view">
                    <td class="small text-nowrap">{{ $routeDate ? \Carbon\Carbon::parse($routeDate)->format('d/m/Y') : '-' }}</td>
                    <td>
                      <div class="fw-semibold text-dark">{{ $item->concepto ?: '-' }}</div>
                    </td>
                    <td>
                      <div>{{ $zonaName ?: '-' }}</div>
                    </td>
                    <td>
                      <div>{{ $itemModel ?: '-' }}</div>
                      @if($itemVehicleType)
                        <div class="small text-muted text-uppercase">{{ \App\Models\Transporte::paymentVehicleTypes(true)[$itemVehicleType] ?? $itemVehicleType }}</div>
                      @endif
                    </td>
                    <td class="text-end">
                      @if(stripos($item->concepto, 'ZONA') !== false)
                        -
                      @else
                        {{ $item->cantidad !== null ? number_format((float) $item->cantidad, 3, ',', '.') : '-' }}
                      @endif
                    </td>
                    <td>
                      <span class="metric">Paradas: {{ $item->paradas !== null ? number_format((float) $item->paradas, 0, ',', '.') : '-' }}</span>
                      <span class="metric">Paquetes: {{ $item->paquetes !== null ? number_format((float) $item->paquetes, 0, ',', '.') : '-' }}</span>
                      <span class="metric">Entregados: {{ $item->entregados !== null ? number_format((float) $item->entregados, 0, ',', '.') : '-' }}</span>
                      <span class="metric">KM: {{ data_get($meta, 'kilometros') !== null ? number_format((float) data_get($meta, 'kilometros'), 2, ',', '.') : '-' }}</span>
                      @if(data_get($meta, 'kilometros_source') !== null)
                        <span class="metric">KM src: {{ number_format((float) data_get($meta, 'kilometros_source'), 2, ',', '.') }}</span>
                      @endif
                      @if(data_get($meta, 'cantidad_label'))
                        <span class="metric">{{ data_get($meta, 'cantidad_label') }}</span>
                      @endif
                    </td>
                    <td class="text-end fw-bold">$ {{ number_format((float) $item->importe, 2, ',', '.') }}</td>
                    <td></td>
                  </tr>

                  <!-- Fila de Edición -->
                  <tr class="js-row-edit d-none">
                    <td class="small text-nowrap">{{ $routeDate ? \Carbon\Carbon::parse($routeDate)->format('d/m/Y') : '-' }}</td>
                    <td>
                      <select form="{{ $editFormId }}" name="concepto" class="form-select form-select-sm row-editor js-concept-select" data-placeholder="Seleccionar concepto" required>
                        <option value=""></option>
                        @foreach($conceptOptions as $conceptOption)
                          <option value="{{ $conceptOption->name }}" {{ $item->concepto === $conceptOption->name ? 'selected' : '' }}>{{ $conceptOption->name }}</option>
                        @endforeach
                        @if($item->concepto && ! $conceptOptions->contains('name', $item->concepto))
                          <option value="{{ $item->concepto }}" selected>{{ $item->concepto }}</option>
                        @endif
                      </select>
                    </td>
                    <td>
                      <div>{{ $zonaName ?: '-' }}</div>
                    </td>
                    <td>
                      <div>{{ $itemModel ?: '-' }}</div>
                      @if($itemVehicleType)
                        <div class="small text-muted text-uppercase">{{ \App\Models\Transporte::paymentVehicleTypes(true)[$itemVehicleType] ?? $itemVehicleType }}</div>
                      @endif
                    </td>
                    <td class="text-end">
                      <div class="js-cantidad-container">
                        <input form="{{ $editFormId }}" type="number" step="0.001" name="cantidad" value="{{ $item->cantidad !== null ? (float) $item->cantidad : '' }}" class="form-control form-control-sm text-end row-editor js-cantidad-input" @if(stripos($item->concepto, 'ZONA') !== false) style="display: none;" @endif>
                        <span class="js-cantidad-placeholder text-muted" @if(stripos($item->concepto, 'ZONA') === false) style="display: none;" @endif>-</span>
                      </div>
                    </td>
                    <td>
                      <span class="metric">Paradas: {{ $item->paradas !== null ? number_format((float) $item->paradas, 0, ',', '.') : '-' }}</span>
                      <span class="metric">Paquetes: {{ $item->paquetes !== null ? number_format((float) $item->paquetes, 0, ',', '.') : '-' }}</span>
                      <span class="metric">Entregados: {{ $item->entregados !== null ? number_format((float) $item->entregados, 0, ',', '.') : '-' }}</span>
                      <span class="metric">KM: {{ data_get($meta, 'kilometros') !== null ? number_format((float) data_get($meta, 'kilometros'), 2, ',', '.') : '-' }}</span>
                      @if(data_get($meta, 'kilometros_source') !== null)
                        <span class="metric">KM src: {{ number_format((float) data_get($meta, 'kilometros_source'), 2, ',', '.') }}</span>
                      @endif
                      @if(data_get($meta, 'cantidad_label'))
                        <span class="metric">{{ data_get($meta, 'cantidad_label') }}</span>
                      @endif
                    </td>
                    <td class="text-end">
                      <input form="{{ $editFormId }}" type="number" step="0.01" name="importe" value="{{ number_format((float) $item->importe, 2, '.', '') }}" class="form-control form-control-sm text-end row-editor" required>
                    </td>
                    <td class="text-end line-actions">
                      <form id="{{ $editFormId }}" method="POST" action="{{ route('pago-choferes.items.update', $item) }}" class="d-none">
                        @csrf
                        @method('PUT')
                      </form>
                      <div class="d-flex gap-1 justify-content-end">
                        <button form="{{ $editFormId }}" class="btn btn-sm btn-outline-primary icon-btn" type="submit" title="Guardar linea" aria-label="Guardar linea">
                          <i class="fa-solid fa-floppy-disk"></i>
                        </button>
                        <form method="POST" action="{{ route('pago-choferes.items.destroy', $item) }}" class="js-delete-item-form">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger icon-btn" type="submit" title="Eliminar linea" aria-label="Eliminar linea">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  @if($showAppliedRules && $appliedRuleEntries->isNotEmpty())
                    <tr class="rule-formula-row">
                      <td colspan="8">
                        <div class="rule-formula-wrap">
                          <span class="rule-formula-label">Fórmula aplicada</span>
                          @foreach($appliedRuleEntries as $ruleEntry)
                            <span class="rule-formula-pill {{ $ruleEntry['css'] }}">
                              {{ $ruleEntry['kind'] }}: {{ $ruleEntry['name'] }}
                              ($ {{ number_format((float) $ruleEntry['amount'], 2, ',', '.') }})
                            </span>
                          @endforeach
                        </div>
                      </td>
                    </tr>
                  @endif
                @else
                  <tr>
                    <td class="small text-nowrap">{{ $routeDate ? \Carbon\Carbon::parse($routeDate)->format('d/m/Y') : '-' }}</td>
                    <td>
                      <div class="fw-semibold text-dark">{{ $item->concepto ?: '-' }}</div>
                    </td>
                    <td>
                      <div>{{ $zonaName ?: '-' }}</div>
                    </td>
                    <td>
                      <div>{{ $itemModel ?: '-' }}</div>
                      @if($itemVehicleType)
                        <div class="small text-muted text-uppercase">{{ \App\Models\Transporte::paymentVehicleTypes(true)[$itemVehicleType] ?? $itemVehicleType }}</div>
                      @endif
                    </td>
                    <td class="text-end">
                      @if(stripos($item->concepto, 'ZONA') !== false)
                        -
                      @else
                        {{ $item->cantidad !== null ? number_format((float) $item->cantidad, 3, ',', '.') : '-' }}
                      @endif
                    </td>
                    <td>
                      <span class="metric">Paradas: {{ $item->paradas !== null ? number_format((float) $item->paradas, 0, ',', '.') : '-' }}</span>
                      <span class="metric">Paquetes: {{ $item->paquetes !== null ? number_format((float) $item->paquetes, 0, ',', '.') : '-' }}</span>
                      <span class="metric">Entregados: {{ $item->entregados !== null ? number_format((float) $item->entregados, 0, ',', '.') : '-' }}</span>
                      <span class="metric">KM: {{ data_get($meta, 'kilometros') !== null ? number_format((float) data_get($meta, 'kilometros'), 2, ',', '.') : '-' }}</span>
                      @if(data_get($meta, 'kilometros_source') !== null)
                        <span class="metric">KM src: {{ number_format((float) data_get($meta, 'kilometros_source'), 2, ',', '.') }}</span>
                      @endif
                      @if(data_get($meta, 'cantidad_label'))
                        <span class="metric">{{ data_get($meta, 'cantidad_label') }}</span>
                      @endif
                    </td>
                    <td class="text-end fw-bold">$ {{ number_format((float) $item->importe, 2, ',', '.') }}</td>
                  </tr>
                  @if($showAppliedRules && $appliedRuleEntries->isNotEmpty())
                    <tr class="rule-formula-row">
                      <td colspan="7">
                        <div class="rule-formula-wrap">
                          <span class="rule-formula-label">Fórmula aplicada</span>
                          @foreach($appliedRuleEntries as $ruleEntry)
                            <span class="rule-formula-pill {{ $ruleEntry['css'] }}">
                              {{ $ruleEntry['kind'] }}: {{ $ruleEntry['name'] }}
                              ($ {{ number_format((float) $ruleEntry['amount'], 2, ',', '.') }})
                            </span>
                          @endforeach
                        </div>
                      </td>
                    </tr>
                  @endif
                @endcan
              @empty
                <tr>
                  <td colspan="{{ $canUpdateReceipt ? 8 : 7 }}" class="text-center text-muted py-4">El recibo no tiene items cargados.</td>
                </tr>
              @endforelse
            </tbody>
            <tfoot>
              <tr>
                <th colspan="{{ $canUpdateReceipt ? 6 : 5 }}" class="text-end">Total recibo</th>
                <th class="text-end">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</th>
                @can('update', $recibo)
                  <th></th>
                @endcan
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      @can('update', $recibo)
        <div class="receipt-panel">
          <div class="receipt-panel-head">
            <h2 class="receipt-panel-title">Gestion administrativa</h2>
            <button
              class="receipt-toggle"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#adminPanel"
              aria-expanded="false"
              aria-controls="adminPanel"
            >
              Desplegar / contraer
            </button>
          </div>
          <div class="collapse" id="adminPanel">
            <div class="p-3">
              <form id="admin-update-form" method="POST" action="{{ route('pago-choferes.recibos.update', $recibo) }}" enctype="multipart/form-data" class="row g-2">
                @csrf
                @method('PUT')

                <div class="col-12">
                  <label class="form-label">Estado</label>
                  <select name="estado" class="form-select">
                    @foreach([\App\Models\ReciboChofer::ESTADO_CARGADO, \App\Models\ReciboChofer::ESTADO_PENDIENTE_PAGO, \App\Models\ReciboChofer::ESTADO_EN_PLANILLA, \App\Models\ReciboChofer::ESTADO_PAGADO, \App\Models\ReciboChofer::ESTADO_ANULADO] as $state)
                      <option value="{{ $state }}" {{ $recibo->estado === $state ? 'selected' : '' }}>{{ str_replace('_', ' ', $state) }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">Fecha emision</label>
                  <input type="date" name="fecha_emision" class="form-control" value="{{ optional($recibo->fecha_emision)->format('Y-m-d') }}">
                </div>

                <div class="col-12">
                  <label class="form-label">Plaza</label>
                  <input type="text" name="plaza" class="form-control" value="{{ old('plaza', $recibo->plaza) }}">
                </div>

                <div class="col-12">
                  <label class="form-label">Referencia factura</label>
                  <input type="text" name="factura_ref" class="form-control" value="{{ old('factura_ref', $recibo->factura_ref) }}">
                </div>

                <div class="col-12">
                  <label class="form-label">Fecha factura</label>
                  <input type="date" name="factura_fecha" class="form-control" value="{{ optional($recibo->factura_fecha)->format('Y-m-d') }}">
                </div>


                <div class="col-12">
                  <label class="form-label">Observaciones factura</label>
                  <textarea name="factura_observaciones" rows="2" class="form-control">{{ old('factura_observaciones', $recibo->factura_observaciones) }}</textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Observaciones internas</label>
                  <textarea name="observaciones" rows="3" class="form-control">{{ old('observaciones', $recibo->observaciones) }}</textarea>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                  <button type="button" class="btn btn-primary js-btn-edit">Editar</button>
                  <button type="submit" class="btn btn-success js-btn-save d-none">Guardar cambios</button>
                  <button type="button" class="btn btn-outline-secondary js-btn-cancel d-none">Cancelar</button>
                </div>
              </form>
              @if($recibo->factura_pdf_path)
                <form method="POST" action="{{ route('pago-choferes.recibos.unlink-invoice', $recibo) }}" data-confirm="Se desvinculara la factura del recibo. Continuar?" class="mt-2">
                  @csrf
                  <button class="btn btn-outline-danger" type="submit">Quitar factura</button>
                </form>
              @endif
            </div>
          </div>
        </div>
      @endcan
    </div>

    <div class="d-flex flex-column gap-3">
      @cannot('update', $recibo)
        @if($recibo->observaciones)
          <div class="receipt-panel">
            <div class="receipt-panel-head">
              <h2 class="receipt-panel-title">Observaciones</h2>
            </div>
            <div class="p-3">
              <div class="note-box">{{ $recibo->observaciones }}</div>
            </div>
          </div>
        @endif
      @endcannot
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    const addRow = document.getElementById('detailAddRow');
    const toggleBtn = document.getElementById('toggleAddItemRow');
    const cancelBtn = document.getElementById('cancelAddItemRow');

    const showAddRow = () => {
      if (!addRow) return;
      addRow.classList.add('is-visible');
      const select = addRow.querySelector('.js-concept-select');
      if (select && window.jQuery && window.jQuery.fn.select2) {
        window.jQuery(select).select2('open');
      }
    };

    const hideAddRow = () => {
      if (!addRow) return;
      addRow.classList.remove('is-visible');
      const form = document.getElementById('item-create-form');
      if (form) form.reset();
      const select = addRow.querySelector('.js-concept-select');
      if (select && window.jQuery && window.jQuery.fn.select2) {
        window.jQuery(select).val('').trigger('change.select2');
      }
    };

    toggleBtn?.addEventListener('click', () => {
      if (!addRow) return;
      if (addRow.classList.contains('is-visible')) {
        hideAddRow();
        return;
      }
      showAddRow();
    });

    cancelBtn?.addEventListener('click', hideAddRow);

    document.querySelectorAll('.js-delete-item-form').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') {
          return;
        }
        event.preventDefault();
        if (typeof window.Swal === 'undefined') {
          form.submit();
          return;
        }

        const result = await window.Swal.fire({
          icon: 'warning',
          text: 'Se eliminara esta linea de detalle. Continuar?',
          confirmButtonText: 'Si, eliminar',
          cancelButtonText: 'Cancelar',
          showCancelButton: true,
          focusCancel: true,
        });

        if (result.isConfirmed) {
          form.dataset.confirmed = 'true';
          form.submit();
        }
      });
    });

    const toggleCantidadInput = (selectElement) => {
        const row = selectElement.closest('tr');
        if (!row) return;
        const container = row.querySelector('.js-cantidad-container');
        if (!container) return;
        
        const input = container.querySelector('.js-cantidad-input');
        const placeholder = container.querySelector('.js-cantidad-placeholder');
        if (!input || !placeholder) return;

        const conceptName = (selectElement.value || '').toUpperCase();
        if (conceptName.includes('ZONA')) {
            input.style.display = 'none';
            placeholder.style.display = 'inline';
        } else {
            input.style.display = '';
            placeholder.style.display = 'none';
        }
    };

    const initConceptSelects = () => {
        if (window.jQuery) {
            window.jQuery('.js-concept-select').on('change', function() {
                toggleCantidadInput(this);
            });
            document.querySelectorAll('.js-concept-select').forEach(toggleCantidadInput);
        }
    };
    
    setTimeout(initConceptSelects, 200);

    // Edit/Read mode logic
    const btnEdits = document.querySelectorAll('.js-btn-edit');
    const btnSaves = document.querySelectorAll('.js-btn-save');
    const btnCancels = document.querySelectorAll('.js-btn-cancel');
    const adminForm = document.getElementById('admin-update-form');
    const toggleAddRowBtn = document.getElementById('toggleAddItemRow');
    
    if (adminForm) {
        const adminInputs = adminForm.querySelectorAll('input, select, textarea');
        
        // By default, disable admin inputs
        adminInputs.forEach(input => {
            input.setAttribute('disabled', 'true');
        });
        
        // Ensure inputs are enabled right before submit so they get POSTed
        adminForm.addEventListener('submit', () => {
            adminInputs.forEach(input => input.removeAttribute('disabled'));
        });
        
        // Top actions save button submit trigger
        const topSaveBtn = document.querySelector('.page-actions .js-btn-save');
        if (topSaveBtn) {
            topSaveBtn.addEventListener('click', () => {
                adminInputs.forEach(input => input.removeAttribute('disabled'));
                adminForm.submit();
            });
        }
        
        const enableEditMode = () => {
            // 1. Enable admin inputs
            adminInputs.forEach(input => {
                input.removeAttribute('disabled');
            });
            
            // 2. Toggle button visibilities
            btnEdits.forEach(btn => btn.classList.add('d-none'));
            btnSaves.forEach(btn => btn.classList.remove('d-none'));
            btnCancels.forEach(btn => btn.classList.remove('d-none'));
            
            // 3. Toggle table rows
            document.querySelectorAll('.js-row-view').forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.js-row-edit').forEach(el => el.classList.remove('d-none'));
            
            // 4. Show add item button
            if (toggleAddRowBtn) toggleAddRowBtn.classList.remove('d-none');
        };
        
        const disableEditMode = () => {
            // 1. Reset admin form and disable inputs
            adminForm.reset();
            adminInputs.forEach(input => {
                input.setAttribute('disabled', 'true');
            });
            
            // 2. Toggle button visibilities
            btnEdits.forEach(btn => btn.classList.remove('d-none'));
            btnSaves.forEach(btn => btn.classList.add('d-none'));
            btnCancels.forEach(btn => btn.classList.add('d-none'));
            
            // 3. Toggle table rows
            document.querySelectorAll('.js-row-view').forEach(el => el.classList.remove('d-none'));
            document.querySelectorAll('.js-row-edit').forEach(el => el.classList.add('d-none'));
            
            // 4. Hide add item button and call hideAddRow to reset create row and its select2
            if (toggleAddRowBtn) toggleAddRowBtn.classList.add('d-none');
            hideAddRow();
            
            // 5. Reset all update form inputs and trigger select2 update
            document.querySelectorAll('.js-row-edit form').forEach(form => {
                form.reset();
                if (window.jQuery && window.jQuery.fn.select2) {
                    window.jQuery(form).find('.js-concept-select').trigger('change.select2');
                }
            });
        };
        
        btnEdits.forEach(btn => btn.addEventListener('click', enableEditMode));
        btnCancels.forEach(btn => btn.addEventListener('click', disableEditMode));
    }
  })();
</script>
@endpush
