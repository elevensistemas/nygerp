@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.agreements.index') }}">Convenios</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nuevo Convenio</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-plus text-primary me-2"></i>Nuevo Convenio / Política Laboral</h3>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <form method="POST" action="{{ route('rrhh.agreements.store') }}">
            @csrf

            <div class="row g-3">
              <div class="col-md-4 col-12">
                <label for="code" class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="Ej: CCT-40-89, FUERA-CONV" required>
                @error('code')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-8 col-12">
                <label for="name" class="form-label fw-semibold">Nombre del Convenio o Política <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Ej: CCT 40/89 Camioneros / Transporte de Cargas" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label for="description" class="form-label fw-semibold">Descripción / Observaciones</label>
                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Detalles de la política laboral, encuadre o acuerdos...">{{ old('description') }}</textarea>
                @error('description')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold" for="is_active">Convenio Activo</label>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
              <a href="{{ route('rrhh.agreements.index') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Convenio</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
