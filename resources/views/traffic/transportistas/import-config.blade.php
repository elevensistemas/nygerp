@extends('layouts.app')

@section('title', 'Importación choferes')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Importación choferes</h1>
      <p class="text-muted mb-0">Define desde qué columna y fila se toma cada campo del Excel.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ route('traffic.transportistas.index') }}">
        <i class="fa-solid fa-arrow-left me-1"></i> Volver a transportistas
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('traffic.transportistas.import-config.store') }}">
    @csrf
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label">Nombre interno (opcional)</label>
            <input class="form-control" type="text" name="name" value="{{ old('name', $format->name ?? '') }}" placeholder="Ej: Formato choferes">
          </div>
          <div class="col-md-3">
            <label class="form-label">Hoja</label>
            <input class="form-control" type="text" name="sheet" value="{{ old('sheet', $format->sheet ?? '') }}" placeholder="Nombre o índice">
          </div>
          <div class="col-md-3">
            <label class="form-label">Fila inicio</label>
            <input class="form-control" type="number" name="start_row" min="1" value="{{ old('start_row', $format->start_row ?? 2) }}">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>Campo</th>
                <th style="width: 180px;">Columna</th>
              </tr>
            </thead>
            <tbody>
              @foreach($fieldLabels as $fieldKey => $fieldLabel)
                <tr>
                  <td>{{ $fieldLabel }}</td>
                  <td>
                    <input class="form-control form-control-sm" type="text"
                           name="fields[{{ $fieldKey }}][column]"
                           value="{{ old("fields.{$fieldKey}.column", $format->field_mappings[$fieldKey]['column'] ?? '') }}"
                           placeholder="Ej: A">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="mt-3 d-flex justify-content-end">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar configuración
          </button>
        </div>
      </div>
    </div>
  </form>
@endsection
