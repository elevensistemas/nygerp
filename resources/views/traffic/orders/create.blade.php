@extends('layouts.app')

@section('title', 'Nuevo pedido')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Nuevo pedido logístico</h1>
      <p class="text-muted mb-0">Definí el cliente, la fecha y las direcciones del pedido para poder planificar rutas.</p>
    </div>
    <div class="page-actions">
    <a class="btn btn-outline-secondary" href="{{ route('traffic.orders.index') }}">
      <i class="fa-solid fa-arrow-left me-1"></i> Volver
    </a>
    </div>

  @php $customers = $customers ?? collect(); @endphp
  @if(!empty($warnings))
    <div class="alert alert-warning">
      <strong>Advertencias de importación:</strong>
      <ul class="mb-0">
        @foreach($warnings as $warn)
          <li>{{ $warn }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('traffic.orders.store') }}" id="orderForm">
    @csrf

    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Cliente *</label>
            <select name="party_id" class="form-select @error('party_id') is-invalid @enderror" required>
              <option value="">Seleccionar...</option>
              @foreach($customers as $customer)
                <option value="{{ $customer->id }}" {{ old('party_id') == $customer->id ? 'selected' : '' }}>
                  {{ $customer->business_name ?: $customer->name }}
                </option>
              @endforeach
            </select>
            @error('party_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha de pedido *</label>
            <input name="order_date" type="date" class="form-control @error('order_date') is-invalid @enderror" value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
            @error('order_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-12">
            <label class="form-label">Instrucciones</label>
            <textarea name="delivery_instructions" class="form-control" rows="2">{{ old('delivery_instructions') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    @php
      $addressesData = old('addresses', $addressesData ?? []);
    @endphp
    <div class="card shadow-sm border-0">
      @include('traffic.components.addresses-form', [
        'wrapperId' => 'addressesWrapper',
        'addButtonId' => 'addAddressBtn',
        'title' => 'Direcciones del pedido',
        'description' => 'Agrega al menos dos direcciones.',
        'addButtonLabel' => 'Agregar dirección',
        'collectionName' => 'addresses',
        'addresses' => $addressesData,
        'minItems' => 2,
        'minItemsMessage' => 'El pedido necesita al menos dos direcciones.',
        'startEmpty' => true,
      ])
      <div class="card-body border-top bg-light">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importAddressesModal">
            <i class="fa-solid fa-file-import me-1"></i> Importar direcciones
          </button>
          <span class="text-muted small">Formato Excel: fila 5 en adelante. A referencia, C notas, D direccion (se geocodifica). Podes elegir la hoja a importar.</span>
        </div>
        <div id="importInvalidList" class="alert alert-warning small mt-2 d-none"></div>
        <div id="importAddressesMapWrapper" class="mt-3 d-none">
          <div class="small text-muted mb-1">Mapa general de direcciones importadas</div>
          <div id="importAddressesMap" class="map-frame" style="height: 220px;"></div>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
          <i class="fa-solid fa-save me-1"></i> Guardar pedido
        </button>
      </div>
    </div>
  </form>

  <div class="modal fade" id="importAddressesModal" tabindex="-1" aria-labelledby="importAddressesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importAddressesModalLabel">Importar direcciones</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="importAddressesForm">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Archivo Excel</label>
              <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv,text/csv" required>
              <div class="form-text">Formato: fila 5 en adelante. A referencia, C notas, D direccion (se geocodifica). Podes adjuntar .xlsx.</div>
            </div>
            <input type="hidden" name="sheet" id="importSheetInput" value="">
            <div id="importPreview" class="border rounded bg-white d-none">
              <div class="d-flex align-items-center justify-content-between border-bottom px-2 py-2">
                <small class="text-muted">Vista previa por hoja</small>
                <div id="importPreviewLoading" class="small text-muted d-none">Cargando...</div>
              </div>
              <ul class="nav nav-tabs px-2 pt-2" id="importPreviewTabs" role="tablist"></ul>
              <div class="tab-content p-2" id="importPreviewContent"></div>
            </div>
            <div class="alert alert-info small mb-0">Se agregarán a la lista actual de direcciones.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Importar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  (function () {
    const form = document.getElementById('importAddressesForm');
    const invalidList = document.getElementById('importInvalidList');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    const fileInput = form ? form.querySelector('input[name="file"]') : null;
    const sheetInput = document.getElementById('importSheetInput');
    const previewWrapper = document.getElementById('importPreview');
    const previewTabs = document.getElementById('importPreviewTabs');
    const previewContent = document.getElementById('importPreviewContent');
    const previewLoading = document.getElementById('importPreviewLoading');
    const mapWrapper = document.getElementById('importAddressesMapWrapper');
    const mapEl = document.getElementById('importAddressesMap');
    const defaultSubmitHtml = submitBtn ? submitBtn.innerHTML : '';
    let isImporting = false;
    let overviewMap = null;
    let overviewLayer = null;

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const renderInvalid = (items) => {
      if (!invalidList) return;
      if (!items.length) {
        invalidList.classList.add('d-none');
        invalidList.innerHTML = '';
        return;
      }
      invalidList.classList.remove('d-none');
      invalidList.innerHTML = '<strong>No se pudieron validar estas direcciones:</strong><ul class="mb-0 mt-2">' +
        (items.map(it => `<li>${typeof it === 'string' ? it : (it.address || 'Direccion no encontrada')}</li>`).join('')) +
        '</ul>';
    };

    const processAddresses = (addresses) => {
      if (!Array.isArray(addresses) || !addresses.length) return;
      const builder = window.addressesFormBuilders ? window.addressesFormBuilders['addressesWrapper'] : null;
      if (!builder) return;
      builder.addAddresses(addresses);
    };

    const renderOverviewMap = (addresses) => {
      if (!mapEl || !window.L) return;
      const coords = (addresses || []).map((addr) => {
        const latRaw = addr.latitude ?? addr.lat;
        const lngRaw = addr.longitude ?? addr.lng;
        const lat = parseFloat(latRaw);
        const lng = parseFloat(lngRaw);
        return (Number.isFinite(lat) && Number.isFinite(lng)) ? [lat, lng] : null;
      }).filter(Boolean);

      if (!coords.length) {
        if (mapWrapper) mapWrapper.classList.add('d-none');
        return;
      }

      if (mapWrapper) mapWrapper.classList.remove('d-none');
      if (!overviewMap) {
        overviewMap = window.L.map(mapEl, { attributionControl: false, zoomControl: false });
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 20,
        }).addTo(overviewMap);
      }

      if (overviewLayer) {
        overviewLayer.remove();
      }
      overviewLayer = window.L.featureGroup().addTo(overviewMap);
      coords.forEach((c) => window.L.marker(c).addTo(overviewLayer));
      if (typeof overviewLayer.getBounds === 'function' && overviewLayer.getLayers().length) {
        overviewMap.fitBounds(overviewLayer.getBounds(), { padding: [20, 20] });
      }
    };

    const resetPreview = () => {
      if (previewTabs) previewTabs.innerHTML = '';
      if (previewContent) previewContent.innerHTML = '';
      if (sheetInput) sheetInput.value = '';
      if (previewWrapper) previewWrapper.classList.add('d-none');
    };

    const setPreviewLoading = (loading, message) => {
      if (!previewLoading) return;
      previewLoading.classList.toggle('d-none', !loading);
      previewLoading.textContent = message || 'Cargando...';
    };

    const renderPreview = (data) => {
      if (!previewWrapper || !previewTabs || !previewContent) return;
      const columns = Array.isArray(data.columns) && data.columns.length ? data.columns : ['A', 'B', 'C', 'D', 'E'];
      const sheets = Array.isArray(data.sheets) ? data.sheets : [];
      previewWrapper.classList.remove('d-none');

      if (!sheets.length) {
        previewTabs.innerHTML = '';
        previewContent.innerHTML = '<div class="text-muted small p-2">No se encontraron hojas para previsualizar.</div>';
        if (sheetInput) sheetInput.value = '';
        return;
      }

      previewTabs.innerHTML = sheets.map((sheet, index) => {
        const label = escapeHtml(sheet.name || `Hoja ${index + 1}`);
        const active = index === 0 ? 'active' : '';
        return `<li class="nav-item" role="presentation">
          <button class="nav-link ${active}" data-bs-toggle="tab" data-bs-target="#importSheet${index}" type="button" role="tab" data-sheet="${label}">
            ${label}
          </button>
        </li>`;
      }).join('');

      previewContent.innerHTML = sheets.map((sheet, index) => {
        const rows = Array.isArray(sheet.rows) ? sheet.rows : [];
        const active = index === 0 ? 'show active' : '';
        const bodyHtml = rows.length
          ? rows.map((row) => {
            const rowCells = columns.map((col) => `<td>${escapeHtml(row?.cells?.[col] ?? '')}</td>`).join('');
            return `<tr><td class="text-muted">${escapeHtml(row?.row ?? '')}</td>${rowCells}</tr>`;
          }).join('')
          : `<tr><td colspan="${columns.length + 1}" class="text-muted text-center">Sin datos para mostrar</td></tr>`;

        return `<div class="tab-pane fade ${active}" id="importSheet${index}" role="tabpanel">
          <div class="table-responsive" style="max-height: 240px; overflow: auto;">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:50px;">#</th>
                  ${columns.map(col => `<th>${escapeHtml(col)}</th>`).join('')}
                </tr>
              </thead>
              <tbody>${bodyHtml}</tbody>
            </table>
          </div>
        </div>`;
      }).join('');

      if (sheetInput) {
        sheetInput.value = sheets[0].name || '0';
      }

      previewTabs.querySelectorAll('button[data-sheet]').forEach((btn) => {
        btn.addEventListener('shown.bs.tab', (event) => {
          if (sheetInput) {
            sheetInput.value = event.target.getAttribute('data-sheet') || '';
          }
        });
      });
    };

    const setImportLoading = (loading) => {
      if (!submitBtn) return;
      isImporting = loading;
      submitBtn.disabled = loading;
      if (fileInput) fileInput.disabled = loading;
      submitBtn.innerHTML = loading
        ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Importando...'
        : defaultSubmitHtml;
    };

    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (isImporting) return;
      if (!fileInput || !fileInput.files.length) {
        if (window.nygAlert) {
          window.nygAlert('Selecciona un archivo Excel o CSV.', 'warning');
        }
        return;
      }
      const formData = new FormData();
      formData.append('file', fileInput.files[0]);
      if (sheetInput && sheetInput.value) {
        formData.append('sheet', sheetInput.value);
      }
      setImportLoading(true);
      try {
        const response = await fetch('{{ route('traffic.orders.import') }}', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: formData,
        });
        if (!response.ok) {
          if (window.nygAlert) {
            window.nygAlert('No se pudo importar el archivo.', 'error');
          }
          return;
        }
        const data = await response.json();
        processAddresses(data.addresses || []);
        renderInvalid(data.warnings || []);
        renderOverviewMap(data.addresses || []);
        const modalEl = document.getElementById('importAddressesModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal?.hide();
        fileInput.value = '';
        resetPreview();
      } catch (error) {
        console.error(error);
        if (window.nygAlert) {
          window.nygAlert('Ocurrio un error al importar.', 'error');
        }
      } finally {
        setImportLoading(false);
      }
    });

    if (fileInput) {
      fileInput.addEventListener('change', async () => {
        if (!fileInput.files.length) {
          resetPreview();
          return;
        }
        if (previewTabs) previewTabs.innerHTML = '';
        if (previewContent) previewContent.innerHTML = '';
        if (previewWrapper) previewWrapper.classList.remove('d-none');
        setPreviewLoading(true);
        try {
          const formData = new FormData();
          formData.append('file', fileInput.files[0]);
          const response = await fetch('{{ route('traffic.orders.import.preview') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData,
          });
          if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = data.error || 'No se pudo previsualizar el archivo.';
            renderPreview({ columns: ['A', 'B', 'C', 'D', 'E'], sheets: [] });
            if (previewContent) {
              previewContent.innerHTML = `<div class="text-danger small p-2">${escapeHtml(message)}</div>`;
            }
            return;
          }
          const data = await response.json();
          renderPreview(data || {});
        } catch (error) {
          console.error(error);
          renderPreview({ columns: ['A', 'B', 'C', 'D', 'E'], sheets: [] });
          if (previewContent) {
            previewContent.innerHTML = '<div class="text-danger small p-2">Ocurrio un error al previsualizar.</div>';
          }
        } finally {
          setPreviewLoading(false);
        }
      });
    }

    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
      orderForm.addEventListener('submit', (e) => {
        const builder = window.addressesFormBuilders ? window.addressesFormBuilders['addressesWrapper'] : null;
        if (builder) {
          builder.syncFromDom?.();
          if (!builder.addresses || builder.addresses.length < 2) {
            e.preventDefault();
            if (window.nygAlert) {
              window.nygAlert('El pedido necesita al menos dos direcciones.', 'warning');
            }
          }
        }
      });
    }

    const addressesWrapper = document.getElementById('addressesWrapper');
    if (addressesWrapper) {
      addressesWrapper.addEventListener('addresses:updated', (event) => {
        const list = event.detail?.addresses || [];
        renderOverviewMap(list);
      });
      setTimeout(() => {
        const builder = window.addressesFormBuilders ? window.addressesFormBuilders['addressesWrapper'] : null;
        if (builder && Array.isArray(builder.addresses)) {
          renderOverviewMap(builder.addresses);
        }
      }, 0);
    }
  })();
</script>
@endpush


