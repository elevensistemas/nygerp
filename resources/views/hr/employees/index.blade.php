@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
          <li class="breadcrumb-item active" aria-current="page">Colaboradores</li>
        </ol>
      </nav>
      <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i>Gestión de Colaboradores</h3>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ asset('manuales/instructivo_rrhh.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo completo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
        <i class="fa-solid fa-circle-question fs-5"></i>
      </a>
      <button type="button" class="btn btn-outline-success d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#exportEmployeesModal" title="Descargar Excel con nómina o plantilla vacía">
        <i class="fa-solid fa-download"></i>
        <span>Descargar Excel</span>
      </button>
      <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#importEmployeesModal" title="Importar o actualizar colaboradores desde Excel">
        <i class="fa-solid fa-upload"></i>
        <span>Cargar Excel</span>
      </button>
      <a href="{{ route('rrhh.employees.create') }}" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
        <i class="fa-solid fa-user-plus"></i>
        <span>Nuevo Colaborador</span>
      </a>
    </div>
  </div>

  {{-- Filtros y Búsqueda --}}
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('rrhh.employees.index') }}" class="row g-2 align-items-center">
        <div class="col-lg-3 col-md-6 col-12">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Nombre, DNI o Legajo..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
          <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="activo" {{ request('status') === 'activo' ? 'selected' : '' }}>Activo</option>
            <option value="licencia" {{ request('status') === 'licencia' ? 'selected' : '' }}>En Licencia</option>
            <option value="suspendido" {{ request('status') === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
            <option value="en_onboarding" {{ request('status') === 'en_onboarding' ? 'selected' : '' }}>Onboarding</option>
            <option value="egresado" {{ request('status') === 'egresado' ? 'selected' : '' }}>Egresado</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
          <select name="department_id" class="form-select">
            <option value="">Todas las áreas</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <select name="position_id" class="form-select">
            <option value="">Todos los puestos</option>
            @foreach($positions as $pos)
              <option value="{{ $pos->id }}" {{ request('position_id') == $pos->id ? 'selected' : '' }}>{{ $pos->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
          <select name="branch_id" class="form-select">
            <option value="">Todas las bases</option>
            @foreach($branches as $branch)
              <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-1 col-md-4 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100" title="Filtrar"><i class="fa-solid fa-filter"></i></button>
          @if(request()->hasAny(['search', 'status', 'department_id', 'position_id', 'branch_id']))
            <a href="{{ route('rrhh.employees.index') }}" class="btn btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-rotate-left"></i></a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de Empleados --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive" style="min-height: 250px;">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Colaborador</th>
              <th>Legajo</th>
              <th>DNI / CUIL</th>
              <th>Área / Puesto</th>
              <th>Sucursal / Base</th>
              <th>Responsable</th>
              <th>Ingreso</th>
              <th class="text-center">Estado</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($employees as $employee)
              <tr>
                <td class="ps-3">
                  <div class="d-flex align-items-center gap-3">
                    @if($employee->avatar_path)
                      <img src="{{ asset('storage/' . $employee->avatar_path) }}" alt="{{ $employee->full_name }}" class="rounded-circle object-fit-cover" style="width: 40px; height: 40px;">
                    @else
                      <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold rounded-circle" style="width: 40px; height: 40px; font-size: 14px;">
                        {{ strtoupper(mb_substr($employee->first_name, 0, 1) . mb_substr($employee->last_name, 0, 1)) }}
                      </div>
                    @endif
                    <div>
                      <a href="{{ route('rrhh.employees.show', $employee) }}" class="fw-bold text-dark text-decoration-none">
                        {{ $employee->full_name }}
                      </a>
                      <div class="text-muted small">
                        @if($employee->user)
                          <span class="badge bg-light text-primary border"><i class="fa-solid fa-user-check me-1"></i>Con acceso</span>
                        @else
                          <span class="text-muted"><i class="fa-solid fa-user-slash me-1"></i>Sin usuario</span>
                        @endif
                      </div>
                    </div>
                  </div>
                </td>
                <td><span class="badge bg-secondary font-monospace">{{ $employee->file_number }}</span></td>
                <td>
                  <div class="fw-semibold">{{ $employee->dni }}</div>
                  @if($employee->cuil)
                    <div class="text-muted small">{{ $employee->cuil }}</div>
                  @endif
                </td>
                <td>
                  <div class="fw-semibold text-dark">{{ $employee->department ? $employee->department->name : '-' }}</div>
                  <div class="text-muted small">{{ $employee->position ? $employee->position->name : '-' }}</div>
                </td>
                <td>
                  <span class="text-dark">{{ $employee->branch ? $employee->branch->name : '-' }}</span>
                </td>
                <td>
                  @if($employee->manager)
                    <span class="small">{{ $employee->manager->full_name }}</span>
                  @else
                    <span class="text-muted small fst-italic">Sin asignar</span>
                  @endif
                </td>
                <td>
                  <div>{{ $employee->hire_date ? $employee->hire_date->format('d/m/Y') : '-' }}</div>
                  <small class="text-muted">{{ $employee->seniority_formatted }}</small>
                </td>
                <td class="text-center">
                  {!! $employee->status_badge !!}
                </td>
                <td class="text-end pe-3">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                      Acciones
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="z-index: 1065; min-width: 220px;">
                      <li>
                        <a class="dropdown-item" href="{{ route('rrhh.employees.show', $employee) }}">
                          <i class="fa-solid fa-folder-open text-primary me-2"></i>Ver Ficha / Legajo
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item" href="{{ route('rrhh.employees.edit', $employee) }}">
                          <i class="fa-solid fa-pen-to-square text-secondary me-2"></i>Editar Datos
                        </a>
                      </li>
                      @if($employee->status !== 'egresado')
                        <li>
                          <a class="dropdown-item text-primary" href="{{ route('rrhh.leave-requests.create', ['employee_id' => $employee->id]) }}">
                            <i class="fa-solid fa-plane-departure me-2"></i>Asignar Vacaciones
                          </a>
                        </li>
                      @endif
                      <li><hr class="dropdown-divider my-1"></li>
                      <li>
                        <button type="button" class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#statusModal{{ $employee->id }}">
                          <i class="fa-solid fa-arrows-rotate me-2"></i>Cambiar Estado
                        </button>
                      </li>
                      @if($employee->status !== 'egresado')
                        <li>
                          <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#terminateModal{{ $employee->id }}">
                            <i class="fa-solid fa-user-xmark me-2"></i>Registrar Egreso
                          </button>
                        </li>
                      @endif
                    </ul>
                  </div>

                  {{-- Modal Cambio de Estado --}}
                  <div class="modal fade" id="statusModal{{ $employee->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <form method="POST" action="{{ route('rrhh.employees.update-status', $employee) }}">
                          @csrf
                          <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold">Cambiar Estado Laboral</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body text-start">
                            <p class="mb-3">Modificar el estado de <strong>{{ $employee->full_name }}</strong>:</p>
                            <div class="mb-3">
                              <label class="form-label fw-semibold">Nuevo Estado</label>
                              <select name="status" class="form-select" required>
                                <option value="activo" {{ $employee->status === 'activo' ? 'selected' : '' }}>Activo</option>
                                <option value="licencia" {{ $employee->status === 'licencia' ? 'selected' : '' }}>En Licencia</option>
                                <option value="suspendido" {{ $employee->status === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
                                <option value="en_onboarding" {{ $employee->status === 'en_onboarding' ? 'selected' : '' }}>En Onboarding</option>
                              </select>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  {{-- Modal Egreso --}}
                  @if($employee->status !== 'egresado')
                    <div class="modal fade" id="terminateModal{{ $employee->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                          <form method="POST" action="{{ route('rrhh.employees.terminate', $employee) }}">
                            @csrf
                            <div class="modal-header bg-danger text-white">
                              <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Registrar Egreso Laboral</h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-start">
                              <div class="alert alert-warning small">
                                El colaborador pasará a estado <strong>Egresado</strong>. No se eliminarán sus registros ni documentos históricos.
                              </div>
                              <div class="mb-3">
                                <label class="form-label fw-semibold">Fecha de Egreso <span class="text-danger">*</span></label>
                                <input type="date" name="termination_date" class="form-control" value="{{ date('Y-m-d') }}" required min="{{ $employee->hire_date ? $employee->hire_date->format('Y-m-d') : '' }}">
                              </div>
                              <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo de Egreso <span class="text-danger">*</span></label>
                                <input type="text" name="termination_reason" class="form-control" placeholder="Ej: Renuncia, Fin de contrato, Despido..." required>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                              <button type="submit" class="btn btn-danger">Confirmar Egreso</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center py-5">
                  <div class="text-muted">
                    <i class="fa-solid fa-users-slash fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-1 fw-semibold">No se encontraron colaboradores registrados</p>
                    <small>Agregue su primer colaborador haciendo clic en "Nuevo Colaborador".</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($employees->hasPages())
      <div class="card-footer bg-white border-0 py-3">
        {{ $employees->links() }}
      </div>
    @endif
  </div>
</div>

{{-- Modal Descargar Excel --}}
<div class="modal fade" id="exportEmployeesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="GET" action="{{ route('rrhh.employees.export') }}">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-excel me-2"></i>Descargar Nómina de Colaboradores</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-start">
          <p class="text-dark fw-semibold mb-3">Seleccione la modalidad de descarga:</p>

          <div class="card border mb-3">
            <div class="card-body py-3">
              <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="mode" id="modePayroll" value="payroll" checked>
                <label class="form-check-label fw-bold text-dark" for="modePayroll">
                  <i class="fa-solid fa-users text-primary me-1"></i> Descargar con la nómina actual
                </label>
                <div class="text-muted small ms-4">
                  Exporta el listado completo de colaboradores registrados con todos sus campos (datos personales, legajo, DNI, teléfonos personal y laboral, correos, etc.).
                </div>
              </div>

              <hr class="my-2">

              <div class="form-check mt-3">
                <input class="form-check-input" type="radio" name="mode" id="modeEmpty" value="empty">
                <label class="form-check-label fw-bold text-dark" for="modeEmpty">
                  <i class="fa-solid fa-file-lines text-secondary me-1"></i> Descargar plantilla vacía
                </label>
                <div class="text-muted small ms-4">
                  Descarga la estructura de columnas limpia con una fila de ejemplo para completado masivo.
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success px-4">
            <i class="fa-solid fa-download me-1"></i>Descargar Excel
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal Cargar / Importar Excel --}}
<div class="modal fade" id="importEmployeesModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow">
      <form id="importEmployeesForm" enctype="multipart/form-data">
        @csrf
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-import me-2"></i>Importación / Carga Masiva desde Excel</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-start">
          <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-3">
            <i class="fa-solid fa-circle-info fa-2x text-primary"></i>
            <div>
              <div class="fw-bold">Creación y Actualización Inteligente</div>
              <small>El sistema actualizará los colaboradores existentes (coincidencia por Legajo o DNI) y creará nuevos colaboradores si no existen. Incluye todos los campos como Teléfono personal y laboral.</small>
            </div>
          </div>

          <div class="mb-3">
            <label for="excel_file_input" class="form-label fw-semibold">Seleccionar archivo Excel (.xlsx, .xls, .csv) <span class="text-danger">*</span></label>
            <input type="file" name="excel_file" id="excel_file_input" class="form-control" accept=".xlsx,.xls,.csv" required>
            <small class="text-muted">Seleccione el archivo para generar la vista previa de importación automáticamente.</small>
          </div>

          {{-- Active Indicator / Spinner de Vista Previa --}}
          <div id="previewSpinner" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
              <span class="visually-hidden">Analizando...</span>
            </div>
            <p class="mt-3 mb-0 fw-bold text-dark">Analizando archivo Excel y cargando vista previa...</p>
            <small class="text-muted">Por favor espere un momento.</small>
          </div>

          {{-- Vista Previa Container --}}
          <div id="previewContainer" class="d-none mt-4">
            <div class="row g-2 mb-3">
              <div class="col-md-3 col-6">
                <div class="card border-0 bg-light text-center p-2">
                  <span class="text-muted small fw-semibold">Filas Analizadas</span>
                  <h4 class="fw-bold text-dark mb-0" id="statTotalRows">0</h4>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="card border-0 bg-success-subtle text-center p-2">
                  <span class="text-success small fw-semibold"><i class="fa-solid fa-user-plus me-1"></i>A Crear (Nuevos)</span>
                  <h4 class="fw-bold text-success mb-0" id="statCreateCount">0</h4>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="card border-0 bg-info-subtle text-center p-2">
                  <span class="text-info small fw-semibold"><i class="fa-solid fa-user-pen me-1"></i>A Actualizar</span>
                  <h4 class="fw-bold text-info mb-0" id="statUpdateCount">0</h4>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="card border-0 bg-danger-subtle text-center p-2">
                  <span class="text-danger small fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Observaciones</span>
                  <h4 class="fw-bold text-danger mb-0" id="statErrorCount">0</h4>
                </div>
              </div>
            </div>

            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-table me-2 text-primary"></i>Vista Previa de Registros:</h6>
            <div class="table-responsive border rounded" style="max-height: 320px; overflow-y: auto;">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                  <tr>
                    <th class="text-center" style="width: 50px;">#</th>
                    <th>Legajo</th>
                    <th>DNI</th>
                    <th>Nombre y Apellido</th>
                    <th>Tel. Personal</th>
                    <th>Tel. Laboral</th>
                    <th class="text-center">Acción</th>
                    <th class="text-center">Estado</th>
                  </tr>
                </thead>
                <tbody id="previewTableBody">
                </tbody>
              </table>
            </div>
          </div>

          {{-- Active Indicator / Spinner de Procesamiento --}}
          <div id="importProcessSpinner" class="text-center py-5 d-none">
            <div class="spinner-border text-success" style="width: 3.5rem; height: 3.5rem;" role="status">
              <span class="visually-hidden">Procesando...</span>
            </div>
            <h5 class="mt-3 mb-1 fw-bold text-success">Procesando importación de nómina...</h5>
            <p class="text-muted small mb-0">Guardando datos e integrando registros en la base de datos.</p>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" id="btnConfirmImport" class="btn btn-primary px-4" disabled>
            <i class="fa-solid fa-cloud-arrow-up me-1"></i>Confirmar e Importar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const excelFileInput = document.getElementById('excel_file_input');
    const previewSpinner = document.getElementById('previewSpinner');
    const previewContainer = document.getElementById('previewContainer');
    const importProcessSpinner = document.getElementById('importProcessSpinner');
    const btnConfirmImport = document.getElementById('btnConfirmImport');
    const importForm = document.getElementById('importEmployeesForm');

    if (excelFileInput) {
      excelFileInput.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;

        previewContainer.classList.add('d-none');
        previewSpinner.classList.remove('d-none');
        btnConfirmImport.disabled = true;

        const formData = new FormData();
        formData.append('excel_file', this.files[0]);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('{{ route("rrhh.employees.import-preview") }}', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: formData,
        })
        .then(response => response.json())
        .then(data => {
          previewSpinner.classList.add('d-none');
          if (data.error) {
            alert(data.error);
            return;
          }

          renderPreview(data);
          previewContainer.classList.remove('d-none');
          if (data.total_rows > 0 && data.error_count < data.total_rows) {
            btnConfirmImport.disabled = false;
          }
        })
        .catch(err => {
          previewSpinner.classList.add('d-none');
          alert('Ocurrió un error al procesar la vista previa del archivo Excel.');
          console.error(err);
        });
      });
    }

    if (importForm) {
      importForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('excel_file_input');
        if (!fileInput.files || !fileInput.files[0]) {
          alert('Debe seleccionar un archivo Excel.');
          return;
        }

        previewContainer.classList.add('d-none');
        importProcessSpinner.classList.remove('d-none');
        btnConfirmImport.disabled = true;

        const formData = new FormData(importForm);

        fetch('{{ route("rrhh.employees.import") }}', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          body: formData,
        })
        .then(response => response.json())
        .then(data => {
          importProcessSpinner.classList.add('d-none');
          if (data.success) {
            alert(data.message);
            window.location.reload();
          } else {
            alert(data.message || 'Error al ejecutar la importación.');
            btnConfirmImport.disabled = false;
            previewContainer.classList.remove('d-none');
          }
        })
        .catch(err => {
          importProcessSpinner.classList.add('d-none');
          alert('Error en la comunicación con el servidor.');
          btnConfirmImport.disabled = false;
          console.error(err);
        });
      });
    }

    function renderPreview(data) {
      document.getElementById('statTotalRows').textContent = data.total_rows;
      document.getElementById('statCreateCount').textContent = data.create_count;
      document.getElementById('statUpdateCount').textContent = data.update_count;
      document.getElementById('statErrorCount').textContent = data.error_count;

      const tbody = document.getElementById('previewTableBody');
      tbody.innerHTML = '';

      data.rows.forEach(r => {
        const tr = document.createElement('tr');
        if (r.status !== 'ok') {
          tr.classList.add('table-danger');
        }

        const badgeAction = r.action === 'create'
          ? '<span class="badge bg-success"><i class="fa-solid fa-user-plus me-1"></i>Crear</span>'
          : '<span class="badge bg-info text-dark"><i class="fa-solid fa-user-pen me-1"></i>Actualizar</span>';

        const badgeStatus = r.status === 'ok'
          ? '<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i>Válido</span>'
          : `<span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i>${r.issues.join(', ')}</span>`;

        tr.innerHTML = `
          <td class="text-center font-monospace">${r.row_number}</td>
          <td><span class="badge bg-secondary font-monospace">${r.file_number}</span></td>
          <td class="fw-semibold">${r.dni}</td>
          <td>${r.full_name}</td>
          <td><small>${r.personal_phone || '-'}</small></td>
          <td><small>${r.work_phone || '-'}</small></td>
          <td class="text-center">${badgeAction}</td>
          <td class="text-center">${badgeStatus}</td>
        `;
        tbody.appendChild(tr);
      });
    }
  });
</script>
@endpush
