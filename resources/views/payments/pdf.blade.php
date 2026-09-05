<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Orden de pago</title>
  <style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 20px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
    th { background: #f2f2f2; }
    .text-right { text-align: right; }
    .muted { color: #666; }
    .summary { margin-bottom: 18px; }
  </style>
</head>
<body>
  <h1>Orden de pago</h1>

  <div class="summary">
    <table>
      <tbody>
        <tr>
          <th style="width: 25%;">Numero</th>
          <td>{{ $document->number ?? 'OP Pendiente' }}</td>
          <th style="width: 25%;">Fecha</th>
          <td>{{ optional($document->issue_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
          <th>Proveedor</th>
          <td>{{ optional($supplier)->name }}</td>
          <th>CUIT</th>
          <td>{{ optional($supplier)->tax_id }}</td>
        </tr>
        <tr>
          <th>Notas</th>
          <td colspan="3">{{ $document->notes }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <h2 style="font-size:16px; margin-bottom:6px;">Lineas de pago</h2>
  <table>
    <thead>
      <tr>
        <th>Concepto</th>
        <th>Cuenta</th>
        <th class="text-right">Importe</th>
      </tr>
    </thead>
    <tbody>
      @foreach($lines as $line)
        <tr>
          <td>{{ $line->concept }}</td>
          <td>{{ optional($line->account)->code }} {{ optional($line->account)->name }}</td>
          <td class="text-right">{{ number_format($line->price * $line->qty, 2, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if($allocations->count())
    <h2 style="font-size:16px; margin-bottom:6px;">Comprobantes imputados</h2>
    <table>
      <thead>
        <tr>
          <th>Documento</th>
          <th class="text-right">Importe imputado</th>
        </tr>
      </thead>
      <tbody>
        @foreach($allocations as $alloc)
          <tr>
            <td>
              {{ optional($alloc->targetDocument)->number }}
              <div class="muted">{{ optional($alloc->targetDocument)->doctype_label }}</div>
            </td>
            <td class="text-right">{{ number_format($alloc->amount, 2, ',', '.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <table>
    <tbody>
      <tr>
        <th style="width: 75%;">Total orden</th>
        <td class="text-right">{{ number_format($document->total, 2, ',', '.') }}</td>
      </tr>
    </tbody>
  </table>
</body>
</html>
