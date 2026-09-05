@extends('layouts.app')

@section('title', 'Motor de Reglas de Liquidación')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fa-solid fa-cogs text-primary me-2"></i> Motor de Reglas de Liquidación
            </h1>
            
        </div>
    </div>

    <!-- The Vue Application Root -->
    <style>
        [v-cloak] { display: none !important; }
    </style>
    <div id="rule-engine-app" v-cloak>
  <div>
    <!-- TOP NAVBAR -->
    <div class="d-flex justify-content-between mb-3 bg-white p-3 border rounded shadow-sm">
      <div>
        <button class="btn btn-outline-primary me-2" @click="currentView = 'list'" :class="{'active': currentView === 'list'}">
          <i class="fa fa-list"></i> Listado de Reglas
        </button>
        <button class="btn btn-outline-secondary me-2" @click="currentView = 'maintenance'" :class="{'active': currentView === 'maintenance'}">
          <i class="fa fa-wrench"></i> Mantenimiento General
        </button>
        <button class="btn btn-primary me-2" @click="createNewRule">
          <i class="fa fa-plus"></i> Nueva Regla
        </button>
      </div>
      <div>
        <button class="btn btn-warning" @click="openSimulator(null)">
          <i class="fa fa-flask"></i> Simulador Engine Completo
        </button>
      </div>
    </div>

    <div v-if="currentView === 'maintenance'" class="card shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        
      </div>
      <div class="card-body">
        <div class="border rounded p-3 bg-light-subtle">
          <div class="border rounded p-3 bg-white mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
              <div>
                <h6 class="fw-bold mb-1">Periodo de Liquidación por Defecto</h6>
                <div class="small text-muted">Aplica cuando un transportista no tiene configurado un periodo específico en su ficha.</div>
              </div>
            </div>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <select class="form-select" v-model="defaultPeriodType">
                  <option value="quincenal">Quincenal (2 recibos por mes)</option>
                  <option value="mensual">Mensual (1 recibo por mes)</option>
                </select>
              </div>
              <div class="col-md-3">
                <button class="btn btn-primary w-100" @click="submitDefaultPeriod" :disabled="savingDefaultPeriod">
                  <i class="fa fa-save me-1"></i> @{{ savingDefaultPeriod ? 'Guardando...' : 'Guardar periodo' }}
                </button>
              </div>
            </div>
          </div>

          <div class="border rounded p-3 bg-white mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
              <div>
                <h6 class="fw-bold mb-1">Alias de Tipos de Vehículo</h6>
                <div class="small text-muted">Configura los nombres y etiquetas visibles para cada tipo de vehículo en las planillas, recibos y CRUD de transportes.</div>
              </div>
            </div>
            <div class="row g-3 align-items-end">
              <div class="col-md-2">
                <label class="form-label mb-1 fw-semibold small text-muted">Moto</label>
                <input type="text" class="form-control form-control-sm" v-model="vehicleAliases.moto" required>
              </div>
              <div class="col-md-2">
                <label class="form-label mb-1 fw-semibold small text-muted">Camioneta</label>
                <input type="text" class="form-control form-control-sm" v-model="vehicleAliases.camioneta" required>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1 fw-semibold small text-muted">Camioneta mediana</label>
                <input type="text" class="form-control form-control-sm" v-model="vehicleAliases.camioneta_mediana" required>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1 fw-semibold small text-muted">Camioneta grande</label>
                <input type="text" class="form-control form-control-sm" v-model="vehicleAliases.camioneta_grande" required>
              </div>
              <div class="col-md-2">
                <label class="form-label mb-1 fw-semibold small text-muted">General (Otros)</label>
                <input type="text" class="form-control form-control-sm" v-model="vehicleAliases.general" required>
              </div>
              <div class="col-12 mt-2">
                <button class="btn btn-primary btn-sm px-3" @click="submitVehicleAliases" :disabled="savingVehicleAliases">
                  <i class="fa fa-save me-1"></i> @{{ savingVehicleAliases ? 'Guardando...' : 'Guardar alias' }}
                </button>
              </div>
            </div>
          </div>

          <div class="border rounded p-3 bg-white mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
              <div>
                <h6 class="fw-bold mb-1">Agrupacion de choferes</h6>
                <div class="small text-muted">Relaciona varios choferes para liquidarlos en un solo recibo a nombre del chofer de cobro.</div>
              </div>
              <span class="badge bg-secondary">@{{ fleets.length }} grupo(s)</span>
            </div>

            <div class="row g-2 align-items-end mb-3">
              <div class="col-md-3">
                <label class="form-label">Nombre del grupo</label>
                <input type="text" class="form-control" v-model.trim="fleetForm.name" placeholder="Ej. Flota Norte">
              </div>
              <div class="col-md-3">
                <label class="form-label">Chofer de cobro</label>
                <select class="form-select" v-model="fleetForm.billing_transportista_id">
                  <option value="">Seleccionar...</option>
                  <option v-for="carrier in transportistaOptions" :key="carrier.id" :value="carrier.id">@{{ carrier.name }}</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Activo</label>
                <select class="form-select" v-model="fleetForm.active">
                  <option :value="true">Si</option>
                  <option :value="false">No</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Choferes incluidos</label>
                <select class="form-select" v-model="fleetForm.transportista_ids" multiple size="6">
                  <option v-for="carrier in transportistaOptions" :key="carrier.id" :value="carrier.id">@{{ carrier.name }}</option>
                </select>
                <div class="small text-muted mt-1">Ctrl/Cmd para seleccionar varios.</div>
              </div>
            </div>

            <div class="d-flex gap-2 mb-3">
              <button class="btn btn-primary" type="button" @click="submitFleet" :disabled="savingFleet">
                <i class="fa fa-users me-1"></i> @{{ savingFleet ? 'Guardando...' : (fleetForm.id ? 'Guardar grupo' : 'Crear grupo') }}
              </button>
              <button v-if="fleetForm.id" class="btn btn-outline-secondary" type="button" @click="resetFleetForm">
                Cancelar edicion
              </button>
            </div>

            <div class="table-responsive border rounded">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Grupo</th>
                    <th>Chofer de cobro</th>
                    <th>Integrantes</th>
                    <th>Estado</th>
                    <th class="text-end">Accion</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="fleets.length === 0">
                    <td colspan="5" class="text-center text-muted py-4">Todavia no hay agrupaciones cargadas.</td>
                  </tr>
                  <tr v-for="fleet in fleets" :key="fleet.id">
                    <td class="fw-semibold">@{{ fleet.name }}</td>
                    <td>@{{ fleet.billing_transportista_name || '-' }}</td>
                    <td>
                      <span v-if="fleet.transportistas && fleet.transportistas.length">@{{ fleet.transportistas.map(item => item.name).join(', ') }}</span>
                      <span v-else class="text-muted">Sin choferes</span>
                    </td>
                    <td>
                      <span class="badge" :class="fleet.active ? 'bg-success' : 'bg-secondary'">@{{ fleet.active ? 'Activo' : 'Inactivo' }}</span>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-secondary me-1" type="button" @click="editFleet(fleet)">
                        <i class="fa fa-pen-to-square me-1"></i> Editar
                      </button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteFleet(fleet)">
                        <i class="fa fa-trash me-1"></i> Eliminar
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
              <label class="form-label">Valor en Excel</label>
              <input type="text" class="form-control" v-model.trim="vehicleMapForm.excel_value" placeholder="ej. vans corta, fiorino, kangoo">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo de transporte sistema</label>
              <select class="form-select" v-model="vehicleMapForm.vehicle_type">
                <option value="">Seleccione...</option>
                <option v-for="opt in vehicleTypeOptions" :key="opt.value" :value="opt.value">@{{ opt.label }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <button class="btn btn-primary w-100" @click="submitVehicleMap" :disabled="savingVehicleMap">
                <i class="fa fa-save me-1"></i> @{{ savingVehicleMap ? 'Guardando...' : 'Agregar mapeo' }}
              </button>
            </div>
          </div>

          <div class="table-responsive bg-white border rounded">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Valor Excel</th>
                  <th>Mapeado a</th>
                  <th class="text-end">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="vehicleMaps.length === 0">
                  <td colspan="3" class="text-center text-muted py-4">Todavía no hay mapeos cargados.</td>
                </tr>
                <tr v-for="map in vehicleMaps" :key="map.id">
                  <td><code>@{{ map.excel_value }}</code></td>
                  <td>@{{ vehicleTypeLabel(map.vehicle_type) }}</td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger" @click="deleteVehicleMap(map)">
                      <i class="fa fa-trash me-1"></i> Eliminar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="border rounded p-3 bg-white mt-3">
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div class="d-flex align-items-center gap-3 flex-grow-1" style="max-width: 400px;">
              <h6 class="fw-bold mb-0 text-nowrap">Conceptos configurados</h6>
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="fa fa-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 bg-light" v-model="conceptQuery" placeholder="Buscar concepto por nombre...">
              </div>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <span class="badge bg-secondary" v-if="conceptQuery">@{{ filteredConcepts.length }} de @{{ maintenanceConcepts.length }}</span>
              <span class="badge bg-secondary" v-else>@{{ maintenanceConcepts.length }} concepto(s)</span>
              <button class="btn btn-sm btn-primary" type="button" @click="openConceptCreateModal()">
                <i class="fa fa-plus me-1"></i> Nuevo concepto
              </button>
            </div>
          </div>

          <div class="table-responsive border rounded">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Concepto</th>
                  <th>Tipo</th>
                  <th>Impacto</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="maintenanceConcepts.length === 0">
                  <td colspan="5" class="text-center text-muted py-4">No hay conceptos cargados.</td>
                </tr>
                <tr v-else-if="filteredConcepts.length === 0">
                  <td colspan="5" class="text-center text-muted py-4">No hay conceptos que coincidan con la búsqueda.</td>
                </tr>
                <tr v-for="concept in filteredConcepts" :key="concept.id">
                  <td>
                    <div class="fw-semibold">@{{ concept.name }}</div>
                    <div class="small text-muted">@{{ conceptSummary(concept) }}</div>
                  </td>
                  <td>
                    <span class="badge" :class="concept.value_type === 'reference' ? 'bg-info text-dark' : 'bg-primary'">
                      @{{ concept.value_type === 'reference' ? 'Referencia' : 'Fijo' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="Number(concept.sign || 1) === -1 ? 'bg-danger' : 'bg-success'">
                      @{{ Number(concept.sign || 1) === -1 ? 'Resta' : 'Suma' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="concept.active ? 'bg-success' : 'bg-secondary'">@{{ concept.active ? 'Activo' : 'Inactivo' }}</span>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="openConceptDetails(concept.id)">
                      <i class="fa fa-pen-to-square me-1"></i> Ver/Editar
                    </button>
                    <button class="btn btn-sm btn-outline-danger ms-1" type="button" @click="deleteConcept(concept)">
                      <i class="fa fa-trash me-1"></i> Eliminar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- LIST VIEW -->
    <div v-if="currentView === 'list'" class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th width="200" class="cursor-pointer text-nowrap" @click="sortBy('name')">Nombre <i class="fa" :class="sortIcon('name')"></i></th>
              <th width="120" class="text-nowrap" @click="sortBy('type')">Tipo <i class="fa" :class="sortIcon('type')"></i></th>
              <th width="150" class="text-nowrap" @click="sortBy('action_type')">Valor <i class="fa" :class="sortIcon('action_type')"></i></th>
              <th class="text-nowrap">Condiciones</th>
              <th width="100" class="cursor-pointer text-nowrap text-center" @click="sortBy('status')">Estado <i class="fa" :class="sortIcon('status')"></i></th>
              <th width="150" class="text-end text-nowrap">Opciones</th>
            </tr>
            <tr class="bg-light">
              <th><input type="text" class="form-control form-control-sm" v-model="filters.name" placeholder="Filtrar Nombre"></th>
              <th>
                <select class="form-select form-select-sm" v-model="filters.type">
                  <option value="">Todos</option>
                  <option value="base">Base</option>
                  <option value="modifier">Modifier</option>
                </select>
              </th>
              <th></th>
              <th><input type="text" class="form-control form-control-sm" v-model="filters.conditions" placeholder="Filtrar (ej. cordoba zona)"></th>
              <th>
                <select class="form-select form-select-sm" v-model="filters.status">
                  <option value="">Todos</option>
                  <option value="true">Activos</option>
                  <option value="false">Inactivos</option>
                </select>
              </th>
              <th class="text-end">
                <button class="btn btn-sm btn-outline-secondary" @click="resetFilters" title="Limpiar Filtros"><i class="fa fa-eraser"></i></button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="text-center py-4"><i class="fa fa-spin fa-spinner text-primary fs-3"></i></td>
            </tr>
            <tr v-else-if="rules.length === 0">
              <td colspan="6" class="text-center py-4 text-muted">No existen reglas configuradas.</td>
            </tr>
            <tr v-else-if="paginatedRules.length === 0">
              <td colspan="6" class="text-center py-4 text-muted">No hay reglas para los filtros aplicados.</td>
            </tr>
            <tr v-for="rule in paginatedRules" :key="rule.id" :class="{'opacity-50': !rule.active}">
              <td>
                <span class="fw-bold">@{{ rule.name }}</span>
              </td>
              <td>
                <div class="d-flex flex-column align-items-start gap-1">
                  <span v-if="rule.is_modifier" class="badge bg-info">Modifier</span>
                  <span v-else class="badge bg-primary">Base Rule</span>
                  <span class="badge" :class="rule.scope === 'receipt' ? 'bg-dark' : 'bg-secondary'">
                    @{{ rule.scope === 'receipt' ? 'Recibo' : 'Línea' }}
                  </span>
                </div>
              </td>
              <td>
                <div style="font-size: 0.9em;">
                    <span v-if="rule.action_type === 'FIXED_AMOUNT'" class="text-success fw-bold"><i class="fa fa-money-bill-wave"></i> $ @{{ rule.action_payload?.amount }}</span>
                    <span v-else-if="rule.action_type === 'MULTIPLIER'" class="text-primary fw-bold"><i class="fa fa-times"></i> x@{{ rule.action_payload?.rate ?? rule.action_payload?.multiplier_rate }}</span>
                    <span v-else-if="rule.action_type === 'NO_PAYMENT'" class="text-danger fw-bold"><i class="fa fa-ban"></i> Sin Pago</span>
                    <span v-else-if="rule.action_type === 'THRESHOLD_EXCESS'" class="text-warning fw-bold text-dark"><i class="fa fa-chart-line"></i> Exceso > @{{ rule.action_payload?.threshold_qty }} (+$ @{{ rule.action_payload?.excess_rate }} c/u)</span>
                    <span v-else-if="rule.action_type === 'COMPOSITE'" class="text-info fw-bold text-dark"><i class="fa fa-cubes"></i> Suma de Reglas</span>
                    <span v-else class="text-secondary fw-bold">@{{ rule.action_type }}</span>
                </div>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1" v-if="rule.conditions && rule.conditions.length > 0">
                  <span class="badge bg-light border text-secondary" style="font-size: 0.75rem; white-space: normal; text-align: left;" v-for="(condText, idx) in formatConditions(rule.conditions)" :key="idx">
                    @{{ condText }}
                  </span>
                </div>
              </td>
              <td>
                <div class="form-check form-switch cursor-pointer">
                  <input class="form-check-input cursor-pointer" type="checkbox" :checked="rule.active" @change="toggleActive(rule.id)">
                </div>
              </td>
              <td>
                <div class="d-flex justify-content-end gap-1">
                  <button class="btn btn-sm btn-outline-success" @click="openSimulator(rule)" title="Probar Regla Sola">
                    <i class="fa fa-play text-success"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" @click="editRule(rule)" title="Editar">
                    <i class="fa fa-edit"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-info" @click="cloneRule(rule)" title="Clonar">
                    <i class="fa fa-copy"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" @click="deleteRule(rule.id)" title="Eliminar">
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2" v-if="!loading && filteredAndSortedRules.length > 0">
        <div class="small text-muted">
          Mostrando @{{ paginationStart }}-@{{ paginationEnd }} de @{{ filteredAndSortedRules.length }} regla(s)
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <div class="input-group input-group-sm" style="width: 132px;">
            <span class="input-group-text">Por página</span>
            <select class="form-select" v-model.number="pageSize">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
          <button class="btn btn-sm btn-outline-secondary" @click="goToPage(currentPage - 1)" :disabled="currentPage <= 1">
            <i class="fa fa-chevron-left"></i>
          </button>
          <span class="small fw-semibold">Página @{{ currentPage }} / @{{ totalPages }}</span>
          <button class="btn btn-sm btn-outline-secondary" @click="goToPage(currentPage + 1)" :disabled="currentPage >= totalPages">
            <i class="fa fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- FORM VIEW -->
    <div v-if="currentView === 'form'" class="card shadow-sm">
      <div class="card-header bg-light">
        <h5 class="mb-0">@{{ form.id ? 'Editar Regla #' + form.id : 'Nueva Regla' }}</h5>
      </div>
      <div class="card-body">
        <div class="row mb-4">
          <div class="col-md-5">
            <label class="form-label">Nombre descriptivo <span class="text-danger">*</span></label>
            <input type="text" class="form-control" v-model="form.name" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Prioridad (Mayor=gana) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" v-model.number="form.priority" required>
          </div>
          <div class="col-md-3">
             <label class="form-label">Tipo de Regla</label>
             <select class="form-select" v-model="form.is_modifier">
               <option :value="false">BASE (Reemplaza valor)</option>
               <option :value="true">MODIFIER (Se suma al base)</option>
             </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Nivel</label>
            <select class="form-select" v-model="form.scope" @change="onScopeChange">
              <option value="line">Por línea</option>
              <option value="receipt">Por recibo</option>
            </select>
          </div>
          <div class="col-md-12 mt-2" v-if="form.scope === 'receipt'">
            <div class="alert alert-info py-2 mb-0">
              La regla se evaluará una sola vez por recibo. Los campos operativos como vehículo, zona o concepto toman la última línea del detalle; los totales usan la suma del recibo.
            </div>
          </div>
          <div class="col-md-2 d-flex align-items-end mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" v-model="form.active" id="ruleActiveCheck">
              <label class="form-check-label" for="ruleActiveCheck">
                Activa
              </label>
            </div>
          </div>
        </div>

        <hr>

        <h6 class="fw-bold mb-3">1. Condiciones de activación</h6>
        <div class="bg-light p-3 border rounded mb-4">
          <div v-for="(cond, index) in form.conditions" :key="index" class="row g-2 align-items-end mb-2">
            <div class="col-md-4">
              <label class="form-label small mb-1" v-if="index === 0">Campo</label>
              <select class="form-select" v-model="cond.field" @change="cond.value = ''">
                <option v-for="(typeObj, field) in availableFieldCatalog" :value="field">@{{ typeObj?.label || field }} (@{{ field }})</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1" v-if="index === 0">Operador</label>
              <select class="form-select" v-model="cond.operator">
                <option value="=">igual</option>
                <option value="!=">distinto de</option>
                <option value=">">mayor que</option>
                <option value="<">menor que</option>
                <option value=">=">mayor o igual que</option>
                <option value="<=">menor o igual que</option>
                <option value="IN">en lista</option>
                <option value="CONTAINS">contiene</option>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label small mb-1" v-if="index === 0">Valor</label>
              
              <!-- Typed Input logic based on Field Catalog -->
              <div class="d-flex w-100">
                <template v-if="cond.operator === 'IN'">
                   <input type="text" class="form-control" v-model="cond.value" placeholder="Array ej: [1,2] o 1,2,3">
                </template>
                <template v-else-if="cond.field && availableFieldCatalog[cond.field] && availableFieldCatalog[cond.field].type === 'boolean'">
                   <select class="form-select" v-model="cond.value">
                     <option :value="true">Verdadero (True)</option>
                     <option :value="false">Falso (False)</option>
                   </select>
                </template>
                <template v-else-if="cond.field && catalogOptions[cond.field]">
                   <select class="form-select" v-model="cond.value">
                      <option value="" disabled>Seleccione uno...</option>
                      <option v-for="opt in catalogOptions[cond.field]" :key="opt.value" :value="opt.value">
                         @{{ opt.label }}
                      </option>
                   </select>
                   <button v-if="cond.field === 'zone_id' && cond.value" class="btn btn-outline-primary ms-1" type="button" @click="openZoneDetails(cond.value, index)" title="Ver o editar zona"><i class="fa fa-pen-to-square"></i></button>
                   <button v-if="cond.field === 'zone_id'" class="btn btn-outline-secondary ms-1" type="button" @click="openZoneCreateModal(index)" title="Crear Zona"><i class="fa fa-plus"></i></button>
                   <button v-if="cond.field === 'carrier_id'" class="btn btn-outline-secondary ms-1" type="button" @click="openModal('carrierModal', index)" title="Crear Transportista"><i class="fa fa-plus"></i></button>
                   <button v-if="cond.field === 'concept_key' && cond.value" class="btn btn-outline-primary ms-1" type="button" @click="openConceptDetailsByValue(cond.value, index)" title="Ver o editar concepto"><i class="fa fa-pen-to-square"></i></button>
                   <button v-if="cond.field === 'concept_key'" class="btn btn-outline-secondary ms-1" type="button" @click="openConceptCreateModal(index)" title="Crear Concepto"><i class="fa fa-plus"></i></button>
                </template>
                <template v-else-if="cond.field && availableFieldCatalog[cond.field] && availableFieldCatalog[cond.field].type === 'number'">
                   <input type="number" step="0.01" class="form-control" v-model.number="cond.value">
                </template>
                <template v-else>
                   <input type="text" class="form-control" v-model="cond.value">
                </template>
              </div>
            </div>
            <div class="col-md-1">
              <button class="btn btn-outline-danger w-100" @click="form.conditions.splice(index, 1)"><i class="fa fa-times"></i></button>
            </div>
          </div>
          
          <button class="btn btn-sm btn-outline-primary mt-2" @click="form.conditions.push(defaultCondition())">
            + Agregar Condición (AND)
          </button>
        </div>

        <h6 class="fw-bold mb-3">2. Acción (Tarifa a aplicar)</h6>
        <div class="row mb-3 border p-3 bg-white rounded shadow-sm ms-0 me-0">
          <div class="col-md-12 mb-3" v-if="form.scope === 'receipt'">
            <label class="form-label">Concepto que se agregará al recibo</label>
            <div class="d-flex w-100">
              <select class="form-select" v-model="form.action_payload.receipt_concept">
                <option value="">Usar nombre de la regla</option>
                <option v-for="opt in (catalogOptions.concept_key || [])" :key="opt.value" :value="opt.value">
                  @{{ opt.label }}
                </option>
              </select>
              <button class="btn btn-outline-primary ms-1" v-if="form.action_payload.receipt_concept" type="button" @click="openConceptDetailsByValue(form.action_payload.receipt_concept)" title="Ver o editar concepto"><i class="fa fa-pen-to-square"></i></button>
              <button class="btn btn-outline-secondary ms-1" type="button" @click="openConceptCreateModal()" title="Crear concepto"><i class="fa fa-plus"></i></button>
            </div>
            <div class="form-text">Ese concepto se usará para nombrar la línea creada y para aplicar su signo suma/resta.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Tipo de Handler</label>
            <select class="form-select border-primary" v-model="form.action_type" @change="initPayload">
              <option value="FIXED_AMOUNT">Fixed Amount (Monto Fijo)</option>
              <option value="MULTIPLIER">Multiplier (Multiplicador)</option>
              <option value="BASE_PLUS_MULTIPLIER">Base + Multiplier (Fijo + Variable)</option>
              <option value="THRESHOLD_EXCESS">Threshold Excess (Cobrar Excedente)</option>
              <option value="COMPOSITE">Composite (Suma Reglas Compuestas)</option>
              <option value="NO_PAYMENT">No Payment (Monto Cero)</option>
            </select>
          </div>
          <div class="col-md-8">
             <div v-if="form.action_type === 'FIXED_AMOUNT'" class="row g-2">
                <div class="col-6">
                  <label class="form-label">amount (Monto $)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.amount">
                </div>
             </div>
             <div v-if="form.action_type === 'COMPOSITE'" class="row g-2">
                <div class="col-12">
                   <p class="text-muted small mb-1"><strong class="text-info"><i class="fa fa-info-circle"></i> Tip:</strong> Ejecuta sub-reglas y suma sus resultados. Weight es el multiplicador (ej: 0.5 para el 50%).</p>
                   <div v-for="(sub, sidx) in (form.action_payload.sub_rules || [])" :key="sidx" class="d-flex gap-2 mb-2 align-items-center">
                       <select class="form-select form-select-sm w-50" v-model="sub.rule_id" required>
                          <option value="">Seleccione sub-regla...</option>
                          <option v-for="r in rules.filter(x => x.id !== form.id)" :key="r.id" :value="r.id">@{{ formatRuleForSelect(r) }}</option>
                       </select>
                       <div class="input-group input-group-sm w-25">
                          <span class="input-group-text bg-light text-muted">x</span>
                          <input type="number" step="0.01" class="form-control" v-model.number="sub.weight" placeholder="1.0" required>
                       </div>
                       <button class="btn btn-sm btn-outline-danger" @click="form.action_payload.sub_rules.splice(sidx, 1)"><i class="fa fa-trash"></i></button>
                   </div>
                   <button class="btn btn-sm btn-outline-primary" @click="(form.action_payload.sub_rules = form.action_payload.sub_rules || []).push({rule_id: '', weight: 1.0})">
                     <i class="fa fa-plus"></i> Añadir Sub-Regla
                   </button>
                </div>
             </div>
             <div v-if="form.action_type === 'MULTIPLIER'" class="row g-2">
                <div class="col-6">
                  <label class="form-label">multiply_by_field</label>
                  <select class="form-select" v-model="form.action_payload.multiply_by_field">
                    <option v-for="(typeObj, field) in availableNumericFields" :value="field">@{{ typeObj?.label || field }} (@{{ field }})</option>
                  </select>
                </div>
                <div class="col-3">
                  <label class="form-label">multiplier_rate (Tarifa $)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.multiplier_rate">
                </div>
                <div class="col-3">
                  <label class="form-label">Restar antes de multiplicar</label>
                  <input type="number" step="0.01" min="0" class="form-control" v-model.number="form.action_payload.subtract_value" placeholder="ej. 30">
                </div>
             </div>

             <div v-if="form.action_type === 'BASE_PLUS_MULTIPLIER'" class="row g-2">
                <div class="col-3">
                  <label class="form-label">base_amount ($)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.base_amount">
                </div>
                <div class="col-3">
                  <label class="form-label">multiplier_rate ($)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.multiplier_rate">
                </div>
                <div class="col-3">
                  <label class="form-label">multiply_by_field</label>
                  <select class="form-select" v-model="form.action_payload.multiply_by_field">
                    <option v-for="(typeObj, field) in availableNumericFields" :value="field">@{{ typeObj?.label || field }} (@{{ field }})</option>
                  </select>
                </div>
                <div class="col-3">
                  <label class="form-label">Restar antes de multiplicar</label>
                  <input type="number" step="0.01" min="0" class="form-control" v-model.number="form.action_payload.subtract_value" placeholder="ej. 30">
                </div>
             </div>

             <div v-if="form.action_type === 'THRESHOLD_EXCESS'" class="row g-2">
                <div class="col-4">
                  <label class="form-label">threshold_limit (Umbral)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.threshold_limit">
                </div>
                <div class="col-4">
                  <label class="form-label">excess_multiplier_rate</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.excess_multiplier_rate">
                </div>
                <div class="col-4">
                  <label class="form-label">evaluate_field</label>
                  <select class="form-select" v-model="form.action_payload.evaluate_field">
                    <option v-for="(typeObj, field) in availableNumericFields" :value="field">@{{ typeObj?.label || field }} (@{{ field }})</option>
                  </select>
                </div>
             </div>
             
             <div v-if="form.action_type === 'NO_PAYMENT'" class="text-muted p-2 mt-4">
                El handler NO_PAYMENT no requiere parámetros y devolverá $0.00.
             </div>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white text-end">
        <button class="btn btn-outline-secondary me-2" @click="currentView = 'list'">Cancelar</button>
        <button class="btn btn-success" @click="saveRule" :disabled="saving">
          <i class="fa fa-save"></i> @{{ saving ? 'Guardando...' : 'Guardar Regla' }}
        </button>
      </div>
    </div>



    <!-- SIMULATOR MODAL -->
    <div v-if="currentView === 'simulate'" class="card border-warning shadow-lg">
      <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fa fa-flask"></i> Laboratorio de Simulación (@{{ simulatorRuleId ? 'Regla Particular #' + simulatorRuleId : 'Engine Completo' }})</span>
        <button class="btn btn-sm btn-dark" @click="currentView = 'list'">Cerrar Simulador</button>
      </div>
      <div class="card-body">
        <!-- Target Rule Info Banner -->
        <div v-if="simulatorRuleData" class="alert alert-light py-2 mb-3 shadow-sm border" style="border-left: 4px solid #0dcaf0 !important;">
            <div class="d-flex justify-content-between align-items-center">
               <div>
                  <h6 class="fw-bold mb-1 text-dark"><i class="fa fa-crosshairs text-info"></i> Objetivo Acertar a: @{{ simulatorRuleData.name }}</h6>
                  <div class="small text-muted">
                     <span class="badge me-2" :class="simulatorRuleData.is_modifier ? 'bg-info' : 'bg-primary'">@{{ simulatorRuleData.is_modifier ? 'Modifier' : 'Base Rule' }}</span>
                     <span class="fw-bold">⇒ @{{ simulatorRuleData.action_type }}</span>
                  </div>
               </div>
               <div class="text-end">
                  <div class="small fw-bold text-muted mb-1">Condiciones a cumplir:</div>
                  <div class="d-flex flex-wrap justify-content-end gap-1">
                    <span class="badge bg-secondary" style="font-size: 0.75rem" v-for="(condText, idx) in formatConditions(simulatorRuleData.conditions)" :key="idx">
                      @{{ condText }}
                    </span>
                    <span v-if="!simulatorRuleData.conditions || simulatorRuleData.conditions.length === 0" class="text-muted small fst-italic">Ninguna (Global)</span>
                  </div>
                  
                  <div v-if="simulatorRuleData.action_type === 'COMPOSITE' && simulatorRuleData.action_payload?.sub_rules?.length > 0" class="mt-2 text-end">
                     <div class="small fw-bold text-info mb-1"><i class="fa fa-cubes"></i> Requiere acertar sus dependencias:</div>
                     <div class="d-flex flex-column align-items-end gap-1">
                        <div v-for="sub in simulatorRuleData.action_payload.sub_rules" :key="sub.rule_id" class="badge border border-info text-dark text-wrap text-start" style="font-size: 0.7rem; background-color: #f8ffff; max-width: 300px;">
                            <strong class="text-primary me-1">x@{{ sub.weight }}</strong> 
                            ▶ @{{ formatRuleForSelect(rules.find(r => r.id === sub.rule_id) || {name: 'Desconocida'}) }}
                        </div>
                     </div>
                  </div>
               </div>
            </div>
        </div>

        <div class="row">
           <div class="col-md-6 border-end">
              <h6 class="mb-3 fw-bold text-secondary border-bottom pb-2">1. Ajustar Contexto (Variables)</h6>
              <div class="row g-2">
                 <div class="col-6 mb-2" v-for="(typeObj, field) in fieldCatalog" :key="field">
                    <label class="form-label small mb-1">@{{ typeObj?.label || field }}</label>
                    <template v-if="catalogOptions[field]">
                       <select class="form-select form-select-sm" v-model="simContext[field]">
                           <option value="">Seleccione o deje vacío...</option>
                           <option v-for="opt in catalogOptions[field]" :key="opt.value" :value="opt.value">
                              @{{ opt.label }}
                           </option>
                        </select>
                    </template>
                    <template v-else-if="typeObj?.type === 'number'">
                       <input type="number" class="form-control form-control-sm" v-model.number="simContext[field]">
                    </template>
                    <template v-else-if="typeObj?.type === 'boolean'">
                       <select class="form-select form-select-sm" v-model="simContext[field]">
                          <option :value="true">True</option>
                          <option :value="false">False</option>
                       </select>
                    </template>
                    <template v-else-if="typeObj?.type === 'date'">
                       <input type="date" class="form-control form-control-sm" v-model="simContext[field]">
                    </template>
                    <template v-else>
                       <input type="text" class="form-control form-control-sm" v-model="simContext[field]">
                    </template>
                 </div>
              </div>
              <button class="btn btn-primary w-100 mt-3" @click="runSimulation" :disabled="simulating">
                 @{{ simulating ? 'Corriendo Motor...' : 'Ejecutar Cálculo' }}
              </button>
           </div>
           <div class="col-md-6">
              <h6 class="mb-3 text-muted">Resultados del Cálculo</h6>
              <div v-if="simResult">
                 <div v-if="!simResult.matched" class="alert alert-warning">
                    No hubo coincidencias o el engine devolvió nulo.
                 </div>
                 <div v-else>
                    <div class="display-6 text-success fw-bold text-center mb-3">
                       $ @{{ simResult.total_amount ?? simResult.calculated_amount }}
                    </div>
                    <hr>
                    <strong>Traza de Ejecución (Breakdown)</strong>
                    <pre class="bg-dark text-light p-3 rounded mt-2" style="font-size:12px; max-height:400px; overflow-y:auto;">@{{ JSON.stringify(simResult.trace || simResult.breakdown, null, 2) }}</pre>
                 </div>
              </div>
              <div v-else class="text-center text-muted p-5">
                 Aún no has simulado nada.
              </div>
           </div>
        </div>
      </div>
    </div>
    <!-- Modals for Creation -->
    <teleport to="body">
       <div class="modal fade" id="zoneModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">@{{ zoneForm.id ? 'Ver / Editar Zona' : 'Crear Nueva Zona' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
               <div class="col-md-6">
                 <label class="form-label">Nombre de la Zona</label>
                 <input type="text" class="form-control" v-model="zoneForm.name">
               </div>
               <div class="col-md-6">
                 <label class="form-label">Prioridad</label>
                 <select class="form-select" v-model="zoneForm.priority">
                    <option value="primary">Primaria</option>
                    <option value="secondary">Secundaria</option>
                 </select>
               </div>
               <div class="col-md-12">
                 <label class="form-label">Tipo de Geometría</label>
                 <select class="form-select mb-2" v-model="zoneForm.type">
                    <option value="circle">Círculo (Radio en KM)</option>
                    <option value="polygon">Polígono (JSON o Múltiples Puntos)</option>
                 </select>
               </div>
               <div class="col-md-4" v-if="zoneForm.type === 'circle'">
                 <label class="form-label">Latitud Centro</label>
                 <input type="number" step="any" class="form-control" v-model="zoneForm.center_lat" placeholder="-34.6037">
               </div>
               <div class="col-md-4" v-if="zoneForm.type === 'circle'">
                 <label class="form-label">Longitud Centro</label>
                 <input type="number" step="any" class="form-control" v-model="zoneForm.center_lng" placeholder="-58.3816">
               </div>
               <div class="col-md-4" v-if="zoneForm.type === 'circle'">
                 <label class="form-label">Radio (KM)</label>
                 <input type="number" step="any" class="form-control" v-model="zoneForm.radius_km" placeholder="5.5">
               </div>
               <div class="col-md-12" v-if="zoneForm.type === 'polygon'">
                 <label class="form-label">Coordenadas del Polígono (Array JSON)</label>
                 <textarea class="form-control" v-model="zoneForm.polygon" rows="3" placeholder='[[lat, lng], [lat, lng], ...]'></textarea>
                 <small class="text-muted">Si no conoces las coordenadas, puedes cargar un JSON genérico y editarlo luego en el mapa de Zonas.</small>
               </div>
               <div class="col-md-12">
                 <div class="form-check">
                    <input class="form-check-input" type="checkbox" v-model="zoneForm.is_soft" id="softZ">
                    <label class="form-check-label" for="softZ">Asignable con penalización (Soft Zone)</label>
                 </div>
               </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" @click="submitZone" :disabled="savingModal">@{{ savingModal ? 'Guardando...' : (zoneForm.id ? 'Guardar cambios' : 'Crear Zona') }}</button>
          </div>
        </div>
      </div>
       </div>
    </teleport>

    <teleport to="body">
       <div class="modal fade" id="carrierModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Crear Transportista / Chofer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
             <div class="row g-3">
                <h6 class="border-bottom pb-2 mb-3 mt-0 text-primary">Datos Personales</h6>
                <div class="col-md-4">
                  <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="carrierForm.name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Razón Social</label>
                  <input type="text" class="form-control" v-model="carrierForm.business_name">
                </div>
                <div class="col-md-4">
                  <label class="form-label">CUIT / Tax ID</label>
                  <input type="text" class="form-control" v-model="carrierForm.tax_id">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" v-model="carrierForm.email" placeholder="usuario@chofer.com">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Teléfono</label>
                  <input type="text" class="form-control" v-model="carrierForm.phone">
                </div>
                <div class="col-md-4">
                  <label class="form-label">DNI</label>
                  <input type="text" class="form-control" v-model="carrierForm.dni">
                </div>

                <h6 class="border-bottom pb-2 mb-3 mt-4 text-primary">Condiciones y Pago</h6>
                <div class="col-md-3">
                  <label class="form-label">CBU / CVU</label>
                  <input type="text" class="form-control" v-model="carrierForm.cbu">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Número de Cuenta</label>
                  <input type="text" class="form-control" v-model="carrierForm.account_number">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tipo Periodo</label>
                  <select class="form-select" v-model="carrierForm.liquidation_tipo_periodo">
                     <option value="quincenal">Quincenal</option>
                     <option value="mensual">Mensual</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Estado</label>
                  <select class="form-select" v-model="carrierForm.status">
                     <option value="active">Activo</option>
                     <option value="inactive">Inactivo</option>
                  </select>
                </div>
             </div>
          </div>
          <div class="modal-footer">
             <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
             <button type="button" class="btn btn-primary" @click="submitCarrier" :disabled="savingModal">@{{ savingModal ? 'Guardando...' : 'Crear Transportista' }}</button>
          </div>
        </div>
      </div>
       </div>
    </teleport>

    <teleport to="body">
       <div class="modal fade" id="conceptModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">@{{ conceptForm.id ? 'Ver / Editar Concepto' : 'Crear Nuevo Concepto' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
             <div class="row g-3">
                <div class="col-md-12">
                   <label class="form-label">Nombre del Concepto</label>
                   <input type="text" class="form-control" v-model="conceptForm.concept_name">
                </div>
                <div class="col-md-12">
                   <label class="form-label">Estado</label>
                   <div class="d-flex gap-3 mt-2">
                      <div class="form-check">
                         <input class="form-check-input" type="radio" :value="true" v-model="conceptForm.active" id="concept_active_true">
                         <label class="form-check-label" for="concept_active_true">Activo</label>
                      </div>
                      <div class="form-check">
                         <input class="form-check-input" type="radio" :value="false" v-model="conceptForm.active" id="concept_active_false">
                         <label class="form-check-label" for="concept_active_false">Inactivo</label>
                      </div>
                   </div>
                </div>
                <div class="col-md-12">
                   <label class="form-label">Impacto en recibo</label>
                   <div class="d-flex gap-3 mt-2">
                      <div class="form-check">
                         <input class="form-check-input" type="radio" :value="1" v-model="conceptForm.sign" id="concept_sign_plus">
                         <label class="form-check-label" for="concept_sign_plus">Suma</label>
                      </div>
                      <div class="form-check">
                         <input class="form-check-input" type="radio" :value="-1" v-model="conceptForm.sign" id="concept_sign_minus">
                         <label class="form-check-label" for="concept_sign_minus">Resta</label>
                      </div>
                   </div>
                </div>
                <div class="col-md-12">
                   <label class="form-label">Tipo de Valor</label>
                   <div class="d-flex gap-3 mt-2">
                       <div class="form-check">
                          <input class="form-check-input" type="radio" value="fixed" v-model="conceptForm.value_type" id="vt_fixed">
                          <label class="form-check-label" for="vt_fixed">Valor Fijo / Zona</label>
                       </div>
                       <div class="form-check">
                          <input class="form-check-input" type="radio" value="reference" v-model="conceptForm.value_type" id="vt_ref">
                          <label class="form-check-label" for="vt_ref">Referenciado a otro</label>
                       </div>
                   </div>
                </div>
                <div class="col-md-12" :class="{'d-none': conceptForm.value_type !== 'fixed'}">
                   <label class="form-label">Monto Fijo por Defecto ($)</label>
                   <input type="number" step="0.01" class="form-control" v-model="conceptForm.default_amount">
                </div>
                <div class="col-md-12" :class="{'d-none': conceptForm.value_type !== 'reference'}">
                   <label class="form-label">Multiplicador de Referencia (Ej: 0.5 para 50%)</label>
                   <input type="number" step="0.01" class="form-control" v-model="conceptForm.reference_multiplier" placeholder="1.0">
                </div>
                <div class="col-md-12" :class="{'d-none': conceptForm.value_type !== 'reference'}">
                   <label class="form-label">Requisito: Concepto Base</label>
                   <select class="form-select no-select2" v-model="conceptForm.reference_concept_id">
                      <option value="">Seleccione o créelo primero...</option>
                      <option v-for="opt in (catalogOptions.concept_key || [])" :key="opt.id" :value="parseInt(opt.id || 0)">
                        @{{ opt.label }}
                      </option>
                   </select>
                   <!-- We map concept names back to IDs in fetch options if needed, wait, catalogOptions.concept_key has values as names. We need the ID for reference. This is a bit tricky, but we can allow them to select. Let's just remove the base selection from the quick modal or provide an ID map -->
                   <small class="text-muted">El concepto base se guarda por ID interno. Aqui puedes elegirlo directamente.</small>
                </div>
             </div>
          </div>
          <div class="modal-footer">
             <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
             <button type="button" class="btn btn-primary" @click="submitConcept" :disabled="savingModal">@{{ savingModal ? 'Guardando...' : (conceptForm.id ? 'Guardar cambios' : 'Crear Concepto') }}</button>
          </div>
        </div>
           </div>
         </div>
       </div>
    </teleport>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script>
  try {
    console.log("Iniciando Vue Script...");
    const apiBase = '{{ url("/pago-choferes/reglas/api") }}';
    const csrfToken = '{{ csrf_token() }}';
    const initialVehicleMaps = @json($vehicleMaps);
    const initialFleets = @json($fleets);
    const initialTransportistas = @json($transportistas);
    const initialZones = @json($zones);
    const initialConcepts = @json($concepts);
    const initialDefaultPeriodType = @json($defaultPeriodType);
    const initialVehicleAliases = @json(\App\Models\Transporte::paymentVehicleTypes(true));
    const vehicleTypeOptionsRaw = @json(collect($vehicleTypes)->map(function ($label, $value) {
        return ['value' => $value, 'label' => $label];
    })->values());
    const settingsBaseUrl = '{{ route('pago-choferes.settings.index') }}';
    const defaultPeriodUrl = '{{ route('pago-choferes.settings.default-period.store') }}';

    const { createApp, ref, computed, watch, onMounted } = Vue;

    const app = createApp({
      setup() {
        const currentView = ref('list');
        const loading = ref(false);
        const saving = ref(false);
        const simulating = ref(false);
        const rules = ref([]);

        const filters = ref({
          id: '',
          name: '',
          type: '',
          priority: '',
          conditions: '',
          status: ''
        });
        const sortKey = ref('id');
        const sortAsc = ref(false);
        
        const lineFieldCatalog = {
          'trip_date': { type: 'date', label: 'Fecha del Viaje' },
          'carrier_id': { type: 'string', label: 'Transportista' },
          'carrier_name': { type: 'string', label: 'Nombre de Transportista' },
          'concept_name': { type: 'string', label: 'Nombre del Concepto' },
          'concept_key': { type: 'string', label: 'Concepto' },
          'zone_id': { type: 'string', label: 'Zona' },
          'vehicle_type': { type: 'string', label: 'Tipo de Vehículo' },
          'model_year': { type: 'number', label: 'Año del Vehículo' },
          'kilometers': { type: 'number', label: 'Kilómetros Recorridos' },
          'delivered_packages': { type: 'number', label: 'Paquetes Entregados' },
          'absent_packages': { type: 'number', label: 'Paquetes Ausentes' },
          'stops': { type: 'number', label: 'Paradas (Stops)' },
          'is_remote_zone': { type: 'boolean', label: 'Es Zona Remota' },
          'total_packages': { type: 'number', label: 'Total de Paquetes' },
        };

        const receiptFieldCatalog = {
          'trip_date': { type: 'date', label: 'Fecha última línea' },
          'concept_name': { type: 'string', label: 'Concepto última línea' },
          'concept_key': { type: 'string', label: 'Concepto última línea' },
          'is_remote_zone': { type: 'boolean', label: 'Zona remota última línea' },
          'receipt_date': { type: 'date', label: 'Fecha de Emisión' },
          'period_start': { type: 'date', label: 'Periodo Desde' },
          'period_end': { type: 'date', label: 'Periodo Hasta' },
          'carrier_id': { type: 'string', label: 'Transportista' },
          'carrier_name': { type: 'string', label: 'Nombre de Transportista' },
          'zone_id': { type: 'string', label: 'Zona' },
          'vehicle_type': { type: 'string', label: 'Tipo de Vehículo' },
          'model_year': { type: 'number', label: 'Año del Vehículo' },
          'kilometers': { type: 'number', label: 'Kilómetros Totales' },
          'delivered_packages': { type: 'number', label: 'Entregados Totales' },
          'absent_packages': { type: 'number', label: 'Ausentes Totales' },
          'stops': { type: 'number', label: 'Paradas Totales' },
          'total_packages': { type: 'number', label: 'Paquetes Totales' },
        };

        const fieldCatalog = ref({ ...lineFieldCatalog, ...receiptFieldCatalog });

        const form = ref({
          id: null,
          name: '',
          priority: 0,
          is_modifier: false,
          scope: 'line',
          active: true,
          action_type: 'FIXED_AMOUNT',
          action_payload: { amount: 0 },
          conditions: [],
        });

        const simulatorRuleId = ref(null);
        const simulatorRuleData = ref(null);
        const catalogOptions = ref({});
        const savingModal = ref(false);
        const vehicleMaps = ref(initialVehicleMaps);
        const fleets = ref((initialFleets || []).map(fleet => ({
          ...fleet,
          billing_transportista_name: fleet.billing_transportista?.name || '',
        })));
        const transportistaOptions = ref(initialTransportistas || []);
        const maintenanceZones = ref(initialZones);
        const maintenanceConcepts = ref(initialConcepts);
        const conceptQuery = ref('');
        const vehicleTypeOptions = ref(vehicleTypeOptionsRaw);
        const vehicleMapForm = ref({ excel_value: '', vehicle_type: '' });
        const savingVehicleMap = ref(false);
        const savingFleet = ref(false);
        const defaultFleetForm = () => ({
          id: null,
          name: '',
          billing_transportista_id: '',
          transportista_ids: [],
          active: true,
        });
        const fleetForm = ref(defaultFleetForm());
        
        const defaultPeriodType = ref(initialDefaultPeriodType?.setting_value || 'quincenal');
        const savingDefaultPeriod = ref(false);
        const vehicleAliases = ref(initialVehicleAliases || {});
        const savingVehicleAliases = ref(false);

        const zoneForm = ref({ id: null, name: '', priority: 'primary', type: 'circle', center_lat: null, center_lng: null, radius_km: null, polygon: '', is_soft: true });
        const carrierForm = ref({ name: '', business_name: '', tax_id: '', email: '', phone: '', dni: '', status: 'active', liquidation_tipo_periodo: 'quincenal', cbu: '', account_number: '' });
        const conceptForm = ref({ id: null, concept_name: '', value_type: 'fixed', default_amount: 0, sign: 1, reference_concept_id: '', reference_multiplier: 1, active: true });

        const simContext = ref({
          trip_date: new Date().toISOString().slice(0,10),
          carrier_id: 1,
          concept_name: '',
          concept_key: '',
          zone_id: null,
          vehicle_type: 'general',
          model_year: 2020,
          receipt_date: new Date().toISOString().slice(0,10),
          period_start: new Date().toISOString().slice(0,10),
          period_end: new Date().toISOString().slice(0,10),
          kilometers: 0,
          delivered_packages: 0,
          absent_packages: 0,
          stops: 0,
          is_remote_zone: false,
          total_packages: 0
        });
        const simResult = ref(null);
        const currentPage = ref(1);
        const pageSize = ref(20);

        const availableFieldCatalog = computed(() => {
          return form.value.scope === 'receipt' ? receiptFieldCatalog : lineFieldCatalog;
        });

        const numericFields = computed(() => {
          const res = {};
          for (const [key, val] of Object.entries(fieldCatalog.value)) {
            if (val.type === 'number') res[key] = val;
          }
          return res;
        });

        const availableNumericFields = computed(() => {
          const res = {};
          for (const [key, val] of Object.entries(availableFieldCatalog.value)) {
            if (val.type === 'number') res[key] = val;
          }
          return res;
        });

        const filteredConcepts = computed(() => {
          if (!conceptQuery.value) {
            return maintenanceConcepts.value;
          }
          const query = conceptQuery.value.toLowerCase().trim();
          return maintenanceConcepts.value.filter(c => 
            String(c.name || '').toLowerCase().includes(query)
          );
        });

        const filteredAndSortedRules = computed(() => {
          let result = rules.value;

          if (filters.value.id) result = result.filter(r => String(r.id).includes(filters.value.id));
          if (filters.value.name) result = result.filter(r => String(r.name).toLowerCase().includes(filters.value.name.toLowerCase()));
          if (filters.value.type) result = result.filter(r => (r.is_modifier ? 'modifier' : 'base').includes(filters.value.type));
          if (filters.value.priority !== '') result = result.filter(r => String(r.priority).includes(filters.value.priority));
          if (filters.value.status !== '') result = result.filter(r => r.active === (filters.value.status === 'true'));
          
          if (filters.value.conditions) {
              const keywords = filters.value.conditions.toLowerCase().split(/[\s,]+/).filter(k => k.trim() !== '');
              result = result.filter(r => {
                  const condsText = formatConditions(r.conditions).join(' ').toLowerCase();
                  return keywords.every(kw => condsText.includes(kw));
              });
          }

          result = result.sort((a, b) => {
            let valA = a[sortKey.value], valB = b[sortKey.value];
            if (sortKey.value === 'name' || sortKey.value === 'action_type') {
              valA = String(valA).toLowerCase();
              valB = String(valB).toLowerCase();
            } else if (sortKey.value === 'action') {
                valA = a.action_type; valB = b.action_type;
            } else if (sortKey.value === 'type') {
                valA = a.is_modifier; valB = b.is_modifier;
            } else if (sortKey.value === 'status') {
                valA = a.active; valB = b.active;
            }
            if (valA < valB) return sortAsc.value ? -1 : 1;
            if (valA > valB) return sortAsc.value ? 1 : -1;
            return 0;
          });
          return result;
        });

        const totalPages = computed(() => {
          const total = Math.ceil(filteredAndSortedRules.value.length / pageSize.value);
          return total > 0 ? total : 1;
        });

        const paginatedRules = computed(() => {
          const safePage = Math.min(currentPage.value, totalPages.value);
          const start = (safePage - 1) * pageSize.value;
          return filteredAndSortedRules.value.slice(start, start + pageSize.value);
        });

        const paginationStart = computed(() => {
          if (!filteredAndSortedRules.value.length) {
            return 0;
          }

          return ((Math.min(currentPage.value, totalPages.value) - 1) * pageSize.value) + 1;
        });

        const paginationEnd = computed(() => {
          if (!filteredAndSortedRules.value.length) {
            return 0;
          }

          return Math.min(Math.min(currentPage.value, totalPages.value) * pageSize.value, filteredAndSortedRules.value.length);
        });

        const sortBy = (key) => {
          if (sortKey.value === key) {
            sortAsc.value = !sortAsc.value;
          } else {
            sortKey.value = key;
            sortAsc.value = true;
          }
        };

        const sortIcon = (key) => {
          if (sortKey.value !== key) return 'fa-sort text-muted';
          return sortAsc.value ? 'fa-sort-up text-primary' : 'fa-sort-down text-primary';
        };

        const defaultCondition = () => ({
          field: form.value.scope === 'receipt' ? 'vehicle_type' : 'concept_key',
          operator: '=',
          value: '',
        });

        const onScopeChange = () => {
          form.value.conditions = (form.value.conditions || []).map((condition) => {
            if (availableFieldCatalog.value[condition.field]) {
              return condition;
            }

            return defaultCondition();
          });

          if (!form.value.conditions.length) {
            form.value.conditions = [defaultCondition()];
          }

          if (form.value.action_type !== 'FIXED_AMOUNT' && !Object.keys(availableNumericFields.value).includes(form.value.action_payload?.multiply_by_field)) {
            initPayload();
          }
        };

        const resetFilters = () => {
          filters.value = { name: '', type: '', priority: '', conditions: '', status: '' };
          currentPage.value = 1;
        };

        const goToPage = (page) => {
          const nextPage = Math.max(1, Math.min(Number(page || 1), totalPages.value));
          currentPage.value = nextPage;
        };

        const vehicleTypeLabel = (vehicleType) => {
          const found = vehicleTypeOptions.value.find(opt => String(opt.value) === String(vehicleType));
          return found ? found.label : vehicleType;
        };

        const normalizeFleet = (fleet) => ({
          ...fleet,
          billing_transportista_name: fleet.billing_transportista?.name || '',
          transportistas: Array.isArray(fleet.transportistas) ? fleet.transportistas : [],
        });

        const resetFleetForm = () => {
          fleetForm.value = defaultFleetForm();
        };

        const editFleet = (fleet) => {
          fleetForm.value = {
            id: fleet.id,
            name: fleet.name || '',
            billing_transportista_id: fleet.billing_transportista_id ? Number(fleet.billing_transportista_id) : '',
            transportista_ids: (fleet.transportistas || []).map(item => Number(item.id)),
            active: !!fleet.active,
          };
          currentView.value = 'maintenance';
          window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        const submitFleet = async () => {
          const payload = {
            name: String(fleetForm.value.name || '').trim(),
            billing_transportista_id: fleetForm.value.billing_transportista_id || null,
            transportista_ids: (fleetForm.value.transportista_ids || []).map(id => Number(id)),
            active: !!fleetForm.value.active,
          };

          if (!payload.name || payload.transportista_ids.length === 0) {
            Swal.fire('Faltan datos', 'Debes indicar un nombre y al menos un chofer incluido.', 'warning');
            return;
          }

          savingFleet.value = true;
          try {
            const isEditing = !!fleetForm.value.id;
            const url = isEditing
              ? `{{ url('/pago-choferes/configuracion/flotas') }}/${fleetForm.value.id}`
              : `{{ route('pago-choferes.settings.fleets.store') }}`;
            const res = await fetch(url, {
              method: isEditing ? 'PUT' : 'POST',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
              body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (!res.ok || !data.ok) {
              let errorMsg = data.message || data.error || 'No se pudo guardar el grupo.';
              if (data.errors) {
                errorMsg = Object.values(data.errors).flat().join('<br>');
              }
              Swal.fire({ title: 'Error', html: errorMsg, icon: 'error' });
              return;
            }

            const normalized = normalizeFleet(data.data);
            fleets.value = fleets.value
              .filter(item => String(item.id) !== String(normalized.id))
              .concat([normalized])
              .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
            resetFleetForm();
            Swal.fire('Guardado', 'La agrupacion de choferes fue actualizada.', 'success');
          } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Hubo un problema de conexion al guardar el grupo.', 'error');
          }
          savingFleet.value = false;
        };

        const deleteFleet = async (fleet) => {
          const result = await Swal.fire({
            title: 'Eliminar agrupacion',
            text: `Se eliminara "${fleet.name}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
          });

          if (!result.isConfirmed) return;

          try {
            const res = await fetch(`{{ url('/pago-choferes/configuracion/flotas') }}/${fleet.id}`, {
              method: 'DELETE',
              headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
              Swal.fire('Error', data.message || data.error || 'No se pudo eliminar el grupo.', 'error');
              return;
            }

            fleets.value = fleets.value.filter(item => String(item.id) !== String(fleet.id));
            if (String(fleetForm.value.id || '') === String(fleet.id)) {
              resetFleetForm();
            }
            Swal.fire('Eliminado', 'La agrupacion fue eliminada.', 'success');
          } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Hubo un problema de conexion al eliminar el grupo.', 'error');
          }
        };

        const defaultZoneForm = () => ({
          id: null,
          name: '',
          priority: 'primary',
          type: 'circle',
          center_lat: null,
          center_lng: null,
          radius_km: null,
          polygon: '',
          is_soft: true,
        });

        const defaultConceptForm = () => ({
          id: null,
          concept_name: '',
          value_type: 'fixed',
          default_amount: 0,
          sign: 1,
          reference_concept_id: '',
          reference_multiplier: 1,
          active: true,
        });

        const openSettingsLink = (tab, focus) => {
          const url = new URL(settingsBaseUrl, window.location.origin);
          if (focus) {
            url.searchParams.set('focus', focus);
          }
          url.hash = tab;
          window.open(url.toString(), '_blank', 'noopener');
        };

        const findConceptOptionByValue = (conceptValue) => {
          return (catalogOptions.value.concept_key || []).find(opt => String(opt.value) === String(conceptValue)) || null;
        };

        const openZoneDetails = (zoneId, idx = -1) => {
          if (!zoneId) return;

          const zone = maintenanceZones.value.find(item => String(item.id) === String(zoneId));
          if (!zone) return;

          zoneForm.value = {
            id: zone.id,
            name: zone.name || '',
            priority: zone.priority || 'primary',
            type: zone.type || 'circle',
            center_lat: zone.center_lat !== undefined ? zone.center_lat : null,
            center_lng: zone.center_lng !== undefined ? zone.center_lng : null,
            radius_km: zone.radius_km !== undefined ? zone.radius_km : null,
            polygon: Array.isArray(zone.polygon) ? JSON.stringify(zone.polygon) : (zone.polygon || ''),
            is_soft: zone.is_soft !== false,
          };

          openModal('zoneModal', idx);
        };

        const openZoneCreateModal = (idx = -1) => {
          zoneForm.value = defaultZoneForm();
          openModal('zoneModal', idx);
        };

        const openConceptDetails = (conceptId, idx = -1) => {
          if (!conceptId) return;

          const concept = maintenanceConcepts.value.find(item => String(item.id) === String(conceptId));
          if (!concept) return;

          conceptForm.value = {
            id: concept.id,
            concept_name: concept.name || '',
            value_type: concept.value_type || 'fixed',
            default_amount: concept.default_amount !== null && concept.default_amount !== undefined ? Number(concept.default_amount) : 0,
            sign: concept.sign !== null && concept.sign !== undefined ? Number(concept.sign) : 1,
            reference_concept_id: concept.reference_concept_id !== null && concept.reference_concept_id !== undefined ? Number(concept.reference_concept_id) : '',
            reference_multiplier: concept.reference_multiplier !== null && concept.reference_multiplier !== undefined ? Number(concept.reference_multiplier) : 1,
            active: concept.active !== false,
          };

          openModal('conceptModal', idx);
        };

        const openConceptDetailsByValue = (conceptValue, idx = -1) => {
          const option = findConceptOptionByValue(conceptValue);
          if (!option || !option.id) return;
          openConceptDetails(option.id, idx);
        };

        const openConceptCreateModal = (idx = -1) => {
          conceptForm.value = defaultConceptForm();
          openModal('conceptModal', idx);
        };

        const conceptSummary = (concept) => {
          if (!concept) return '';
          const impact = Number(concept.sign || 1) === -1 ? 'resta' : 'suma';
          if (concept.value_type === 'reference') {
            const baseName = concept.reference_concept?.name || 'Sin base';
            const multiplier = Number(concept.reference_multiplier || 1).toFixed(4);
            return `${baseName} x ${multiplier} | ${impact}`;
          }

          return `$ ${Number(concept.default_amount || 0).toFixed(2)} | ${impact}`;
        };

        const submitVehicleMap = async () => {
          const excelValue = String(vehicleMapForm.value.excel_value || '').trim().toLowerCase();
          const vehicleType = String(vehicleMapForm.value.vehicle_type || '').trim();

          if (!excelValue || !vehicleType) {
            Swal.fire('Faltan datos', 'Debes completar el valor del Excel y el tipo de vehículo.', 'warning');
            return;
          }

          savingVehicleMap.value = true;
          try {
            const res = await fetch(`{{ route('pago-choferes.settings.vehicle-maps.store') }}`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
              body: JSON.stringify({
                excel_value: excelValue,
                vehicle_type: vehicleType,
              })
            });

            const data = await res.json();
            if (!res.ok) {
              let errorMsg = data.message || data.error || 'No se pudo guardar el mapeo.';
              if (data.errors) {
                errorMsg = Object.values(data.errors).flat().join('<br>');
              }
              Swal.fire({ title: 'Error', html: errorMsg, icon: 'error' });
              return;
            }

            vehicleMaps.value.push(data.data);
            vehicleMaps.value = vehicleMaps.value.slice().sort((a, b) => String(a.excel_value).localeCompare(String(b.excel_value)));
            vehicleMapForm.value = { excel_value: '', vehicle_type: '' };
            Swal.fire('Guardado', 'El mapeo de unidad fue agregado.', 'success');
          } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Hubo un problema de conexión al guardar el mapeo.', 'error');
          }
          savingVehicleMap.value = false;
        };

        const deleteVehicleMap = async (map) => {
          const result = await Swal.fire({
            title: '¿Eliminar mapeo?',
            text: `Se eliminará el mapeo "${map.excel_value}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
          });

          if (!result.isConfirmed) return;

          try {
            const res = await fetch(`{{ url('/pago-choferes/configuracion/vehicle-maps') }}/${map.id}`, {
              method: 'DELETE',
              headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });

            const data = await res.json();
            if (!res.ok || !data.ok) {
              Swal.fire('Error', data.message || data.error || 'No se pudo eliminar el mapeo.', 'error');
              return;
            }

            vehicleMaps.value = vehicleMaps.value.filter(item => item.id !== map.id);
            Swal.fire('Eliminado', 'El mapeo fue eliminado.', 'success');
          } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Hubo un problema de conexión al eliminar el mapeo.', 'error');
          }
        };

        const fetchRules = async () => {
          loading.value = true;
          try {
            const res = await fetch(`${apiBase}/rules`, { headers: { 'Accept': 'application/json' }});
            rules.value = await res.json();
          } catch (err) {
            console.error(err);
          }
          loading.value = false;
        };

        const fetchOptions = async () => {
          try {
            const res = await fetch(`${apiBase}/options`, { headers: { 'Accept': 'application/json' }});
            catalogOptions.value = await res.json();
          } catch (err) {
            console.error(err);
          }
        };

        const activeConditionIndex = ref(-1);

        const openModal = (modalId, idx = -1) => {
           activeConditionIndex.value = idx;
           let modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
           if (!modal) {
              modal = new bootstrap.Modal(document.getElementById(modalId));
           }
           modal.show();
        };

        const closeModal = (modalId) => {
           const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
           if (modal) modal.hide();
        };

        const submitZone = async () => {
           savingModal.value = true;
           try {
              const isEditing = !!zoneForm.value.id;
              const res = await fetch(isEditing
                 ? `{{ url('/traffic/zones') }}/${zoneForm.value.id}`
                 : `{{ route('traffic.zones.store') }}`, {
                 method: isEditing ? 'PUT' : 'POST',
                 headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                 body: JSON.stringify(zoneForm.value)
              });
              if(res.ok) {
                 const json = await res.json();
                 await fetchOptions();
                 if (json.data) {
                    maintenanceZones.value = maintenanceZones.value
                      .filter(zone => String(zone.id) !== String(json.data.id))
                      .concat([json.data])
                      .sort((a, b) => String(a.name).localeCompare(String(b.name)));
                 }
                 closeModal('zoneModal');
                 if (activeConditionIndex.value >= 0 && json.data) {
                     form.value.conditions[activeConditionIndex.value].value = json.data.id;
                 }
                 zoneForm.value = defaultZoneForm();
                 Swal.fire('Guardado', 'La zona ha sido creada con éxito.', 'success');
              } else {
                 const data = await res.json();
                 let errorMsg = data.message || "Error desconocido";
                 if (data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('<br>');
                 }
                 Swal.fire('Error', errorMsg, 'error');
              }
           } catch (e) {
              console.error(e);
              Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
           }
           savingModal.value = false;
        };

        const submitCarrier = async () => {
           savingModal.value = true;
           try {
              const res = await fetch(`{{ route('traffic.transportistas.store') }}`, {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                 body: JSON.stringify(carrierForm.value)
              });
              if(res.ok) {
                 let json = null;
                 try { json = await res.json(); } catch(e) {}
                 await fetchOptions();
                 closeModal('carrierModal');
                 if (activeConditionIndex.value >= 0 && json && json.data) {
                     form.value.conditions[activeConditionIndex.value].value = json.data.id;
                 }
                 carrierForm.value = { name: '', business_name: '', tax_id: '', email: '', phone: '', dni: '', status: 'active', liquidation_tipo_periodo: 'quincenal', cbu: '', account_number: '' };
                 Swal.fire('Guardado', 'El transportista ha sido creado con éxito.', 'success');
              } else {
                 const data = await res.json();
                 let errorMsg = data.message || "Error desconocido";
                 if (data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('<br>');
                 }
                 Swal.fire('Error', errorMsg, 'error');
              }
           } catch (e) { 
              console.error(e); 
              Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
           }
           savingModal.value = false;
        };

        const submitConcept = async () => {
           savingModal.value = true;
           try {
              const isEditing = !!conceptForm.value.id;
              const payload = {
                concept_name: String(conceptForm.value.concept_name || '').trim(),
                name: String(conceptForm.value.concept_name || '').trim(),
                value_type: conceptForm.value.value_type,
                default_amount: conceptForm.value.value_type === 'fixed'
                  ? Number(conceptForm.value.default_amount || 0)
                  : null,
                sign: Number(conceptForm.value.sign || 1) === -1 ? -1 : 1,
                reference_concept_id: conceptForm.value.value_type === 'reference' && conceptForm.value.reference_concept_id !== '' && conceptForm.value.reference_concept_id !== null
                  ? Number(conceptForm.value.reference_concept_id)
                  : null,
                reference_multiplier: conceptForm.value.value_type === 'reference'
                  ? Number(conceptForm.value.reference_multiplier || 1)
                  : null,
                active: !!conceptForm.value.active,
              };

              const res = await fetch(isEditing
                 ? `{{ url('/pago-choferes/configuracion/conceptos') }}/${conceptForm.value.id}`
                 : `{{ route('pago-choferes.settings.concepts.store') }}`, {
                 method: isEditing ? 'PUT' : 'POST',
                 headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                 body: JSON.stringify(payload)
              });
              if(res.ok) {
                 const json = await res.json();
                 await fetchOptions();
                 if (json.data) {
                    maintenanceConcepts.value = maintenanceConcepts.value
                      .filter(concept => String(concept.id) !== String(json.data.id))
                      .concat([json.data])
                      .sort((a, b) => String(a.name).localeCompare(String(b.name)));
                 }
                 closeModal('conceptModal');
                 if (activeConditionIndex.value >= 0 && json.data) {
                     form.value.conditions[activeConditionIndex.value].value = json.data.name;
                 }
                 conceptForm.value = defaultConceptForm();
                 Swal.fire('Guardado', 'El concepto ha sido creado con éxito.', 'success');
              } else {
                 const data = await res.json();
                 let errorMsg = data.message || "Error desconocido";
                 if (data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('<br>');
                 }
                 Swal.fire({ title: 'Error de Validación', html: errorMsg, icon: 'error' });
              }
           } catch (e) { 
              console.error(e);
              Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
           }
           savingModal.value = false;
        };

        const deleteConcept = async (concept) => {
          const result = await Swal.fire({
            title: '¿Eliminar concepto?',
            text: `Se eliminará "${concept.name}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
          });

          if (!result.isConfirmed) return;

          const res = await fetch(`{{ url('/pago-choferes/configuracion/conceptos') }}/${concept.id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
          });

          if (!res.ok) {
            Swal.fire('Error', 'No se pudo eliminar el concepto.', 'error');
            return;
          }

          maintenanceConcepts.value = maintenanceConcepts.value.filter(item => String(item.id) !== String(concept.id));
          await fetchOptions();
          Swal.fire('Eliminado', 'El concepto fue eliminado.', 'success');
        };

        const submitDefaultPeriod = async () => {
          if (!defaultPeriodType.value) return;
          savingDefaultPeriod.value = true;
          try {
             const res = await fetch(defaultPeriodUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ period_type: defaultPeriodType.value })
             });
             if (!res.ok) throw new Error('Error guardando periodo');
             Swal.fire({ icon: 'success', title: 'Período guardado', text: 'Se ha guardado la configuración por defecto', timer: 1500, showConfirmButton: false });
          } catch(e) {
             console.error(e);
             Swal.fire({ icon: 'error', text: 'Ocurrió un error.' });
          } finally {
             savingDefaultPeriod.value = false;
          }
        };

        const submitVehicleAliases = async () => {
          savingVehicleAliases.value = true;
          try {
             const res = await fetch('{{ route('pago-choferes.settings.vehicle-aliases.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ aliases: vehicleAliases.value })
             });
             const data = await res.json();
             if (!res.ok || !data.ok) throw new Error(data.message || 'Error guardando alias');
             
             // Update the vehicleTypeOptions options to match the new names dynamically
             vehicleTypeOptions.value = Object.entries(vehicleAliases.value).map(([key, label]) => {
                return { value: key, label: label };
             });
             
             Swal.fire({ icon: 'success', title: 'Alias guardados', text: 'Se han actualizado los alias de tipos de vehículos', timer: 1500, showConfirmButton: false });
          } catch(e) {
             console.error(e);
             Swal.fire({ icon: 'error', text: e.message || 'Ocurrió un error al guardar los alias.' });
          } finally {
             savingVehicleAliases.value = false;
          }
        };

        const toggleActive = async (id) => {
          await fetch(`${apiBase}/rules/${id}/toggle`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
          });
          fetchRules();
        };

        const deleteRule = async (id) => {
          Swal.fire({
            title: '¿Estás seguro?',
            text: "¿Deseas eliminar esta regla de liquidación?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
          }).then(async (result) => {
            if (result.isConfirmed) {
              await fetch(`${apiBase}/rules/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
              });
              fetchRules();
              Swal.fire('¡Eliminada!', 'La regla ha sido eliminada.', 'success');
            }
          });
        };

        const createNewRule = () => {
          form.value = {
            id: null, name: '', priority: 0, is_modifier: false, scope: 'line', active: true,
            action_type: 'FIXED_AMOUNT', action_payload: { amount: 0 }, conditions: [{field: 'concept_key', operator: '=', value: ''}],
          };
          currentView.value = 'form';
        };

        const editRule = (rule) => {
          form.value = JSON.parse(JSON.stringify(rule));
          form.value.scope = form.value.scope || 'line';
          currentView.value = 'form';
        };

        const cloneRule = (rule) => {
          form.value = JSON.parse(JSON.stringify(rule));
          form.value.id = null;
          form.value.scope = form.value.scope || 'line';
          form.value.name = form.value.name + ' (Copia)';
          currentView.value = 'form';
        };

        const initPayload = () => {
          const pay = {};
          if(form.value.action_type === 'FIXED_AMOUNT') { pay.amount = 0; }
          else if(form.value.action_type === 'MULTIPLIER') { pay.multiplier_rate = 0; pay.multiply_by_field = Object.keys(availableNumericFields.value)[0] || 'kilometers'; pay.subtract_value = 0; }
          else if(form.value.action_type === 'BASE_PLUS_MULTIPLIER') { pay.base_amount = 0; pay.multiplier_rate = 0; pay.multiply_by_field = Object.keys(availableNumericFields.value)[0] || 'kilometers'; pay.subtract_value = 0; }
          else if(form.value.action_type === 'THRESHOLD_EXCESS') { pay.threshold_qty = 0; pay.base_amount = 0; pay.excess_rate = 0; pay.target_field = Object.keys(availableNumericFields.value)[0] || 'total_packages'; }
          else if(form.value.action_type === 'COMPOSITE') { pay.sub_rules = [{rule_id: '', weight: 1.0}]; }

          if (form.value.scope === 'receipt') {
            pay.receipt_concept = form.value.action_payload?.receipt_concept || '';
          }

          form.value.action_payload = pay;
        };

        const saveRule = async () => {
          const payloadDto = JSON.parse(JSON.stringify(form.value));
          payloadDto.conditions.forEach(c => {
             if(c.operator === 'IN' && typeof c.value === 'string') {
                 c.value = c.value.replace(/\[|\]/g, '').split(',').map(x => {
                     let t = x.trim();
                     if(!isNaN(t) && t !== '') return Number(t);
                     return t;
                 });
             }
          });

          // Duplicate rule check
          const isDuplicate = rules.value.some(r => {
             if (payloadDto.id && r.id === payloadDto.id) return false;
             
             const sameBasic = r.priority === payloadDto.priority &&
                              r.is_modifier === payloadDto.is_modifier &&
                              (r.scope || 'line') === (payloadDto.scope || 'line') &&
                              r.active === payloadDto.active &&
                              r.action_type === payloadDto.action_type;
             
             if (!sameBasic) return false;
             
             const samePayload = JSON.stringify(r.action_payload) === JSON.stringify(payloadDto.action_payload);
             if (!samePayload) return false;
             
             const sortConds = (conds) => {
                if (!conds) return [];
                return conds.slice().sort((a,b) => (a.field+a.operator+a.value).localeCompare(b.field+b.operator+b.value));
             };
             
             return JSON.stringify(sortConds(r.conditions)) === JSON.stringify(sortConds(payloadDto.conditions));
          });
          
          // Conflict check (same conditions, same priority, different action/value)
          const isConflict = rules.value.some(r => {
             if (payloadDto.id && r.id === payloadDto.id) return false;
             
             const sameBasic = r.priority === payloadDto.priority &&
                               r.is_modifier === payloadDto.is_modifier &&
                               r.active === payloadDto.active;
             
             if (!sameBasic) return false;
             
             const sortConds = (conds) => {
                if (!conds) return [];
                return conds.slice().sort((a,b) => (a.field+a.operator+a.value).localeCompare(b.field+b.operator+b.value));
             };
             
             return JSON.stringify(sortConds(r.conditions)) === JSON.stringify(sortConds(payloadDto.conditions));
          });

          if (isDuplicate) {
             Swal.fire({
                icon: 'error',
                title: 'Regla Duplicada',
                text: 'No se puede guardar porque ya existe una regla exactamente igual (mismas condiciones, acción y prioridad).'
             });
             return;
          } else if (isConflict) {
             const result = await Swal.fire({
                title: 'Posible Conflicto',
                text: 'Ya existe una regla con exactamente las mismas condiciones y prioridad, pero con distinto valor. El sistema tomará la regla más nueva. ¿Deseas guardarla de todas formas?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f1ab2b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, estoy seguro',
                cancelButtonText: 'Revisar'
             });
             if (!result.isConfirmed) return;
          }

          saving.value = true;
          const url = form.value.id ? `${apiBase}/rules/${form.value.id}` : `${apiBase}/rules`;
          const method = form.value.id ? 'PUT' : 'POST';

          try {
            const res = await fetch(url, {
              method: method,
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
              body: JSON.stringify(payloadDto)
            });
            const data = await res.json();
            if(res.ok && data.success) {
               fetchRules();
               currentView.value = 'list';
            } else {
               Swal.fire('Error de validación', JSON.stringify(data.errors || data.error), 'error');
            }
          } catch (err) {
            console.error(err);
            Swal.fire('Error', 'Hubo un error de conexión', 'error');
          }
          saving.value = false;
        };

        const openSimulator = (rule) => {
          if (rule) {
            simulatorRuleId.value = rule.id;
            simulatorRuleData.value = rule;
          } else {
            simulatorRuleId.value = null;
            simulatorRuleData.value = null;
          }
          simResult.value = null;
          currentView.value = 'simulate';
        };

        const runSimulation = async () => {
          simulating.value = true;
          try {
            const body = {
               simulate_mode: simulatorRuleId.value ? 'single_rule' : 'full_engine',
               context: simContext.value
            };
            
            if (simulatorRuleData.value) {
                body.rule_data = simulatorRuleData.value;
            }

            const res = await fetch(`${apiBase}/simulate`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
              body: JSON.stringify(body)
            });
            const data = await res.json();
            
            if (!res.ok) {
               alert("Error en simulación: " + (data.message || data.error));
            } else {
               simResult.value = data;
            }
          } catch (err) {
            console.error(err);
          }
          simulating.value = false;
        };

        onMounted(() => {
            fetchRules();
            fetchOptions();
        });

        watch([filters, sortKey, sortAsc, pageSize], () => {
          currentPage.value = 1;
        }, { deep: true });

        watch(totalPages, (value) => {
          if (currentPage.value > value) {
            currentPage.value = value;
          }
        });

        const getConditionValueLabel = (field, val) => {
             if (!catalogOptions.value[field]) return val;
             if (Array.isArray(val)) {
                 return val.map(v => {
                     const opt = catalogOptions.value[field].find(o => String(o.value) === String(v));
                     return opt ? opt.label : v;
                 }).join(', ');
             } else {
                 const opt = catalogOptions.value[field].find(o => String(o.value) === String(val));
                 return opt ? opt.label : val;
             }
        };

        const formatConditions = (conds) => {
          if (!conds || conds.length === 0) return [];
          const operatorLabels = {
            '=': 'igual a',
            '!=': 'distinto de',
            '>': 'mayor que',
            '<': 'menor que',
            '>=': 'mayor o igual que',
            '<=': 'menor o igual que',
            'IN': 'en lista',
            'CONTAINS': 'contiene'
          };
          return conds.map(c => {
             const label = fieldCatalog.value[c.field] ? fieldCatalog.value[c.field].label : c.field;
             const valLabel = getConditionValueLabel(c.field, c.value);
             const opLabel = operatorLabels[c.operator] || c.operator;
             return `${label} ${opLabel} ${valLabel}`;
          });
        };

        const formatRuleForSelect = (r) => {
            const condsText = formatConditions(r.conditions).join('  AND  ');
            let actionText = '';
            if (r.action_type === 'FIXED_AMOUNT') {
               actionText = `$${r.action_payload?.amount || 0}`;
            } else if (r.action_type === 'MULTIPLIER') {
               actionText = `Multiplicador x${r.action_payload?.rate || r.action_payload?.multiplier_rate || 0}`;
            } else if (r.action_type === 'NO_PAYMENT') {
               actionText = 'Sin Pago';
            } else if (r.action_type === 'THRESHOLD_EXCESS') {
               actionText = `Exceso > ${r.action_payload?.threshold_qty||0} (+$${r.action_payload?.excess_rate||0})`;
            } else if (r.action_type === 'COMPOSITE') {
               actionText = 'Suma Reglas';
            } else {
               actionText = r.action_type;
            }

            let label = String(r.name).toUpperCase();
            if (actionText) label += `  |  ${actionText}`;
            if (condsText) label += `  |  [ ${condsText} ]`;
            
            return label;
        };

        return {
          currentView, loading, saving, savingModal, simulating, rules, fieldCatalog, availableFieldCatalog, numericFields, availableNumericFields,
          form, simulatorRuleId, simulatorRuleData, simContext, simResult, catalogOptions, currentPage, pageSize, totalPages, paginatedRules, paginationStart, paginationEnd,
          zoneForm, carrierForm, conceptForm, filters, sortKey, sortAsc, filteredAndSortedRules,
          vehicleMaps, vehicleMapForm, vehicleTypeOptions, savingVehicleMap, maintenanceConcepts, conceptQuery, filteredConcepts,
          fleets, fleetForm, transportistaOptions, savingFleet,
          fetchRules, toggleActive, deleteRule, createNewRule, editRule, cloneRule, formatConditions, formatRuleForSelect,
          initPayload, saveRule, openSimulator, runSimulation, openModal, closeModal, defaultCondition, onScopeChange, goToPage,
          submitZone, submitCarrier, submitConcept, sortBy, sortIcon, resetFilters,
          submitVehicleMap, deleteVehicleMap, vehicleTypeLabel,
          openZoneDetails, openZoneCreateModal, openConceptDetails, openConceptDetailsByValue, openConceptCreateModal, conceptSummary, deleteConcept,
          submitFleet, editFleet, deleteFleet, resetFleetForm,
          defaultPeriodType, savingDefaultPeriod, submitDefaultPeriod,
          vehicleAliases, savingVehicleAliases, submitVehicleAliases
        };
      }
    });

    const mountApp = () => {
        const target = document.querySelector('#rule-engine-app');
        if (target) {
            app.mount('#rule-engine-app');
            console.log("Vue montado exitosamente en #rule-engine-app");
        } else {
            setTimeout(mountApp, 50);
        }
    };
    mountApp();
  } catch (e) {
    console.error("VUE CRASH ERROR:", e);
    alert("Hubo un error del sistema al cargar el motor de reglas. Revisa la consola.");
  }
</script>
