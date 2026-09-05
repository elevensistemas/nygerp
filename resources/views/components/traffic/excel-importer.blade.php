@props([
  'formId',
  'previewFileUrl',
  'uploadedStops' => collect(),
  'mapProvider' => 'mapbox',
  'prefix' => 'excel',
  'actionField' => 'import_action',
  'manualField' => 'manual_addresses',
  'sheetField' => 'sheet',
  'mapField' => 'map_provider',
  'warnings' => [],
  'showMap' => true,
])

@php
  $prefixId = $prefix ?: 'excel';
  $defaultDescription = 'Debe incluir columnas de direccion y referencia del pedido (datos desde fila 5).';
@endphp

<input type="hidden" name="{{ $manualField }}" id="{{ $prefixId }}ManualInput" value="">
<input type="hidden" name="{{ $actionField }}" id="{{ $prefixId }}ActionInput" value="validate">
<input type="hidden" name="{{ $sheetField }}" id="{{ $prefixId }}SheetInput" value="">

<div class="card shadow-sm border-0 h-100">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <div>
      <h2 class="h6 mb-0 text-uppercase text-muted" style="letter-spacing: 0.08em;">Importar Excel</h2>
      <small class="text-muted">Sube un archivo, previsualiza la hoja y valida direcciones.</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-primary" type="button" id="{{ $prefixId }}ValidateBtn">
        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
        <i class="fa-solid fa-shield-check me-1"></i>
        <span class="{{ $prefixId }}-validate-label">Validar direcciones</span>
      </button>
      <button class="btn btn-sm btn-primary" type="button" id="{{ $prefixId }}SaveBtn">
        <i class="fa-solid fa-save me-1"></i> Guardar sueltas
      </button>
    </div>
    <div class="w-100 mt-3 d-none" id="{{ $prefixId }}ProgressWrapper">
      <div class="small text-muted mb-1" id="{{ $prefixId }}ProgressLabel">Procesando direcciones...</div>
      <div class="progress" style="height: 10px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated" id="{{ $prefixId }}ProgressBar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <input class="form-control mb-2" type="file" name="file" accept=".xlsx,.xls,.csv,.txt" aria-label="Archivo de pedidos">
    <div class="form-text mb-3"
         id="{{ $prefixId }}Description"
         data-default-description="{{ e($defaultDescription) }}">
      {{ $defaultDescription }}
    </div>

    <div id="{{ $prefixId }}Preview" class="solver-preview d-none mt-2">
      <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-2 bg-white">
        <small class="text-muted">Vista previa por hoja</small>
        <div id="{{ $prefixId }}PreviewLoading" class="small text-muted d-none">Cargando...</div>
      </div>
      <ul class="nav nav-tabs px-3 pt-2" id="{{ $prefixId }}PreviewTabs" role="tablist"></ul>
      <div class="tab-content p-3" id="{{ $prefixId }}PreviewContent"></div>
    </div>

    <div class="mt-3">
      <div class="border rounded p-3 bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="small text-muted">Resumen</div>
            <div class="fw-semibold" id="{{ $prefixId }}TotalStops">0 direcciones</div>
          </div>
          <div class="text-success fw-semibold" id="{{ $prefixId }}ValidStops">0 válidas</div>
          <div class="text-danger fw-semibold" id="{{ $prefixId }}InvalidStops">0 invalidas</div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-outline-warning btn-sm" type="button" id="{{ $prefixId }}InvalidBtn" data-bs-toggle="modal" data-bs-target="#{{ $prefixId }}InvalidModal" disabled>
            <i class="fa-solid fa-triangle-exclamation me-1"></i> Ver direcciones invalidas
          </button>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
          <h6 class="mb-0 text-uppercase text-muted" style="letter-spacing: 0.08em;">Direcciones validadas</h6>
          <small class="text-muted">Lista de direcciones que pasaron la validación. Puedes editarlas si fue por error.</small>
        </div>
        <div class="card-body" id="{{ $prefixId }}ValidListContainer">
          <div class="text-muted">No hay direcciones validadas aún.</div>
        </div>
      </div>
    </div>

    @if($showMap)
      <div class="mt-3">
        <div class="small text-muted mb-2">Mapa general</div>
        <div id="{{ $prefixId }}OverviewMap" class="map-frame" style="height: 220px;"></div>
      </div>
    @endif
  </div>
</div>

<div class="modal fade" id="{{ $prefixId }}InvalidModal" tabindex="-1" aria-labelledby="{{ $prefixId }}InvalidModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $prefixId }}InvalidModalLabel">Direcciones a corregir</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning small">
          Ajusta las direcciones sin validar y vuelve a ejecutar la previsualizacion.
        </div>
        <div id="{{ $prefixId }}InvalidList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger" id="{{ $prefixId }}RemoveInvalidBtn">
          <i class="fa-solid fa-trash-can me-1"></i> Eliminar todas las inválidas
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="{{ $prefixId }}RevalidateBtn">Validar nuevamente</button>
      </div>
    </div>
  </div>
</div>

@once
  @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  @endpush
  @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  @endpush
@endonce

@push('scripts')
  <script>
    (() => {
      const form = document.getElementById(@json($formId));
      if (!form) return;

      const prefix = @json($prefixId);
      const fileInput = form.querySelector('input[name="file"]');
      const sheetInput = document.getElementById(`${prefix}SheetInput`);
      const manualInput = document.getElementById(`${prefix}ManualInput`);
      const actionInput = document.getElementById(`${prefix}ActionInput`);
      const previewWrapper = document.getElementById(`${prefix}Preview`);
      const previewTabs = document.getElementById(`${prefix}PreviewTabs`);
      const previewContent = document.getElementById(`${prefix}PreviewContent`);
      const previewLoading = document.getElementById(`${prefix}PreviewLoading`);
      const validateBtn = document.getElementById(`${prefix}ValidateBtn`);
      const saveBtn = document.getElementById(`${prefix}SaveBtn`);
      const invalidBtn = document.getElementById(`${prefix}InvalidBtn`);
      const invalidList = document.getElementById(`${prefix}InvalidList`);
      const revalidateBtn = document.getElementById(`${prefix}RevalidateBtn`);
      const removeInvalidBtn = document.getElementById(`${prefix}RemoveInvalidBtn`);
      const invalidModalEl = document.getElementById(`${prefix}InvalidModal`);
      if (invalidModalEl && invalidModalEl.parentElement !== document.body) {
        document.body.appendChild(invalidModalEl);
      }
      const invalidModal = (invalidModalEl && window.bootstrap) ? window.bootstrap.Modal.getOrCreateInstance(invalidModalEl) : null;
      const totalStopsEl = document.getElementById(`${prefix}TotalStops`);
      const invalidStopsEl = document.getElementById(`${prefix}InvalidStops`);
      const overviewEl = document.getElementById(`${prefix}OverviewMap`);
      const progressWrapper = document.getElementById(`${prefix}ProgressWrapper`);
      const progressLabel = document.getElementById(`${prefix}ProgressLabel`);
      const progressBar = document.getElementById(`${prefix}ProgressBar`);
      const previewFileUrl = @json($previewFileUrl);
      const uploadedStops = @json($uploadedStops);
      const warnings = @json($warnings);
      const googleMapsKey = @json(config('services.google_maps.key') ?? env('GOOGLE_MAPS_KEY'));
      const googleRegion = 'AR';

      const fetchOpenStreetSuggestions = async (query) => {
        try {
          const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}` +
            '&format=jsonv2&addressdetails=1&limit=5&countrycodes=ar&accept-language=es';
          const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
          });
          if (!response.ok) {
            return [];
          }
          const data = await response.json();
          if (!Array.isArray(data) || !data.length) {
            return [];
          }
          return data.map((feature) => ({
            address: feature.display_name ?? '',
            lat: feature.lat ? parseFloat(feature.lat) : null,
            lng: feature.lon ? parseFloat(feature.lon) : null,
          }));
        } catch (error) {
          console.error('Error en autocomplete OpenStreetMap:', error);
          return [];
        }
      };

      const fetchGoogleSuggestions = async (query) => {
        if (!googleMapsKey) {
          return [];
        }
        const params = new URLSearchParams({
          key: googleMapsKey,
          address: query,
          language: 'es',
          region: googleRegion,
        });
        const response = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?${params.toString()}`);
        if (!response.ok) {
          throw new Error(`Google Maps error ${response.status}`);
        }
        const data = await response.json();
        if (!data || data.status !== 'OK') {
          if (data?.status === 'ZERO_RESULTS') {
            return [];
          }
          throw new Error(data?.error_message || data?.status || 'Google Maps geocode error');
        }
        return (Array.isArray(data.results) ? data.results : []).map((result) => ({
          address: result.formatted_address ?? '',
          lat: result.geometry?.location?.lat ?? null,
          lng: result.geometry?.location?.lng ?? null,
        }));
      };

      const fetchAddressSuggestions = async (query) => {
        if (!query || query.length < 3) {
          return [];
        }
        if (googleMapsKey) {
          try {
            const results = await fetchGoogleSuggestions(query);
            if (results.length) {
              return results;
            }
          } catch (error) {
            console.error('Error en autocomplete Google Maps:', error);
          }
        }
        return fetchOpenStreetSuggestions(query);
      };
      let stopsState = Array.isArray(uploadedStops) ? uploadedStops : [];
      const importModalEl = form.closest('.modal');
      const importModal = (importModalEl && window.bootstrap) ? window.bootstrap.Modal.getOrCreateInstance(importModalEl) : null;

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const resetPreview = () => {
        if (previewTabs) previewTabs.innerHTML = '';
        if (previewContent) previewContent.innerHTML = '';
        if (sheetInput) sheetInput.value = '';
        if (previewWrapper) previewWrapper.classList.add('d-none');
      };

      const startProgress = (message = 'Procesando direcciones...') => {
        if (!progressWrapper || !progressBar || !progressLabel) return;
        progressWrapper.classList.remove('d-none');
        progressLabel.textContent = message;
        progressBar.style.width = '100%';
        progressBar.setAttribute('aria-valuenow', '100');
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
            <button class="nav-link ${active}" data-bs-toggle="tab" data-bs-target="#${prefix}Sheet${index}" type="button" role="tab" data-sheet="${label}">
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

          return `<div class="tab-pane fade ${active}" id="${prefix}Sheet${index}" role="tabpanel">
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
              const sheetValue = event.target?.dataset?.sheet;
              sheetInput.value = sheetValue || '0';
            }
          });
        });
      };

      const invalidStops = () => {
        return stopsState.filter((stop) => {
          const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
          const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
          return !(Number.isFinite(lat) && Number.isFinite(lng));
        });
      };

      const dropInvalidStops = () => {
        const invalidIndexes = stopsState
          .map((stop, index) => {
            const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
            const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
            return !(Number.isFinite(lat) && Number.isFinite(lng)) ? index : null;
          })
          .filter((idx) => idx !== null);

        if (!invalidIndexes.length) return 0;

        stopsState = stopsState.filter((_, index) => !invalidIndexes.includes(index));
        if (manualInput) {
          manualInput.value = JSON.stringify(stopsState);
        }
        renderInvalidList();
        renderValidList();
        updateValidationSummary();
        return invalidIndexes.length;
      };

      const setupInvalidAutocomplete = (input, index) => {
        const wrapper = input.parentElement;
        const suggestions = wrapper?.querySelector('.solver-invalid-suggestions');
        if (!suggestions) return;

        const fetchSuggestions = async () => {
          const query = input.value.trim();
          suggestions.innerHTML = '';
          suggestions.style.display = 'none';
          if (query.length < 3) return;
          try {
            const data = await fetchAddressSuggestions(query);
            if (!data.length) return;
            data.forEach((feature, featureIdx) => {
              const lat = parseFloat(feature.lat ?? '');
              const lng = parseFloat(feature.lng ?? '');
              const hasCoords = Number.isFinite(lat) && Number.isFinite(lng);
              const displayAddress = feature.address ?? '';

              const suggestionWrapper = document.createElement('div');
              suggestionWrapper.className = 'solver-suggestion-wrapper';
              suggestionWrapper.style.cssText = 'padding: 8px; border-bottom: 1px solid #e5e7eb; display: flex; gap: 8px; align-items: flex-start; cursor: pointer;';
              suggestionWrapper.addEventListener('mouseenter', () => {
                suggestionWrapper.style.backgroundColor = '#f3f4f6';
              });
              suggestionWrapper.addEventListener('mouseleave', () => {
                suggestionWrapper.style.backgroundColor = 'transparent';
              });

              const contentDiv = document.createElement('div');
              contentDiv.style.cssText = 'flex: 1; display: flex; gap: 8px;';

              const textDiv = document.createElement('div');
              textDiv.style.cssText = 'flex: 1; min-width: 200px;';
              const addressText = document.createElement('div');
              addressText.className = 'small fw-semibold';
              addressText.style.cssText = 'line-height: 1.3; word-break: break-word;';
              addressText.textContent = displayAddress;
              textDiv.appendChild(addressText);

              let mapContainer = null;
              if (hasCoords) {
                mapContainer = document.createElement('div');
                const mapId = `sugg-map-${prefix}-${index}-${featureIdx}-${Date.now()}`;
                mapContainer.id = mapId;
                mapContainer.style.cssText = 'width: 120px; height: 80px; border: 1px solid #d1d5db; border-radius: 4px; flex-shrink: 0;';

                setTimeout(() => {
                  if (!window.L) return;
                  const mapEl = document.getElementById(mapId);
                  if (!mapEl || mapEl.innerHTML !== '') return;

                  const map = window.L.map(mapId, {
                    attributionControl: false,
                    zoomControl: true,
                    dragging: false,
                    scrollWheelZoom: false,
                    tap: false,
                  });

                  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 20,
                  }).addTo(map);

                  const marker = window.L.marker([lat, lng]).addTo(map);
                  map.setView([lat, lng], 12);

                  suggestionWrapper._map = map;
                }, 10);
              }

              contentDiv.appendChild(textDiv);
              if (mapContainer) {
                contentDiv.appendChild(mapContainer);
              }

              suggestionWrapper.appendChild(contentDiv);

              suggestionWrapper.addEventListener('click', () => {
                input.value = displayAddress;
                suggestions.innerHTML = '';
                if (stopsState[index]) {
                  stopsState[index].address = input.value.trim();
                  stopsState[index].lat = hasCoords ? lat : null;
                  stopsState[index].lng = hasCoords ? lng : null;
                }
                updateValidationSummary();
                renderInvalidList();
                if (suggestionWrapper._map) {
                  suggestionWrapper._map.remove();
                }
              });

              suggestions.appendChild(suggestionWrapper);
            });
            suggestions.style.display = 'block';
          } catch (error) {
            console.error('Error en autocomplete:', error);
          }
        };

        input.addEventListener('input', fetchSuggestions);
      };

      const renderInvalidList = () => {
        if (!invalidList) return;
        const items = stopsState
          .map((stop, index) => ({ stop, index }))
          .filter((item) => {
            const lat = parseFloat(item.stop.lat ?? item.stop.latitude ?? '');
            const lng = parseFloat(item.stop.lng ?? item.stop.longitude ?? '');
            return !(Number.isFinite(lat) && Number.isFinite(lng));
          });
        if (!items.length) {
          invalidList.innerHTML = '<div class="text-muted">No hay direcciones invalidas.</div>';
          return;
        }
        invalidList.innerHTML = items.map((item) => `
          <div class="border rounded p-2 mb-2">
            <div class="small text-muted mb-1">Pedido ${escapeHtml(item.stop.code || `#${item.index + 1}`)}</div>
            <div class="d-flex gap-2 align-items-start">
              <div class="flex-grow-1 position-relative solver-invalid-wrapper">
                <input type="text" class="form-control form-control-sm solver-invalid-input" data-index="${item.index}" value="${escapeHtml(item.stop.address)}" autocomplete="off">
                <div class="list-group position-absolute w-100 solver-invalid-suggestions" style="z-index:1000; max-height:400px; overflow-y:auto; top:100%; left:0; border: 1px solid #d1d5db; border-radius: 4px; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger solver-invalid-remove" data-index="${item.index}" title="Quitar direccion">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>
        `).join('');

        invalidList.querySelectorAll('.solver-invalid-input').forEach((input) => {
          const idx = Number(input.dataset.index);
          if (!Number.isFinite(idx)) return;
          setupInvalidAutocomplete(input, idx);
        });

        invalidList.querySelectorAll('.solver-invalid-remove').forEach((btn) => {
          btn.addEventListener('click', () => {
            const idx = Number(btn.dataset.index);
            if (!Number.isFinite(idx)) return;
            stopsState.splice(idx, 1);
            if (manualInput) {
              manualInput.value = JSON.stringify(stopsState);
            }
            renderInvalidList();
            updateValidationSummary();
          });
        });
      };

      const validStops = () => {
        return stopsState.filter((stop) => {
          const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
          const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
          return (Number.isFinite(lat) && Number.isFinite(lng));
        });
      };

      const renderValidList = () => {
        const validListContainer = document.getElementById(`${prefix}ValidListContainer`);
        if (!validListContainer) return;
        const items = validStops();
        if (!items.length) {
          validListContainer.innerHTML = '<div class="text-muted">No hay direcciones validadas.</div>';
          return;
        }
        validListContainer.innerHTML = items.map((stop, idx) => {
          const stopsIndex = stopsState.indexOf(stop);
          const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
          const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
          const mapId = `valid-map-${prefix}-${idx}-${Date.now()}`;
          
          return `
          <div class="border rounded p-3 mb-3">
            <div class="d-flex gap-3 align-items-start">
              <div class="flex-grow-1">
                <div class="small text-muted mb-1">Pedido ${escapeHtml(stop.code || `#${stopsIndex + 1}`)}</div>
                <div class="fw-semibold mb-2">${escapeHtml(stop.address)}</div>
                <div class="small text-muted mb-2">
                  Lat: ${lat.toFixed(4)} / Lng: ${lng.toFixed(4)}
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary solver-valid-edit" data-index="${stopsIndex}">
                  <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                </button>
              </div>
              <div id="${mapId}" style="width: 140px; height: 100px; border: 1px solid #d1d5db; border-radius: 4px; flex-shrink: 0;"></div>
            </div>
          </div>
          `;
        }).join('');

        // Renderizar mapas para cada dirección válida
        items.forEach((stop, idx) => {
          const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
          const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
          const mapId = `valid-map-${prefix}-${idx}-${Date.now()}`;
          
          setTimeout(() => {
            if (!window.L) return;
            const mapEl = document.getElementById(mapId);
            if (!mapEl || mapEl.innerHTML !== '') return;

            const map = window.L.map(mapId, {
              attributionControl: false,
              zoomControl: true,
              dragging: false,
              scrollWheelZoom: false,
              tap: false,
            });

            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 20,
            }).addTo(map);

            const marker = window.L.marker([lat, lng]).addTo(map);
            map.setView([lat, lng], 14);
          }, 10);
        });
        // Agregar eventos a los botones de editar
        validListContainer.querySelectorAll('.solver-valid-edit').forEach((btn) => {
          btn.addEventListener('click', () => {
            const idx = Number(btn.dataset.index);
            if (!Number.isFinite(idx)) return;
            startEditingValidStop(idx);
          });
        });
      };

      const startEditingValidStop = (index) => {
        const stop = stopsState[index];
        if (!stop) return;

        // Crear el HTML del editor
        const editorHtml = `
          <div class="border rounded p-3 mb-3 bg-warning bg-opacity-10" id="solver-edit-container-${index}">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong>Editando: ${escapeHtml(stop.code || `Pedido #${index + 1}`)}</strong>
              <button type="button" class="btn-close" data-action="cancel-edit" data-index="${index}"></button>
            </div>
            <div class="position-relative solver-valid-edit-wrapper mb-2">
              <input type="text" class="form-control form-control-sm solver-valid-edit-input" data-index="${index}" value="${escapeHtml(stop.address)}" autocomplete="off" placeholder="Escribe la dirección corregida...">
              <div class="list-group position-absolute w-100 solver-valid-edit-suggestions" style="z-index:1000; max-height:400px; overflow-y:auto; top:100%; left:0; border: 1px solid #d1d5db; border-radius: 4px; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none;"></div>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-primary" data-action="save-edit" data-index="${index}">
                <i class="fa-solid fa-check me-1"></i> Guardar
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-action="cancel-edit" data-index="${index}">
                Cancelar
              </button>
            </div>
          </div>
        `;

        // Insertar el editor al principio del contenedor válido
        const validListContainer = document.getElementById(`${prefix}ValidListContainer`);
        if (validListContainer) {
          validListContainer.insertAdjacentHTML('afterbegin', editorHtml);
        }

        // Configurar el autocomplete
        const editInput = document.querySelector(`#solver-edit-container-${index} .solver-valid-edit-input`);
        const editSuggestions = document.querySelector(`#solver-edit-container-${index} .solver-valid-edit-suggestions`);

        if (editInput) {
          setupValidEditAutocomplete(editInput, index, editSuggestions);
          editInput.focus();
          editInput.select();
        }

        // Configurar botones
        document.querySelectorAll(`[data-action="save-edit"][data-index="${index}"]`).forEach((btn) => {
          btn.addEventListener('click', () => {
            const newAddress = editInput.value.trim();
            if (!newAddress) {
              if (window.nygAlert) {
                window.nygAlert('Ingresa una dirección válida', 'warning');
              }
              return;
            }
            // La dirección ya fue validada por autocomplete
            const container = document.getElementById(`solver-edit-container-${index}`);
            if (container) container.remove();
            renderValidList();
            updateValidationSummary();
            if (manualInput) {
              manualInput.value = JSON.stringify(stopsState);
            }
          });
        });

        document.querySelectorAll(`[data-action="cancel-edit"][data-index="${index}"]`).forEach((btn) => {
          btn.addEventListener('click', () => {
            const container = document.getElementById(`solver-edit-container-${index}`);
            if (container) container.remove();
          });
        });
      };

      const setupValidEditAutocomplete = (input, index, suggestions) => {
        if (!suggestions) return;

        const fetchSuggestions = async () => {
          const query = input.value.trim();
          suggestions.innerHTML = '';
          suggestions.style.display = 'none';
          if (query.length < 3) return;

          try {
            const data = await fetchAddressSuggestions(query);
            if (!data.length) return;

            suggestions.style.display = 'block';

            data.forEach((feature, featureIdx) => {
              const lat = parseFloat(feature.lat ?? '');
              const lng = parseFloat(feature.lng ?? '');
              const hasCoords = Number.isFinite(lat) && Number.isFinite(lng);
              const displayAddress = feature.address ?? '';

              const suggestionWrapper = document.createElement('div');
              suggestionWrapper.className = 'solver-suggestion-wrapper';
              suggestionWrapper.style.cssText = 'padding: 8px; border-bottom: 1px solid #e5e7eb; display: flex; gap: 8px; align-items: flex-start; cursor: pointer;';
              suggestionWrapper.addEventListener('mouseenter', () => {
                suggestionWrapper.style.backgroundColor = '#f3f4f6';
              });
              suggestionWrapper.addEventListener('mouseleave', () => {
                suggestionWrapper.style.backgroundColor = 'transparent';
              });

              const contentDiv = document.createElement('div');
              contentDiv.style.cssText = 'flex: 1; display: flex; gap: 8px;';

              const textDiv = document.createElement('div');
              textDiv.style.cssText = 'flex: 1; min-width: 200px;';
              const addressText = document.createElement('div');
              addressText.className = 'small fw-semibold';
              addressText.style.cssText = 'line-height: 1.3; word-break: break-word;';
              addressText.textContent = displayAddress;
              textDiv.appendChild(addressText);

              let mapContainer = null;
              if (hasCoords) {
                mapContainer = document.createElement('div');
                const mapId = `edit-map-${prefix}-${index}-${featureIdx}-${Date.now()}`;
                mapContainer.id = mapId;
                mapContainer.style.cssText = 'width: 120px; height: 80px; border: 1px solid #d1d5db; border-radius: 4px; flex-shrink: 0;';

                setTimeout(() => {
                  if (!window.L) return;
                  const mapEl = document.getElementById(mapId);
                  if (!mapEl || mapEl.innerHTML !== '') return;

                  const map = window.L.map(mapId, {
                    attributionControl: false,
                    zoomControl: true,
                    dragging: false,
                    scrollWheelZoom: false,
                    tap: false,
                  });

                  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 20,
                  }).addTo(map);

                  const marker = window.L.marker([lat, lng]).addTo(map);
                  map.setView([lat, lng], 12);

                  suggestionWrapper._map = map;
                }, 10);
              }

              contentDiv.appendChild(textDiv);
              if (mapContainer) {
                contentDiv.appendChild(mapContainer);
              }

              suggestionWrapper.appendChild(contentDiv);

              suggestionWrapper.addEventListener('click', () => {
                input.value = displayAddress;
                suggestions.innerHTML = '';
                suggestions.style.display = 'none';
                if (stopsState[index]) {
                  stopsState[index].address = input.value.trim();
                  stopsState[index].lat = hasCoords ? lat : null;
                  stopsState[index].lng = hasCoords ? lng : null;
                }
                if (suggestionWrapper._map) {
                  suggestionWrapper._map.remove();
                }
              });

              suggestions.appendChild(suggestionWrapper);
            });
          } catch (error) {
            console.error('Error en autocomplete:', error);
          }
        };
        input.addEventListener('input', fetchSuggestions);
      };

      const updateValidationSummary = () => {
        const total = stopsState.length;
        const invalidCount = invalidStops().length;
        const validCount = total - invalidCount;
        if (totalStopsEl) totalStopsEl.textContent = `${total} direcciones`;
        const validStopsEl = document.getElementById(`${prefix}ValidStops`);
        if (validStopsEl) validStopsEl.textContent = `${validCount} válidas`;
        if (invalidStopsEl) invalidStopsEl.textContent = `${invalidCount} invalidas`;
        const validBtn = document.getElementById(`${prefix}ValidBtn`);
        if (validBtn) validBtn.disabled = validCount === 0;
        if (invalidBtn) invalidBtn.disabled = invalidCount === 0;
        // Habilitar saveBtn solo si hay al menos 1 dirección válida
        if (saveBtn) saveBtn.disabled = validCount < 1;
      };

      updateValidationSummary();
      renderInvalidList();
      renderValidList();

      if (invalidBtn && invalidModal) {
        invalidBtn.addEventListener('click', () => {
          invalidModal.show();
        });
      }

      removeInvalidBtn?.addEventListener('click', () => {
        const removed = dropInvalidStops();
        if (!removed) {
          const msg = 'No hay direcciones inválidas para eliminar.';
          if (window.nygAlert) {
            window.nygAlert(msg, 'info');
          }
          return;
        }
        const msg = `Se eliminaron ${removed} direcciones inválidas.`;
        if (window.nygAlert) {
          window.nygAlert(msg, 'success');
        }
        const modalInstance = window.bootstrap ? window.bootstrap.Modal.getInstance(invalidModalEl) : null;
        modalInstance?.hide();
      });

      document.addEventListener('click', (event) => {
        document.querySelectorAll('.solver-invalid-suggestions').forEach((list) => {
          const wrapper = list.closest('.solver-invalid-wrapper');
          if (!wrapper || wrapper.contains(event.target)) return;
          list.innerHTML = '';
        });
      });

      if (validateBtn) {
        if ((!fileInput || !fileInput.files.length) && (!uploadedStops || !uploadedStops.length)) {
          validateBtn.disabled = true;
        }
        validateBtn.addEventListener('click', () => {
          const hasFile = fileInput && fileInput.files.length;
          const hasStops = Array.isArray(stopsState) && stopsState.length;
          if (!hasFile && !hasStops) {
            return;
          }
          validateBtn.disabled = true;
          const spinner = validateBtn.querySelector('.spinner-border');
          const label = validateBtn.querySelector(`.${prefix}-validate-label`);
          if (spinner) spinner.classList.remove('d-none');
          if (label) label.textContent = 'Validando...';
          startProgress('Validando direcciones...');
          if (manualInput) {
            manualInput.value = hasFile ? '' : JSON.stringify(stopsState);
          }
          if (actionInput) {
            actionInput.value = 'validate';
          }
          form.submit();
        });
      }

      if (saveBtn) {
        saveBtn.addEventListener('click', () => {
          const invalidCount = invalidStops().length;
          if (invalidCount > 0) {
            const modalEl = document.getElementById(`${prefix}InvalidModal`);
            const modal = (modalEl && window.bootstrap) ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
            modal?.show();
            return;
          }
          const hasFile = fileInput && fileInput.files.length;
          const hasStops = Array.isArray(stopsState) && stopsState.length;
          if (!hasFile && !hasStops) {
            return;
          }
          if (manualInput) {
            manualInput.value = hasFile ? '' : JSON.stringify(stopsState);
          }
          if (actionInput) {
            actionInput.value = 'save';
          }
          startProgress('Guardando direcciones...');
          form.submit();
        });
      }

      if (revalidateBtn) {

        revalidateBtn.addEventListener('click', () => {
          const inputs = Array.from(document.querySelectorAll('.solver-invalid-input'));
          inputs.forEach((input) => {
            const idx = Number(input.dataset.index);
            if (!Number.isFinite(idx) || !stopsState[idx]) return;
            stopsState[idx].address = input.value.trim();
            stopsState[idx].lat = null;
            stopsState[idx].lng = null;
          });
          if (manualInput) {
            manualInput.value = JSON.stringify(stopsState);
          }
          if (actionInput) {
            actionInput.value = 'validate';
          }
          startProgress('Revalidando direcciones...');
          form.submit();
        });
      }

      form.addEventListener('submit', () => startProgress());

      if (fileInput) {
        fileInput.addEventListener('change', async () => {
          if (validateBtn) {
            validateBtn.disabled = !fileInput.files.length && !(Array.isArray(uploadedStops) && uploadedStops.length);
          }
          if (!fileInput.files.length) {
            resetPreview();
            updateValidationSummary();
            return;
          }
          if (manualInput) {
            manualInput.value = '';
          }
          stopsState = [];
          updateValidationSummary();
          renderInvalidList();
          if (previewTabs) previewTabs.innerHTML = '';
          if (previewContent) previewContent.innerHTML = '';
          if (previewWrapper) previewWrapper.classList.remove('d-none');
          setPreviewLoading(true);
          try {
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            const response = await fetch(previewFileUrl, {
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

      if (overviewEl && window.L) {
        const coords = Array.isArray(uploadedStops)
          ? uploadedStops.map((stop) => {
            const lat = parseFloat(stop.lat ?? stop.latitude ?? '');
            const lng = parseFloat(stop.lng ?? stop.longitude ?? '');
            return (Number.isFinite(lat) && Number.isFinite(lng)) ? [lat, lng] : null;
          }).filter(Boolean)
          : [];

        if (coords.length) {
          const overviewMap = window.L.map(overviewEl, { attributionControl: false, zoomControl: false });
          window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
          }).addTo(overviewMap);
          const layer = window.L.featureGroup().addTo(overviewMap);
          coords.forEach((c) => window.L.marker(c).addTo(layer));
          if (typeof layer.getBounds === 'function' && layer.getLayers().length) {
            overviewMap.fitBounds(layer.getBounds(), { padding: [20, 20] });
          }
        }
      }

      const hasWarnings = Array.isArray(warnings) && warnings.length > 0;
      const invalidCount = invalidStops().length;
      const hasUploaded = Array.isArray(uploadedStops) && uploadedStops.length > 0;
      if (importModal && (hasUploaded || hasWarnings)) {
        importModal.show();
      }
      if (invalidModal && invalidCount > 0) {
        invalidModal.show();
      }
    })();
  </script>
@endpush
