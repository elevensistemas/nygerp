@extends('layouts.app')
@section('title','Gasto en compras')
@section('content')
@php
  $totalSpent = $summary['total'] ?? 0;
  $documentsCount = $summary['documents'] ?? 0;
  $avgPerDay = $metrics['avg_per_day'] ?? 0;
  $avgOnSpent = $metrics['avg_on_spent'] ?? 0;
  $peakDay = $metrics['peak_day'] ?? null;
@endphp
<style>
  .chart-wrap {
    position: relative;
    height: clamp(220px, 60vh, 520px);
  }
  #spendingChart {
    width: 100% !important;
    height: 100% !important;
  }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h5 mb-0">Gasto en compras</h1>
  @if($documentsCount > 0)
    <span class="badge bg-primary bg-opacity-75 fs-6">
      Total periodo: {{ number_format($totalSpent, 2, ',', '.') }}
    </span>
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
  <div class="col-sm-6 col-lg-2 d-grid">
    <label class="form-label opacity-0">Aplicar</label>
    <button class="btn btn-primary">Aplicar</button>
  </div>
  <div class="col-sm-6 col-lg-2 d-grid">
    <label class="form-label opacity-0">Limpiar</label>
    <a class="btn btn-outline-secondary" href="{{ route('purchases.spending') }}">Limpiar</a>
  </div>
</form>

@if(!empty($shortcuts))
  <div class="mb-4 d-flex flex-wrap align-items-center gap-2">
    <span class="text-muted small">Atajos rapidos:</span>
    @foreach($shortcuts as $shortcut)
      @php
        $isActive = $filters['from'] === $shortcut['from'] && $filters['to'] === $shortcut['to'];
      @endphp
      <a class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}"
         href="{{ route('purchases.spending', ['from' => $shortcut['from'], 'to' => $shortcut['to']]) }}">
        {{ $shortcut['label'] }}
      </a>
    @endforeach
  </div>
@endif

<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="bg-white border rounded p-3 h-100">
      <div class="text-muted small">Total gastado</div>
      <div class="h5 mb-0">{{ number_format($totalSpent, 2, ',', '.') }}</div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="bg-white border rounded p-3 h-100">
      <div class="text-muted small">Comprobantes</div>
      <div class="h5 mb-0">{{ number_format($documentsCount, 0, ',', '.') }}</div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="bg-white border rounded p-3 h-100">
      <div class="text-muted small">Promedio diario</div>
      <div class="h5 mb-0">{{ number_format($avgPerDay, 2, ',', '.') }}</div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="bg-white border rounded p-3 h-100">
      <div class="text-muted small">Promedio dias con gasto</div>
      <div class="h5 mb-0">{{ number_format($avgOnSpent, 2, ',', '.') }}</div>
    </div>
  </div>
</div>

@if($peakDay)
  <div class="alert alert-light border mb-4">
    Mejor dia dentro del periodo: <strong>{{ $peakDay['label'] }}</strong>
    ({{ number_format($peakDay['amount'], 2, ',', '.') }}, {{ $peakDay['documents'] }} comprobantes)
  </div>
@endif

<div class="bg-white border rounded p-3 mb-4">
  <div class="chart-wrap">
    <canvas id="spendingChart"></canvas>
  </div>
</div>

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Dia</th>
        <th class="text-end">Comprobantes</th>
        <th class="text-end">Monto del dia</th>
        <th class="text-end">Acumulado</th>
      </tr>
    </thead>
    <tbody>
      @forelse($daily as $row)
        <tr>
          <td>{{ $row['label'] }}</td>
          <td class="text-end">{{ number_format($row['documents'], 0, ',', '.') }}</td>
          <td class="text-end">{{ number_format($row['amount'], 2, ',', '.') }}</td>
          <td class="text-end">{{ number_format($row['running_total'], 2, ',', '.') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="text-center text-muted py-4">
            No se registraron comprobantes en el periodo seleccionado.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('spendingChart');
  if (!ctx) return;

  const labels = @json($chart['labels']);
  const series = @json($chart['series']);
  const cumulative = @json($chart['cumulative']);

  if (!labels.length) {
    ctx.parentElement.innerHTML = '<p class="text-muted mb-0">Aun no hay datos para graficar.</p>';
    return;
  }

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          type: 'bar',
          label: 'Monto diario',
          data: series,
          backgroundColor: 'rgba(13, 110, 253, 0.35)',
          borderColor: 'rgba(13, 110, 253, 0.7)',
          borderWidth: 1,
        },
        {
          type: 'line',
          label: 'Acumulado',
          data: cumulative,
          borderColor: 'rgba(25, 135, 84, 0.9)',
          backgroundColor: 'rgba(25, 135, 84, 0.25)',
          tension: 0.3,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: value => new Intl.NumberFormat('es-AR').format(value) },
        },
        y1: {
          beginAtZero: true,
          position: 'right',
          grid: { drawOnChartArea: false },
          ticks: { callback: value => new Intl.NumberFormat('es-AR').format(value) },
        },
      },
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: function (context) {
              const label = context.dataset.label || '';
              const value = context.parsed.y ?? context.raw;
              return `${label}: ${new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)}`;
            },
          },
        },
      },
    },
  });
});
</script>
@endpush
