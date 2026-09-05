@extends('layouts.app')
@section('title','Mayor analitico')

@section('content')
<h1 class="h5 mb-3">Mayor analitico</h1>

<form class="row g-3 align-items-end mb-4" method="get">
  <div class="col-md-3">
    <label class="form-label">Desde</label>
    <input type="date" class="form-control" name="from" value="{{ $from }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Hasta</label>
    <input type="date" class="form-control" name="to" value="{{ $to }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Cuentas (opcional)</label>
    <select class="form-select" name="accounts[]" id="filter_accounts" multiple data-placeholder="Selecciona una o mas cuentas">
      @foreach($accounts as $account)
        <option value="{{ $account->id }}" {{ in_array($account->id, $selectedAccountIds ?? []) ? 'selected' : '' }}>
          {{ $account->code }} - {{ $account->name }}
        </option>
      @endforeach
    </select>
    <div class="form-text">Si no seleccionas cuentas se muestran todos los movimientos del periodo.</div>
  </div>
  <div class="col-md-2 d-flex gap-2">
    <button type="submit" class="btn btn-outline-secondary flex-fill">Filtrar</button>
    <button type="submit"
            class="btn btn-outline-primary flex-fill"
            formaction="{{ route('ledger.analytics.pdf') }}"
            formtarget="_blank">
      Exportar PDF
    </button>
  </div>
</form>

<div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Saldo inicial</div>
        <div class="fs-5 fw-semibold">{{ number_format($summary['opening'] ?? 0, 2, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Debe</div>
        <div class="fs-5 fw-semibold text-success">{{ number_format($summary['debit'] ?? 0, 2, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Haber</div>
        <div class="fs-5 fw-semibold text-danger">{{ number_format($summary['credit'] ?? 0, 2, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Saldo final</div>
        <div class="fs-5 fw-semibold">{{ number_format($summary['closing'] ?? 0, 2, ',', '.') }}</div>
      </div>
    </div>
  </div>
</div>

@if(!$groups->count())
  <div class="alert alert-info">
    No se encontraron movimientos para los filtros seleccionados.
  </div>
@else
  <div class="accordion" id="analyticsAccordion">
    @foreach($groups as $index => $group)
      @php
        $account = $group['account'];
        $headingId = 'accHeading' . $index;
        $collapseId = 'accBody' . $index;
      @endphp
      <div class="accordion-item mb-3 border-0 shadow-sm">
        <h2 class="accordion-header" id="{{ $headingId }}">
          <button class="accordion-button @if($index>0) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
            <div class="d-flex flex-column flex-md-row w-100">
              <div class="me-auto">
                <div class="fw-semibold">
                  {{ optional($account)->code }} - {{ optional($account)->name }}
                </div>
                <div class="small text-muted">
                  Movimientos: {{ $group['entries_count'] }} | Saldo final: {{ number_format($group['closing'], 2, ',', '.') }}
                </div>
              </div>
              <div class="d-flex gap-3 small mt-2 mt-md-0">
                <div>Saldo inicial: <strong>{{ number_format($group['opening'], 2, ',', '.') }}</strong></div>
                <div>Debe: <strong>{{ number_format($group['debit_total'], 2, ',', '.') }}</strong></div>
                <div>Haber: <strong>{{ number_format($group['credit_total'], 2, ',', '.') }}</strong></div>
              </div>
            </div>
          </button>
        </h2>
        <div id="{{ $collapseId }}" class="accordion-collapse collapse @if($index===0) show @endif" data-bs-parent="#analyticsAccordion">
          <div class="accordion-body">
            @if(empty($group['rows']))
              <div class="alert alert-light mb-0">
                No hay movimientos registrados para esta cuenta en el periodo seleccionado.
              </div>
            @else
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 100px;">Fecha</th>
                      <th>Detalle</th>
                      <th class="text-end" style="width: 140px;">Debe</th>
                      <th class="text-end" style="width: 140px;">Haber</th>
                      <th class="text-end" style="width: 140px;">Saldo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="table-secondary">
                      <td colspan="4" class="text-end fw-semibold">Saldo inicial</td>
                      <td class="text-end fw-semibold">{{ number_format($group['opening'], 2, ',', '.') }}</td>
                    </tr>
                    @foreach($group['rows'] as $row)
                      <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="text-end">{{ $row['debit'] ? number_format($row['debit'], 2, ',', '.') : '' }}</td>
                        <td class="text-end">{{ $row['credit'] ? number_format($row['credit'], 2, ',', '.') : '' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($row['balance'], 2, ',', '.') }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                  <tfoot class="table-light">
                    <tr>
                      <th colspan="2">Totales</th>
                      <th class="text-end">{{ number_format($group['debit_total'], 2, ',', '.') }}</th>
                      <th class="text-end">{{ number_format($group['credit_total'], 2, ',', '.') }}</th>
                      <th class="text-end">{{ number_format($group['closing'], 2, ',', '.') }}</th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection
