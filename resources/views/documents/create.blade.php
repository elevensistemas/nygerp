@extends('layouts.app')

@section('title','Nuevo comprobante')

@section('content')
@php
    $productOptionsData = $products->map(function ($product) {
        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'unit' => $product->unit,
            'default_price' => $product->default_price,
            'default_account_id' => $product->default_account_id,
            'default_cost_center_id' => $product->default_cost_center_id,
        ];
    })->values();
@endphp
<style>
  #lineModal {
    z-index: 1055;
  }
  #productQuickModal {
    z-index: 1065;
  }
  .modal-backdrop.nested-backdrop {
    z-index: 1060 !important;
  }
</style>
<h1 class="h4 mb-3">Nuevo comprobante de {{ $scope === 'purchase' ? 'compra' : 'venta' }}</h1>

<form id="docForm" method="post" action="{{ route('documents.store') }}">
  @csrf

  {{-- Wizard header --}}
  <div class="d-flex align-items-center gap-2 mb-3">
    <div class="step badge rounded-pill px-3 py-2 bg-primary" data-step="1">1</div>
    <div class="text-muted me-3">Cabezal</div>
    <div class="step badge rounded-pill px-3 py-2 bg-secondary" data-step="2">2</div>
    <div class="text-muted me-3">Detalle</div>
    <div class="step badge rounded-pill px-3 py-2 bg-secondary" data-step="3">3</div>
    <div class="text-muted me-3">Pagos</div>
    <div class="step badge rounded-pill px-3 py-2 bg-secondary" data-step="4">4</div>
    <div class="text-muted">Imputaciones</div>
  </div>

  <ul class="nav nav-tabs mb-3" id="docTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-head" type="button">Cabezal</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lines" type="button">Detalle</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button">Pagos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-alloc" type="button">Imputaciones</button></li>
  </ul>

  <div class="tab-content border rounded p-3 bg-white shadow-sm">

    {{-- C A B E Z A L --}}
    <div class="tab-pane fade show active" id="tab-head">
      <div class="row g-3">
        <input type="hidden" name="scope" value="{{ $scope ?? 'purchase' }}">
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          {{-- Mostrar tipos según el scope: las Órdenes de Pago (payment_order) se crean desde el módulo Pagos --}}
          <select class="form-select" name="doctype" id="doctype" required>
            @if(($scope ?? 'purchase') === 'purchase')
              <option value="invoice" selected>Factura</option>
              <option value="credit_note">Nota de Crédito</option>
              <option value="debit_note">Nota de Débito</option>
              <option value="receipt">Recibo</option>
              {{-- Las Órdenes de Pago deben crearse desde Pagos -> Orden de pago --}}
            @else
              <option value="invoice" selected>Factura</option>
              <option value="credit_note">Nota de Crédito</option>
              <option value="debit_note">Nota de Débito</option>
              <option value="receipt">Recibo</option>
              <option value="fund_movement">Mov. de Fondo</option>
            @endif
          </select>
        </div>
        @if(($scope ?? 'purchase') === 'purchase')
          <div class="col-md-9 d-flex align-items-end">
            <div class="form-text">La Orden de Pago se gestiona desde el módulo de Pagos. <a href="{{ route('payments.create') }}">Ir a Orden de Pago</a></div>
          </div>
        @endif

        @if(($scope ?? 'purchase') === 'purchase')
          <div class="col-md-5">
            <label class="form-label">Proveedor</label>
            <select class="form-select" name="supplier_id" id="supplier_id" required>
              <option value="">-- Seleccione --</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}">{{ $s->name }} @if($s->tax_id) ({{ $s->tax_id }}) @endif</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="col-md-2">
          <label class="form-label">Número</label>
          <input name="number" id="number" class="form-control" placeholder="0001-00000001" required>
        </div>

        <div class="col-md-2">
          <label class="form-label">Fecha</label>
          <input type="date" name="issue_date" id="issue_date" class="form-control" value="{{ now()->toDateString() }}" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Condición de pago</label>
          <select class="form-select" name="payment_term_id" id="payment_term_id">
            <option value="">—</option>
            @foreach($terms as $t)
              <option value="{{ $t->id }}" data-days='@json($t->days ?? [])'>{{ $t->name }}</option>
            @endforeach
          </select>
          <div class="form-text">Si tiene días definidos, se programan los vencimientos automáticamente.</div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Notas</label>
          <input type="text" name="notes" class="form-control" placeholder="Observaciones internas (opcional)">
        </div>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-primary next-step" data-next="#tab-lines">Siguiente</button>
      </div>
    </div>

    {{-- D E T A L L E --}}
    <div class="tab-pane fade" id="tab-lines">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Detalle de ítems</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLine">
          + Agregar item
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle" id="linesTable">
          <thead class="table-light">
            <tr>
              <th style="width: 22%">Producto</th>
              <th style="width: 22%">Concepto</th>
              <th style="width: 80px" class="text-end">Cant</th>
              <th style="width: 90px">Unidad</th>
              <th style="width: 110px" class="text-end">Precio</th>
              <th style="width: 14%">Cuenta</th>
              <th style="width: 12%">CC</th>
              <th style="width: 110px" class="text-end">Total</th>
              <th style="width: 90px" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            {{-- filas via JS --}}
          </tbody>
          <tfoot class="table-light">
            <tr>
              <th colspan="5" class="text-end">Subtotal</th>
              <th class="text-end"><span id="subtotalTxt">0,00</span></th>
              <th></th>
            </tr>
            <tr>
              <th colspan="5" class="text-end">
                Impuestos manuales
                <span class="ms-1 text-muted small">(IVA u otros cargados)</span>
              </th>
              <th class="text-end">
                <input type="number" name="tax" id="tax" class="form-control form-control-sm text-end" value="0" step="0.01">
              </th>
              <th></th>
            </tr>
            <tr>
              <th colspan="5" class="text-end">
                Percepciones/retenciones automaticas
                <span class="ms-1 text-muted small">(segun proveedor)</span>
              </th>
              <th class="text-end"><span id="autoTaxTxt">0,00</span></th>
              <th></th>
            </tr>
            <tr class="fw-bold">
              <th colspan="5" class="text-end">Total</th>
              <th class="text-end"><span id="totalTxt">0,00</span></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="small text-muted mt-2" id="autoTaxDetails"></div>

      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary prev-step" data-prev="#tab-head">Volver</button>
        <button type="button" class="btn btn-primary next-step" data-next="#tab-payments">Siguiente</button>
      </div>
    </div>

    {{-- P A G O S --}}
    <div class="tab-pane fade" id="tab-payments">
      <div class="alert alert-info">
        Los pagos se cargan luego de crear el comprobante (según tu circuito).<br>
        Si la condición de pago tiene días, se generará la agenda de vencimientos.
      </div>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h6 class="mb-0" id="installmentPreviewLabel">Vencimientos previstos</h6>
              <small class="text-muted">Se muestran las cuotas estimadas para la condición seleccionada.</small>
            </div>
            <span id="installmentPreviewStatus" class="badge bg-light text-secondary border">Faltan datos</span>
          </div>
          <div id="installmentPreviewBody" class="small text-muted">
            Completa los datos del cabezal y agrega al menos un ítem para ver los vencimientos.
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-between mt-2">
        <button type="button" class="btn btn-outline-secondary prev-step" data-prev="#tab-lines">Volver</button>
        <button type="button" class="btn btn-primary next-step" data-next="#tab-alloc">Siguiente</button>
      </div>
    </div>

    {{-- I M P U T A C I O N E S --}}
    <div class="tab-pane fade" id="tab-alloc">
      <div class="alert alert-secondary">
        Las imputaciones (NC/ND/OP) se gestionan luego de guardar el comprobante.
      </div>
      <div class="d-flex justify-content-between mt-2">
        <button type="button" class="btn btn-outline-secondary prev-step" data-prev="#tab-payments">Volver</button>
        <button type="submit" class="btn btn-success" id="btnSubmit">Guardar comprobante</button>
      </div>
    </div>

  </div>

  {{-- contenedor donde se serializan las líneas para enviar al backend --}}
  <div id="linesHiddenInputs"></div>

</form>

{{-- Modal Add/Edit Line --}}
<div class="modal fade" id="lineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="lineModalTitle">Agregar item</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
                <form id="lineForm">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Producto</label>
              <select class="form-select" id="ln_product_id">
                <option value="">Seleccionar producto</option>
                @foreach($products as $p)
                  <option value="{{ $p->id }}"
                    data-code="{{ $p->code }}"
                    data-name="{{ $p->name }}"
                    data-unit="{{ $p->unit }}"
                    data-price="{{ $p->default_price }}"
                    data-account="{{ $p->default_account_id }}"
                    data-center="{{ $p->default_cost_center_id }}">
                    {{ $p->code }} - {{ $p->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">Selecciona un producto para autocompletar.</div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnQuickProduct">Nuevo producto</button>
            </div>
            <div class="col-md-6">
              <label class="form-label">Concepto</label>
              <input type="text" class="form-control" id="ln_concept" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Cantidad</label>
              <input type="number" step="0.01" class="form-control text-end" id="ln_qty" value="1" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Unidad</label>
              <input type="text" class="form-control" id="ln_unit" placeholder="unidad">
            </div>
            <div class="col-md-2">
              <label class="form-label">Precio</label>
              <input type="number" step="0.01" class="form-control text-end" id="ln_price" value="0" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cuenta</label>
              <select class="form-select" id="ln_account_id">
                <option value="">Sin cuenta</option>
                @foreach($accounts as $a)
                  <option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Centro de costo</label>
              <select class="form-select" id="ln_cost_center_id">
                <option value="">Sin asignar</option>
                @foreach($centers as $c)
                  <option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4 ms-auto">
              <label class="form-label">Subtotal</label>
              <input type="text" class="form-control text-end" id="ln_total" readonly>
            </div>
          </div>
          <input type="hidden" id="ln_edit_index" value="">
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="lineSaveBtn">Guardar item</button>
      </div>
    </div>
  </div>
</div>
@endsection

<div class="modal fade" id="productQuickModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="quickProductForm">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title">Nuevo producto</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Codigo</label>
              <input type="text" name="code" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Nombre</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Tipo</label>
              <select name="type" class="form-select" id="qp_type">
                <option value="goods">Bien</option>
                <option value="service">Servicio</option>
                <option value="other">Otro</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Unidad</label>
              <input type="text" name="unit" class="form-control" value="unidad">
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio</label>
              <input type="number" name="default_price" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">IVA %</label>
              <input type="number" name="iva_rate" class="form-control" step="0.01" min="0" max="100" value="21">
            </div>
            <div class="col-md-6">
              <label class="form-label">Cuenta</label>
              <select name="default_account_id" class="form-select">
                <option value="">Sin cuenta</option>
                @foreach($accounts as $a)
                  <option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Centro de costo</label>
              <select name="default_cost_center_id" class="form-select">
                <option value="">Sin asignar</option>
                @foreach($centers as $c)
                  <option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Descripcion</label>
              <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 form-check mt-2">
              <input type="checkbox" class="form-check-input" id="qp_active" name="is_active" value="1" checked>
              <label class="form-check-label" for="qp_active">Activo</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary">Guardar producto</button>
        </div>
      </form>
    </div>
  </div>
</div>
@push('scripts')
<script>
$(function(){
  const products = @json($productOptionsData);
  const productMap = new Map();
  products.forEach(p => productMap.set(String(p.id), p));

  const lines = []; // { product_id, product_label, concept, qty, unit, price, account_id, cost_center_id, line_total, account_label, cost_center_label }

  const fmt = n => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n || 0));
  const fmtQty = n => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n || 0));

  const productSelect = $('#ln_product_id');
  const conceptInput = $('#ln_concept');
  const qtyInput = $('#ln_qty');
  const unitInput = $('#ln_unit');
  const priceInput = $('#ln_price');
  const accountSelect = $('#ln_account_id');
  const centerSelect = $('#ln_cost_center_id');
  const totalInput = $('#ln_total');
  const supplierSelect = $('#supplier_id');
  const autoTaxValueEl = $('#autoTaxTxt');
  const autoTaxDetailsBox = $('#autoTaxDetails');
  let supplierTaxes = [];
  const paymentTermsMeta = @json($termsData ?? []);
  const paymentTermSelect = $('#payment_term_id');
  const issueDateInput = $('#issue_date');
  const installmentPreviewBody = $('#installmentPreviewBody');
  const installmentPreviewLabel = $('#installmentPreviewLabel');
  const installmentPreviewStatus = $('#installmentPreviewStatus');
  const paymentTermsMap = new Map(paymentTermsMeta.map(term => [String(term.id), term]));
  const totalsCache = { subtotal: 0, manualTax: 0, autoTax: 0, total: 0 };

  const computeAutoTaxes = (subtotal, manualTax) => {
    if (!supplierTaxes.length || subtotal <= 0) {
      return { total: 0, rows: [] };
    }

    let total = 0;
    const rows = [];

    supplierTaxes.forEach((tax) => {
      if (!tax || String(tax.applies_on) !== 'invoice') {
        return;
      }
      const rate = Number(tax.rate || 0);
      if (!rate) {
        return;
      }
      const base = tax.base === 'gross'
        ? subtotal + manualTax + total
        : subtotal;
      if (base <= 0) {
        return;
      }
      const amount = Math.round((base * rate / 100) * 100) / 100;
      if (amount <= 0) {
        return;
      }
      const label = `${tax.type === 'retention' ? 'Retencion' : 'Percepcion'} ${tax.name}`;
      rows.push({ id: tax.id, label, amount });
      total = Math.round((total + amount) * 100) / 100;
    });

    return { total, rows };
  };

  const renderAutoTaxDetails = (rows) => {
    if (!autoTaxDetailsBox.length) {
      return;
    }

    autoTaxDetailsBox.empty();

    if (!supplierSelect.length) {
      if (rows.length) {
        const list = $('<ul class="mb-0 ps-3"></ul>');
        rows.forEach((row) => list.append(`<li>${row.label}: ${fmt(row.amount)}</li>`));
        autoTaxDetailsBox.append(list);
      }
      return;
    }

    if (!rows.length) {
      if (!supplierSelect.val()) {
        autoTaxDetailsBox.text('Selecciona un proveedor para evaluar percepciones automaticas.');
      } else {
        autoTaxDetailsBox.text('El proveedor seleccionado no tiene percepciones automaticas configuradas.');
      }
      return;
    }

    const list = $('<ul class="mb-0 ps-3"></ul>');
    rows.forEach((row) => list.append(`<li>${row.label}: ${fmt(row.amount)}</li>`));
    autoTaxDetailsBox.append(list);
  };

  const normalizeTermDays = (rawDays) => {
    if (!rawDays) {
      return [];
    }
    let daysArray = [];
    if (Array.isArray(rawDays)) {
      daysArray = rawDays;
    } else if (typeof rawDays === 'string') {
      daysArray = rawDays.split(',').map((v) => v.trim());
    } else {
      daysArray = [rawDays];
    }

    return Array.from(new Set(daysArray
      .map((v) => Number(v))
      .filter((v) => !Number.isNaN(v))
      .map((v) => (v < 0 ? 0 : v))
    )).sort((a, b) => a - b);
  };

  const planInstallmentsPreview = (total, days) => {
    const normalized = normalizeTermDays(days);
    const schedule = normalized.length ? normalized : [0];
    if (!total || total <= 0) {
      return [];
    }
    const count = Math.max(1, schedule.length);
    const base = count > 1
      ? Math.floor((total / count) * 100) / 100
      : Number(total.toFixed(2));
    const rest = Number((total - base * (count - 1)).toFixed(2));
    const issueDateValue = issueDateInput.val();
    const issueDate = issueDateValue ? new Date(issueDateValue) : new Date();
    return schedule.map((day, index) => {
      const dueDate = new Date(issueDate.getTime());
      dueDate.setDate(issueDate.getDate() + (Number(day) || 0));
      return {
        number: index + 1,
        amount: index < count - 1 ? base : rest,
        dueDate,
        day: Number(day) || 0,
      };
    });
  };

  const formatDueDate = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    return date.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const renderInstallmentPreview = () => {
    if (!installmentPreviewBody.length) {
      return;
    }
    const total = Number(totalsCache.total || 0);
    const selectedTermId = paymentTermSelect.val();
    const term = selectedTermId ? paymentTermsMap.get(String(selectedTermId)) : null;
    const plan = planInstallmentsPreview(total, term?.days ?? []);

    installmentPreviewLabel.text(term ? `Vencimientos (${term.name})` : 'Vencimientos previstos');

    const statusActive = plan.length > 0;
    installmentPreviewStatus
      .toggleClass('bg-success text-white border', statusActive)
      .toggleClass('bg-light text-secondary border', !statusActive)
      .text(statusActive ? 'Cuotas estimadas' : 'Sin cuotas');

    if (!statusActive) {
      installmentPreviewBody.html('Completa el detalle y la condición de pago para ver los vencimientos estimados.');
      return;
    }

    const rows = plan.map((installment) => `
      <div class="d-flex justify-content-between align-items-center border-bottom py-2">
        <div>
          <strong>Cuota ${installment.number}</strong><br>
          <small class="text-muted">${installment.day} días desde la emisión</small>
        </div>
        <div class="text-end">
          <div>${fmt(installment.amount)}</div>
          <div class="text-muted small">${formatDueDate(installment.dueDate)}</div>
        </div>
      </div>
    `);
    installmentPreviewBody.html(rows.join(''));
  };

  const recalcTotals = () => {
    let subtotal = 0;
    lines.forEach(l => subtotal += Number(l.line_total || 0));
    $('#subtotalTxt').text(fmt(subtotal));

    const manualTax = Number($('#tax').val() || 0);
    const autoData = computeAutoTaxes(subtotal, manualTax);

    autoTaxValueEl.text(fmt(autoData.total));
    renderAutoTaxDetails(autoData.rows);

    const total = subtotal + manualTax + autoData.total;
    $('#totalTxt').text(fmt(total));
    totalsCache.subtotal = subtotal;
    totalsCache.manualTax = manualTax;
    totalsCache.autoTax = autoData.total;
    totalsCache.total = total;
    renderInstallmentPreview();
  };

  const loadSupplierTaxes = (supplierId) => {
    supplierTaxes = [];

    if (!supplierSelect.length) {
      recalcTotals();
      return;
    }

    if (!supplierId) {
      renderAutoTaxDetails([]);
      recalcTotals();
      return;
    }

    if (autoTaxDetailsBox.length) {
      autoTaxDetailsBox.text('Calculando percepciones automaticas...');
    }

    fetch(`/suppliers/${supplierId}/edit`, {
      headers: {
        'Accept': 'application/json',
      },
    })
      .then((resp) => {
        if (!resp.ok) {
          throw resp;
        }
        return resp.json();
      })
      .then((payload) => {
        const taxes = Array.isArray(payload.taxes) ? payload.taxes : [];
        supplierTaxes = taxes.filter((tax) => tax && tax.active && tax.applies_on === 'invoice');
        recalcTotals();
      })
      .catch(() => {
        supplierTaxes = [];
        recalcTotals();
      });
  };

  if (supplierSelect.length) {
    supplierSelect.on('change', function () {
      loadSupplierTaxes($(this).val());
    });
    if (supplierSelect.val()) {
      loadSupplierTaxes(supplierSelect.val());
    } else {
      renderAutoTaxDetails([]);
    }
  } else {
    renderAutoTaxDetails([]);
  }

  if (paymentTermSelect.length) {
    paymentTermSelect.on('change', renderInstallmentPreview);
  }

  if (issueDateInput.length) {
    issueDateInput.on('change', renderInstallmentPreview);
  }

  renderInstallmentPreview();

  const pushHiddenInputs = () => {
    const $c = $('#linesHiddenInputs').empty();
    lines.forEach((l, i) => {
      if (l.product_id) {
        $c.append(`<input type="hidden" name="lines[${i}][product_id]" value="${l.product_id}">`);
      }
      $c.append(`<input type="hidden" name="lines[${i}][concept]" value="${$('<div>').text(l.concept).html()}">`);
      $c.append(`<input type="hidden" name="lines[${i}][qty]" value="${l.qty}">`);
      if (l.unit) {
        $c.append(`<input type="hidden" name="lines[${i}][unit]" value="${$('<div>').text(l.unit).html()}">`);
      }
      $c.append(`<input type="hidden" name="lines[${i}][price]" value="${l.price}">`);
      if (l.account_id) {
        $c.append(`<input type="hidden" name="lines[${i}][account_id]" value="${l.account_id}">`);
      }
      if (l.cost_center_id) {
        $c.append(`<input type="hidden" name="lines[${i}][cost_center_id]" value="${l.cost_center_id}">`);
      }
    });
  };

  const redrawTable = () => {
    const $tb = $('#linesTable tbody').empty();
    lines.forEach((l, i) => {
      $tb.append(`
        <tr>
          <td>${l.product_label || ''}</td>
          <td>${l.concept}</td>
          <td class="text-end">${fmtQty(l.qty)}</td>
          <td>${l.unit || ''}</td>
          <td class="text-end">${fmt(l.price)}</td>
          <td>${l.account_label || ''}</td>
          <td>${l.cost_center_label || ''}</td>
          <td class="text-end">${fmt(l.line_total)}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-edit" data-i="${i}">Editar</button>
            <button type="button" class="btn btn-sm btn-outline-danger btn-del" data-i="${i}">Quitar</button>
          </td>
        </tr>
      `);
    });
    recalcTotals();
    pushHiddenInputs();
  };

  const updateLineTotalPreview = () => {
    const q = Number(qtyInput.val() || 0);
    const p = Number(priceInput.val() || 0);
    totalInput.val(fmt(q * p));
  };

  const applyProductDefaults = (product) => {
    if (!product) return;
    if (!conceptInput.val()) {
      conceptInput.val(product.name || '');
    }
    if (!unitInput.val() && product.unit) {
      unitInput.val(product.unit);
    }
    if (Number(priceInput.val() || 0) === 0 && product.default_price) {
      priceInput.val(product.default_price);
    }
    if (product.default_account_id && !accountSelect.val()) {
      accountSelect.val(String(product.default_account_id)).trigger('change');
    }
    if (product.default_cost_center_id && !centerSelect.val()) {
      centerSelect.val(String(product.default_cost_center_id)).trigger('change');
    }
    updateLineTotalPreview();
  };

  productSelect.on('change', function(){
    const productId = $(this).val();
    applyProductDefaults(productMap.get(String(productId)));
  });

  const lineModal = new bootstrap.Modal(document.getElementById('lineModal'));

  const openModal = (editIndex = null) => {
    $('#ln_edit_index').val(editIndex ?? '');
    if (editIndex !== null) {
      const l = lines[editIndex];
      $('#lineModalTitle').text('Editar item');
      productSelect.val(l.product_id ? String(l.product_id) : '').trigger('change');
      conceptInput.val(l.concept);
      qtyInput.val(l.qty);
      unitInput.val(l.unit || '');
      priceInput.val(l.price);
      accountSelect.val(l.account_id ? String(l.account_id) : '').trigger('change');
      centerSelect.val(l.cost_center_id ? String(l.cost_center_id) : '').trigger('change');
      totalInput.val(fmt(l.line_total));
    } else {
      $('#lineModalTitle').text('Agregar item');
      $('#lineForm')[0].reset();
      qtyInput.val('1');
      priceInput.val('0');
      unitInput.val('');
      totalInput.val(fmt(0));
      productSelect.val('').trigger('change');
      accountSelect.val('').trigger('change');
      centerSelect.val('').trigger('change');
    }
    lineModal.show();
  };

  $('#btnAddLine').on('click', () => openModal(null));
  $('#linesTable').on('click', '.btn-edit', function(){ openModal(Number($(this).data('i'))); });
  $('#linesTable').on('click', '.btn-del', function(){
    const index = Number($(this).data('i'));
    lines.splice(index, 1);
    redrawTable();
  });

  qtyInput.on('input', updateLineTotalPreview);
  priceInput.on('input', updateLineTotalPreview);

  $('#lineSaveBtn').on('click', function(){
    const product_id = productSelect.val() || null;
    const product = product_id ? productMap.get(String(product_id)) : null;
    const concept = (conceptInput.val() || '').trim() || (product ? product.name : '');
    if (!concept) {
      nygAlert('Ingresa un concepto o selecciona un producto.');
      return;
    }
    const qty = Number(qtyInput.val() || 0);
    if (qty <= 0) {
      nygAlert('La cantidad debe ser mayor a cero.');
      return;
    }
    const price = Number(priceInput.val() || 0);
    const unit = (unitInput.val() || (product ? product.unit : '') || '').trim();
    const account_id = accountSelect.val() || null;
    const cost_center_id = centerSelect.val() || null;
    const account_label = account_id ? accountSelect.find('option:selected').text() : '';
    const cost_center_label = cost_center_id ? centerSelect.find('option:selected').text() : '';
    const line_total = +(qty * price).toFixed(2);
    const product_label = product ? `${product.code} - ${product.name}` : '';

    const payload = { product_id, product_label, concept, qty, unit, price, account_id, cost_center_id, line_total, account_label, cost_center_label };
    const editIndex = $('#ln_edit_index').val();
    if (editIndex) {
      lines[Number(editIndex)] = payload;
    } else {
      lines.push(payload);
    }
    lineModal.hide();
    redrawTable();
  });

  $('#tax').on('input', recalcTotals);

  const goTo = (selector) => {
    const el = document.querySelector(`${selector}-tab`) || document.querySelector(`[data-bs-target="${selector}"]`);
    if (el) new bootstrap.Tab(el).show();
  };

  const setStepBadge = (n) => {
    $('.step').removeClass('bg-primary').addClass('bg-secondary');
    $(`.step[data-step="${n}"]`).removeClass('bg-secondary').addClass('bg-primary');
  };

  const validateHead = () => {
    if (!$('#doctype').val()) return false;
    if ($('input[name="scope"]').val() === 'purchase' && !$('#supplier_id').val()) return false;
    if (!$('#number').val()) return false;
    if (!$('#issue_date').val()) return false;
    return true;
  };

  const validateLines = () => lines.length > 0;

  $('.next-step').on('click', function(){
    const target = $(this).data('next');
    const current = $('.tab-pane.active').attr('id');

    if (current === 'tab-head' && !validateHead()) {
      nygAlert('Completa los datos del cabezal (proveedor, numero y fecha).');
      return;
    }
    if (current === 'tab-lines' && !validateLines()) {
      nygAlert('Agrega al menos un item al detalle.');
      return;
    }
    goTo(target);
    const stepNo = (target === '#tab-lines') ? 2 : (target === '#tab-payments' ? 3 : 4);
    setStepBadge(stepNo);
  });

  $('.prev-step').on('click', function(){
    const target = $(this).data('prev');
    goTo(target);
    const stepNo = (target === '#tab-head') ? 1 : (target === '#tab-lines' ? 2 : 3);
    setStepBadge(stepNo);
  });

  $('#docForm').on('submit', function(e){
    if (!validateHead()) {
      e.preventDefault();
      nygAlert('Faltan datos del cabezal.');
      return;
    }
    if (!validateLines()) {
      e.preventDefault();
      nygAlert('Agrega al menos un item.');
      return;
    }
    pushHiddenInputs();
  });

  const productQuickModalEl = document.getElementById('productQuickModal');
  const quickModal = new bootstrap.Modal(productQuickModalEl);
  let nestedBackdrop = null;

  productQuickModalEl.addEventListener('shown.bs.modal', () => {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    if (backdrops.length) {
      nestedBackdrop = backdrops[backdrops.length - 1];
      nestedBackdrop.classList.add('nested-backdrop');
    }
  });

  productQuickModalEl.addEventListener('hidden.bs.modal', () => {
    if (nestedBackdrop) {
      nestedBackdrop.classList.remove('nested-backdrop');
      nestedBackdrop = null;
    }
  });
  $('#btnQuickProduct').on('click', function(){
    $('#quickProductForm')[0].reset();
    $('#qp_type').val('goods').trigger('change');
    $('#qp_active').prop('checked', true);
    quickModal.show();
  });

  $('#quickProductForm').on('submit', function(e){
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    fetch('{{ route('products.store') }}', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: formData
    }).then(resp => {
      if (!resp.ok) {
        throw resp;
      }
      return resp.json();
    }).then(data => {
      const product = data.product;
      productMap.set(String(product.id), product);
      const option = $('<option/>', {
        value: product.id,
        text: `${product.code} - ${product.name}`
      }).attr({
        'data-code': product.code || '',
        'data-name': product.name || '',
        'data-unit': product.unit || '',
        'data-price': product.default_price || 0,
        'data-account': product.default_account_id || '',
        'data-center': product.default_cost_center_id || ''
      });
      productSelect.append(option);
      productSelect.val(String(product.id)).trigger('change');
      quickModal.hide();
    }).catch(async err => {
      let message = 'No se pudo crear el producto.';
      if (err.json) {
        try {
          const data = await err.json();
          if (data && data.message) {
            message = data.message;
          }
        } catch (_e) {}
      }
      nygAlert(message, 'error');
    });
  });
});
</script>
@endpush











