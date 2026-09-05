<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #ddd; padding: 6px; }
    th { background: #f5f5f5; text-align: left; }
    .muted { color: #555; }
  </style>
</head>
<body>
  <h1>Ruta {{ $route->code }}</h1>
  <p class="muted">Generada el {{ $route->created_at->format('d/m/Y H:i') }}</p>

  <table>
    <tr>
      <th>Transportista</th>
      <td>{{ $route->transportista->name ?? '-' }}</td>
      <th>Transporte</th>
      <td>{{ $route->transporte->alias ?? '-' }}</td>
    </tr>
    <tr>
      <th>Fecha programada</th>
      <td>{{ optional($route->scheduled_date)->format('d/m/Y') ?? 'Sin fecha' }}</td>
      <th>Tráfico</th>
      <td>{{ $route->traffic_summary ?? 'Sin datos' }}</td>
    </tr>
    <tr>
      <th>Distancia</th>
      <td>{{ $route->distance_km ? $route->distance_km . ' km' : 'n/d' }}</td>
      <th>Duración</th>
      <td>{{ $route->duration_formatted ?? 'n/d' }}</td>
    </tr>
  </table>

  <h2>Paradas</h2>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Etiqueta</th>
        <th>Dirección</th>
        <th>Contacto</th>
        <th>Notas</th>
      </tr>
    </thead>
    <tbody>
      @foreach($route->stops->sortBy('sequence') as $stop)
        <tr>
          <td>{{ $stop->sequence }}</td>
          <td>{{ $stop->label }}</td>
          <td>{{ $stop->address }}</td>
          <td>
            {{ $stop->contact_name ?: '-' }}<br>
            <span class="muted">{{ $stop->contact_phone ?: '' }}</span>
          </td>
          <td>{{ $stop->notes ?: '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if(!empty($route->traffic_report['congestion_counts']))
    <h2>Resumen de tráfico</h2>
    <table>
      <thead>
        <tr>
          <th>Nivel</th>
          <th>Segmentos</th>
        </tr>
      </thead>
      <tbody>
        @foreach($route->traffic_report['congestion_counts'] as $level => $count)
          <tr>
            <td>{{ ucfirst($level) }}</td>
            <td>{{ $count }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
  <h1>Ruta {{ $route->code }}</h1>
<p class="muted">Generada el {{ $route->created_at->format('d/m/Y H:i') }}</p>

@if(!empty($mapImageBase64) || !empty($mapImageUrl))
  <h2>Mapa de la ruta</h2>
  <img
    src="{{ $mapImageBase64 ?: $mapImageUrl }}"
    alt="Mapa de la ruta"
    style="width: 100%; max-height: 400px; margin: 8px 0;"
  >
@endif

</body>
</html>
