@extends('layouts.app')
@section('title','Asientos')

@section('content')
@php
  $rebuildScope = old('scope', 'both');
  $rebuildFrom = old('from', $from);
  $rebuildTo = old('to', $to);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h5 mb-0">Asientos</h1>
  <div class="d-flex flex-wrap gap-2">
    <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#ledgerRebuildModal">
      Regenerar asientos
    </button>
    <a class="btn btn-primary" href="{{ route('ledger.create') }}">Nuevo asiento</a>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form class="row g-2 mb-3 align-items-end" method="get">
  <div class="col-sm-3">
    <label class="form-label">Desde</label>
    <input type="date" class="form-control" name="from" value="{{ request('from', $from) }}">
  </div>
  <div class="col-sm-3">
    <label class="form-label">Hasta</label>
    <input type="date" class="form-control" name="to" value="{{ request('to', $to) }}">
  </div>
  <div class="col-sm-2 col-lg-1 d-grid">
    <label class="form-label opacity-0 d-none d-sm-block">Filtrar</label>
    <button class="btn btn-outline-secondary">Filtrar</button>
  </div>
</form>

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm mb-0">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Cuenta</th>
        <th>CC</th>
        <th>Descripcion</th>
        <th class="text-end">Debe</th>
        <th class="text-end">Haber</th>
      </tr>
    </thead>
    <tbody>
      @foreach($entries as $e)
        <tr>
          <td>{{ \Illuminate\Support\Carbon::parse($e->entry_date)->format('d/m/Y') }}</td>
          <td>{{ optional($e->account)->code }} {{ optional($e->account)->name }}</td>
          <td>{{ optional($e->costCenter)->code }}</td>
          <td>{{ $e->description }}</td>
          <td class="text-end">
            {{ $e->debit ? number_format($e->debit, 2, ',', '.') : '' }}
          </td>
          <td class="text-end">
            {{ $e->credit ? number_format($e->credit, 2, ',', '.') : '' }}
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="mt-2">{{ $entries->links() }}</div>

<div class="modal fade" id="ledgerRebuildModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="{{ route('ledger.rebuild') }}" data-confirm="Esta accion regenerara los asientos automaticos dentro del periodo seleccionado. Continuar?">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Regenerar asientos desde gestion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">
            Se eliminaran y volveran a crear los asientos generados automaticamente por los comprobantes del periodo. Los asientos manuales permanecen sin cambios.
          </p>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Desde</label>
              <input type="date" class="form-control" name="from" value="{{ $rebuildFrom }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Hasta</label>
              <input type="date" class="form-control" name="to" value="{{ $rebuildTo }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Ambito</label>
              <select class="form-select" name="scope">
                <option value="both" {{ $rebuildScope === 'both' ? 'selected' : '' }}>Compras y ventas</option>
                <option value="purchase" {{ $rebuildScope === 'purchase' ? 'selected' : '' }}>Solo compras</option>
                <option value="sale" {{ $rebuildScope === 'sale' ? 'selected' : '' }}>Solo ventas</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-warning">Regenerar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
