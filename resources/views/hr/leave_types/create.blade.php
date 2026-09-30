@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.leave-types.index') }}">Tipos de Ausencia</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nuevo Tipo</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-plus text-primary me-2"></i>Nuevo Tipo de Ausencia</h3>
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
      <form action="{{ route('rrhh.leave-types.store') }}" method="POST">
        @csrf

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control font-monospace @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="ej. VAC, MED, MAT" required maxlength="20">
            <div class="form-text">Identificador alfanumérico único.</div>
            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Nombre del Tipo <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="ej. Vacaciones Ordinarias" required maxlength="100">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Color Identificador</label>
            <div class="input-group">
              <input type="color" name="color" class="form-control form-control-color" value="{{ old('color', '#0d6efd') }}" title="Elegir color para calendario">
              <input type="text" class="form-control font-monospace" id="colorText" value="{{ old('color', '#0d6efd') }}" readonly>
            </div>
            <div class="form-text">Color representativo en el calendario.</div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Descripción / Observaciones</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Detalle informativo sobre la aplicación de este tipo de ausencia...">{{ old('description') }}</textarea>
          </div>

          <div class="col-12"><hr class="my-2"></div>
          <div class="col-12"><h5 class="fw-bold text-secondary mb-2"><i class="fa-solid fa-gears me-2"></i>Reglas de Cómputo y Validación</h5></div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Tipo de Cómputo de Días <span class="text-danger">*</span></label>
            <select name="counts_as_working_days" class="form-select @error('counts_as_working_days') is-invalid @enderror">
              <option value="1" {{ old('counts_as_working_days', '1') == '1' ? 'selected' : '' }}>Días Hábiles (Excluye fines de semana y feriados)</option>
              <option value="0" {{ old('counts_as_working_days') == '0' ? 'selected' : '' }}>Días Corridos (Incluye fines de semana y feriados)</option>
            </select>
            @error('counts_as_working_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Anticipación Mínima (Días)</label>
            <input type="number" name="min_anticipation_days" class="form-control" value="{{ old('min_anticipation_days', 0) }}" min="0" max="365">
            <div class="form-text">0 = permite solicitar para hoy o con antelación inmediata.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Duración Máxima por Solicitud (Días opcional)</label>
            <input type="number" name="max_days_limit" class="form-control" value="{{ old('max_days_limit') }}" min="1" placeholder="Sin límite específico">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Orden de Visualización</label>
            <input type="number" name="display_order" class="form-control" value="{{ old('display_order', 10) }}" min="0">
          </div>

          <div class="col-12"><hr class="my-2"></div>
          <div class="col-12"><h5 class="fw-bold text-secondary mb-2"><i class="fa-solid fa-toggle-on me-2"></i>Parámetros y Restricciones</h5></div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="deducts_from_balance" id="deducts_from_balance" value="1" {{ old('deducts_from_balance', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="deducts_from_balance">Descuenta del Saldo</label>
              <div class="small text-muted ps-4">Resta días del saldo anual asignado.</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="allows_negative_balance" id="allows_negative_balance" value="1" {{ old('allows_negative_balance') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="allows_negative_balance">Permite Saldo Negativo</label>
              <div class="small text-muted ps-4">Permite solicitar aunque no tenga saldo suficiente.</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="requires_approval" id="requires_approval" value="1" {{ old('requires_approval', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="requires_approval">Requiere Aprobación</label>
              <div class="small text-muted ps-4">Pasa por flujo Manager y/o RR. HH.</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="requires_attachment" id="requires_attachment" value="1" {{ old('requires_attachment') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="requires_attachment">Requiere Comprobante</label>
              <div class="small text-muted ps-4">Obliga a adjuntar certificado o constancia.</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="requires_reason" id="requires_reason" value="1" {{ old('requires_reason', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="requires_reason">Requiere Motivo</label>
              <div class="small text-muted ps-4">Obliga al empleado a escribir el motivo.</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="allows_half_day" id="allows_half_day" value="1" {{ old('allows_half_day') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="allows_half_day">Permite Medio Día (0.5)</label>
              <div class="small text-muted ps-4">Habilita fracción de media jornada.</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check form-switch p-2 border rounded">
              <input class="form-check-input ms-0 me-2" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold text-success" for="is_active">Tipo Activo</label>
              <div class="small text-muted ps-4">Disponible para ser solicitado en el sistema.</div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
          <a href="{{ route('rrhh.leave-types.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Tipo</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
