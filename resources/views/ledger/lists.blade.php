@extends('layouts.app')
@section('title','Listas de asientos')
@section('content')
@php
  $queryWithoutExport = request()->except(['export']);
  $balanceClass = ($summary['balance'] ?? 0) >= 0 ? 'text-success' : 'text-danger';
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Listas de asientos</h1>
  @if($periods->count())
    <a class="btn btn-sm btn-outline-success"
       href="{{ route('ledger.lists', array_merge($queryWithoutExport, ['export' => 'csv'])) }}">
      Exportar CSV
    </a>
  @endif
</div>

<form method="get" class="row g-3 mb-3">
  <div class="col-sm-3 col-lg-2">
    <label class="form-label">Desde</label>
    <input type="date" class="form-control" name="from" value="{{ $filters['from'] }}">
  </div>
  <div class="col-sm-3 col-lg-2">
    <label class="form-label">Hasta</label>
    <input type="date" class="form-control" name="to" value="{{ $filters['to'] }}">
  </div>
  <div class="col-sm-3 col-lg-2">
    <label class="form-label">Agrupar por</label>
    <select name="group_by" class="form-select">
      <option value="month" {{ $filters['group_by']==='month' ? 'selected' : '' }}>Mes</option>
      <option value="quarter" {{ $filters['group_by']==='quarter' ? 'selected' : '' }}>Trimestre</option>
      <option value="year" {{ $filters['group_by']==='year' ? 'selected' : '' }}>Anio</option>
    </select>
  </div>
  <div class="col-sm-6 col-lg-3">
    <label class="form-label">Cuenta</label>
    <select name="account_id" class="form-select">
      <option value="">Todas</option>
      @foreach($accounts as $account)
        <option value="{{ $account->id }}" {{ (int)($filters['account_id'] ?? 0) === $account->id ? 'selected' : '' }}>
          {{ $account->code }} - {{ $account->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-6 col-lg-3">
    <label class="form-label">Centro de costo</label>
    <select name="cost_center_id" class="form-select">
      <option value="">Todos</option>
      @foreach($centers as $center)
        <option value="{{ $center->id }}" {{ (int)($filters['cost_center_id'] ?? 0) === $center->id ? 'selected' : '' }}>
          {{ $center->code }} - {{ $center->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-6 col-lg-1 d-grid">
    <label class="form-label opacity-0">Aplicar</label>
    <button class="btn btn-primary">Aplicar</button>
  </div>
  <div class="col-sm-6 col-lg-1 d-grid">
    <label class="form-label opacity-0">Limpiar</label>
    <a class="btn btn-outline-secondary" href="{{ route('ledger.lists') }}">Limpiar</a>
  </div>
</form>

@if($availableYears->count())
  <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
    <span class="text-muted small">Atajos por anio:</span>
    @foreach($availableYears as $year)
      @php
        $yearStart = sprintf('%d-01-01', $year);
        $yearEnd = sprintf('%d-12-31', $year);
        $yearActive = $filters['from'] === $yearStart && $filters['to'] === $yearEnd;
      @endphp
      <a class="btn btn-sm {{ $yearActive ? 'btn-primary' : 'btn-outline-secondary' }}"
         href="{{ route('ledger.lists', array_merge($queryWithoutExport, ['from' => $yearStart, 'to' => $yearEnd])) }}">
        {{ $year }}
      </a>
    @endforeach
  </div>
@endif

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="bg-white border rounded p-3">
      <div class="text-muted small">Movimientos</div>
      <div class="h5 mb-0">{{ number_format($summary['entries'] ?? 0, 0, ',', '.') }}</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="bg-white border rounded p-3">
      <div class="text-muted small">Debe total</div>
      <div class="h5 mb-0">{{ number_format($summary['debit'] ?? 0, 2, ',', '.') }}</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="bg-white border rounded p-3">
      <div class="text-muted small">Haber total</div>
      <div class="h5 mb-0">{{ number_format($summary['credit'] ?? 0, 2, ',', '.') }}</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="bg-white border rounded p-3">
      <div class="text-muted small">Saldo neto</div>
      <div class="h5 mb-0 {{ $balanceClass }}">{{ number_format($summary['balance'] ?? 0, 2, ',', '.') }}</div>
    </div>
  </div>
</div>

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Periodo</th>
        <th>Fechas</th>
        <th class="text-end">Movimientos</th>
        <th class="text-end">Debe</th>
        <th class="text-end">Haber</th>
        <th class="text-end">Saldo</th>
        <th class="text-end">Detalle</th>
      </tr>
    </thead>
    <tbody>
      @forelse($periods as $period)
        @php $rowClass = $period['balance'] >= 0 ? 'text-success' : 'text-danger'; @endphp
        <tr>
          <td>{{ $period['label'] }}</td>
          <td>{{ $period['from_label'] }} al {{ $period['to_label'] }}</td>
          <td class="text-end">{{ number_format($period['entries'], 0, ',', '.') }}</td>
          <td class="text-end">{{ number_format($period['debit'], 2, ',', '.') }}</td>
          <td class="text-end">{{ number_format($period['credit'], 2, ',', '.') }}</td>
          <td class="text-end {{ $rowClass }}">{{ number_format($period['balance'], 2, ',', '.') }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('ledger.index', ['from' => $period['from'], 'to' => $period['to']]) }}">
              Ver detalle
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            No hay movimientos para el periodo seleccionado.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
