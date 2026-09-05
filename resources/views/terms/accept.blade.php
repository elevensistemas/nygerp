@php $hideSidebar = true; @endphp
@extends('layouts.app')

@section('title', 'Aceptar términos')

@section('content')
  <div class="d-flex justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div>
            <h1 class="h5 mb-0">Términos y condiciones</h1>
            <p class="text-muted small mb-0">Leélos y confirmá para continuar.</p>
          </div>
        </div>
        <form method="POST" action="{{ route('terms.accept.store', $token) }}">
          @csrf
          <div class="card-body">
            @if($errors->any())
              <div class="alert alert-danger py-2 mb-3">{{ $errors->first() }}</div>
            @endif
            <div class="border rounded p-3 mb-3" style="max-height: 320px; overflow-y: auto; background: #f8f9fa;">
              {!! nl2br(e($term->content)) !!}
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="acceptTerms" name="accept_terms" value="1" required>
              <label class="form-check-label" for="acceptTerms">He leído y acepto los términos y condiciones.</label>
            </div>
          </div>
          <div class="card-footer d-flex justify-content-end gap-2 bg-white">
            <a href="{{ route('login') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Continuar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
