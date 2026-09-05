@extends('layouts.app')
@section('title','Nuevo asiento')
@section('content')
<h1 class="h5 mb-3">Nuevo asiento</h1>

@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="post" action="{{ route('ledger.store') }}">
  @csrf
  <div class="row g-2 mb-3">
    <div class="col-sm-3">
      <label class="form-label">Fecha</label>
      <input type="date" class="form-control" name="entry_date" value="{{ $today }}">
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle" id="rows">
      <thead class="table-light">
        <tr>
          <th>Cuenta</th><th>CC</th><th>Descripción</th>
          <th style="width:120px" class="text-end">Debe</th>
          <th style="width:120px" class="text-end">Haber</th>
          <th style="width:48px"></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr><td colspan="6">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="add">Agregar renglón</button>
        </td></tr>
      </tfoot>
    </table>
  </div>

  <div class="text-end">
    <button class="btn btn-primary">Guardar</button>
  </div>
</form>
@endsection

@push('scripts')
<script>
const accounts = @json($accounts);
const centers = @json($centers);
function row(){
  return `<tr>
    <td><select name="rows[][account_id]" class="form-select form-select-sm">
      ${accounts.map(a=>`<option value="${a.id}">${a.code} - ${a.name}</option>`).join('')}
    </select></td>
    <td><select name="rows[][cost_center_id]" class="form-select form-select-sm">
      <option value="">—</option>${centers.map(c=>`<option value="${c.id}">${c.code}</option>`).join('')}
    </select></td>
    <td><input class="form-control form-control-sm" name="rows[][description]" placeholder="Descripción"/></td>
    <td><input class="form-control form-control-sm text-end" name="rows[][debit]" type="number" step="0.01" value="0"></td>
    <td><input class="form-control form-control-sm text-end" name="rows[][credit]" type="number" step="0.01" value="0"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger del">×</button></td>
  </tr>`;
}
document.getElementById('add').addEventListener('click',()=> {
  document.querySelector('#rows tbody').insertAdjacentHTML('beforeend', row());
});
document.querySelector('#rows').addEventListener('click', e=>{
  if(e.target.classList.contains('del')) e.target.closest('tr').remove();
});
document.getElementById('add').click(); document.getElementById('add').click();
</script>
@endpush
