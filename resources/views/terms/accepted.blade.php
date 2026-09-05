@php $hideSidebar = true; @endphp
@extends('layouts.app')

@section('title', 'Confirmación recibida')
@section('content')
<div class="text-center py-5">
  <h1 class="display-6 mb-3">{{ $message ?? 'Gracias por aceptar los terminos.' }}</h1>
  <p class="text-muted">Ya podes volver a la aplicación y acceder según el rol asignado.</p>
  @if(!isset($showLoginLink) || $showLoginLink)
    <a href="{{ route('login') }}" class="btn btn-primary mt-3">Ir al ingreso</a>
  @endif
</div>
@endsection
