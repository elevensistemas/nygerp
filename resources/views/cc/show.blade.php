@extends('layouts.app')
@section('title','Cuenta Corriente')

@section('content')
@php
    $money = fn (float $value) => '$' . number_format($value, 2, ',', '.');
@endphp

<h1 class="h5 mb-3">Cuenta Corriente - {{ $party->name }}</h1>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Facturas / Notas de Debito (Debe)</div>
        <div class="fs-4 fw-semibold text-danger mb-0">{{ $money($totals['debe']) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Notas de Credito / Pagos (Haber)</div>
        <div class="fs-4 fw-semibold text-success mb-0">{{ $money($totals['haber']) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Saldo acumulado</div>
        <div class="fs-4 fw-semibold {{ $totals['saldo'] >= 0 ? 'text-danger' : 'text-success' }}">
          {{ $money($totals['saldo']) }}
        </div>
        <div class="small text-muted mb-0">
          {{ $totals['saldo'] >= 0 ? 'Monto a pagar' : 'Saldo a favor' }}
        </div>
      </div>
    </div>
  </div>
</div>

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm mb-0">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Comprobante</th>
        <th class="text-end">Debe</th>
        <th class="text-end">Haber</th>
        <th class="text-end">Saldo</th>
      </tr>
    </thead>
    <tbody>
      @forelse($movs as $m)
        <tr>
          <td>{{ $m['date'] }}</td>
          <td>
            <a href="{{ route('documents.show',$m['id']) }}" class="text-decoration-none">
              {{ $m['doc'] }}
            </a>
          </td>
          <td class="text-end fw-medium text-danger">{{ $m['debe'] ? $money($m['debe']) : '' }}</td>
          <td class="text-end fw-medium text-success">{{ $m['haber'] ? $money($m['haber']) : '' }}</td>
          <td class="text-end fw-semibold">{{ $money($m['saldo']) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center text-muted py-3">Sin movimientos registrados.</td>
        </tr>
      @endforelse
    </tbody>
    <tfoot class="table-light">
      <tr class="fw-semibold">
        <td colspan="2" class="text-end">Totales</td>
        <td class="text-end text-danger">{{ $money($totals['debe']) }}</td>
        <td class="text-end text-success">{{ $money($totals['haber']) }}</td>
        <td class="text-end">{{ $money($totals['saldo']) }}</td>
      </tr>
    </tfoot>
  </table>
</div>
@endsection
