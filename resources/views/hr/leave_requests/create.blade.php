@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-requests.index') }}">Licencias y Vacaciones</a></li>
        <li class="breadcrumb-item active" aria-current="page">Asignar Vacaciones / Licencia</li>
      </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h3 class="fw-bold mb-0 text-dark">
          <i class="fa-solid fa-calendar-plus text-primary me-2"></i>Asignar Vacaciones o Licencia
        </h3>
        <p class="text-muted small mb-0">Otorgue y registre períodos de vacaciones o ausencias autorizadas directamente a un colaborador.</p>
      </div>
      <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Volver a Solicitudes</span>
      </a>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
      <div class="d-flex align-items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div>
          <div class="fw-bold mb-1">Por favor revise los siguientes errores:</div>
          <ul class="mb-0 ps-3">
            @foreach($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('rrhh.leave-requests.store') }}" enctype="multipart/form-data" id="adminLeaveForm">
    @csrf
    <div class="row g-4">
      {{-- Formulario Principal --}}
      <div class="col-lg-7 col-12">
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-check text-primary me-2"></i>Datos de la Asignación</h5>
          </div>
          <div class="card-body p-4">
            {{-- Selector de Empleado --}}
            <div class="mb-4">
              <label for="employee_id" class="form-label fw-semibold">
                Colaborador <span class="text-danger">*</span>
              </label>
              <select name="employee_id" id="employee_id" class="form-select" required>
                <option value="">Seleccione un colaborador...</option>
                @foreach($employees as $emp)
                  <option value="{{ $emp->id }}"
                    data-name="{{ $emp->full_name }}"
                    data-file="{{ $emp->file_number }}"
                    data-dept="{{ $emp->department ? $emp->department->name : 'Sin Área' }}"
                    data-pos="{{ $emp->position ? $emp->position->name : 'Sin Puesto' }}"
                    data-seniority="{{ $emp->seniority_formatted }}"
                    data-hire="{{ $emp->hire_date ? $emp->hire_date->format('d/m/Y') : '-' }}"
                    {{ (old('employee_id', $selectedEmployeeId) == $emp->id) ? 'selected' : '' }}>
                    {{ $emp->last_name }}, {{ $emp->first_name }} (Legajo: {{ $emp->file_number }} - {{ $emp->department ? $emp->department->name : 'General' }})
                  </option>
                @endforeach
              </select>

              {{-- Badge con ficha rápida del colaborador --}}
              <div id="employeeInfoCard" class="mt-2 p-3 bg-light rounded border {{ $selectedEmployee ? '' : 'd-none' }}">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                  <div>
                    <strong id="empName" class="text-dark">{{ $selectedEmployee ? $selectedEmployee->full_name : '' }}</strong>
                    <div class="text-muted small">
                      <span id="empDept">{{ $selectedEmployee && $selectedEmployee->department ? $selectedEmployee->department->name : '' }}</span>
                      &bull;
                      <span id="empPos">{{ $selectedEmployee && $selectedEmployee->position ? $selectedEmployee->position->name : '' }}</span>
                    </div>
                  </div>
                  <div class="text-end">
                    <span class="badge bg-primary px-2 py-1">
                      <i class="fa-solid fa-clock-rotate-left me-1"></i>Antigüedad: <span id="empSeniority">{{ $selectedEmployee ? $selectedEmployee->seniority_formatted : '' }}</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {{-- Selector de Tipo de Licencia / Vacaciones --}}
            <div class="mb-4">
              <label for="leave_type_id" class="form-label fw-semibold">
                Tipo de Licencia o Vacaciones <span class="text-danger">*</span>
              </label>
              <select name="leave_type_id" id="leave_type_id" class="form-select" required>
                @foreach($leaveTypes as $lt)
                  <option value="{{ $lt->id }}"
                    data-color="{{ $lt->color }}"
                    data-deducts="{{ $lt->deducts_from_balance ? '1' : '0' }}"
                    data-unit="{{ $lt->calculation_unit }}"
                    data-halfday="{{ $lt->allows_half_day ? '1' : '0' }}"
                    data-negative="{{ $lt->allows_negative_balance ? '1' : '0' }}"
                    data-category="{{ $lt->category }}"
                    {{ (old('leave_type_id', $selectedLeaveTypeId) == $lt->id) ? 'selected' : '' }}>
                    {{ $lt->name }} ({{ $lt->calculation_unit === 'habiles' ? 'Días hábiles' : 'Días corridos' }}{{ $lt->deducts_from_balance ? ' - Descuenta cupo' : '' }})
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Rango de Fechas --}}
            <div class="row g-3 mb-4">
              <div class="col-md-6 col-12">
                <label for="date_from" class="form-label fw-semibold">
                  Fecha Desde (Inicio) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="fa-regular fa-calendar"></i></span>
                  <input type="date" name="date_from" id="date_from" class="form-control"
                    value="{{ old('date_from', date('Y-m-d')) }}" required>
                </div>
              </div>

              <div class="col-md-6 col-12">
                <label for="date_to" class="form-label fw-semibold">
                  Fecha Hasta (Fin) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="fa-regular fa-calendar-check"></i></span>
                  <input type="date" name="date_to" id="date_to" class="form-control"
                    value="{{ old('date_to', date('Y-m-d')) }}" required>
                </div>
              </div>
            </div>

            {{-- Opción Medio Día (dinámico) --}}
            <div id="halfDayContainer" class="mb-4 d-none">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="is_half_day" id="is_half_day" value="1" {{ old('is_half_day') ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="is_half_day">Medio Día (0.5 días)</label>
              </div>
              <div id="halfDayTypeWrapper" class="ps-4 {{ old('is_half_day') ? '' : 'd-none' }}">
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="half_day_type" id="half_day_manana" value="manana" {{ old('half_day_type', 'manana') === 'manana' ? 'checked' : '' }}>
                    <label class="form-check-label" for="half_day_manana">Turno Mañana</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="half_day_type" id="half_day_tarde" value="tarde" {{ old('half_day_type') === 'tarde' ? 'checked' : '' }}>
                    <label class="form-check-label" for="half_day_tarde">Turno Tarde</label>
                  </div>
                </div>
              </div>
            </div>

            {{-- Motivo / Justificación --}}
            <div class="mb-4">
              <label for="reason" class="form-label fw-semibold">Motivo o Observaciones de la Asignación</label>
              <textarea name="reason" id="reason" rows="2" class="form-control" placeholder="Ej: Vacaciones correspondientes al período ordinario...">{{ old('reason') }}</textarea>
            </div>

            {{-- Documento Adjunto (opcional) --}}
            <div class="mb-4">
              <label for="attachment" class="form-label fw-semibold">Documento o Constancia Adjunta (Opcional)</label>
              <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
              <small class="text-muted">Formatos permitidos: PDF, JPG, PNG, DOC (Máx. 10MB)</small>
            </div>

            {{-- Notas Confidenciales de RRHH --}}
            <div class="mb-4">
              <label for="confidential_notes" class="form-label fw-semibold text-muted">Notas Internas Confidenciales (Solo RRHH)</label>
              <textarea name="confidential_notes" id="confidential_notes" rows="2" class="form-control bg-light" placeholder="Comentarios privados para el legajo de RRHH...">{{ old('confidential_notes') }}</textarea>
            </div>

            {{-- Switch Aprobación Inmediata --}}
            <div class="p-3 bg-light rounded border mb-4">
              <div class="form-check form-switch d-flex align-items-center gap-3">
                <input class="form-check-input mt-0" type="checkbox" role="switch" name="auto_approve" id="auto_approve" value="1" checked style="transform: scale(1.3);">
                <div>
                  <label class="form-check-label fw-bold text-dark mb-0" for="auto_approve">
                    Aprobar de forma inmediata e impactar saldo
                  </label>
                  <div class="text-muted small">
                    Si está activo, las vacaciones quedan aprobadas automáticamente por RRHH y los días se descuentan de forma definitiva en el saldo.
                  </div>
                </div>
              </div>
            </div>

            {{-- Botones de Acción --}}
            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
              <a href="{{ route('rrhh.leave-requests.index') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2" id="submitBtn">
                <i class="fa-solid fa-check"></i>
                <span>Confirmar y Asignar Vacaciones</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {{-- Panel Lateral: Previsualización en Vivo y Reglas Legales --}}
      <div class="col-lg-5 col-12">
        {{-- Card de Previsualización y Cómputo --}}
        <div class="card border-0 shadow-sm mb-4 sticky-top" style="top: calc(var(--nyg-nav-height, 64px) + 16px);">
          <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calculator text-primary me-2"></i>Cómputo y Saldo Estimado</h5>
          </div>
          <div class="card-body p-4">
            <div id="previewLoading" class="text-center py-4 d-none">
              <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
              <span class="text-muted small">Calculando días laborables y saldos...</span>
            </div>

            <div id="previewContent">
              {{-- Tarjeta de Días Computables --}}
              <div class="text-center p-3 mb-3 rounded bg-primary bg-opacity-10 border border-primary border-opacity-25">
                <div class="text-uppercase small fw-bold text-primary">Días a Computar</div>
                <h1 class="display-4 fw-bold text-primary my-1" id="previewTotalDays">0</h1>
                <div class="small text-muted" id="previewUnitLabel">Días Corridos</div>
              </div>

              {{-- Estado del Saldo --}}
              <div class="card border mb-3">
                <div class="card-body p-3">
                  <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Impacto en Saldo del Colaborador</h6>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Saldo Disponible Actual:</span>
                    <strong class="text-dark fs-6" id="previewAvailBefore">-</strong>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Días a Descontar:</span>
                    <strong class="text-danger fs-6" id="previewDeductCount">-0</strong>
                  </div>
                  <hr class="my-2">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">Saldo Restante Estimado:</span>
                    <strong class="fs-5" id="previewAvailAfter">-</strong>
                  </div>
                </div>
              </div>

              {{-- Advertencias de Superposición o Saldo --}}
              <div id="overlapAlert" class="alert alert-warning py-2 px-3 small d-none mb-3">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                <span id="overlapText"></span>
              </div>

              <div id="negativeBalanceAlert" class="alert alert-danger py-2 px-3 small d-none mb-3">
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                <span>El saldo resultante será negativo. Este tipo de ausencia no admite sobregiro.</span>
              </div>

              {{-- Detalle de Períodos e Impacto por Año --}}
              <div id="periodsBreakdownContainer" class="d-none mb-3">
                <h6 class="fw-bold text-dark mb-2 small text-uppercase">Desglose por Período</h6>
                <ul class="list-group list-group-flush small border rounded" id="periodsList"></ul>
              </div>
            </div>

            {{-- Información Referencial LCT --}}
            <div class="p-3 bg-light rounded border mt-3">
              <h6 class="fw-bold text-dark mb-2 small"><i class="fa-solid fa-scale-unbalanced-flip text-secondary me-1"></i>Escala Legal de Vacaciones (LCT)</h6>
              <ul class="list-unstyled mb-0 small text-muted">
                <li class="mb-1">&bull; <strong>Hasta 5 años:</strong> 14 días corridos</li>
                <li class="mb-1">&bull; <strong>De 5 a 10 años:</strong> 21 días corridos</li>
                <li class="mb-1">&bull; <strong>De 10 a 20 años:</strong> 28 días corridos</li>
                <li>&bull; <strong>Más de 20 años:</strong> 35 días corridos</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const employeeSelect = document.getElementById('employee_id');
  const leaveTypeSelect = document.getElementById('leave_type_id');
  const dateFromInput = document.getElementById('date_from');
  const dateToInput = document.getElementById('date_to');
  const isHalfDayCheck = document.getElementById('is_half_day');
  const halfDayContainer = document.getElementById('halfDayContainer');
  const halfDayWrapper = document.getElementById('halfDayTypeWrapper');

  const previewTotalDays = document.getElementById('previewTotalDays');
  const previewUnitLabel = document.getElementById('previewUnitLabel');
  const previewAvailBefore = document.getElementById('previewAvailBefore');
  const previewDeductCount = document.getElementById('previewDeductCount');
  const previewAvailAfter = document.getElementById('previewAvailAfter');
  const overlapAlert = document.getElementById('overlapAlert');
  const overlapText = document.getElementById('overlapText');
  const negativeBalanceAlert = document.getElementById('negativeBalanceAlert');
  const previewLoading = document.getElementById('previewLoading');
  const periodsBreakdownContainer = document.getElementById('periodsBreakdownContainer');
  const periodsList = document.getElementById('periodsList');

  const employeeInfoCard = document.getElementById('employeeInfoCard');
  const empName = document.getElementById('empName');
  const empDept = document.getElementById('empDept');
  const empPos = document.getElementById('empPos');
  const empSeniority = document.getElementById('empSeniority');

  // Actualizar Ficha Rápida del Colaborador
  function updateEmployeeCard() {
    const selectedOpt = employeeSelect.options[employeeSelect.selectedIndex];
    if (selectedOpt && selectedOpt.value) {
      empName.textContent = selectedOpt.dataset.name || '';
      empDept.textContent = selectedOpt.dataset.dept || '';
      empPos.textContent = selectedOpt.dataset.pos || '';
      empSeniority.textContent = selectedOpt.dataset.seniority || '';
      employeeInfoCard.classList.remove('d-none');
    } else {
      employeeInfoCard.classList.add('d-none');
    }
  }

  // Actualizar Soporte para Medio Día
  function updateHalfDayVisibility() {
    const selectedTypeOpt = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
    if (selectedTypeOpt && selectedTypeOpt.dataset.halfday === '1') {
      halfDayContainer.classList.remove('d-none');
    } else {
      halfDayContainer.classList.add('d-none');
      isHalfDayCheck.checked = false;
      halfDayWrapper.classList.add('d-none');
    }
  }

  isHalfDayCheck.addEventListener('change', function () {
    if (this.checked) {
      halfDayWrapper.classList.remove('d-none');
      dateToInput.value = dateFromInput.value;
      dateToInput.disabled = true;
    } else {
      halfDayWrapper.classList.add('d-none');
      dateToInput.disabled = false;
    }
    recalculatePreview();
  });

  dateFromInput.addEventListener('change', function () {
    if (dateToInput.value < this.value || isHalfDayCheck.checked) {
      dateToInput.value = this.value;
    }
    recalculatePreview();
  });

  dateToInput.addEventListener('change', recalculatePreview);

  employeeSelect.addEventListener('change', function () {
    updateEmployeeCard();
    recalculatePreview();
  });

  leaveTypeSelect.addEventListener('change', function () {
    updateHalfDayVisibility();
    recalculatePreview();
  });

  // Cálculo AJAX en Vivo
  let debounceTimer = null;
  function recalculatePreview() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(doRecalculate, 250);
  }

  function doRecalculate() {
    const empId = employeeSelect.value;
    const typeId = leaveTypeSelect.value;
    const fromVal = dateFromInput.value;
    const toVal = dateToInput.value;
    const isHalf = isHalfDayCheck.checked ? 1 : 0;
    const halfType = document.querySelector('input[name="half_day_type"]:checked')?.value || 'manana';

    if (!empId || !typeId || !fromVal || !toVal) {
      previewTotalDays.textContent = '0';
      previewAvailBefore.textContent = '-';
      previewDeductCount.textContent = '-0';
      previewAvailAfter.textContent = '-';
      overlapAlert.classList.add('d-none');
      negativeBalanceAlert.classList.add('d-none');
      periodsBreakdownContainer.classList.add('d-none');
      return;
    }

    previewLoading.classList.remove('d-none');

    fetch('{{ route("rrhh.leave-requests.calculate-preview") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        employee_id: empId,
        leave_type_id: typeId,
        date_from: fromVal,
        date_to: toVal,
        is_half_day: isHalf,
        half_day_type: halfType
      })
    })
    .then(res => res.json())
    .then(data => {
      previewLoading.classList.add('d-none');
      if (!data.success) return;

      previewTotalDays.textContent = data.total_computable_days;
      previewUnitLabel.textContent = data.leave_type.calculation_unit === 'habiles' ? 'Días Hábiles Laborales' : 'Días Corridos';

      if (data.leave_type.deducts_from_balance) {
        previewAvailBefore.textContent = data.balance.available_days + ' días';
        previewDeductCount.textContent = '-' + data.total_computable_days + ' días';
        const rem = data.balance.remaining_after;
        previewAvailAfter.textContent = rem + ' días';
        previewAvailAfter.className = rem >= 0 ? 'fs-5 text-success' : 'fs-5 text-danger';

        if (rem < 0 && !data.leave_type.allows_negative_balance) {
          negativeBalanceAlert.classList.remove('d-none');
        } else {
          negativeBalanceAlert.classList.add('d-none');
        }
      } else {
        previewAvailBefore.textContent = 'No aplica cupo';
        previewDeductCount.textContent = '0 días';
        previewAvailAfter.textContent = 'Sin descuento';
        previewAvailAfter.className = 'fs-5 text-muted';
        negativeBalanceAlert.classList.add('d-none');
      }

      // Superposición
      if (data.has_overlap) {
        overlapText.textContent = data.overlap_details;
        overlapAlert.classList.remove('d-none');
      } else {
        overlapAlert.classList.add('d-none');
      }

      // Desglose por períodos
      if (data.periods_breakdown && data.periods_breakdown.length > 0) {
        periodsList.innerHTML = '';
        data.periods_breakdown.forEach(p => {
          const li = document.createElement('li');
          li.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
          li.innerHTML = `<span>Período <strong>${p.year}</strong></span> <span class="badge bg-secondary">${p.days} días</span>`;
          periodsList.appendChild(li);
        });
        periodsBreakdownContainer.classList.remove('d-none');
      } else {
        periodsBreakdownContainer.classList.add('d-none');
      }
    })
    .catch(err => {
      previewLoading.classList.add('d-none');
      console.error('Error calculando previsualización:', err);
    });
  }

  // Inicializar estado
  updateEmployeeCard();
  updateHalfDayVisibility();
  recalculatePreview();
});
</script>
@endpush
@endsection
