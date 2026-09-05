@extends('layouts.app')
@section('title','Nueva orden de pago')

@section('content')
@php
    $formatCurrency = static fn (float $value): string => '$' . number_format($value, 2, ',', '.');
    $paymentMethodPayload = $paymentMethods->map(function ($method) {
        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->name,
            'account_id' => $method->account_id,
            'account_label' => $method->account
                ? trim(($method->account->code ?? '') . ' ' . ($method->account->name ?? ''))
                : '',
        ];
    });
@endphp

<h1 class="h4 mb-3">Nueva orden de pago</h1>

@if($errors->any())
  <div class="alert alert-danger">
    <strong>Revisa los datos cargados:</strong>
    <ul class="mb-0">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form id="paymentForm" method="POST" action="{{ route('payments.store') }}">
  @csrf

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <label class="form-label">Proveedor <span class="text-danger">*</span></label>
      <select class="form-select" name="supplier_id" id="supplier_id" required>
        <option value="">-- Seleccione --</option>
        @foreach($suppliers as $supplier)
          <option value="{{ $supplier->id }}" data-tax="{{ $supplier->tax_id ?? '' }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>
            {{ $supplier->name }}@if($supplier->tax_id) ({{ $supplier->tax_id }}) @endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Fecha <span class="text-danger">*</span></label>
      <input type="date" class="form-control" name="issue_date" id="issue_date" value="{{ old('issue_date', $today) }}" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Numero sugerido</label>
      <input type="text" class="form-control" value="{{ $nextNumber }}" readonly>
      <div class="form-text">Se asigna automaticamente al guardar.</div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Notas</label>
      <input type="text" class="form-control" name="notes" value="{{ old('notes') }}" placeholder="Referencia interna (opcional)">
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
          Lineas de pago
          <button class="btn btn-sm btn-outline-primary" type="button" id="btnAddLine">Agregar linea</button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="linesTable">
              <thead class="table-light">
                <tr>
                  <th>Concepto</th>
                  <th>Medio</th>
                  <th>Cuenta</th>
                  <th class="text-end" style="width:130px;">Importe</th>
                  <th style="width:60px;"></th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot class="table-light">
                <tr>
                  <th colspan="3" class="text-end">Total lineas</th>
                  <th class="text-end"><span id="linesTotalTxt">$0,00</span></th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
          <div>
            Facturas a cancelar
            <div class="small text-muted">
              Selecciona las cuotas pendientes del proveedor.
            </div>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" id="refreshAllocationsBtn">
            <i class="fa-solid fa-rotate"></i> Refrescar
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="allocTable">
              <thead class="table-light">
                <tr>
                  <th></th>
                  <th>Factura</th>
                  <th>Vence</th>
                  <th class="text-end">Pendiente</th>
                  <th class="text-end" style="width:130px;">Pagar</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="5" class="text-center text-muted py-3">Selecciona un proveedor para ver sus pendientes.</td>
                </tr>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <th colspan="4" class="text-end">Total imputado</th>
                  <th class="text-end"><span id="allocTotalTxt">$0,00</span></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="retentionCard" class="card border-0 shadow-sm mb-3 d-none">
    <div class="card-header bg-light fw-semibold">Retenciones estimadas</div>
    <div class="card-body">
      <p class="text-muted mb-2 small" id="retentionIntro">
        Se calculan a partir de las percepciones y retenciones configuradas para el proveedor.
      </p>
      <ul id="retentionList" class="list-unstyled mb-0"></ul>
      <p class="text-muted small mt-2 mb-0" id="retentionFootnote"></p>
    </div>
  </div>

  <div class="alert alert-info d-flex justify-content-between align-items-center">
    <div>
      Las imputaciones deben coincidir con el total de la orden.
      Ajusta los importes si necesitas anticipos o pagos parciales.
    </div>
    <div class="fw-semibold">
      Diferencia: <span id="diffTxt">$0,00</span>
    </div>
  </div>

  <div class="text-end">
    <a href="{{ route('documents.index', ['scope' => 'purchase']) }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-success">Registrar pago</button>
  </div>

  <div id="linesHidden"></div>
  <div id="allocHidden"></div>
</form>

<!-- Modal lineas -->
<div class="modal fade" id="lineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="lineModalTitle">Agregar linea</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Concepto</label>
            <input type="text" class="form-control" id="lineConcept" maxlength="255">
          </div>
          <div class="col-md-6">
            <label class="form-label">Medio de pago</label>
            <select class="form-select" id="linePaymentMethodId" required>
              <option value="">Seleccionar</option>
              @foreach($paymentMethods as $method)
                <option value="{{ $method->id }}">
                  {{ $method->code }} - {{ $method->name }}
                </option>
              @endforeach
            </select>
            <div class="form-text" id="lineAccountHelper"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Importe</label>
            <input type="number" min="0" step="0.01" class="form-control" id="lineAmount">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <input type="hidden" id="lineEditIndex" value="">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="lineSaveBtn">Guardar</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const paymentMethods = @json($paymentMethodPayload);
  const oldLinesData = @json(old('lines', []));
  const oldAllocationsData = @json(old('allocations', []));
  let shouldApplyOldAllocations = Array.isArray(oldAllocationsData) && oldAllocationsData.length > 0;
  const formatCurrency = (value) => {
    const number = Number(value) || 0;
    return '$' + number.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const suppliersSelect = document.getElementById('supplier_id');
  const linesTableBody = document.querySelector('#linesTable tbody');
  const allocTableBody = document.querySelector('#allocTable tbody');
  const linesTotalTxt = document.getElementById('linesTotalTxt');
  const allocTotalTxt = document.getElementById('allocTotalTxt');
  const diffTxt = document.getElementById('diffTxt');
  const hiddenLines = document.getElementById('linesHidden');
  const hiddenAllocs = document.getElementById('allocHidden');
  const retentionCard = document.getElementById('retentionCard');
  const retentionList = document.getElementById('retentionList');
  const retentionFootnote = document.getElementById('retentionFootnote');
  const lineModalEl = document.getElementById('lineModal');
  const lineModal = new bootstrap.Modal(lineModalEl);
  const lineEditIndex = document.getElementById('lineEditIndex');
  const lineConcept = document.getElementById('lineConcept');
  const linePaymentMethodSelect = $('#linePaymentMethodId');
  const linePaymentMethodId = linePaymentMethodSelect.get(0);
  const lineAmount = document.getElementById('lineAmount');
  const lineAccountHelper = document.getElementById('lineAccountHelper');
  const refreshAllocationsBtn = document.getElementById('refreshAllocationsBtn');

  let lines = [];
  let allocations = [];
  let supplierRetentions = [];

  const findPaymentMethod = (id) => paymentMethods.find((method) => Number(method.id) === Number(id));

  if (Array.isArray(oldLinesData) && oldLinesData.length) {
    lines = oldLinesData.map((line) => {
      const method = findPaymentMethod(line.payment_method_id);
      if (!method) {
        return null;
      }
      return {
        concept: line.concept || `Pago ${method.code}`,
        payment_method_id: method.id,
        payment_method_label: `${method.code} - ${method.name}`,
        account_id: method.account_id,
        account_label: method.account_label,
        amount: Number(line.amount || 0),
      };
    }).filter(Boolean);
  }

  const renderLines = () => {
    linesTableBody.innerHTML = '';
    if (!lines.length) {
      const row = document.createElement('tr');
      row.innerHTML = '<td colspan="5" class="text-center text-muted py-3">Agrega lineas de pago.</td>';
      linesTableBody.appendChild(row);
      return;
    }

    lines.forEach((line, index) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${line.concept}</td>
        <td>${line.payment_method_label}</td>
        <td>${line.account_label || ''}</td>
        <td class="text-end">${formatCurrency(line.amount)}</td>
        <td class="text-end">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary btn-line-edit" data-index="${index}">Editar</button>
            <button class="btn btn-outline-danger btn-line-delete" data-index="${index}">Quitar</button>
          </div>
        </td>
      `;
      linesTableBody.appendChild(tr);
    });
  };

  const renderAllocations = () => {
    allocTableBody.innerHTML = '';

    if (!allocations.length) {
      const row = document.createElement('tr');
      row.innerHTML = '<td colspan="5" class="text-center text-muted py-3">No hay pendientes para este proveedor.</td>';
      allocTableBody.appendChild(row);
      return;
    }

    allocations.forEach((item, index) => {
      const tr = document.createElement('tr');
      const checked = item.selected ? 'checked' : '';
      tr.innerHTML = `
        <td>
          <input type="checkbox" class="form-check-input alloc-check" data-index="${index}" ${checked}>
        </td>
        <td>
          <div class="fw-semibold">${item.document_number}</div>
          <div class="small text-muted">${item.doctype_label}</div>
        </td>
        <td>${item.due_date ?? '-'}</td>
        <td class="text-end">${formatCurrency(item.amount_due)}</td>
        <td>
          <input type="number"
                 class="form-control form-control-sm text-end alloc-amount"
                 step="0.01"
                 min="0"
                 max="${item.amount_due}"
                 value="${item.pay_amount}"
                 data-index="${index}">
        </td>
      `;
      allocTableBody.appendChild(tr);
    });
  };

  const syncHiddenInputs = () => {
    hiddenLines.innerHTML = '';
    hiddenAllocs.innerHTML = '';

    lines.forEach((line, index) => {
      const baseName = `lines[${index}]`;
      ['concept', 'payment_method_id', 'amount'].forEach((field) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `${baseName}[${field}]`;
        input.value = line[field];
        hiddenLines.appendChild(input);
      });
    });

    const selectedAllocations = allocations.filter((item) => item.selected && Number(item.pay_amount) > 0);
    selectedAllocations.forEach((alloc, index) => {
      const baseName = `allocations[${index}]`;
      [
        ['document_id', alloc.document_id],
        ['installment_id', alloc.installment_id],
        ['amount', alloc.pay_amount],
      ].forEach(([field, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `${baseName}[${field}]`;
        input.value = value;
        hiddenAllocs.appendChild(input);
      });
    });

    const linesTotal = lines.reduce((acc, line) => acc + Number(line.amount || 0), 0);
    const allocTotal = selectedAllocations.reduce((acc, item) => acc + Number(item.pay_amount || 0), 0);

    return { linesTotal, allocTotal };
  };

  const renderRetentions = (baseAmount) => {
    const retentions = supplierRetentions
      .filter((tax) => tax.type === 'retention' && tax.applies_on === 'payment' && tax.active)
      .map((tax) => {
        const rate = Number(tax.rate) || 0;
        const amount = Math.round(baseAmount * rate) / 100;
        return { ...tax, amount };
      })
      .filter((item) => item.amount > 0);

    if (!retentions.length || baseAmount <= 0) {
      retentionCard.classList.add('d-none');
      retentionList.innerHTML = '';
      retentionFootnote.textContent = '';
      return;
    }

    retentionCard.classList.remove('d-none');
    retentionList.innerHTML = '';

    retentions.forEach((item) => {
      const li = document.createElement('li');
      li.classList.add('d-flex', 'justify-content-between', 'border-bottom', 'py-1');
      li.innerHTML = `
        <span>${item.code} - ${item.name}</span>
        <span>${formatCurrency(item.amount)}</span>
      `;
      retentionList.appendChild(li);
    });

    retentionFootnote.textContent = `Base utilizada: ${formatCurrency(baseAmount)}. El ajuste final se calcula al guardar.`;
  };

  const updateTotals = () => {
    const { linesTotal, allocTotal } = syncHiddenInputs();
    linesTotalTxt.textContent = formatCurrency(linesTotal);
    allocTotalTxt.textContent = formatCurrency(allocTotal);
    diffTxt.textContent = formatCurrency(linesTotal - allocTotal);

    const base = allocTotal > 0 ? allocTotal : linesTotal;
    renderRetentions(base);
  };

  const fetchInstallments = (supplierId) => {
    if (refreshAllocationsBtn) {
      refreshAllocationsBtn.disabled = !supplierId;
      refreshAllocationsBtn.classList.toggle('disabled', !supplierId);
    }

    allocations = [];
    allocTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-3">Cargando...</td></tr>';

    if (!supplierId) {
      allocTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Selecciona un proveedor para ver sus pendientes.</td></tr>';
      supplierRetentions = [];
      renderRetentions(0);
      updateTotals();
      return;
    }

    fetch(`{{ route('payments.pending') }}?supplier_id=${supplierId}`, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((resp) => {
        if (!resp.ok) {
          throw new Error('No se pudo obtener la agenda del proveedor.');
        }
        return resp.json();
      })
      .then((data) => {
        const allocMap = new Map();
        if (shouldApplyOldAllocations) {
          oldAllocationsData.forEach((item) => {
            if (!item) return;
            const key = Number(item.installment_id);
            if (!Number.isNaN(key)) {
              allocMap.set(key, Number(item.amount || 0));
            }
          });
        }

        allocations = data.map((item) => {
          const key = Number(item.installment_id);
          const storedAmount = allocMap.has(key) ? allocMap.get(key) : null;
          const maxAmount = Number(item.amount_due);
          const payAmount = storedAmount !== null ? Math.min(Math.max(storedAmount, 0), maxAmount) : maxAmount;
          const selected = storedAmount !== null ? payAmount > 0 : false;
          return {
            ...item,
            selected,
            pay_amount: selected ? payAmount : maxAmount,
          };
        });

        if (shouldApplyOldAllocations) {
          shouldApplyOldAllocations = false;
        }
        renderAllocations();
        updateTotals();
      })
      .catch((error) => {
        allocTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">No se pudieron cargar los pendientes.</td></tr>';
        console.error(error);
      });

    fetch(`/suppliers/${supplierId}/edit`, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((resp) => {
        if (!resp.ok) {
          throw new Error('No se pudo obtener la configuracion del proveedor.');
        }
        return resp.json();
      })
      .then((payload) => {
        supplierRetentions = Array.isArray(payload.taxes) ? payload.taxes : [];
        updateTotals();
      })
      .catch((error) => {
        console.error(error);
        supplierRetentions = [];
        updateTotals();
      });
  };

  const triggerInstallmentsRefresh = () => {
    const supplierId = suppliersSelect.value;
    fetchInstallments(supplierId);
  };

  suppliersSelect.addEventListener('change', triggerInstallmentsRefresh);

  if (refreshAllocationsBtn) {
    refreshAllocationsBtn.addEventListener('click', triggerInstallmentsRefresh);
  }

  allocTableBody.addEventListener('input', (event) => {
    if (event.target.classList.contains('alloc-amount')) {
      const idx = Number(event.target.dataset.index);
      const max = Number(allocations[idx].amount_due || 0);
      const value = Math.max(0, Math.min(Number(event.target.value || 0), max));
      allocations[idx].pay_amount = value;
      event.target.value = value;
      if (value > 0) {
        allocations[idx].selected = true;
        const checkbox = allocTableBody.querySelector(`.alloc-check[data-index="${idx}"]`);
        if (checkbox) checkbox.checked = true;
      }
      updateTotals();
    }
  });

  allocTableBody.addEventListener('change', (event) => {
    if (event.target.classList.contains('alloc-check')) {
      const idx = Number(event.target.dataset.index);
      allocations[idx].selected = event.target.checked;
      if (event.target.checked && Number(allocations[idx].pay_amount) <= 0) {
        allocations[idx].pay_amount = allocations[idx].amount_due;
        const input = allocTableBody.querySelector(`.alloc-amount[data-index="${idx}"]`);
        if (input) input.value = allocations[idx].pay_amount;
      }
      updateTotals();
    }
  });

  document.getElementById('btnAddLine').addEventListener('click', () => {
    lineEditIndex.value = '';
    lineConcept.value = 'Pago';
    linePaymentMethodSelect.val('').trigger('change');
    lineAmount.value = '0.00';
    lineAccountHelper.textContent = '';
    document.getElementById('lineModalTitle').textContent = 'Agregar linea';
    lineModal.show();
  });

  linePaymentMethodSelect.on('change', () => {
    const methodId = linePaymentMethodSelect.val();
    const selected = findPaymentMethod(methodId);
    lineAccountHelper.textContent = selected?.account_label ? `Cuenta contable: ${selected.account_label}` : '';
    if (selected && (!lineConcept.value || lineConcept.value === 'Pago')) {
      lineConcept.value = `Pago ${selected.code}`;
    }
  });

  document.getElementById('lineSaveBtn').addEventListener('click', () => {
    const method = findPaymentMethod(linePaymentMethodSelect.val());
    const concept = lineConcept.value.trim() || (method ? `Pago ${method.code}` : '');
    const amount = Number(lineAmount.value || 0);

    if (!method || amount <= 0) {
      nygAlert('Completa medio de pago e importe.');
      return;
    }

    const payload = {
      concept,
      payment_method_id: method.id,
      payment_method_label: `${method.code} - ${method.name}`,
      account_id: method.account_id,
      account_label: method.account_label,
      amount,
    };

    if (lineEditIndex.value !== '') {
      lines[Number(lineEditIndex.value)] = payload;
    } else {
      lines.push(payload);
    }

    renderLines();
    updateTotals();
    lineModal.hide();
  });

  linesTableBody.addEventListener('click', (event) => {
    if (event.target.classList.contains('btn-line-edit')) {
      const idx = Number(event.target.dataset.index);
      const line = lines[idx];
      lineEditIndex.value = idx;
      lineConcept.value = line.concept;
      linePaymentMethodSelect.val(String(line.payment_method_id)).trigger('change');
      lineAmount.value = Number(line.amount).toFixed(2);
      const selected = findPaymentMethod(line.payment_method_id);
      lineAccountHelper.textContent = selected?.account_label ? `Cuenta contable: ${selected.account_label}` : '';
      document.getElementById('lineModalTitle').textContent = 'Editar linea';
      lineModal.show();
    }

    if (event.target.classList.contains('btn-line-delete')) {
      const idx = Number(event.target.dataset.index);
      lines.splice(idx, 1);
      renderLines();
      updateTotals();
    }
  });

  document.getElementById('paymentForm').addEventListener('submit', (event) => {
    const totals = syncHiddenInputs();
    if (!lines.length) {
      event.preventDefault();
      nygAlert('Agrega al menos una linea de pago.');
      return;
    }

    const selectedAllocations = allocations.filter((item) => item.selected);
    if (selectedAllocations.length && Math.abs(totals.linesTotal - totals.allocTotal) > 0.01) {
      event.preventDefault();
      nygAlert('El total de la orden y las imputaciones deben coincidir.');
    }
  });

  renderLines();
  renderAllocations();
  updateTotals();

  if (suppliersSelect.value) {
    fetchInstallments(suppliersSelect.value);
  } else if (refreshAllocationsBtn) {
    //refreshAllocationsBtn.disabled = true;
  }
});
</script>
@endpush
