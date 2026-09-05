@extends('layouts.app')
@section('title','Confirmaciones pendientes')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h5 mb-0">Confirmaciones pendientes</h1>
    <p class="text-muted mb-0">Usuarios que recibieron el correo pero todavía no aceptaron.</p>
  </div>
  <a href="{{ route('terms.edit') }}" class="btn btn-sm btn-outline-secondary">Editar términos</a>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Nombre</th>
        <th>Email</th>
        <th>Enviado</th>
        <th>Creado</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($pending as $user)
        <tr>
          <td>{{ $user->name }}</td>
          <td>{{ $user->email }}</td>
          <td>{{ optional($user->confirmation_sent_at)->format('d/m/Y H:i') ?? 'Sin envíos' }}</td>
          <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
          <td class="text-end">
            <form method="post" action="{{ route('confirmations.resend', $user) }}" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-outline-primary">Reenviar correo</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No hay usuarios pendientes.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">
  {{ $pending->links() }}
</div>
@endsection
