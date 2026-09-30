@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-policies.index') }}">Políticas y Escalas</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nueva Política</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-plus text-primary me-2"></i>Nueva Política de Ausencias / Vacaciones</h3>
  </div>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
      <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Por favor corrige los errores:</strong>
      <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form action="{{ route('rrhh.leave-policies.store') }}" method="POST">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Convenio Colectivo de Trabajo</label>
            <select name="agreement_id" class="form-select @error('agreement_id') is-invalid @enderror">
              <option value="">-- General (Aplica a todos los convenios) --</option>
              @foreach($agreements as $agr)
                <option value="{{ $agr->id }}" {{ old('agreement_id') == $agr->id ? 'selected' : '' }}>{{ $agr->name }} ({{ $agr->code }})</option>
              @endforeach
            </select>
            <div class="form-text">Dejar en blanco para aplicar de forma supletoria/general.</div>
            @error('agreement_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Tipo de Ausencia <span class="text-danger">*</span></label>
            <select name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
              <option value="">-- Seleccione Tipo --</option>
              @foreach($leaveTypes as $lt)
                <option value="{{ $lt->id }}" {{ old('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }} ({{ $lt->code }})</option>
              @endforeach
            </select>
            @error('leave_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Antigüedad Mínima (Años) <span class="text-danger">*</span></label>
            <input type="number" name="min_seniority_years" class="form-control" value="{{ old('min_seniority_years', 0) }}" min="0" required>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Antigüedad Máxima (Años)</label>
            <input type="number" name="max_seniority_years" class="form-control" value="{{ old('max_seniority_years') }}" min="0" placeholder="Vacío = Sin límite sup.">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Días Otorgados <span class="text-danger">*</span></label>
            <input type="number" name="days_granted" class="form-control fw-bold text-primary" value="{{ old('days_granted', 14) }}" min="0" max="365" required>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Cómputo <span class="text-danger">*</span></label>
            <select name="counts_as_working_days" class="form-select">
              <option value="1" {{ old('counts_as_working_days', '1') == '1' ? 'selected' : '' }}>Días Hábiles</option>
              <option value="0" {{ old('counts_as_working_days') == '0' ? 'selected' : '' }}>Días Corridos</option>
            </select>
          </div>

          <div class="col-12"><hr class="my-2"></div>
          <div class="col-12"><h5 class="fw-bold text-secondary mb-2"><i class="fa-solid fa-clock-rotate-left me-2"></i>Vencimiento y Traslado</h5></div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="allows_carryover" id="allows_carryover" value="1" {{ old('allows_carryover') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="allows_carryover">Permite Trasladar Saldo</label>
              <div class="small text-muted ps-4">Permite pasar saldo remanente al año siguiente.</div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Límite de Días Trasladables</label>
            <input type="number" name="max_carryover_days" class="form-control" value="{{ old('max_carryover_days') }}" min="1" placeholder="Vacío = ilimitado">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Meses de Caducidad de Saldo</label>
            <input type="number" name="expiration_months" class="form-control" value="{{ old('expiration_months') }}" min="1" max="60" placeholder="ej. 12 meses">
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold text-success" for="is_active">Política Activa</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
          <a href="{{ route('rrhh.leave-policies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Política</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
