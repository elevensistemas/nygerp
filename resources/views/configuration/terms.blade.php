@extends('layouts.app')
@section('title','Términos y condiciones')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h5 mb-0">Términos y condiciones</h1>
    <p class="text-muted mb-0">Redactá aquí los términos, la política de privacidad y cualquier otro aviso legal que los nuevos usuarios deben aceptar.</p>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<form method="post" action="{{ route('terms.update') }}">
  @csrf
  @method('PUT')
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Contenido</label>
        <textarea name="content" rows="12" class="form-control font-monospace">{{ old('content', $term->content) }}</textarea>
      </div>
      <button class="btn btn-primary">Guardar</button>
    </div>
  </div>
</form>
@endsection
