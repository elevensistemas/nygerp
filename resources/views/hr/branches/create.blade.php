@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
  <div class="mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('rrhh.dashboard') }}">Recursos Humanos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rrhh.branches.index') }}">Sucursales</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nueva Sucursal</li>
      </ol>
    </nav>
    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-plus text-primary me-2"></i>Nueva Sucursal / Base Operativa</h3>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8 col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <form method="POST" action="{{ route('rrhh.branches.store') }}">
            @csrf

            <div class="row g-3">
              <div class="col-md-4 col-12">
                <label for="code" class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="Ej: BASE-MDZ, SUC-BUE" required>
                @error('code')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-8 col-12">
                <label for="name" class="form-label fw-semibold">Nombre de la Sucursal / Base <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Ej: Base Operativa Mendoza" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6 col-12">
                <label for="city" class="form-label fw-semibold">Ciudad / Localidad</label>
                <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}" placeholder="Ej: Maipú">
                @error('city')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6 col-12">
                <label for="province" class="form-label fw-semibold">Provincia</label>
                <input type="text" name="province" id="province" class="form-control @error('province') is-invalid @enderror" value="{{ old('province') }}" placeholder="Ej: Mendoza">
                @error('province')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label for="address" class="form-label fw-semibold">Dirección</label>
                <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" placeholder="Ej: Ruta 7 Km 1020">
                @error('address')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6 col-12">
                <label for="phone" class="form-label fw-semibold">Teléfono de Contacto</label>
                <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="Ej: +54 261 4455667">
                @error('phone')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6 col-12">
                <label for="manager_name" class="form-label fw-semibold">Responsable de Base</label>
                <input type="text" name="manager_name" id="manager_name" class="form-control @error('manager_name') is-invalid @enderror" value="{{ old('manager_name') }}" placeholder="Ej: Ing. Roberto Méndez">
                @error('manager_name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold" for="is_active">Sucursal Activa</label>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
              <a href="{{ route('rrhh.branches.index') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Sucursal</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
