<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $report->alias }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
    h1 { font-size: 18px; margin: 0 0 6px; }
    p { margin: 0 0 12px; color: #4b5563; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; text-transform: uppercase; font-size: 10px; letter-spacing: .04em; }
    .empty { padding: 16px; border: 1px solid #d1d5db; background: #f9fafb; }
  </style>
</head>
<body>
  <h1>{{ $report->alias }}</h1>
  <p>Generado: {{ $generatedAt->format('d/m/Y H:i') }}</p>

  @if(empty($rows))
    <div class="empty">Sin resultados.</div>
  @else
    <table>
      <thead>
        <tr>
          @foreach($columns as $column)
            <th>{{ str_replace('_', ' ', $column) }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr>
            @foreach($columns as $column)
              <td>{{ data_get((array) $row, $column) }}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</body>
</html>
