@extends('layouts.app')

@section('title', 'Resultado de Importacion')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Resultado importacion #{{ $run->id }}</h1>
    <p class="text-muted mb-0">Archivo: {{ $run->source_file }}</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.import.create') }}">Nueva importacion</a>
    <a class="btn btn-outline-warning" href="{{ route('pago-choferes.import.errors', $run) }}">Descargar errores</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card card-body"><strong>Procesadas</strong><span>{{ $run->rows_processed }}</span></div></div>
  <div class="col-md-3"><div class="card card-body"><strong>Creadas</strong><span>{{ $run->rows_created }}</span></div></div>
  <div class="col-md-3"><div class="card card-body"><strong>Actualizadas</strong><span>{{ $run->rows_updated }}</span></div></div>
  <div class="col-md-3"><div class="card card-body"><strong>Con error</strong><span>{{ $run->rows_with_errors }}</span></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
      <tr>
        <th>Sheet</th>
        <th>Fila</th>
        <th>Estado</th>
        <th>Mensaje</th>
      </tr>
      </thead>
      <tbody>
      @foreach($rows as $row)
        <tr>
          <td>{{ $row->sheet_name }}</td>
          <td>{{ $row->row_number }}</td>
          <td><span class="badge text-bg-light">{{ $row->status }}</span></td>
          <td>{{ $row->message }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <div class="card-body">{{ $rows->links() }}</div>
</div>
@endsection
