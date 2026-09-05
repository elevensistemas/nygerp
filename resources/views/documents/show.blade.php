@extends('layouts.app')
@section('title','Comprobante')
@section('content')

<h1 class="h5 mb-3">{{ $document->doctype_label }} {{ $document->number }}</h1>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <h6 class="text-muted mb-1">Proveedor</h6>
        <strong>{{ data_get($document, 'supplier.name', '—') }}</strong><br>
        CUIT: {{ data_get($document, 'supplier.tax_id', '—') }}<br>
        {{ data_get($document, 'supplier.address', '—') }}
      </div>
      <div class="col-md-6 text-end">
        <h6 class="text-muted mb-1">Fecha emisión</h6>
        <strong>{{ optional($document->issue_date)->format('d/m/Y') ?? '—' }}</strong><br>
        Condición: {{ data_get($document, 'term.name', '—') }}
      </div>
    </div>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-lines">Detalle</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-installments">Vencimientos</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ledger">Asiento contable</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-lines">
    <table class="table table-sm table-striped">
      <thead class="table-light">
        <tr>
          <th>Producto</th>
          <th>Concepto</th>
          <th class="text-end">Cantidad</th>
          <th>Unidad</th>
          <th class="text-end">Precio</th>
          <th class="text-end">Importe</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($document->lines ?? []) as $l)
          <tr>
            <td>{{ trim((optional($l->product)->code ? optional($l->product)->code . ' ' : '') . (optional($l->product)->name ?? '')) }}</td>
            <td>{{ $l->concept }}</td>
            <td class="text-end">{{ $l->qty !== null ? number_format($l->qty, 2, ',', '.') : '' }}</td>
            <td>{{ $l->unit }}</td>
            <td class="text-end">{{ $l->price !== null ? '$' . number_format($l->price, 2, ',', '.') : '' }}</td>
            <td class="text-end">${{ number_format($l->line_total ?? 0, 2, ',', '.') }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-muted">Sin líneas.</td>
          </tr>
        @endforelse
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="5" class="text-end">Total</th>
          <th class="text-end">${{ number_format($document->total ?? 0, 2, ',', '.') }}</th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="tab-pane fade" id="tab-installments">
    @if(($document->installments ?? collect())->count())
      <ul class="list-group">
        @foreach($document->installments as $i)
          <li class="list-group-item d-flex justify-content-between">
            <span>Vto {{ optional($i->due_date)->format('d/m/Y') ?? '—' }}</span>
            <span class="badge bg-{{ ($i->paid ?? false) ? 'success' : 'secondary' }}">
              ${{ number_format($i->amount ?? 0, 2, ',', '.') }}
            </span>
          </li>
        @endforeach
      </ul>
    @else
      <p class="text-muted">No hay cuotas generadas.</p>
    @endif
  </div>

  <div class="tab-pane fade" id="tab-ledger">
    <p class="text-muted">Podés consultar el asiento desde el módulo Contabilidad.</p>
  </div>
</div>

@php $canAllocate = in_array($document->doctype, ['credit_note','debit_note','payment_order']); @endphp
@if($canAllocate)
  <div class="mt-3">
    <button class="btn btn-outline-primary" id="btnOpenAlloc" data-pending-url="{{ route('payments.pending') }}" data-supplier-id="{{ $document->supplier_id }}">Crear imputación</button>
  </div>

  <!-- Allocation Modal -->
  <div class="modal fade" id="allocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <form method="POST" action="{{ route('allocations.store') }}">
          @csrf
          <input type="hidden" name="document_id" value="{{ $document->id }}">
          <div class="modal-header">
            <h6 class="modal-title">Crear imputación desde {{ $document->doctype_label }} {{ $document->number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Documento destino (factura/cuota)</label>
              <select id="alloc_installment_select" class="form-select" name="installment_id" required>
                <option value="">Cargando...</option>
              </select>
              <input type="hidden" id="alloc_target_document_id" name="target_document_id" value="">
            </div>
            <div class="mb-3">
              <label class="form-label">Importe a imputar</label>
              <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear imputación</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @push('scripts')
  <script>
    $(function(){
      const $btn = $('#btnOpenAlloc');
      const $modal = new bootstrap.Modal(document.getElementById('allocModal'));
      const $select = $('#alloc_installment_select');
      const $targetInput = $('#alloc_target_document_id');

      function loadInstallments() {
        $select.html('<option value="">Cargando...</option>');
  const pendingUrl = $btn.data('pending-url');
  const supplierId = $btn.data('supplier-id');
  $.getJSON(pendingUrl, { supplier_id: supplierId })
          .done(function(rows){
            $select.empty();
            if (!rows.length) {
              $select.append('<option value="">No hay cuotas disponibles</option>');
              return;
            }
            rows.forEach(function(r){
              // option value = installment_id, store target document id in data attr
              const text = r.document_number + ' - ' + r.due_date + ' - $' + Number(r.amount_due).toFixed(2);
              const $opt = $('<option>').val(r.installment_id).text(text).data('doc', r.document_id).data('amt', r.amount_due);
              $select.append($opt);
            });
            $select.on('change', function(){
              const docId = $(this).find('option:selected').data('doc');
              $targetInput.val(docId || '');
            }).trigger('change');
          })
          .fail(function(){
            $select.html('<option value="">Error al cargar</option>');
          });
      }

      $btn.on('click', function(){
        loadInstallments();
        $modal.show();
      });

      // check hidden flag element to decide whether to auto-open
      const openAllocFlag = parseInt($('#open_alloc_flag').val() || '0');
      if (openAllocFlag === 1) {
        loadInstallments();
        $modal.show();
      }
    });
  </script>
  @endpush
  <input type="hidden" id="open_alloc_flag" value="{{ session()->has('open_alloc') ? '1' : '0' }}">
@endif

@endsection



