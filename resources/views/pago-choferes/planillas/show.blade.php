@extends('layouts.app')

@section('title', 'Detalle Planilla')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Planilla {{ $planilla->numero }}</h1>
      <p class="text-muted mb-0">Estado: {{ $planilla->estado }} | Total: $
        {{ number_format((float) $planilla->total, 2, ',', '.') }}</p>
    </div>
    <div class="page-actions d-flex align-items-center gap-2">
      <a href="{{ asset('manuales/instructivo_pago_choferes.pdf') }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center justify-content-center shadow-sm" title="¿Cómo usar? Ver instructivo (PDF)" style="width: 38px; height: 38px; border-radius: 50%;">
        <i class="fa-solid fa-circle-question fs-5"></i>
      </a>
      <a class="btn btn-outline-secondary" href="{{ route('pago-choferes.planillas.index') }}">Volver</a>
      <a class="btn btn-outline-primary" target="_blank"
        href="{{ route('pago-choferes.planillas.print', $planilla) }}">Imprimir</a>
      {{-- <a class="btn btn-outline-success" href="{{ route('pago-choferes.planillas.export-bank', $planilla) }}">Export
        banco</a> --}}
      <a class="btn btn-outline-info" href="{{ route('pago-choferes.planillas.export-santander', $planilla) }}">Exportar
        Santander TXT</a>
      @if(auth()->user() && auth()->user()->isAdminOrSuper())
        <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#testSantanderModal">
          Simulador Santander
        </button>
      @endif
      @can('conciliate', $planilla)
        <form method="POST" action="{{ route('pago-choferes.planillas.todos-pagados', $planilla) }}" id="todosPagadosForm"
          class="m-0">
          @csrf
          <input type="hidden" name="close" id="todosPagadosCloseVal" value="1">
          <button type="button" id="btnTodosPagados" class="btn btn-outline-success">Todos pagados</button>
        </form>
        @if(in_array($planilla->estado, [\App\Models\PlanillaPagoChofer::ESTADO_CONFIRMADA, \App\Models\PlanillaPagoChofer::ESTADO_PAGADA_PARCIAL]))
          <form method="POST" action="{{ route('pago-choferes.planillas.close', $planilla) }}" class="m-0"
            data-confirm="¿Desea cerrar la planilla? Esta acción no se puede deshacer.">
            @csrf
            <button type="submit" class="btn btn-outline-warning">Cerrar planilla</button>
          </form>
        @endif
      @endcan
      @can('delete', $planilla)
        <form method="POST" action="{{ route('pago-choferes.planillas.destroy', $planilla) }}"
          data-confirm="Si elimina la planilla, todos los pagos y recibos incluidos también regresarán al estado cargado. ¿Desea continuar?">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-outline-danger">Eliminar planilla</button>
        </form>
      @endcan
      @if($planilla->estado !== \App\Models\PlanillaPagoChofer::ESTADO_CERRADA)
        <form method="POST" action="{{ route('pago-choferes.planillas.close', $planilla) }}"
          data-confirm="¿Desea cerrar esta planilla? Una vez cerrada no se podrá modificar ni editar.">
          @csrf
          <button type="submit" class="btn btn-warning">Cerrar planilla</button>
        </form>
      @endif
    </div>
  </div>

  @if(session('warnings'))
    <div class="alert alert-warning">
      @foreach((array) session('warnings') as $warning)
        <div>{{ $warning }}</div>
      @endforeach
    </div>
  @endif

  @if($planilla->estado !== \App\Models\PlanillaPagoChofer::ESTADO_CERRADA)
    <div class="card card-body mb-3">
      <h2 class="h5">Importar resultado banco</h2>
      <form method="POST" enctype="multipart/form-data"
        action="{{ route('pago-choferes.planillas.import-bank', $planilla) }}" class="row g-2">
        @csrf
        <div class="col-md-8"><input type="file" class="form-control" name="file" required></div>
        <div class="col-md-4"><button class="btn btn-outline-primary w-100">Importar conciliacion</button></div>
      </form>
    </div>
  @else
    <div class="alert alert-secondary mb-3">
      <strong>Planilla cerrada.</strong> La planilla ya ha sido cerrada y no admite modificaciones ni cargas de archivos.
    </div>
  @endif

  <div class="card">
    <div class="card-body border-bottom">
      <form method="GET" class="d-flex align-items-center gap-2">
        <label for="perPage" class="form-label mb-0">Registros por pagina</label>
        <select id="perPage" name="per_page" class="form-select form-select-sm" style="width: auto;"
          onchange="this.form.submit()">
          @foreach($perPageOptions as $option)
            <option value="{{ $option }}" {{ (int) $perPage === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
          @endforeach
        </select>
        <span class="text-muted small">Mostrando {{ $reciboLinks->firstItem() ?: 0 }} a
          {{ $reciboLinks->lastItem() ?: 0 }} de {{ $reciboLinks->total() }} registros.</span>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Recibo</th>
            <th>Chofer</th>
            <th>Periodo</th>
            <th>CBU/CVU</th>
            <th class="text-end">Monto</th>
            <th>Estado pago</th>
            <th>Actualizacion manual</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reciboLinks as $link)
            @php
              $recibo = $link->recibo;
              $transportista = optional($recibo)->transportista;
              $defaultMethod = $transportista ? $transportista->defaultPaymentMethod : null;
              $paymentCbu = trim((string) ($defaultMethod ? $defaultMethod->cbu : ($transportista ? $transportista->cbu : '')));
              $missingCbu = $paymentCbu === '';
              $isNew = $transportista && $transportista->isNewDriver();
            @endphp
            <tr class="{{ $missingCbu ? 'table-danger' : ($isNew ? 'new-driver-row' : '') }}" @if($missingCbu) style="opacity: .62;" @endif>
              <td>#{{ $link->recibo_chofer_id }}</td>
              <td>
                <div>
                  {{ $link->recibo ? $link->recibo->displayName() : '-' }}
                  @if($isNew)
                    <span class="new-driver-badge" title="Nuevo chofer ({{ $transportista->days_since_hired }} días dado de alta)"><i class="fa-solid fa-user-plus"></i> Nuevo ({{ $transportista->days_since_hired }} d)</span>
                  @endif
                </div>
                @if($missingCbu)
                  <small class="text-danger">Sin CBU/CVU default</small>
                @endif
              </td>
              <td>{{ optional(optional($link->recibo)->periodo_desde)->format('d/m/Y') }} -
                {{ optional(optional($link->recibo)->periodo_hasta)->format('d/m/Y') }}</td>
              <td>
                @if($paymentCbu !== '')
                  <span class="font-monospace">{{ $paymentCbu }}</span>
                  @if($defaultMethod && $defaultMethod->bank)
                    <div class="text-muted small">{{ $defaultMethod->bank->name }}</div>
                  @endif
                  @if($defaultMethod && !empty($defaultMethod->tags))
                    <div class="mt-1">
                      @foreach((array) $defaultMethod->tags as $tag)
                        <span class="badge bg-secondary">{{ $tag }}</span>
                      @endforeach
                    </div>
                  @endif
                @else
                  <span class="text-danger">Sin CBU/CVU</span>
                @endif
              </td>
              <td class="text-end">$ {{ number_format((float) $link->monto_en_planilla, 2, ',', '.') }}</td>
              <td><span class="badge text-bg-light">{{ $link->estado_pago }}</span></td>
              <td>
                @if($planilla->estado !== \App\Models\PlanillaPagoChofer::ESTADO_CERRADA)
                  <form method="POST" action="{{ route('pago-choferes.planillas.manual-result', $link) }}"
                    class="d-flex gap-2">
                    @csrf
                    <select name="resultado" class="form-select form-select-sm">
                      <option value="">Sin resultado</option>
                      @foreach($paymentTypes as $paymentType)
                        <option value="{{ $paymentType->id }}" {{ (int) $link->driver_payment_type_id === (int) $paymentType->id ? 'selected' : '' }}>
                          {{ $paymentType->description }}
                        </option>
                      @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">Guardar</button>
                  </form>
                @else
                  <span class="text-muted small">Planilla cerrada</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No hay recibos asociados en esta planilla.</td>
            </tr>
          @endforelse
        </tbody>
        <tfoot>
          <tr class="table-warning">
            <th colspan="4" class="text-end">TOTAL GENERAL DE TODA LA TABLA</th>
            <th class="text-end">$ {{ number_format((float) $planilla->total, 2, ',', '.') }}</th>
            <th colspan="2" class="text-muted small"></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="card-body border-top d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        {{ $reciboLinks->links() }}
      </div>
      <div class="text-end">
        <div class="text-muted small">Total general de la suma de la tabla</div>
        <div class="fs-4 fw-bold">$ {{ number_format((float) $planilla->total, 2, ',', '.') }}</div>
      </div>
    </div>
  </div>

  @if(auth()->user() && auth()->user()->isAdminOrSuper())
    <div class="modal fade" id="testSantanderModal" tabindex="-1" aria-labelledby="testSantanderModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST" action="{{ route('pago-choferes.planillas.export-santander-sandbox') }}">
            @csrf
            <div class="modal-header">
              <h5 class="modal-title" id="testSantanderModalLabel">Simulador de Exportación Santander (Prueba)</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small">Este simulador permite generar un archivo de exportación de prueba (formato FUR)
                ingresando valores manuales de prueba, ideal para verificar la estructura CUIT/CBU con el banco sin afectar
                registros reales de choferes.</p>

              <h6 class="border-bottom pb-2 mb-3 text-primary">1. Datos de Cabecera (Empresa)</h6>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label">CUIT de la Empresa *</label>
                  <input type="text" name="company_cuit" class="form-control form-control-sm"
                    value="{{ \App\Models\SystemParameter::value('company_cuit', '30123456789') }}" required
                    placeholder="11 dígitos">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Número de Acuerdo Santander *</label>
                  <input type="text" name="agreement_number" class="form-control form-control-sm"
                    value="{{ \App\Models\SystemParameter::value('santander_agreement_number', '01') }}" required
                    placeholder="1 o 2 dígitos">
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
                  <input type="text" name="period" class="form-control form-control-sm" value="{{ date('Ym') }}" required
                    placeholder="Ej: 202607">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Fecha de Pago *</label>
                  <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}"
                    required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Nombre del Beneficiario *</label>
                  <input type="text" name="beneficiary_name" class="form-control form-control-sm" value="EjemploO" required
                    placeholder="Ej: EjemploO">
                </div>
                <div class="col-md-6">
                  <label class="form-label">CUIT del Beneficiario *</label>
                  <input type="text" name="beneficiary_cuit" class="form-control form-control-sm" required
                    placeholder="11 dígitos sin guiones">
                </div>
                <div class="col-md-6">
                  <label class="form-label">CBU del Beneficiario (22 dígitos) *</label>
                  <input type="text" name="beneficiary_cbu" class="form-control form-control-sm" required
                    placeholder="22 dígitos sin espacios ni guiones">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Monto a Pagar ($) *</label>
                  <input type="number" step="0.01" name="amount" class="form-control form-control-sm" value="10.00"
                    required>
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

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btnTodosPagados');
        const form = document.getElementById('todosPagadosForm');
        const closeInput = document.getElementById('todosPagadosCloseVal');

        if (btn && form) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
              title: '¿Marcar todos como pagados?',
              text: '¿Deseas cerrar la planilla al finalizar o mantenerla abierta?',
              icon: 'question',
              showCancelButton: true,
              showDenyButton: true,
              confirmButtonText: 'Sí, Cerrar planilla',
              denyButtonText: 'Mantener abierta',
              cancelButtonText: 'Cancelar',
              confirmButtonColor: '#198754',
              denyButtonColor: '#0dcaf0',
            }).then((result) => {
              if (result.isConfirmed) {
                closeInput.value = '1';
                form.submit();
              } else if (result.isDenied) {
                closeInput.value = '0';
                form.submit();
              }
            });
          });
        }
      });
    </script>
  @endpush
@endsection