@extends('layouts.app')
@section('title', $title)

@section('content')
<h1 class="h5 mb-3">{{ $title }}</h1>

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Nombre</th>
        <th>CUIT / DNI</th>
        <th>Email</th>
        <th class="text-end" style="width:160px;">Cuenta Corriente</th>
      </tr>
    </thead>
    <tbody>
      @foreach($parties as $p)
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ $p->tax_id }}</td>
          <td>{{ $p->email }}</td>
          <td class="text-end">
            <a href="{{ route('cc.show', ['role'=>$role, 'party'=>$p->id]) }}" class="btn btn-sm btn-outline-primary">
              Ver movimientos
            </a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="mt-2">{{ $parties->links() }}</div>
@endsection
