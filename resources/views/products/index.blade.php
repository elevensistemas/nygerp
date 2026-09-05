@extends('layouts.app')
@section('title','Productos')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h5 mb-0">Productos y servicios</h1>
  <div class="d-flex gap-2">
    <form method="get" action="{{ route('products.index') }}" class="row row-cols-auto g-2 align-items-center">
      <div class="col">
        <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar producto">
      </div>
      <div class="col">
        <select name="type" class="form-select form-select-sm">
          <option value="">Todos</option>
          <option value="goods" {{ $type === 'goods' ? 'selected' : '' }}>Bienes</option>
          <option value="service" {{ $type === 'service' ? 'selected' : '' }}>Servicios</option>
          <option value="other" {{ $type === 'other' ? 'selected' : '' }}>Otros</option>
        </select>
      </div>
      <div class="col">
        <button class="btn btn-sm btn-outline-secondary">Filtrar</button>
      </div>
    </form>
    <button class="btn btn-sm btn-primary" id="btnNewProduct" data-bs-toggle="modal" data-bs-target="#productModal">Nuevo producto</button>
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Codigo</th>
        <th>Nombre</th>
        <th>Tipo</th>
        <th>Unidad</th>
        <th class="text-end">Precio</th>
        <th class="text-end">IVA %</th>
        <th class="text-center">Activo</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($products as $product)
        <tr>
          <td>{{ $product->code }}</td>
          <td>{{ $product->name }}</td>
          <td>{{ ucfirst($product->type) }}</td>
          <td>{{ $product->unit }}</td>
          <td class="text-end">{{ number_format($product->default_price, 2, ',', '.') }}</td>
          <td class="text-end">{{ number_format($product->iva_rate, 2, ',', '.') }}</td>
          <td class="text-center">
            <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
              {{ $product->is_active ? 'Si' : 'No' }}
            </span>
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="{{ $product->id }}">Editar</button>
            <form method="post" action="{{ route('products.destroy', $product) }}" class="d-inline" data-confirm="Eliminar producto?">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted py-4">No hay productos cargados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $products->links() }}</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="{{ route('products.store') }}" id="productForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nuevo producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Codigo</label>
              <input type="text" name="code" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Nombre</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select name="type" class="form-select" required>
                <option value="goods">Bienes</option>
                <option value="service">Servicios</option>
                <option value="other">Otros</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Unidad</label>
              <input type="text" name="unit" class="form-control" value="unidad">
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio sugerido</label>
              <input type="number" name="default_price" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">IVA %</label>
              <input type="number" name="iva_rate" class="form-control" step="0.01" min="0" max="100" value="21">
            </div>
            <div class="col-md-6">
              <label class="form-label">Cuenta contable por defecto</label>
              <select name="default_account_id" class="form-select">
                <option value="">Sin asignar</option>
                @foreach($accounts as $account)
                  <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Centro de costo por defecto</label>
              <select name="default_cost_center_id" class="form-select">
                <option value="">Sin asignar</option>
                @foreach($centers as $center)
                  <option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Descripcion</label>
              <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 form-check mt-2">
              <input type="checkbox" class="form-check-input" id="productActive" name="is_active" value="1" checked>
              <label class="form-check-label" for="productActive">Activo</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('productModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('productForm');
  const titleEl = modalEl.querySelector('.modal-title');

  const setFormAction = (action, method = 'POST') => {
    form.action = action;
    let methodField = form.querySelector('input[name="_method"]');
    if (method === 'POST') {
      if (methodField) methodField.remove();
    } else {
      if (!methodField) {
        methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        form.prepend(methodField);
      }
      methodField.value = method;
    }
  };

  document.getElementById('btnNewProduct').addEventListener('click', () => {
    form.reset();
    form.querySelector('[name="unit"]').value = 'unidad';
    form.querySelector('[name="iva_rate"]').value = '21';
    form.querySelector('#productActive').checked = true;
    setFormAction("{{ route('products.store') }}", 'POST');
    titleEl.textContent = 'Nuevo producto';
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`/products/${id}/edit`)
        .then(resp => resp.json())
        .then(data => {
          form.reset();
          setFormAction(`/products/${id}`, 'PUT');
          titleEl.textContent = 'Editar producto';
          Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (!input) return;
            if (input.type === 'checkbox') {
              input.checked = Boolean(data[key]);
            } else {
              input.value = data[key] ?? '';
            }
          });
          modal.show();
        });
    });
  });
});
</script>
@endpush
