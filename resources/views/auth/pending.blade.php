@php $hideSidebar = true; @endphp
@extends('layouts.app')

@section('title', 'Usuario pendiente de confirmación')
@section('content')
<div class="text-center py-5">
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  <h1 class="display-5 mb-3">Usuario pendiente de confirmación</h1>
  <p class="lead">Tu cuenta fue creada, pero aún necesitas confirmar los términos y condiciones que te enviamos por correo.</p>
  <p>Revisa tu correo y pulsa el botón <strong>Acepto</strong>. Si no encontrás el email, pediles a los administradores que te reenvíen la invitación.</p>
  <p class="text-muted small">Mientras tanto, no tendrás acceso al resto del menú.</p>
</div>
@endsection
