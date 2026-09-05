@extends('layouts.app')
@section('title','Gráficos')
@section('content')
<style>
  .chart-card { min-height: 340px; }
  .chart-container { position: relative; height: 260px; }
</style>

<h1 class="h5 mb-3">Indicadores de compras y ventas</h1>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm chart-card">
      <div class="card-body">
        <h2 class="h6 mb-3">Top proveedores (últimos 30 días)</h2>
        <div class="chart-container">
          <canvas id="chart-suppliers"></canvas>
        </div>
        @if(empty($topSuppliers['labels']))
          <p class="text-muted small mb-0 mt-3">No hay datos de compras en el período seleccionado.</p>
        @endif
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm chart-card">
      <div class="card-body">
        <h2 class="h6 mb-3">Compras vs Ventas (últimos 6 meses)</h2>
        <div class="chart-container">
          <canvas id="chart-monthly"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const suppliers = @json($topSuppliers);
  const monthly = @json($monthlyComparison);

  const suppliersCanvas = document.getElementById('chart-suppliers');
  if (suppliersCanvas && suppliers.labels.length) {
    new Chart(suppliersCanvas, {
      type: 'bar',
      data: {
        labels: suppliers.labels,
        datasets: [{
          label: 'Compras ($)',
          data: suppliers.totals,
          backgroundColor: 'rgba(13, 110, 253, 0.6)',
          borderColor: 'rgba(13, 110, 253, 1)',
          borderWidth: 1,
          borderRadius: 6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => new Intl.NumberFormat('es-AR').format(value),
            },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: context => `Compras: ${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(context.parsed.y)}`,
            },
          },
        },
      },
    });
  } else if (suppliersCanvas) {
    suppliersCanvas.parentElement.innerHTML = '<p class="text-muted small mb-0">No hay datos para mostrar.</p>';
  }

  const monthlyCanvas = document.getElementById('chart-monthly');
  if (monthlyCanvas) {
    new Chart(monthlyCanvas, {
      data: {
        labels: monthly.labels,
        datasets: [
          {
            type: 'bar',
            label: 'Compras',
            data: monthly.purchases,
            backgroundColor: 'rgba(25, 135, 84, 0.6)',
            borderColor: 'rgba(25, 135, 84, 1)',
            borderWidth: 1,
            borderRadius: 6,
          },
          {
            type: 'line',
            label: 'Ventas',
            data: monthly.sales,
            borderColor: 'rgba(255, 193, 7, 1)',
            backgroundColor: 'rgba(255, 193, 7, 0.3)',
            borderWidth: 2,
            tension: 0.35,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => new Intl.NumberFormat('es-AR').format(value),
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: context => `${context.dataset.label}: ${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(context.parsed.y)}`,
            },
          },
        },
      },
    });
  }
});
</script>
@endpush
