@extends('layouts.app')

@section('title', 'Configuración de importación de pedidos')

@section('content')
  @php
    $selectedClientId = old('party_id') ?: ($clients->first()->id ?? null);
    $selectedFormat = $selectedClientId ? $formats->firstWhere('party_id', $selectedClientId) : null;
    $selectedMappings = $selectedFormat ? ($selectedFormat->field_mappings ?? []) : [];
    $oldFields = collect(old('fields', []));
    $hasOldFieldValues = $oldFields->filter(function ($mapping) {
      return is_array($mapping)
        && (trim((string) ($mapping['column'] ?? '')) !== '' || trim((string) ($mapping['row'] ?? '')) !== '');
    })->isNotEmpty();
    $hasOldMeta = old('sheet') !== null || old('start_row') !== null || old('name') !== null;
    $shouldPrepopulate = ! ($hasOldFieldValues || $hasOldMeta);
    $fieldLabels = \App\Models\TrafficLooseStopImportFormat::fieldLabels();
  @endphp

  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Importación de pedidos</h1>
      <p class="text-muted mb-0">Define para cada cliente en qué columnas y fila empiezan los datos del Excel.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.loose.index') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a pedidos
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  
  <div class="row g-4">
    <div class="col-12">
      <form method="POST" action="{{ route('traffic.loose.import-config.store') }}">
        @csrf
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Cliente</label>
                <select class="form-select import-client-highlight" name="party_id" id="importFormatClient" required>
                  <option value="">Selecciona cliente</option>
                  @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ (int) $selectedClientId === $client->id ? 'selected' : '' }}>
                      {{ $client->business_name ?: $client->name }}
                    </option>
                  @endforeach
                </select>
                <div class="form-text">El formato se guarda por cliente.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nombre interno (opcional)</label>
                <input id="importFormatName" class="form-control" type="text" name="name"
                       value="{{ old('name', $selectedFormat->name ?? '') }}" placeholder="Ej: Excel estándar">
              </div>
              <div class="col-md-6">
                <label class="form-label">Hoja</label>
                <input id="importFormatSheet" class="form-control" type="text" name="sheet"
                       value="{{ old('sheet', $selectedFormat->sheet ?? '') }}" placeholder="Nombre o índice">
                <div class="form-text">Ingresa nombre o número (0 = primera hoja).</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Fila donde comienzan los datos</label>
                <input id="importFormatStartRow" class="form-control" type="number" name="start_row" min="1"
                       value="{{ old('start_row', $selectedFormat->start_row ?? 5) }}">
              </div>
              <div class="col-lg-6">
                <label class="form-label">Fecha de pedido general</label>
                <div class="row g-2">
                  <div class="col">
                    <input class="form-control form-control-sm" type="text" name="order_date_cell_column" id="orderDateCellColumn"
                           value="{{ old('order_date_cell_column', $selectedFormat->order_date_cell_column ?? '') }}"
                           placeholder="Columna">
                  </div>
                  <div class="col">
                    <input class="form-control form-control-sm" type="number" min="1" name="order_date_cell_row" id="orderDateCellRow"
                           value="{{ old('order_date_cell_row', $selectedFormat->order_date_cell_row ?? '') }}"
                           placeholder="Fila">
                  </div>
                </div>
                <div class="form-text text-muted small">
                  Si cada fila no trae fecha, indica la celda fija donde está la fecha general del archivo.
                </div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="importFormatAddressIncludesLocality"
                         name="address_includes_locality"
                         {{ old('address_includes_locality', optional($selectedFormat)->address_includes_locality ?? false) ? 'checked' : '' }}>
                  <label class="form-check-label" for="importFormatAddressIncludesLocality">
                    La dirección ya incluye localidad (no combinar la columna de localidad)
                  </label>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Campo</th>
                    <th style="width: 150px;">Columna</th>
                    <th style="width: 150px;">Fila</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($fieldLabels as $fieldKey => $fieldLabel)
                    <tr data-field-key="{{ $fieldKey }}">
                      <td>{{ $fieldLabel }}</td>
                      <td>
                        <input class="form-control form-control-sm" type="text"
                               name="fields[{{ $fieldKey }}][column]"
                               value="{{ old("fields.{$fieldKey}.column", $selectedMappings[$fieldKey]['column'] ?? '') }}"
                               placeholder="Ej: D">
                      </td>
                      <td>
                        <input class="form-control form-control-sm" type="number" name="fields[{{ $fieldKey }}][row]" min="1"
                        value="{{ old("fields.{$fieldKey}.row", $selectedMappings[$fieldKey]['row'] ?? '') }}"
                        placeholder="{{ optional($selectedFormat)->start_row ?? 5 }}">
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="d-flex align-items-center gap-2">
                <div class="small text-muted" id="importFormatSummary">
                  @if($selectedFormat)
                    Configuración guardada: <strong>{{ $selectedFormat->name ?: 'sin nombre' }}</strong>
                    • Hoja {{ $selectedFormat->sheet ?: 'predeterminada' }} • fila {{ $selectedFormat->start_row }}
                  @else
                    Selecciona un cliente para ver su formato.
                  @endif
                </div>
                <span class="badge bg-secondary" id="importFormatModeBadge">-</span>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-danger" type="button" id="deleteImportFormatBtn" data-delete-route="{{ $selectedFormat ? route('traffic.loose.import-config.destroy', $selectedFormat) : '' }}">
                  <i class="fa-solid fa-trash me-1"></i> Eliminar formato
                </button>
                <button class="btn btn-primary" type="submit">
                  <i class="fa-solid fa-floppy-disk me-1"></i> Guardar formato
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
            <div>
              <h2 class="h6 text-uppercase text-muted mb-1">Resumen de formatos</h2>
              <p class="small text-muted mb-0">
                Asigna columnas y fila inicial para cada columna del modelo. Solo se tomarán los campos que tengan columna definida.
              </p>
            </div>
            <span class="badge text-bg-light">{{ $formats->count() }} formatos</span>
          </div>
          <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
            @forelse($formats as $format)
              <div class="col">
                <div class="border rounded-3 p-3 h-100">
                  <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <div class="fw-semibold">{{ $format->party->business_name ?: $format->party->name }}</div>
                    <span class="badge bg-success">Listo</span>
                  </div>
                  <div class="small text-muted mb-1">
                    Hoja: {{ $format->sheet ?$format->sheet: 'predeterminada' }}: Fila {{ $format->start_row }}
                  </div>
                  @if(!empty($format->name))
                    <div class="small text-muted">Nombre interno: {{ $format->name }}</div>
                  @endif
                </div>
              </div>
            @empty
              <div class="col">
                <div class="text-muted small">Todavía no hay formatos cargados.</div>
              </div>
            @endforelse
          </div>
          <div class="small text-muted mt-3">
            Luego, al importar pedidos y elegir el cliente, se tomará esta configuración automáticamente.
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('styles')
  <style>
    #importFormatClient.import-client-highlight {
      border: 2px solid #d4a017;
      box-shadow: 0 0 0 0.2rem rgba(212, 160, 23, 0.18);
      background: #fff8e1;
    }
  </style>
@endpush

@push('scripts')
  <script>
    $(function () {
      const formats = @json($formatsMap ?? []);
      const shouldPrepopulate = {{ $shouldPrepopulate ? 'true' : 'false' }};
      let allowHydrate = shouldPrepopulate;
      const $client = $('#importFormatClient');
      const $startRow = $('#importFormatStartRow');
      const $sheet = $('#importFormatSheet');
      const $name = $('#importFormatName');
      const $summary = $('#importFormatSummary');
      const $modeBadge = $('#importFormatModeBadge');
      const $locality = $('#importFormatAddressIncludesLocality');
      const $orderDateCol = $('#orderDateCellColumn');
      const $orderDateRow = $('#orderDateCellRow');
      const $fields = $('[data-field-key]');

      const hydrateFields = (mappings = {}) => {
        $fields.each(function () {
          const key = $(this).data('field-key');
          $(this).find(`input[name="fields[${key}][column]"]`).val(mappings[key]?.column ?? '');
          $(this).find(`input[name="fields[${key}][row]"]`).val(mappings[key]?.row ?? '');
        });
      };

      const formatSummary = (config) => {
        if (!config) {
          $summary.html('Selecciona un cliente para ver su formato.');
          return;
        }
        const name = config.name || 'sin nombre';
        const sheet = config.sheet || 'predeterminada';
        const row = config.start_row || '';
        $summary.text(`Configuración guardada: ${name} · Hoja ${sheet} · fila ${row}`);
      };

      const setModeBadge = (config) => {
        if (!$modeBadge.length) return;
        if (config) {
          $modeBadge.text('Edición').removeClass('bg-secondary').addClass('bg-primary');
        } else {
          $modeBadge.text('Nuevo').removeClass('bg-primary').addClass('bg-secondary');
        }
      };

      const handleClientChange = (forceHydrate = false) => {
        const selectedId = ($client.val() || '').toString();
        const config = selectedId && Object.prototype.hasOwnProperty.call(formats, selectedId) ? formats[selectedId] : null;
        formatSummary(config);
        setModeBadge(config);
        const deleteRoute = config?.delete_route || '';
        $('#deleteImportFormatBtn').data('delete-route', deleteRoute);
        const doHydrate = forceHydrate || allowHydrate;
        if (!doHydrate) {
          return;
        }
        if (!config) {
          $startRow.val('');
          $sheet.val('');
          $name.val('');
          $locality.prop('checked', false);
          $orderDateCol.val('');
          $orderDateRow.val('');
          hydrateFields({});
          return;
        }
        $startRow.val(config.start_row ?? '');
        $sheet.val(config.sheet ?? '');
        $name.val(config.name ?? '');
        $locality.prop('checked', !!config.address_includes_locality);
        $orderDateCol.val(config.order_date_cell_column ?? '');
        $orderDateRow.val(config.order_date_cell_row ?? '');
        hydrateFields(config.field_mappings ?? {});
      };

      $client.on('change input', () => {
        allowHydrate = true;
        handleClientChange(true);
      });
      handleClientChange();

      $('#deleteImportFormatBtn').on('click', function () {
        const selectedId = ($client.val() || '').toString();
        const explicitRoute = $(this).data('delete-route');
        const route = explicitRoute || (selectedId ? "{{ url('/traffic/loose-stops/import-config') }}/" + selectedId : '');
        if (!route) {
          if (window.nygAlert) {
          window.nygAlert('Selecciona un cliente con formato para eliminar.', 'info');
        }
          return;
        }
        Swal.fire({
          icon: 'warning',
          title: 'Eliminar formato',
          text: 'Seguro que deseas eliminar este formato?',
          showCancelButton: true,
          confirmButtonText: 'Si, eliminar',
          cancelButtonText: 'Cancelar',
        }).then((result) => {
          if (!result.isConfirmed) {
            return;
          }
          const form = $('<form>', { method: 'POST', action: route });
          form.append('@csrf');
          form.append('@method("DELETE")');
          $('body').append(form);
          form.trigger('submit');
        });
      });
    });
  </script>
@endpush
