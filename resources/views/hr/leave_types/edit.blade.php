@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-types.index') }}">Tipos de Ausencia</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar: {{ $leaveType->name }}</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Editar Tipo de Ausencia</h3>
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
      <form action="{{ route('rrhh.leave-types.update', $leaveType) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control font-monospace @error('code') is-invalid @enderror" value="{{ old('code', $leaveType->code) }}" required maxlength="20">
            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Nombre del Tipo <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $leaveType->name) }}" required maxlength="100">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Color Identificador</label>
            <div class="input-group">
              <input type="color" name="color" class="form-control form-control-color" value="{{ old('color', $leaveType->color ?? '#0d6efd') }}">
              <input type="text" class="form-control font-monospace" id="colorText" value="{{ old('color', $leaveType->color ?? '#0d6efd') }}" readonly>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Descripción / Observaciones</label>
            <textarea name="description" class="form-control" rows="2">{{ old('description', $leaveType->description) }}</textarea>
          </div>

          <div class="col-12"><hr class="my-2"></div>
          <div class="col-12"><h5 class="fw-bold text-secondary mb-2"><i class="fa-solid fa-gears me-2"></i>Reglas de Cómputo y Validación</h5></div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Tipo de Cómputo de Días <span class="text-danger">*</span></label>
            <select name="counts_as_working_days" class="form-select @error('counts_as_working_days') is-invalid @enderror">
              <option value="1" {{ old('counts_as_working_days', $leaveType->counts_as_working_days ? '1' : '0') == '1' ? 'selected' : '' }}>Días Hábiles (Excluye fines de semana y feriados)</option>
              <option value="0" {{ old('counts_as_working_days', $leaveType->counts_as_working_days ? '1' : '0') == '0' ? 'selected' : '' }}>Días Corridos (Incluye fines de semana y feriados)</option>
            </select>
            @error('counts_as_working_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Anticipación Mínima (Días)</label>
            <input type="number" name="min_anticipation_days" class="form-control" value="{{ old('min_anticipation_days', $leaveType->min_anticipation_days) }}" min="0" max="365">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Duración Máxima por Solicitud (Días opcional)</label>
            <input type="number" name="max_days_limit" class="form-control" value="{{ old('max_days_limit', $leaveType->max_days_limit) }}" min="1">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Orden de Visualización</label>
            <input type="number" name="display_order" class="form-control" value="{{ old('display_order', $leaveType->display_order ?? 10) }}" min="0">
          </div>

          <div class="col-12"><hr class="my-2"></div>
          <div class="col-12"><h5 class="fw-bold text-secondary mb-2"><i class="fa-solid fa-toggle-on me-2"></i>Parámetros y Restricciones</h5></div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="deducts_from_balance" id="deducts_from_balance" value="1" {{ old('deducts_from_balance', $leaveType->deducts_from_balance) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="deducts_from_balance">Descuenta del Saldo</label>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="allows_negative_balance" id="allows_negative_balance" value="1" {{ old('allows_negative_balance', $leaveType->allows_negative_balance) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="allows_negative_balance">Permite Saldo Negativo</label>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="requires_approval" id="requires_approval" value="1" {{ old('requires_approval', $leaveType->requires_approval) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="requires_approval">Requiere Aprobación</label>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="requires_attachment" id="requires_attachment" value="1" {{ old('requires_attachment', $leaveType->requires_attachment) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="requires_attachment">Requiere Comprobante</label>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="requires_reason" id="requires_reason" value="1" {{ old('requires_reason', $leaveType->requires_reason) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="requires_reason">Requiere Motivo</label>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="allows_half_day" id="allows_half_day" value="1" {{ old('allows_half_day', $leaveType->allows_half_day) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="allows_half_day">Permite Medio Día (0.5)</label>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $leaveType->is_active) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold text-success" for="is_active">Tipo Activo</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
          <a href="{{ route('rrhh.leave-types.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Actualizar Tipo</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
