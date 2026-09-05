@extends('layouts.app')

@section('title', 'Planillas de Pago')

@section('content')
<div class="page-header">
  <div class="title-block">
    <h1 class="h3 mb-1">Planillas de Pago a Choferes</h1>
  </div>
  <div class="page-actions d-flex gap-2">
    @if(auth()->user() && auth()->user()->isAdminOrSuper())
      <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#testSantanderModal">
        Simulador Santander
      </button>
    @endif
    <a class="btn btn-primary" href="{{ route('pago-choferes.planillas.create') }}">Crear planilla</a>
  </div>
</div>

<form method="GET" class="card card-body mb-3">
  <div class="row g-2">
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" value="{{ request('desde') }}" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Transportista</label>
      <select name="transportista_id" class="form-select">
        <option value="">Todos</option>
        @foreach($transportistas as $transportista)
          <option value="{{ $transportista->id }}" {{ request('transportista_id') == $transportista->id ? 'selected' : '' }}>
            {{ $transportista->name }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        @foreach($estados as $value => $label)
          <option value="{{ $value }}" {{ request('estado') === $value ? 'selected' : '' }}>
            {{ $label }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-outline-primary">Aplicar</button>
    <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.planillas.index') }}">Limpiar</a>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Numero</th>
          <th>Fecha</th>
          <th>Estado</th>
          <th class="text-end">Total</th>
          <th>Recibos</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
      @forelse($planillas as $planilla)
        <tr>
          <td>{{ $planilla->numero }}</td>
          <td>{{ optional($planilla->fecha)->format('d/m/Y') }}</td>
          <td><span class="badge text-bg-light">{{ $planilla->estado }}</span></td>
          <td class="text-end">$ {{ number_format((float) $planilla->total, 2, ',', '.') }}</td>
          <td>{{ $planilla->recibo_links_count }}</td>
          <td>
            <div class="d-flex gap-1">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('pago-choferes.planillas.show', $planilla) }}">Ver</a>
              @can('delete', $planilla)
              <form method="POST" action="{{ route('pago-choferes.planillas.destroy', $planilla) }}" class="m-0" data-confirm="Si elimina la planilla, todos los pagos y recibos incluidos también regresarán al estado cargado. ¿Desea continuar?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
              @endcan
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted">Sin planillas.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body">{{ $planillas->links() }}</div>
</div>

@if(auth()->user() && auth()->user()->isAdminOrSuper())
<div class="modal fade" id="testSantanderModal" tabindex="-1" aria-labelledby="testSantanderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('pago-choferes.planillas.export-santander-sandbox') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="testSantanderModalLabel">Simulador de Exportación Santander (Prueba)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">Este simulador permite generar un archivo de exportación de prueba (formato FUR) ingresando valores manuales de prueba, ideal para verificar la estructura CUIT/CBU con el banco sin afectar registros reales de choferes.</p>
          
          <h6 class="border-bottom pb-2 mb-3 text-primary">1. Datos de Cabecera (Empresa)</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label">CUIT de la Empresa *</label>
              <input type="text" name="company_cuit" class="form-control form-control-sm" value="{{ \App\Models\SystemParameter::value('company_cuit', '30123456789') }}" required placeholder="11 dígitos">
            </div>
            <div class="col-md-6">
              <label class="form-label">Número de Acuerdo Santander *</label>
              <input type="text" name="agreement_number" class="form-control form-control-sm" value="{{ \App\Models\SystemParameter::value('santander_agreement_number', '01') }}" required placeholder="1 o 2 dígitos">
            </div>
          </div>

          <h6 class="border-bottom pb-2 mb-3 text-primary">2. Datos de Detalle (Beneficiario / Chofer Prueba)</h6>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">ID / Código de Beneficiario *</label>
              <input type="text" name="beneficiary_id" class="form-control form-control-sm" value="999" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Período Abonado (AAAAMM) *</label>
              <input type="text" name="period" class="form-control form-control-sm" value="{{ date('Ym') }}" required placeholder="Ej: 202607">
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de Pago *</label>
              <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre del Beneficiario *</label>
              <input type="text" name="beneficiary_name" class="form-control form-control-sm" value="Ejemplo" required placeholder="Ej: EjemploO">
            </div>
            <div class="col-md-6">
              <label class="form-label">CUIT del Beneficiario *</label>
              <input type="text" name="beneficiary_cuit" class="form-control form-control-sm" required placeholder="11 dígitos sin guiones">
            </div>
            <div class="col-md-6">
              <label class="form-label">CBU del Beneficiario (22 dígitos) *</label>
              <input type="text" name="beneficiary_cbu" class="form-control form-control-sm" required placeholder="22 dígitos sin espacios ni guiones">
            </div>
            <div class="col-md-6">
              <label class="form-label">Monto a Pagar ($) *</label>
              <input type="number" step="0.01" name="amount" class="form-control form-control-sm" value="10.00" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary">Generar TXT Prueba</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection
