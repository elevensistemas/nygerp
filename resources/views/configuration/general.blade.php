@extends('layouts.app')

@section('title', 'Configuración general')

@section('content')
  <div class="page-header">
    <div class="title-block">
      <h1 class="h3 mb-1">Parámetros generales</h1>
      <p class="mb-0">Controla el comportamiento global del sistema.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary" href="{{ url()->previous() }}">Volver</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="content-card p-3">
        <h2 class="h5 mb-3">Parámetros activos</h2>
        @forelse($parameters as $parameter)
          <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <strong>{{ $parameter->label }}</strong>
                <div class="text-muted small">{{ $parameter->key }}</div>
              </div>
              <form method="POST" action="{{ route('config.parameters.update', $parameter) }}" class="d-flex align-items-center gap-2">
                @csrf
                @method('PUT')
                @if(in_array($parameter->key, ['company_cuit', 'santander_agreement_number']))
                  <div class="m-0">
                    <input class="form-control form-control-sm" type="text" name="value" id="parameter_{{ $parameter->key }}" value="{{ $parameter->value }}" style="width: 140px;" placeholder="Valor...">
                  </div>
                @else
                  <input type="hidden" name="value" value="0">
                  <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" name="value" id="parameter_{{ $parameter->key }}" value="1"
                      {{ $parameter->value === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="parameter_{{ $parameter->key }}">{{ $parameter->value === '1' ? 'Activado' : 'Desactivado' }}</label>
                  </div>
                @endif
                <button class="btn btn-sm btn-primary" type="submit">Guardar</button>
              </form>
            </div>
            <p class="small text-muted mb-0">{{ $parameter->description }}</p>
          </div>
        @empty
          <p class="text-muted">No se definieron parámetros.</p>
        @endforelse
      </div>

      <div class="content-card p-3 mb-3">
        <h2 class="h5 mb-2"><i class="fa-solid fa-user-plus text-primary me-2"></i>Configuración de Choferes Nuevos</h2>
        <p class="small text-muted mb-3">Define la cantidad de días para considerar a un chofer como nuevo y el color destacado para pintarlo en los listados y planillas.</p>
        <form method="POST" action="{{ route('pago-choferes.settings.new-drivers.store') }}">
          @csrf
          <div class="row g-3 align-items-end">
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Días para ser considerado nuevo</label>
              <input type="number" name="new_driver_days" class="form-control form-control-sm" min="0" value="{{ optional($newDriverDays)->setting_value ?? 30 }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Color de resaltado</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="new_driver_color" class="form-control form-control-color form-control-sm" style="width: 45px; min-width: 45px; height: 31px; padding: 2px;" value="{{ optional($newDriverColor)->setting_value ?? '#28a745' }}" required>
                <input type="text" class="form-control form-control-sm" value="{{ optional($newDriverColor)->setting_value ?? '#28a745' }}" readonly style="max-width: 90px;">
              </div>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-sm btn-primary" type="submit">Guardar parámetros choferes</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="content-card p-3">
        <h2 class="h5 mb-3">Historial de cambios</h2>
        <div class="table-responsive" style="max-height: 520px;">
          <table class="table table-striped table-hover mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Parámetro</th>
                <th>Antes</th>
                <th>Después</th>
              </tr>
            </thead>
            <tbody>
              @forelse($logs as $log)
                <tr>
                  <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $log->user->name ?? 'Sistema' }}</td>
                  <td>{{ $log->parameter->label }}</td>
                  <td>{{ $log->old_value ?? '—' }}</td>
                  <td>{{ $log->new_value ?? '—' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center text-muted">No hay registros todavía.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
