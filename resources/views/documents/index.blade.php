@extends('layouts.app')

@section('title','Comprobantes')

@section('content')
@php
    $fmt = static fn (float $value): string => '$' . number_format($value, 2, ',', '.');

    $scopeLabel = 'Todos';
    if ($scope === 'sale') {
        $scopeLabel = 'Ventas';
    } elseif ($scope === 'purchase') {
        $scopeLabel = 'Compras';
    }

  $signFor = static fn (?string $scope, ?string $doctype): int =>
    ($scope === 'purchase')
      ? (in_array(strtolower((string)$doctype), ['invoice','debit_note']) ? 1 : (in_array(strtolower((string)$doctype), ['credit_note','payment_order']) ? -1 : 0))
      : (in_array(strtolower((string)$doctype), ['invoice','debit_note']) ? 1 : (in_array(strtolower((string)$doctype), ['credit_note','receipt']) ? -1 : 0));

  // Totales de la página respetando el signo según tipo (sólo para compras tiene efecto)
  $pageTotals = ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
  foreach ($documents as $pd) {
    $s = $signFor($scope, $pd->doctype);
    $pageTotals['subtotal'] += $s * (float) $pd->subtotal;
    $pageTotals['tax'] += $s * (float) $pd->tax;
    $pageTotals['total'] += $s * (float) $pd->total;
  }
  $pageTotals = array_map(fn($v) => round((float)$v, 2), $pageTotals);
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
  <div>
    <h1 class="h4 mb-1">Comprobantes · {{ $scopeLabel }}</h1>
    <p class="text-muted mb-0">Controlá tus comprobantes y analiza los totales por proveedor, tipo y período.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary {{ $scope === 'sale' ? 'active' : '' }}" href="{{ route('documents.index', array_filter(['scope' => 'sale'] + ($filters ?? []))) }}">Ventas</a>
    <a class="btn btn-outline-secondary {{ $scope === 'purchase' ? 'active' : '' }}" href="{{ route('documents.index', array_filter(['scope' => 'purchase'] + ($filters ?? []))) }}">Compras</a>
    <a class="btn btn-primary" href="{{ route('documents.create', ['scope' => $scope ?? 'purchase']) }}">Nuevo comprobante</a>
    @if($scope === 'purchase')
      <a class="btn btn-outline-success" href="{{ route('payments.create') }}">Orden de pago</a>
    @endif
  </div>
</div>

<form method="GET" class="card shadow-sm border-0 mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Filtros</span>
    <a href="{{ route('documents.index', ['scope' => $scope]) }}" class="text-decoration-none small">Limpiar</a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <input type="hidden" name="scope" value="{{ $scope }}">
      <div class="col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="from" value="{{ $filters['from'] ?? '' }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="to" value="{{ $filters['to'] ?? '' }}">
      </div>
      @if($scope === 'purchase')
        <div class="col-md-3">
          <label class="form-label">Proveedor</label>
          <select class="form-select" name="supplier_id">
            <option value="">Todos</option>
            @foreach($suppliers as $supplier)
              <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? null) == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="col-md-3">
        <label class="form-label">Buscar</label>
        <input type="text" class="form-control" name="search" placeholder="Número, notas..." value="{{ $filters['search'] ?? '' }}">
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Total filtrado</div>
        <div class="fs-4 fw-semibold">{{ $fmt($summary['total']) }}</div>
        <div class="small text-muted">Subtotal {{ $fmt($summary['subtotal']) }} · IVA {{ $fmt($summary['tax']) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Comprobantes</div>
        <div class="fs-4 fw-semibold">{{ number_format($summary['count']) }}</div>
        <div class="small text-muted">Promedio {{ $fmt($summary['average']) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Totales de la página</div>
        <div class="fs-4 fw-semibold">{{ $fmt($pageTotals['total']) }}</div>
        <div class="small text-muted">Mostrando {{ $documents->count() }} registros</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        @if($scope === 'purchase' && $topSuppliers->isNotEmpty())
          @php $top = $topSuppliers->first(); @endphp
          <div class="text-muted small">Proveedor destacado</div>
          <div class="fw-semibold">{{ $top->name }}</div>
          <div class="small text-muted">{{ number_format($top->documents_count) }} comprobantes · {{ $fmt($top->total_sum) }}</div>
        @elseif($doctypeBreakdown->isNotEmpty())
          @php $topType = $doctypeBreakdown->first(); @endphp
          <div class="text-muted small">Tipo más frecuente</div>
          <div class="fw-semibold">{{ $topType->label }}</div>
          <div class="small text-muted">{{ number_format($topType->count) }} comprobantes · {{ $fmt($topType->total) }}</div>
        @else
          <div class="text-muted small">Resumen</div>
          <div class="fw-semibold">Sin datos</div>
          <div class="small text-muted">Ajustá los filtros para ver estadísticas.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold">Totales por tipo</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Tipo</th>
              <th class="text-end">Comprobantes</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($doctypeBreakdown as $row)
              <tr>
                <td>{{ $row->label }}</td>
                <td class="text-end">{{ number_format($row->count) }}</td>
                <td class="text-end">{{ $fmt($row->total) }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-3">Sin datos para los filtros seleccionados</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @if($scope === 'purchase')
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold">Top proveedores</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Proveedor</th>
                <th class="text-end">Comp.</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              @forelse($topSuppliers as $item)
                <tr>
                  <td>{{ $item->name }}</td>
                  <td class="text-end">{{ number_format($item->documents_count) }}</td>
                  <td class="text-end">{{ $fmt($item->total_sum) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted py-3">Sin proveedores para mostrar.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
</div>

<div class="table-responsive bg-white shadow-sm rounded">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
          <th>Tipo</th>
          <th>Número</th>
          <th>Proveedor / Cliente</th>
          <th class="text-end">Subtotal</th>
          <th class="text-end">IVA</th>
          <th class="text-end">Total</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($documents as $doc)
        <tr>
          <td>{{ optional($doc->issue_date)->format('d/m/Y') }}</td>
          <td>
            @php $docSign = $signFor($scope, $doc->doctype); @endphp
            @if($docSign > 0)
              <span class="badge bg-success-subtle text-success text-uppercase px-3">{{ $doc->doctype_label }}</span>
            @elseif($docSign < 0)
              <span class="badge bg-danger-subtle text-danger text-uppercase px-3">{{ $doc->doctype_label }}</span>
            @else
              <span class="badge bg-secondary-subtle text-secondary text-uppercase px-3">{{ $doc->doctype_label }}</span>
            @endif
          </td>
          <td class="fw-semibold">{{ $doc->number }}</td>
          <td>
            @if($doc->scope === 'purchase')
              {{ $doc->supplier->name ?? 'Sin proveedor' }}
            @else
              <span class="text-muted">Cliente pendiente</span>
            @endif
            @if($doc->notes)
              <div class="text-muted small">{{ \Illuminate\Support\Str::limit($doc->notes, 60) }}</div>
            @endif
          </td>
          @php $sign = $docSign; $absSubtotal = abs((float)$doc->subtotal); $absTax = abs((float)$doc->tax); $absTotal = abs((float)$doc->total); @endphp
          <td class="text-end {{ $sign > 0 ? 'text-success' : ($sign < 0 ? 'text-danger' : '') }}">{{ $sign >= 0 ? '' : '-' }}{{ $fmt($absSubtotal) }}</td>
          <td class="text-end {{ $sign > 0 ? 'text-success' : ($sign < 0 ? 'text-danger' : '') }}">{{ $sign >= 0 ? '' : '-' }}{{ $fmt($absTax) }}</td>
          <td class="text-end fw-semibold {{ $sign > 0 ? 'text-success' : ($sign < 0 ? 'text-danger' : '') }}">{{ $sign >= 0 ? '' : '-' }}{{ $fmt($absTotal) }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show', $doc->id) }}">
              Ver
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            No se encontraron comprobantes con los filtros seleccionados.
          </td>
        </tr>
      @endforelse
    </tbody>
    <tfoot class="table-light">
      <tr class="fw-semibold">
        <td colspan="4" class="text-end">Totales de la página</td>
        <td class="text-end">{{ $fmt($pageTotals['subtotal']) }}</td>
        <td class="text-end">{{ $fmt($pageTotals['tax']) }}</td>
        <td class="text-end">{{ $fmt($pageTotals['total']) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="mt-3">
  {{ $documents->onEachSide(1)->links() }}
</div>
@endsection
