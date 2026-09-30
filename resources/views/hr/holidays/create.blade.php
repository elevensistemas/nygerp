@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.holidays.index') }}">Feriados</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nuevo Feriado</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-plus text-primary me-2"></i>Nuevo Feriado o Día No Laborable</h3>
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
      <form action="{{ route('rrhh.holidays.store') }}" method="POST">
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Fecha del Feriado <span class="text-danger">*</span></label>
            <input type="date" name="holiday_date" class="form-control @error('holiday_date') is-invalid @enderror" value="{{ old('holiday_date', date('Y-m-d')) }}" required>
            @error('holiday_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-5">
            <label class="form-label fw-semibold">Nombre / Motivo del Feriado <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="ej. Día de la Independencia, Feriado Puente Turístico" required maxlength="150">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
              <option value="national" {{ old('type') === 'national' ? 'selected' : '' }}>Feriado Nacional</option>
              <option value="provincial" {{ old('type') === 'provincial' ? 'selected' : '' }}>Feriado Provincial</option>
              <option value="bridge" {{ old('type') === 'bridge' ? 'selected' : '' }}>Feriado Puente / Turístico</option>
              <option value="custom" {{ old('type') === 'custom' ? 'selected' : '' }}>Día No Laborable Empresa</option>
            </select>
            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Notas / Aclaraciones Adicionales</label>
            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Observaciones opcionales">
          </div>

          <div class="col-md-6">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="is_recurring" id="is_recurring" value="1" {{ old('is_recurring', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="is_recurring">Feriado Recurrente Anual</label>
              <div class="small text-muted ps-4">Se aplica automáticamente todos los años en la misma fecha (ej. 25 de Mayo, 9 de Julio).</div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold text-success" for="is_active">Feriado Activo</label>
              <div class="small text-muted ps-4">Se descuenta en los cálculos de días hábiles de ausencias.</div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
          <a href="{{ route('rrhh.holidays.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Feriado</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
