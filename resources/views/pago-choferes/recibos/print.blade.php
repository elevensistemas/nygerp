<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Recibo #{{ $recibo->id }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h3>Recibo de Liquidacion #{{ $recibo->id }}</h3>
  <p>Transportista: {{ $recibo->displayName() ?: '-' }}</p>
  <p>Periodo: {{ optional($recibo->periodo_desde)->format('d/m/Y') }} - {{ optional($recibo->periodo_hasta)->format('d/m/Y') }}</p>
  <p>Estado: {{ $recibo->estado }}</p>
  <table class="table table-sm">
    <thead><tr><th>Fecha</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
    <tbody>
      @foreach($recibo->items as $item)
        @php $routeDate = data_get($item->meta, 'fecha'); @endphp
        <tr>
          <td>{{ $routeDate ? \Carbon\Carbon::parse($routeDate)->format('d/m/Y') : '-' }}</td>
          <td>{{ $item->concepto }}</td>
          <td class="text-end">$ {{ number_format((float) $item->importe, 2, ',', '.') }}</td>
        </tr>
      @endforeach
      <tr><th colspan="2">Total</th><th class="text-end">$ {{ number_format((float) $recibo->importe_total, 2, ',', '.') }}</th></tr>
    </tbody>
  </table>
  <script>window.print();</script>
</body>
</html>
