@extends('layouts.app')

@section('title', 'Importar Excel de Trafico')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Wizard de Importacion</h1>
    <p class="text-muted mb-0">Paso 1: subir archivo. Paso 2: revisar resumen de filas procesadas y errores.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs border-bottom-0" id="importTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 py-3 fw-semibold border-0 rounded-0" id="excel-tab" data-bs-toggle="tab" data-bs-target="#excel-pane" type="button" role="tab" aria-controls="excel-pane" aria-selected="true">
              <i class="fa-solid fa-file-excel text-success me-2"></i> Importar Excel
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-3 fw-semibold border-0 rounded-0" id="logistics-tab" data-bs-toggle="tab" data-bs-target="#logistics-pane" type="button" role="tab" aria-controls="logistics-pane" aria-selected="false">
              <i class="fa-solid fa-truck-fast text-primary me-2"></i> Traer desde Logística
            </button>
          </li>
        </ul>
      </div>
      <div class="card-body tab-content" id="importTabsContent">
        <!-- Tab 1: Cargar Excel -->
        <div class="tab-pane fade show active" id="excel-pane" role="tabpanel" aria-labelledby="excel-tab">
          <h2 class="h5 mb-3 text-dark">Subir archivo Excel</h2>
          <form method="POST" enctype="multipart/form-data" action="{{ route('pago-choferes.import.store') }}" id="driverImportForm">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold">Archivo Excel</label>
              <input type="file" class="form-control" name="file" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Quincena a Procesar</label>
              <select name="quincena_option" class="form-select" required>
                <option value="ambas">Ambas quincenas (Procesar todo)</option>
                <option value="1">Solo 1ra Quincena (Omitir fechas de la 2da)</option>
                <option value="2">Solo 2da Quincena (Omitir fechas de la 1ra)</option>
              </select>
            </div>
            <div class="small text-muted mb-4">
              El tipo de liquidación se toma desde la configuración de cada chofer. La importación agrupa automáticamente por fecha en quincenas o mes completo según corresponda.
            </div>
            <button class="btn btn-primary w-100" id="driverImportSubmitBtn">Procesar importación</button>
          </form>
        </div>

        <!-- Tab 2: Traer desde Logistica -->
        <div class="tab-pane fade" id="logistics-pane" role="tabpanel" aria-labelledby="logistics-tab">
          <h2 class="h5 mb-3 text-dark">Traer datos de Logística</h2>
          <form method="POST" action="{{ route('pago-choferes.import.logistics') }}" id="logisticsImportForm">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold">Fecha Desde</label>
              <input type="date" class="form-control" name="fecha_desde" value="{{ date('Y-m-01') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Fecha Hasta</label>
              <input type="date" class="form-control" name="fecha_hasta" value="{{ date('Y-m-t') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Quincena a Procesar</label>
              <select name="quincena_option" class="form-select" required>
                <option value="ambas">Ambas quincenas (Procesar todo)</option>
                <option value="1">Solo 1ra Quincena (Omitir fechas de la 2da)</option>
                <option value="2">Solo 2da Quincena (Omitir fechas de la 1ra)</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Modo de Importación</label>
              @php $currentMode = old('import_mode', $logisticsImportMode ?? 'replace'); @endphp
              <select name="import_mode" class="form-select" required>
                <option value="replace" {{ $currentMode === 'replace' ? 'selected' : '' }}>Reemplazar y reinsertar (Pisar todo lo existente en el rango)</option>
                <option value="merge" {{ $currentMode === 'merge' ? 'selected' : '' }}>Mantener y actualizar (Solo agregar lo que falte)</option>
              </select>
              <div class="form-text text-muted">
                <strong>Reemplazar:</strong> Elimina los ítems previamente importados de las fechas seleccionadas y los regenera (ideal si se corrigieron vehículos o zonas en la Planilla Diaria).<br>
                <strong>Mantener:</strong> Conserva los ítems cargados anteriormente y solo agrega o actualiza novedades.<br>
                <span class="badge bg-light text-dark border mt-1"><i class="fa-solid fa-gear me-1 text-primary"></i> Parámetro General: {{ $currentMode === 'replace' ? 'Reemplazar y reinsertar' : 'Mantener y actualizar' }} (se usará automáticamente en ejecuciones en segundo plano)</span>
              </div>
            </div>
            <div class="small text-muted mb-4">
              Esta opción buscará los registros cargados en la Planilla Diaria de Logística entre las fechas indicadas, ejecutará el motor de reglas y armará los recibos correspondientes.
            </div>
            <div id="logisticsActiveStatus" class="alert alert-warning border-0 shadow-sm d-none align-items-center mb-3">
              <div class="spinner-border spinner-border-sm text-dark me-3 flex-shrink-0" role="status"></div>
              <div>
                <div class="fw-semibold text-dark mb-0" id="logisticsStatusTitle">Procesando Planilla Diaria...</div>
                <div class="small text-dark text-opacity-75" id="logisticsStatusSub">Obteniendo viajes, aplicando tarifarios por plaza y armando recibos.</div>
              </div>
            </div>
            <button type="submit" class="btn btn-warning text-dark fw-bold w-100 py-2.5 d-flex align-items-center justify-content-center gap-2" id="logisticsImportSubmitBtn">
              <i class="fa-solid fa-truck-fast" id="logisticsBtnIcon"></i>
              <span id="logisticsBtnText">Traer datos de logística</span>
              <span id="logisticsBtnSpinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-body">
      <h2 class="h5">Ultimas corridas</h2>
      <div class="list-group">
        @forelse($latestRuns as $run)
          <a class="list-group-item list-group-item-action" href="{{ route('pago-choferes.import.show', $run) }}">
            #{{ $run->id }} - {{ $run->source_file }}
            <span class="d-block small text-muted">Procesadas {{ $run->rows_processed }} | Errores {{ $run->rows_with_errors }}</span>
          </a>
        @empty
          <div class="text-muted">Sin corridas.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<div id="driverImportLoadingOverlay" class="driver-import-overlay d-none" aria-live="polite" aria-busy="true">
  <div class="driver-import-overlay__card text-center">
    <div id="importIconContainer" class="mb-3">
      <div class="spinner-border text-warning" role="status" aria-hidden="true"></div>
    </div>
    <div class="fw-semibold fs-5 text-dark mb-1" id="importTitle">Procesando Excel</div>
    <div class="small text-muted mb-3" id="importSub">Esto puede tardar unos segundos según el tamaño del archivo.</div>
    <div class="progress" style="height: 12px; border-radius: 6px; background-color: #e9ecef;">
      <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold" role="progressbar" style="width: 0%; border-radius: 6px;"></div>
    </div>
    <div id="importProgressText" class="mt-2 small fw-bold text-dark">0%</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .driver-import-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(17, 24, 39, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .driver-import-overlay__card {
    width: min(360px, 100%);
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
    padding: 1.25rem;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
  (function () {
    const form = document.getElementById('driverImportForm');
    const button = document.getElementById('driverImportSubmitBtn');
    const overlay = document.getElementById('driverImportLoadingOverlay');
    const importTitle = document.getElementById('importTitle');
    const importSub = document.getElementById('importSub');
    const importProgressBar = document.getElementById('importProgressBar');
    const importProgressText = document.getElementById('importProgressText');

    if (!form) {
      return;
    }

    function normalizeHeader(value) {
      if (value === null || value === undefined) return '';
      let str = String(value);
      str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      str = str.toLowerCase().trim();
      str = str.replace(/[^a-z0-9]+/g, ' ');
      return str.trim();
    }

    function isIgnoredSheet(sheetName) {
      const normalized = sheetName
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/\s+/g, ' ');
      return ['base choferes', 'flota madre', 'coordinadores', 'choferes'].includes(normalized);
    }

    function resolveHeaderRowAndMap(sheetRows) {
      const limit = Math.min(sheetRows.length, 30);
      let bestRowIndex = 0;
      let bestMap = {};
      let maxKeys = -1;

      for (let r = 0; r < limit; r++) {
        const row = sheetRows[r] || [];
        const map = {};

        for (let c = 0; c < row.length; c++) {
          const val = row[c];
          const label = normalizeHeader(val);
          if (label === '') continue;
          map[label] = c;
        }

        if (map['chofer'] !== undefined && (map['fecha'] !== undefined || map['titular'] !== undefined || map['patente'] !== undefined)) {
          return {
            headerRowIndex: r,
            map: map
          };
        }

        const mapKeysCount = Object.keys(map).length;
        if (mapKeysCount > maxKeys) {
          maxKeys = mapKeysCount;
          bestRowIndex = r;
          bestMap = map;
        }
      }

      return {
        headerRowIndex: bestRowIndex,
        map: bestMap
      };
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const fileInput = form.querySelector('input[type="file"]');
      const file = fileInput.files[0];
      if (!file) {
        alert('Por favor seleccione un archivo.');
        return;
      }

      const quincenaOption = form.querySelector('select[name="quincena_option"]').value;

      if (button) {
        button.disabled = true;
        button.textContent = 'Procesando...';
      }
      if (overlay) {
        overlay.classList.remove('d-none');
      }

      // Reset progress elements
      importTitle.textContent = "Preparando importación...";
      importSub.textContent = "Leyendo archivo Excel localmente...";
      importProgressBar.style.width = "0%";
      importProgressText.textContent = "0%";

      const reader = new FileReader();
      reader.onload = function (e) {
        try {
          const data = new Uint8Array(e.target.result);
          // cellDates: true so SheetJS parses dates as JavaScript Dates
          const workbook = XLSX.read(data, { type: 'array', cellDates: true });
          
          const chunks = [];
          const sheetNames = workbook.SheetNames;
          
          for (const sheetName of sheetNames) {
            if (isIgnoredSheet(sheetName)) {
              continue;
            }
            
            const sheet = workbook.Sheets[sheetName];
            if (!sheet) {
              continue;
            }
            
            const sheetRows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: "" });
            if (sheetRows.length === 0) {
              continue;
            }
            
            const { headerRowIndex, map } = resolveHeaderRowAndMap(sheetRows);
            
            if (Object.keys(map).length === 0) {
              chunks.push({
                sheet_name: sheetName,
                start_row: 1,
                end_row: 1,
                rows: [[]]
              });
              continue;
            }
            
            const headerRow = sheetRows[headerRowIndex];
            const chunkSize = 150;
            
            for (let startIdx = headerRowIndex + 1; startIdx < sheetRows.length; startIdx += chunkSize) {
              const endIdx = Math.min(startIdx + chunkSize, sheetRows.length);
              const rowsSlice = sheetRows.slice(startIdx, endIdx);
              const chunkRows = [headerRow, ...rowsSlice];
              
              chunks.push({
                sheet_name: sheetName,
                start_row: startIdx + 1, // Excel row is 1-based
                end_row: endIdx,
                rows: chunkRows
              });
            }
          }

          startImportRun(file.name, quincenaOption, chunks);
          
        } catch (err) {
          showError('Error leyendo el archivo Excel: ' + err.message);
        }
      };

      reader.onerror = function () {
        showError('No se pudo leer el archivo seleccionado.');
      };

      reader.readAsArrayBuffer(file);

      function startImportRun(filename, quincenaOption, chunks) {
        importTitle.textContent = "Iniciando importación...";
        importSub.textContent = "Registrando corrida...";
        importProgressBar.style.width = "0%";
        importProgressText.textContent = "0%";

        fetch(form.action, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            original_filename: filename,
            quincena_option: quincenaOption
          })
        })
        .then(response => {
          if (!response.ok) {
            return response.json().then(err => { throw err; });
          }
          return response.json();
        })
        .then(data => {
          if (!data.success) {
            throw new Error(data.message || 'Error al iniciar la importación.');
          }

          const runId = data.run_id;
          const totalChunks = chunks.length;

          if (totalChunks === 0) {
            finalizeImport(runId);
            return;
          }

          // Process chunks sequentially
          let currentChunkIndex = 0;

          function processNextChunk() {
            if (currentChunkIndex >= totalChunks) {
              finalizeImport(runId);
              return;
            }

            const chunk = chunks[currentChunkIndex];
            const progressPercent = Math.round((currentChunkIndex / totalChunks) * 100);

            importTitle.textContent = `Procesando hoja: ${chunk.sheet_name}`;
            importSub.textContent = `Filas ${chunk.start_row} a ${chunk.end_row} (${currentChunkIndex + 1} de ${totalChunks})`;
            importProgressBar.style.width = `${progressPercent}%`;
            importProgressText.textContent = `${progressPercent}%`;

            fetch("{{ route('pago-choferes.import.chunk') }}", {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              },
              body: JSON.stringify({
                run_id: runId,
                chunk: chunk
              })
            })
            .then(res => {
              if (!res.ok) {
                return res.json().then(err => { throw err; });
              }
              return res.json();
            })
            .then(chunkResult => {
              currentChunkIndex++;
              processNextChunk();
            })
            .catch(err => {
              showError(err.message || `Error procesando la hoja ${chunk.sheet_name} en filas ${chunk.start_row}-${chunk.end_row}`);
            });
          }

          processNextChunk();
        })
        .catch(error => {
          showError(error.message || 'Ocurrió un error inesperado al iniciar la corrida.');
        });
      }

      function finalizeImport(runId) {
        importTitle.textContent = "Finalizando importación...";
        importSub.textContent = "Recalculando importes de recibos...";
        importProgressBar.style.width = "95%";
        importProgressText.textContent = "95%";

        fetch("{{ route('pago-choferes.import.finish') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            run_id: runId
          })
        })
        .then(res => {
          if (!res.ok) {
            return res.json().then(err => { throw err; });
          }
          return res.json();
        })
        .then(finalData => {
          importProgressBar.style.width = "100%";
          importProgressText.textContent = "100%";
          window.location.href = finalData.redirect_url;
        })
        .catch(err => {
          showError(err.message || 'Error al finalizar la importación.');
        });
      }

      function showError(message) {
        if (overlay) {
          overlay.classList.add('d-none');
        }
        if (button) {
          button.disabled = false;
          button.textContent = 'Procesar importacion';
        }
        alert('Error: ' + message);
      }
    });

    // Logistics Import Form handler
    const logisticsForm = document.getElementById('logisticsImportForm');
    const logisticsBtn = document.getElementById('logisticsImportSubmitBtn');
    const logisticsBtnText = document.getElementById('logisticsBtnText');
    const logisticsBtnIcon = document.getElementById('logisticsBtnIcon');
    const logisticsBtnSpinner = document.getElementById('logisticsBtnSpinner');
    const logisticsActiveStatus = document.getElementById('logisticsActiveStatus');
    const importIconContainer = document.getElementById('importIconContainer');

    if (logisticsForm) {
      logisticsForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const fechaDesde = logisticsForm.querySelector('input[name="fecha_desde"]').value;
        const fechaHasta = logisticsForm.querySelector('input[name="fecha_hasta"]').value;
        const quincenaOption = logisticsForm.querySelector('select[name="quincena_option"]').value;
        const importModeSelect = logisticsForm.querySelector('select[name="import_mode"]');
        const importMode = importModeSelect ? importModeSelect.value : 'replace';

        if (!fechaDesde || !fechaHasta) {
          alert('Por favor complete el rango de fechas.');
          return;
        }

        // Active indicator on button
        if (logisticsBtn) {
          logisticsBtn.disabled = true;
          if (logisticsBtnText) logisticsBtnText.textContent = 'Trayendo datos de logística...';
          if (logisticsBtnIcon) logisticsBtnIcon.className = 'fa-solid fa-circle-notch fa-spin me-2';
          if (logisticsBtnSpinner) logisticsBtnSpinner.classList.remove('d-none');
        }

        // Active banner in form
        if (logisticsActiveStatus) {
          logisticsActiveStatus.classList.remove('d-none');
          logisticsActiveStatus.classList.add('d-flex');
        }

        // Active overlay indicator
        if (overlay) {
          overlay.classList.remove('d-none');
        }

        if (importIconContainer) {
          importIconContainer.innerHTML = '<i class="fa-solid fa-truck-fast text-warning fa-bounce fa-2x"></i>';
        }

        importTitle.textContent = "Obteniendo Planilla Diaria";
        importSub.textContent = "Consultando viajes y calculando liquidaciones por plaza...";
        importProgressBar.style.width = "25%";
        importProgressText.textContent = "25%";

        setTimeout(() => {
          if (importProgressBar && importProgressBar.style.width === "25%") {
            importProgressBar.style.width = "65%";
            importProgressText.textContent = "65%";
            importSub.textContent = "Aplicando tarifarios por zona y armando recibos...";
          }
        }, 600);

        fetch(logisticsForm.action, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            fecha_desde: fechaDesde,
            fecha_hasta: fechaHasta,
            quincena_option: quincenaOption,
            import_mode: importMode
          })
        })
        .then(response => {
          if (!response.ok) {
            return response.json().then(err => { throw err; });
          }
          return response.json();
        })
        .then(data => {
          importProgressBar.style.width = "100%";
          importProgressText.textContent = "100%";
          importTitle.textContent = "¡Proceso Completado!";
          importSub.textContent = "Redirigiendo a Pagos a choferes...";
          setTimeout(() => {
            window.location.href = data.redirect_url;
          }, 300);
        })
        .catch(err => {
          if (overlay) {
            overlay.classList.add('d-none');
          }
          if (logisticsActiveStatus) {
            logisticsActiveStatus.classList.add('d-none');
            logisticsActiveStatus.classList.remove('d-flex');
          }
          if (logisticsBtn) {
            logisticsBtn.disabled = false;
            if (logisticsBtnText) logisticsBtnText.textContent = 'Traer datos de logística';
            if (logisticsBtnIcon) logisticsBtnIcon.className = 'fa-solid fa-truck-fast';
            if (logisticsBtnSpinner) logisticsBtnSpinner.classList.add('d-none');
          }
          alert('Error: ' + (err.message || 'No se pudieron traer los datos.'));
        });
      });
    }
  })();
</script>
@endpush
