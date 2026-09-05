<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mayor analitico</title>
  <style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 12px; }
    h2 { font-size: 14px; margin: 18px 0 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
    th { background: #f2f2f2; }
    .text-right { text-align: right; }
    .summary-table td { border: none; }
    .muted { color: #666; font-size: 10px; }
    .spacer { height: 12px; }
  </style>
</head>
<body>
  <h1>Mayor analitico</h1>
  <table class="summary-table">
    <tr>
      <td><strong>Periodo</strong></td>
      <td>{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</td>
    </tr>
    <tr>
      <td><strong>Saldo inicial</strong></td>
      <td>{{ number_format($summary['opening'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
      <td><strong>Debe</strong></td>
      <td>{{ number_format($summary['debit'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
      <td><strong>Haber</strong></td>
      <td>{{ number_format($summary['credit'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
      <td><strong>Saldo final</strong></td>
      <td>{{ number_format($summary['closing'] ?? 0, 2, ',', '.') }}</td>
    </tr>
  </table>
  <p class="muted">Generado el {{ optional($generatedAt ?? now())->format('d/m/Y H:i') }}</p>

  @foreach($groups as $group)
    @php $account = $group['account']; @endphp
    <div class="spacer"></div>
    <h2>{{ optional($account)->code }} - {{ optional($account)->name }}</h2>
    <table>
      <thead>
        <tr>
          <th style="width: 80px;">Fecha</th>
          <th>Detalle</th>
          <th class="text-right" style="width: 90px;">Debe</th>
          <th class="text-right" style="width: 90px;">Haber</th>
          <th class="text-right" style="width: 90px;">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="4" class="text-right"><strong>Saldo inicial</strong></td>
          <td class="text-right"><strong>{{ number_format($group['opening'], 2, ',', '.') }}</strong></td>
        </tr>
        @forelse($group['rows'] as $row)
          <tr>
            <td>{{ $row['date'] }}</td>
            <td>{{ $row['description'] }}</td>
            <td class="text-right">{{ $row['debit'] ? number_format($row['debit'], 2, ',', '.') : '' }}</td>
            <td class="text-right">{{ $row['credit'] ? number_format($row['credit'], 2, ',', '.') : '' }}</td>
            <td class="text-right"><strong>{{ number_format($row['balance'], 2, ',', '.') }}</strong></td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-right">Sin movimientos en el periodo.</td>
          </tr>
        @endforelse
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2">Totales</th>
          <th class="text-right">{{ number_format($group['debit_total'], 2, ',', '.') }}</th>
          <th class="text-right">{{ number_format($group['credit_total'], 2, ',', '.') }}</th>
          <th class="text-right">{{ number_format($group['closing'], 2, ',', '.') }}</th>
        </tr>
      </tfoot>
    </table>
  @endforeach
</body>
</html>
