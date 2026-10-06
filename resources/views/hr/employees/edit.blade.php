@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.employees.index') }}">Colaboradores</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.employees.show', $employee) }}">{{ $employee->full_name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i>Editar Colaborador: {{ $employee->full_name }}</h3>
  </div>

  <form method="POST" action="{{ route('rrhh.employees.update', $employee) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-lg-12">
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-bottom pt-3 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="employeeFormTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                  <i class="fa-solid fa-user me-1 text-primary"></i>1. Datos Personales
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                  <i class="fa-solid fa-address-book me-1 text-primary"></i>2. Contacto y Domicilio
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                  <i class="fa-solid fa-briefcase me-1 text-primary"></i>3. Información Laboral
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="access-tab" data-bs-toggle="tab" data-bs-target="#access" type="button" role="tab">
                  <i class="fa-solid fa-key me-1 text-primary"></i>4. Acceso al Sistema
                </button>
              </li>
            </ul>
          </div>

          <div class="card-body p-4">
            <div class="tab-content" id="employeeFormTabsContent">
              {{-- 1. DATOS PERSONALES --}}
              <div class="tab-pane fade show active" id="personal" role="tabpanel">
                <h5 class="fw-bold text-dark mb-3">Información de Identificación</h5>
                <div class="row g-3">
                  <div class="col-md-4 col-12">
                    <label for="first_name" class="form-label fw-semibold">Nombre(s) <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $employee->first_name) }}" required>
                    @error('first_name')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="last_name" class="form-label fw-semibold">Apellido(s) <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $employee->last_name) }}" required>
                    @error('last_name')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="dni" class="form-label fw-semibold">DNI / Documento <span class="text-danger">*</span></label>
                    <input type="text" name="dni" id="dni" class="form-control @error('dni') is-invalid @enderror" value="{{ old('dni', $employee->dni) }}" required>
                    @error('dni')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="cuil" class="form-label fw-semibold">CUIL / CUIT</label>
                    <input type="text" name="cuil" id="cuil" class="form-control @error('cuil') is-invalid @enderror" value="{{ old('cuil', $employee->cuil) }}">
                    @error('cuil')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="birth_date" class="form-label fw-semibold">Fecha de Nacimiento</label>
                    <input type="date" name="birth_date" id="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $employee->birth_date ? $employee->birth_date->format('Y-m-d') : '') }}">
                    @error('birth_date')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="gender" class="form-label fw-semibold">Género</label>
                    <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror">
                      <option value="M" {{ old('gender', $employee->gender) === 'M' ? 'selected' : '' }}>Masculino</option>
                      <option value="F" {{ old('gender', $employee->gender) === 'F' ? 'selected' : '' }}>Femenino</option>
                      <option value="X" {{ old('gender', $employee->gender) === 'X' ? 'selected' : '' }}>No binario / X</option>
                      <option value="otro" {{ old('gender', $employee->gender) === 'otro' ? 'selected' : '' }}>Otro / No especifica</option>
                    </select>
                    @error('gender')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="nationality" class="form-label fw-semibold">Nacionalidad</label>
                    <input type="text" name="nationality" id="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality', $employee->nationality) }}">
                    @error('nationality')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="marital_status" class="form-label fw-semibold">Estado Civil</label>
                    <select name="marital_status" id="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                      <option value="">-- Seleccione --</option>
                      <option value="soltero" {{ old('marital_status', $employee->marital_status) === 'soltero' ? 'selected' : '' }}>Soltero/a</option>
                      <option value="casado" {{ old('marital_status', $employee->marital_status) === 'casado' ? 'selected' : '' }}>Casado/a</option>
                      <option value="union_convivencial" {{ old('marital_status', $employee->marital_status) === 'union_convivencial' ? 'selected' : '' }}>Unión Convivencial</option>
                      <option value="divorciado" {{ old('marital_status', $employee->marital_status) === 'divorciado' ? 'selected' : '' }}>Divorciado/a</option>
                      <option value="viudo" {{ old('marital_status', $employee->marital_status) === 'viudo' ? 'selected' : '' }}>Viudo/a</option>
                    </select>
                    @error('marital_status')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="avatar" class="form-label fw-semibold">Foto de Perfil</label>
                    <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/jpeg,image/png,image/jpg">
                    <small class="text-muted">Dejar vacío si no desea cambiar la foto actual</small>
                    @error('avatar')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
              </div>

              {{-- 2. CONTACTO Y DOMICILIO --}}
              <div class="tab-pane fade" id="contact" role="tabpanel">
                <h5 class="fw-bold text-dark mb-3">Datos de Contacto Directo</h5>
                <div class="row g-3 mb-4">
                  <div class="col-md-3 col-12">
                    <label for="personal_phone" class="form-label fw-semibold">Teléfono Personal</label>
                    <input type="text" name="personal_phone" id="personal_phone" class="form-control @error('personal_phone') is-invalid @enderror" value="{{ old('personal_phone', $employee->personal_phone ?? $employee->phone) }}">
                    @error('personal_phone')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-12">
                    <label for="work_phone" class="form-label fw-semibold">Teléfono Laboral</label>
                    <input type="text" name="work_phone" id="work_phone" class="form-control @error('work_phone') is-invalid @enderror" value="{{ old('work_phone', $employee->work_phone) }}">
                    @error('work_phone')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-12">
                    <label for="personal_email" class="form-label fw-semibold">Correo Personal</label>
                    <input type="email" name="personal_email" id="personal_email" class="form-control @error('personal_email') is-invalid @enderror" value="{{ old('personal_email', $employee->personal_email) }}">
                    @error('personal_email')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-12">
                    <label for="work_email" class="form-label fw-semibold">Correo Corporativo</label>
                    <input type="email" name="work_email" id="work_email" class="form-control @error('work_email') is-invalid @enderror" value="{{ old('work_email', $employee->work_email) }}">
                    @error('work_email')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-6 col-12">
                    <label for="address" class="form-label fw-semibold">Domicilio (Calle y Número)</label>
                    <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $employee->address) }}">
                    @error('address')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-6">
                    <label for="city" class="form-label fw-semibold">Ciudad / Localidad</label>
                    <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $employee->city) }}">
                    @error('city')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-6">
                    <label for="province" class="form-label fw-semibold">Provincia</label>
                    <input type="text" name="province" id="province" class="form-control @error('province') is-invalid @enderror" value="{{ old('province', $employee->province) }}">
                    @error('province')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <h5 class="fw-bold text-dark mb-3 border-top pt-3">Contacto de Emergencia</h5>
                <div class="row g-3">
                  <div class="col-md-4 col-12">
                    <label for="emergency_contact_name" class="form-label fw-semibold">Nombre y Apellido</label>
                    <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control @error('emergency_contact_name') is-invalid @enderror" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                    @error('emergency_contact_name')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="emergency_contact_phone" class="form-label fw-semibold">Teléfono de Emergencia</label>
                    <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control @error('emergency_contact_phone') is-invalid @enderror" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                    @error('emergency_contact_phone')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="emergency_contact_relationship" class="form-label fw-semibold">Vínculo / Parentesco</label>
                    <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" class="form-control @error('emergency_contact_relationship') is-invalid @enderror" value="{{ old('emergency_contact_relationship', $employee->emergency_contact_relationship) }}">
                    @error('emergency_contact_relationship')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
              </div>

              {{-- 3. INFORMACIÓN LABORAL --}}
              <div class="tab-pane fade" id="employment" role="tabpanel">
                <h5 class="fw-bold text-dark mb-3">Encuadre y Ubicación Laboral</h5>
                <div class="row g-3">
                  <div class="col-md-3 col-6">
                    <label for="file_number" class="form-label fw-semibold">Número de Legajo <span class="text-danger">*</span></label>
                    <input type="text" name="file_number" id="file_number" class="form-control @error('file_number') is-invalid @enderror" value="{{ old('file_number', $employee->file_number) }}" required>
                    @error('file_number')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-6">
                    <label for="hire_date" class="form-label fw-semibold">Fecha de Ingreso <span class="text-danger">*</span></label>
                    <input type="date" name="hire_date" id="hire_date" class="form-control @error('hire_date') is-invalid @enderror" value="{{ old('hire_date', $employee->hire_date ? $employee->hire_date->format('Y-m-d') : '') }}" required>
                    @error('hire_date')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-6">
                    <label for="vacation_seniority_date" class="form-label fw-semibold" title="Completar si difiere del ingreso legal por reconocimiento de antigüedad">
                      Antigüedad p/ Vacaciones
                      <i class="fa-solid fa-circle-info text-muted ms-1 small" data-bs-toggle="tooltip" title="Dejar en blanco si coincide con la Fecha de Ingreso"></i>
                    </label>
                    <input type="date" name="vacation_seniority_date" id="vacation_seniority_date" class="form-control @error('vacation_seniority_date') is-invalid @enderror" value="{{ old('vacation_seniority_date', $employee->vacation_seniority_date ? $employee->vacation_seniority_date->format('Y-m-d') : '') }}">
                    @error('vacation_seniority_date')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-6">
                    <label for="status" class="form-label fw-semibold">Estado Laboral <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                      <option value="activo" {{ old('status', $employee->status) === 'activo' ? 'selected' : '' }}>Activo</option>
                      <option value="en_onboarding" {{ old('status', $employee->status) === 'en_onboarding' ? 'selected' : '' }}>En Onboarding</option>
                      <option value="licencia" {{ old('status', $employee->status) === 'licencia' ? 'selected' : '' }}>En Licencia</option>
                      <option value="suspendido" {{ old('status', $employee->status) === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
                      <option value="egresado" {{ old('status', $employee->status) === 'egresado' ? 'selected' : '' }}>Egresado</option>
                    </select>
                    @error('status')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-3 col-6">
                    <label for="contract_type" class="form-label fw-semibold">Tipo de Contrato</label>
                    <select name="contract_type" id="contract_type" class="form-select @error('contract_type') is-invalid @enderror">
                      <option value="indeterminado" {{ old('contract_type', $employee->contract_type) === 'indeterminado' ? 'selected' : '' }}>Tiempo Indeterminado</option>
                      <option value="plazo_fijo" {{ old('contract_type', $employee->contract_type) === 'plazo_fijo' ? 'selected' : '' }}>Plazo Fijo</option>
                      <option value="pasantia" {{ old('contract_type', $employee->contract_type) === 'pasantia' ? 'selected' : '' }}>Pasantía</option>
                      <option value="eventual" {{ old('contract_type', $employee->contract_type) === 'eventual' ? 'selected' : '' }}>Eventual</option>
                      <option value="otro" {{ old('contract_type', $employee->contract_type) === 'otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('contract_type')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="department_id" class="form-label fw-semibold">Área / Departamento</label>
                    <select name="department_id" id="department_id" class="form-select @error('department_id') is-invalid @enderror">
                      <option value="">-- Sin área asignada --</option>
                      @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', $employee->department_id) == $dept->id ? 'selected' : '' }}>
                          {{ $dept->name }}
                        </option>
                      @endforeach
                    </select>
                    @error('department_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="position_id" class="form-label fw-semibold">Puesto de Trabajo</label>
                    <select name="position_id" id="position_id" class="form-select @error('position_id') is-invalid @enderror">
                      <option value="">-- Sin puesto asignado --</option>
                      @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" {{ old('position_id', $employee->position_id) == $pos->id ? 'selected' : '' }}>
                          {{ $pos->name }}
                        </option>
                      @endforeach
                    </select>
                    @error('position_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="branch_id" class="form-label fw-semibold">Sucursal / Base Operativa</label>
                    <select name="branch_id" id="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                      <option value="">-- Sin base asignada --</option>
                      @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('branch_id', $employee->branch_id) == $branch->id ? 'selected' : '' }}>
                          {{ $branch->name }}
                        </option>
                      @endforeach
                    </select>
                    @error('branch_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="manager_id" class="form-label fw-semibold">Responsable Directo</label>
                    <select name="manager_id" id="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                      <option value="">-- Sin responsable directo --</option>
                      @foreach($managers as $mgr)
                        <option value="{{ $mgr->id }}" {{ old('manager_id', $employee->manager_id) == $mgr->id ? 'selected' : '' }}>
                          {{ $mgr->full_name }} (Legajo: {{ $mgr->file_number }})
                        </option>
                      @endforeach
                    </select>
                    @error('manager_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="agreement_id" class="form-label fw-semibold">Convenio Colectivo / Política</label>
                    <select name="agreement_id" id="agreement_id" class="form-select @error('agreement_id') is-invalid @enderror">
                      <option value="">-- Fuera de convenio / General --</option>
                      @foreach($agreements as $ag)
                        <option value="{{ $ag->id }}" {{ old('agreement_id', $employee->agreement_id) == $ag->id ? 'selected' : '' }}>
                          {{ $ag->name }}
                        </option>
                      @endforeach
                    </select>
                    @error('agreement_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-4 col-12">
                    <label for="salary" class="form-label fw-semibold">Salario Bruto de Referencia ($)</label>
                    <input type="number" step="0.01" name="salary" id="salary" class="form-control @error('salary') is-invalid @enderror" value="{{ old('salary', $employee->salary) }}">
                    @error('salary')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-12">
                    <label for="notes" class="form-label fw-semibold">Observaciones Internas</label>
                    <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $employee->notes) }}</textarea>
                    @error('notes')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
              </div>

              {{-- 4. ACCESO AL SISTEMA --}}
              <div class="tab-pane fade" id="access" role="tabpanel">
                <h5 class="fw-bold text-dark mb-3">Vinculación de Usuario del ERP</h5>
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
                  <i class="fa-solid fa-circle-info fa-2x text-primary"></i>
                  <div>
                    <div class="fw-semibold">Vinculación segura</div>
                    <small>Asocie este empleado a una cuenta de usuario existente en el ERP sin modificar sus roles ni permisos operativos.</small>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-md-8 col-12">
                    <label for="user_id" class="form-label fw-semibold">Usuario del Sistema Vinculado</label>
                    <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror">
                      <option value="">-- Sin usuario vinculado (Sin acceso al sistema) --</option>
                      @foreach($availableUsers as $u)
                        <option value="{{ $u->id }}" {{ old('user_id', $employee->user_id) == $u->id ? 'selected' : '' }}>
                          {{ $u->name }} ({{ $u->email }}) - Rol: {{ $u->role ?? 'usuario' }}
                        </option>
                      @endforeach
                    </select>
                    @error('user_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center">
              <a href="{{ route('rrhh.employees.show', $employee) }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Cancelar
              </a>
              <button type="submit" class="btn btn-primary px-4">
                <i class="fa-solid fa-floppy-disk me-1"></i>Actualizar Colaborador
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    if (form) {
      form.addEventListener('invalid', function (e) {
        var invalidInput = e.target;
        var tabPane = invalidInput.closest('.tab-pane');
        if (tabPane && !tabPane.classList.contains('active')) {
          var tabId = tabPane.getAttribute('id');
          var tabTrigger = document.querySelector('[data-bs-target="#' + tabId + '"], [href="#' + tabId + '"]');
          if (tabTrigger) {
            var bsTab = new bootstrap.Tab(tabTrigger);
            bsTab.show();
            setTimeout(function() {
              invalidInput.focus();
            }, 150);
          }
        }
      }, true);
    }
  });
</script>
@endpush
