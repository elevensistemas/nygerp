@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.portal.dashboard') }}">Mi Portal</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.portal.requests') }}">Mis Licencias</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nueva Solicitud</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plane-departure text-primary me-2"></i>Solicitar Ausencia o Licencia</h3>
  </div>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
      <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Por favor corrige los siguientes errores:</strong>
      <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-lg-8 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <form id="leaveRequestForm" action="{{ route('rrhh.portal.requests.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
              {{-- Selección de Tipo de Ausencia --}}
              <div class="col-md-6 col-12">
                <label class="form-label fw-semibold">Tipo de Ausencia / Licencia <span class="text-danger">*</span></label>
                <select name="leave_type_id" id="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                  <option value="">-- Seleccionar Tipo --</option>
                  @foreach($leaveTypes as $lt)
                    <option value="{{ $lt->id }}"
                            data-deducts="{{ $lt->deducts_from_balance ? '1' : '0' }}"
                            data-halfday="{{ $lt->allows_half_day ? '1' : '0' }}"
                            data-attachment="{{ $lt->requires_attachment ? '1' : '0' }}"
                            data-reason="{{ $lt->requires_reason ? '1' : '0' }}"
                            data-anticipation="{{ $lt->min_anticipation_days }}"
                            data-maxdays="{{ $lt->max_days_limit }}"
                            data-working="{{ $lt->counts_as_working_days ? '1' : '0' }}"
                            data-color="{{ $lt->color }}"
                            {{ old('leave_type_id') == $lt->id ? 'selected' : '' }}>
                      {{ $lt->name }}
                    </option>
                  @endforeach
                </select>
                @error('leave_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Fecha Desde --}}
              <div class="col-md-3 col-6">
                <label class="form-label fw-semibold">Fecha Desde <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', date('Y-m-d')) }}" required>
                @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Fecha Hasta --}}
              <div class="col-md-3 col-6">
                <label class="form-label fw-semibold">Fecha Hasta <span class="text-danger">*</span></label>
                <input type="date" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', date('Y-m-d')) }}" required>
                @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Opción Medio Día --}}
              <div class="col-12" id="halfDayContainer" style="display: none;">
                <div class="form-check form-switch p-2 border rounded bg-light">
                  <input class="form-check-input ms-0 me-2" type="checkbox" name="is_half_day" id="is_half_day" value="1" {{ old('is_half_day') ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold" for="is_half_day">Solicitar Fracción de Medio Día (0.5 días)</label>
                  <div class="small text-muted ps-4">Aplica únicamente cuando la fecha de inicio y fin es el mismo día.</div>
                </div>
              </div>

              {{-- Motivo --}}
              <div class="col-12">
                <label class="form-label fw-semibold" id="reasonLabel">Motivo de la Solicitud</label>
                <textarea name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror" rows="3" placeholder="Explique brevemente el motivo o detalle de la ausencia...">{{ old('reason') }}</textarea>
                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Comprobante / Certificado Adjunto --}}
              <div class="col-12" id="attachmentContainer">
                <label class="form-label fw-semibold" id="attachmentLabel">Comprobante o Certificado Adjunto</label>
                <input type="file" name="attachment" id="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">Formatos válidos: PDF, JPG, PNG. Tamaño máximo: 8MB. El documento se guardará de forma confidencial.</div>
                @error('attachment') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
              <a href="{{ route('rrhh.portal.dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" id="submitBtn" class="btn btn-primary px-4 fw-semibold">
                <i class="fa-solid fa-paper-plane me-1"></i>Enviar Solicitud
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Columna Derecha: Vista Previa y Cálculo Dinámico --}}
    <div class="col-lg-4 col-12">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calculator text-primary me-2"></i>Cálculo y Validación en Vivo</h6>
        </div>
        <div class="card-body p-3">
          <div id="calcLoading" class="text-center py-4" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <div class="small text-muted mt-2">Calculando días y verificando saldos...</div>
          </div>

          <div id="calcContent">
            <div class="text-center py-3 text-muted" id="calcPrompt">
              <i class="fa-solid fa-calendar-week fa-2x mb-2 d-block text-secondary opacity-50"></i>
              Seleccione un tipo de ausencia y las fechas para ver el cálculo computable.
            </div>

            <div id="calcResults" style="display: none;">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Días Corridos:</span>
                <span class="fw-semibold text-dark" id="resCalendarDays">-</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Fines de Semana Excluidos:</span>
                <span class="fw-semibold text-secondary" id="resWeekendDays">-</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Feriados Excluidos:</span>
                <span class="fw-semibold text-danger" id="resHolidayDays">-</span>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold text-dark">Total Computable:</span>
                <span class="badge bg-primary fs-5" id="resComputableDays">0 días</span>
              </div>

              <div id="balanceSection" style="display: none;">
                <div class="p-3 bg-light rounded border mb-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">Saldo Disponible:</span>
                    <span class="small fw-bold text-dark" id="resAvailableBalance">-</span>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="small text-muted">Saldo Restante Post-Solicitud:</span>
                    <span class="small fw-bold" id="resRemainingBalance">-</span>
                  </div>
                </div>
              </div>

              <div id="calcAlert" class="alert alert-warning small mb-0" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      {{-- Información de Ayuda / Reglas --}}
      <div class="card border-0 shadow-sm bg-light">
        <div class="card-body p-3">
          <h6 class="fw-bold text-dark small mb-2"><i class="fa-solid fa-circle-info text-info me-1"></i>Información Importante</h6>
          <ul class="text-muted small ps-3 mb-0">
            <li>La solicitud será enviada a tu responsable o RR. HH. para su validación.</li>
            <li>No se descontará definitivamente de tu saldo hasta que sea formalmente aprobada.</li>
            <li>Los certificados adjuntos son confidenciales.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const leaveTypeSelect = document.getElementById('leave_type_id');
  const startDateInput = document.getElementById('start_date');
  const endDateInput = document.getElementById('end_date');
  const isHalfDayCheckbox = document.getElementById('is_half_day');
  const halfDayContainer = document.getElementById('halfDayContainer');
  const reasonLabel = document.getElementById('reasonLabel');
  const reasonInput = document.getElementById('reason');
  const attachmentContainer = document.getElementById('attachmentContainer');
  const attachmentLabel = document.getElementById('attachmentLabel');
  const attachmentInput = document.getElementById('attachment');

  const calcLoading = document.getElementById('calcLoading');
  const calcPrompt = document.getElementById('calcPrompt');
  const calcResults = document.getElementById('calcResults');
  const resCalendarDays = document.getElementById('resCalendarDays');
  const resWeekendDays = document.getElementById('resWeekendDays');
  const resHolidayDays = document.getElementById('resHolidayDays');
  const resComputableDays = document.getElementById('resComputableDays');
  const balanceSection = document.getElementById('balanceSection');
  const resAvailableBalance = document.getElementById('resAvailableBalance');
  const resRemainingBalance = document.getElementById('resRemainingBalance');
  const calcAlert = document.getElementById('calcAlert');

  function updateTypeRules() {
    const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
      halfDayContainer.style.display = 'none';
      return;
    }

    const allowsHalfDay = selectedOption.getAttribute('data-halfday') === '1';
    const requiresAttachment = selectedOption.getAttribute('data-attachment') === '1';
    const requiresReason = selectedOption.getAttribute('data-reason') === '1';

    if (allowsHalfDay) {
      halfDayContainer.style.display = 'block';
    } else {
      halfDayContainer.style.display = 'none';
      isHalfDayCheckbox.checked = false;
    }

    if (requiresAttachment) {
      attachmentLabel.innerHTML = 'Comprobante o Certificado <span class="text-danger">* (Obligatorio)</span>';
      attachmentInput.required = true;
    } else {
      attachmentLabel.innerHTML = 'Comprobante o Certificado Adjunto (Opcional)';
      attachmentInput.required = false;
    }

    if (requiresReason) {
      reasonLabel.innerHTML = 'Motivo de la Solicitud <span class="text-danger">* (Obligatorio)</span>';
      reasonInput.required = true;
    } else {
      reasonLabel.innerHTML = 'Motivo de la Solicitud';
      reasonInput.required = false;
    }
  }

  let previewTimeout = null;

  function triggerCalculation() {
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(fetchCalculationPreview, 300);
  }

  function fetchCalculationPreview() {
    const typeId = leaveTypeSelect.value;
    const startDate = startDateInput.value;
    const endDate = endDateInput.value;
    const isHalfDay = isHalfDayCheckbox.checked ? 1 : 0;

    if (!typeId || !startDate || !endDate) {
      calcPrompt.style.display = 'block';
      calcResults.style.display = 'none';
      calcLoading.style.display = 'none';
      return;
    }

    calcPrompt.style.display = 'none';
    calcLoading.style.display = 'block';
    calcResults.style.display = 'none';

    fetch("{{ route('rrhh.portal.requests.preview') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        leave_type_id: typeId,
        start_date: startDate,
        end_date: endDate,
        is_half_day: isHalfDay
      })
    })
    .then(response => response.json())
    .then(data => {
      calcLoading.style.display = 'none';
      calcResults.style.display = 'block';

      if (data.success) {
        resCalendarDays.textContent = data.calendar_days + ' d';
        resWeekendDays.textContent = data.weekend_days + ' d';
        resHolidayDays.textContent = data.holiday_days + ' d';
        resComputableDays.textContent = data.computable_days + ' días';

        if (data.balance) {
          balanceSection.style.display = 'block';
          resAvailableBalance.textContent = data.balance.available_days + ' días';
          const remaining = data.balance.available_days - data.computable_days;
          resRemainingBalance.textContent = remaining + ' días';
          resRemainingBalance.className = remaining >= 0 ? 'small fw-bold text-success' : 'small fw-bold text-danger';
        } else {
          balanceSection.style.display = 'none';
        }

        if (data.warning) {
          calcAlert.textContent = data.warning;
          calcAlert.style.display = 'block';
        } else {
          calcAlert.style.display = 'none';
        }
      } else {
        calcAlert.textContent = data.message || 'Error en el cálculo.';
        calcAlert.style.display = 'block';
      }
    })
    .catch(error => {
      calcLoading.style.display = 'none';
      calcResults.style.display = 'block';
      calcAlert.textContent = 'No se pudo comunicar con el servicio de cálculo.';
      calcAlert.style.display = 'block';
    });
  }

  leaveTypeSelect.addEventListener('change', function() {
    updateTypeRules();
    triggerCalculation();
  });

  startDateInput.addEventListener('change', function() {
    if (endDateInput.value < startDateInput.value) {
      endDateInput.value = startDateInput.value;
    }
    triggerCalculation();
  });

  endDateInput.addEventListener('change', triggerCalculation);
  isHalfDayCheckbox.addEventListener('change', triggerCalculation);

  updateTypeRules();
  if (leaveTypeSelect.value) {
    triggerCalculation();
  }
});
</script>
@endsection
