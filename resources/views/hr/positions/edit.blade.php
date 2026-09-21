@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.positions.index') }}">Puestos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar Puesto</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Editar Puesto: {{ $position->name }}</h3>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <form method="POST" action="{{ route('rrhh.positions.update', $position) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
              <div class="col-md-4 col-12">
                <label for="code" class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $position->code) }}" required>
                @error('code')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-8 col-12">
                <label for="name" class="form-label fw-semibold">Nombre del Puesto <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $position->name) }}" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label for="department_id" class="form-label fw-semibold">Área / Departamento Asociado</label>
                <select name="department_id" id="department_id" class="form-select @error('department_id') is-invalid @enderror">
                  <option value="">-- Sin área específica (General) --</option>
                  @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ old('department_id', $position->department_id) == $dept->id ? 'selected' : '' }}>
                      {{ $dept->name }} ({{ $dept->code }})
                    </option>
                  @endforeach
                </select>
                @error('department_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label for="description" class="form-label fw-semibold">Descripción del Puesto y Responsabilidades</label>
                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $position->description) }}</textarea>
                @error('description')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label for="requirements" class="form-label fw-semibold">Requisitos / Perfil</label>
                <textarea name="requirements" id="requirements" rows="2" class="form-control @error('requirements') is-invalid @enderror">{{ old('requirements', $position->requirements) }}</textarea>
                @error('requirements')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $position->is_active) ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold" for="is_active">Puesto Activo</label>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
              <a href="{{ route('rrhh.positions.index') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Actualizar Puesto</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
