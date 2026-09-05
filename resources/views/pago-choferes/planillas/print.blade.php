<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Planilla {{ $planilla->numero }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h3>Planilla {{ $planilla->numero }}</h3>
  <p>Fecha: {{ optional($planilla->fecha)->format('d/m/Y') }} | Estado: {{ $planilla->estado }}</p>
  <table class="table table-sm">
    <thead><tr><th>Recibo</th><th>Chofer</th><th>CBU/CVU</th><th class="text-end">Monto</th></tr></thead>
    <tbody>
      @foreach($planilla->reciboLinks as $link)
        @php
          $transportista = optional($link->recibo)->transportista;
          $defaultMethod = $transportista ? $transportista->defaultPaymentMethod : null;
          $paymentCbu = trim((string) ($defaultMethod ? $defaultMethod->cbu : ($transportista ? $transportista->cbu : '')));
        @endphp
        <tr>
          <td>#{{ $link->recibo_chofer_id }}</td>
          <td>{{ optional($link->recibo)->displayName() ?: '-' }}</td>
          <td>{{ $paymentCbu !== '' ? $paymentCbu : 'Sin CBU/CVU' }}</td>
          <td class="text-end">$ {{ number_format((float) $link->monto_en_planilla, 2, ',', '.') }}</td>
        </tr>
      @endforeach
      <tr><th colspan="3">Total</th><th class="text-end">$ {{ number_format((float) $planilla->total, 2, ',', '.') }}</th></tr>
    </tbody>
  </table>
  <script>window.print();</script>
</body>
</html>
