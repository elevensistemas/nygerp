@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.departments.index') }}">Áreas y Deptos.</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar Área</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Editar Área: {{ $department->name }}</h3>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <form method="POST" action="{{ route('rrhh.departments.update', $department) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
              <div class="col-md-4 col-12">
                <label for="code" class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $department->code) }}" required>
                @error('code')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-8 col-12">
                <label for="name" class="form-label fw-semibold">Nombre del Área <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $department->name) }}" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6 col-12">
                <label for="parent_id" class="form-label fw-semibold">Área Superior</label>
                <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                  <option value="">-- Ninguna (Área Principal) --</option>
                  @foreach($parentDepartments as $parent)
                    <option value="{{ $parent->id }}" {{ old('parent_id', $department->parent_id) == $parent->id ? 'selected' : '' }}>
                      {{ $parent->name }} ({{ $parent->code }})
                    </option>
                  @endforeach
                </select>
                @error('parent_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6 col-12">
                <label for="manager_id" class="form-label fw-semibold">Responsable de Área</label>
                <select name="manager_id" id="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                  <option value="">-- Sin asignar --</option>
                  @foreach($potentialManagers as $mgr)
                    <option value="{{ $mgr->id }}" {{ old('manager_id', $department->manager_id) == $mgr->id ? 'selected' : '' }}>
                      {{ $mgr->full_name }} (Legajo: {{ $mgr->file_number }})
                    </option>
                  @endforeach
                </select>
                @error('manager_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label for="description" class="form-label fw-semibold">Descripción o Funciones</label>
                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $department->description) }}</textarea>
                @error('description')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $department->is_active) ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold" for="is_active">Área Activa</label>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
              <a href="{{ route('rrhh.departments.index') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Actualizar Área</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
