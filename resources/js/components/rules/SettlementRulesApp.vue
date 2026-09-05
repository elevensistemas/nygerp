<template>
  <div>
    <!-- TOP NAVBAR -->
    <div class="d-flex justify-content-between mb-3 bg-white p-3 border rounded shadow-sm">
      <div>
        <button class="btn btn-outline-primary me-2" @click="currentView = 'list'" :class="{'active': currentView === 'list'}">
          <i class="fa fa-list"></i> Listado de Reglas
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

    <!-- LIST VIEW -->
    <div v-if="currentView === 'list'" class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th width="80" class="cursor-pointer text-nowrap" @click="sortBy('id')">ID <i class="fa" :class="sortIcon('id')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sortBy('name')">Nombre <i class="fa" :class="sortIcon('name')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sortBy('type')">Tipo <i class="fa" :class="sortIcon('type')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sortBy('priority')">Prioridad <i class="fa" :class="sortIcon('priority')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sortBy('action')">Action / Condiciones <i class="fa" :class="sortIcon('action')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sortBy('status')">Estado <i class="fa" :class="sortIcon('status')"></i></th>
              <th class="text-end">Opciones</th>
            </tr>
            <tr class="bg-light">
              <th><input type="text" class="form-control form-control-sm" v-model="filters.id" placeholder="Filtrar ID"></th>
              <th><input type="text" class="form-control form-control-sm" v-model="filters.name" placeholder="Filtrar Nombre"></th>
              <th>
                <select class="form-select form-select-sm" v-model="filters.type">
                  <option value="">Todos</option>
                  <option value="base">Base</option>
                  <option value="modifier">Modifier</option>
                </select>
              </th>
              <th><input type="text" class="form-control form-control-sm" v-model="filters.priority" placeholder="Filtrar Prio"></th>
              <th><input type="text" class="form-control form-control-sm" v-model="filters.action" placeholder="Filtrar Action"></th>
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
              <td colspan="7" class="text-center py-4"><i class="fa fa-spin fa-spinner text-primary fs-3"></i></td>
            </tr>
            <tr v-else-if="rules.length === 0">
              <td colspan="7" class="text-center py-4 text-muted">No existen reglas configuradas.</td>
            </tr>
            <tr v-for="rule in filteredAndSortedRules" :key="rule.id" :class="{'opacity-50': !rule.active}">
              <td><b>#{{ rule.id }}</b></td>
              <td>
                <span class="fw-bold">{{ rule.name }}</span>
              </td>
              <td>
                <span v-if="rule.is_modifier" class="badge bg-info">Modifier</span>
                <span v-else class="badge bg-primary">Base Rule</span>
              </td>
              <td class="text-center"><span class="badge bg-secondary fs-6">{{ rule.priority }}</span></td>
              <td>
                <div><span class="text-uppercase fw-bold text-success">{{ rule.action_type }}</span></div>
                <div class="small text-muted mt-1">
                  {{ rule.conditions.length }} cond. {{ rule.conditions.slice(0, 2).map(c => c.field + ' ' + c.operator + ' ' + c.value).join(' AND ') }} {{ rule.conditions.length > 2 ? '...' : '' }}
                </div>
              </td>
              <td>
                <div class="form-check form-switch cursor-pointer">
                  <input class="form-check-input cursor-pointer" type="checkbox" :checked="rule.active" @change="toggleActive(rule.id)">
                </div>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-success me-1" @click="openSimulator(rule)" title="Probar Regla Sola">
                  <i class="fa fa-play text-success"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary me-1" @click="editRule(rule)" title="Editar">
                  <i class="fa fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-info me-1" @click="cloneRule(rule)" title="Clonar">
                  <i class="fa fa-copy"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" @click="deleteRule(rule.id)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- FORM VIEW -->
    <div v-if="currentView === 'form'" class="card shadow-sm">
      <div class="card-header bg-light">
        <h5 class="mb-0">{{ form.id ? 'Editar Regla #' + form.id : 'Nueva Regla' }}</h5>
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
                <option v-for="(type, field) in fieldCatalog" :value="field">{{ field }}</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1" v-if="index === 0">Operador</label>
              <select class="form-select" v-model="cond.operator">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">></option>
                <option value="<"><</option>
                <option value=">=">>=</option>
                <option value="<="><=</option>
                <option value="IN">IN</option>
                <option value="CONTAINS">CONTAINS</option>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label small mb-1" v-if="index === 0">Valor</label>
              
              <!-- Typed Input logic based on Field Catalog -->
              <template v-if="cond.operator === 'IN'">
                 <input type="text" class="form-control" v-model="cond.value" placeholder="Array ej: [1,2] o 1,2,3">
              </template>
              <template v-else-if="cond.field && fieldCatalog[cond.field].type === 'boolean'">
                 <select class="form-select" v-model="cond.value">
                   <option :value="true">Verdadero (True)</option>
                   <option :value="false">Falso (False)</option>
                 </select>
              </template>
              <template v-else-if="cond.field && fieldCatalog[cond.field].type === 'number'">
                 <input type="number" step="0.01" class="form-control" v-model.number="cond.value">
              </template>
              <template v-else>
                 <input type="text" class="form-control" v-model="cond.value">
              </template>
            </div>
            <div class="col-md-1">
              <button class="btn btn-outline-danger w-100" @click="form.conditions.splice(index, 1)"><i class="fa fa-times"></i></button>
            </div>
          </div>
          
          <button class="btn btn-sm btn-outline-primary mt-2" @click="form.conditions.push({field: 'kilometers', operator: '>', value: ''})">
            + Agregar Condición (AND)
          </button>
        </div>

        <h6 class="fw-bold mb-3">2. Acción (Tarifa a aplicar)</h6>
        <div class="row mb-3 border p-3 bg-white rounded shadow-sm ms-0 me-0">
          <div class="col-md-4">
            <label class="form-label">Tipo de Handler</label>
            <select class="form-select border-primary" v-model="form.action_type" @change="initPayload">
              <option value="FIXED_AMOUNT">Fixed Amount (Monto Fijo)</option>
              <option value="MULTIPLIER">Multiplier (Multiplicador)</option>
              <option value="BASE_PLUS_MULTIPLIER">Base + Multiplier (Fijo + Variable)</option>
              <option value="THRESHOLD_EXCESS">Threshold Excess (Cobrar Excedente)</option>
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
             
             <div v-if="form.action_type === 'MULTIPLIER'" class="row g-2">
                <div class="col-6">
                  <label class="form-label">multiply_by_field</label>
                  <select class="form-select" v-model="form.action_payload.multiply_by_field">
                    <option v-for="(type, field) in numericFields" :value="field">{{ field }}</option>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label">multiplier_rate (Tarifa $)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.multiplier_rate">
                </div>
             </div>

             <div v-if="form.action_type === 'BASE_PLUS_MULTIPLIER'" class="row g-2">
                <div class="col-4">
                  <label class="form-label">base_amount ($)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.base_amount">
                </div>
                <div class="col-4">
                  <label class="form-label">multiplier_rate ($)</label>
                  <input type="number" step="0.01" class="form-control" v-model.number="form.action_payload.multiplier_rate">
                </div>
                <div class="col-4">
                  <label class="form-label">multiply_by_field</label>
                  <select class="form-select" v-model="form.action_payload.multiply_by_field">
                    <option v-for="(type, field) in numericFields" :value="field">{{ field }}</option>
                  </select>
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
                    <option v-for="(type, field) in numericFields" :value="field">{{ field }}</option>
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
          <i class="fa fa-save"></i> {{ saving ? 'Guardando...' : 'Guardar Regla' }}
        </button>
      </div>
    </div>

    <!-- SIMULATOR MODAL (Uses Bootstrap classes logically, toggled via v-if to full screen or fixed panel) -->
    <div v-if="currentView === 'simulate'" class="card border-warning shadow-lg">
      <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fa fa-flask"></i> Laboratorio de Simulación ({{ simulatorRuleId ? 'Regla Particular #' + simulatorRuleId : 'Engine Completo' }})</span>
        <button class="btn btn-sm btn-dark" @click="currentView = 'list'">Cerrar Simulador</button>
      </div>
      <div class="card-body">
        <div class="row">
           <div class="col-md-6 border-end">
              <h6 class="mb-3 text-muted">Variables de Entorno (Context)</h6>
              <div class="row g-2">
                 <div class="col-6 mb-2" v-for="(typeObj, field) in fieldCatalog" :key="field">
                    <label class="form-label small mb-1">{{ field }}</label>
                    <input v-if="typeObj.type === 'number'" type="number" class="form-control form-control-sm" v-model.number="simContext[field]">
                    <select v-else-if="typeObj.type === 'boolean'" class="form-select form-select-sm" v-model="simContext[field]">
                       <option :value="true">True</option>
                       <option :value="false">False</option>
                    </select>
                    <input v-else-if="typeObj.type === 'date'" type="date" class="form-control form-control-sm" v-model="simContext[field]">
                    <input v-else type="text" class="form-control form-control-sm" v-model="simContext[field]">
                 </div>
              </div>
              <button class="btn btn-primary w-100 mt-3" @click="runSimulation" :disabled="simulating">
                 {{ simulating ? 'Corriendo Motor...' : 'Ejecutar Cálculo' }}
              </button>
           </div>
           <div class="col-md-6">
              <h6 class="mb-3 text-muted">Resultados del Cálculo</h6>
              <div v-if="simResult">
                 <div v-if="!simResult.matched" class="alert alert-warning">
                    No hubo coincidencias. Condiciones no cumplidas o reglas insuficientes.
                 </div>
                 <div v-else>
                    <div class="display-6 text-success fw-bold text-center mb-3">
                       $ {{ simResult.total_amount ?? simResult.calculated_amount }}
                    </div>
                    <hr>
                    <strong>Traza de Ejecución (Breakdown)</strong>
                    <pre class="bg-dark text-light p-3 rounded mt-2" style="font-size:12px; max-height:400px;">{{ JSON.stringify(simResult.trace ?? simResult.breakdown, null, 2) }}</pre>
                 </div>
              </div>
              <div v-else class="text-center text-muted p-5">
                 Aún no has simulado nada.
              </div>
           </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      apiBase: window.RuleEngineConfig.apiBase,
      currentView: 'list', // 'list', 'form', 'simulate'
      loading: false,
      saving: false,
      simulating: false,
      rules: [],
      filters: {
        id: '',
        name: '',
        type: '',
        priority: '',
        action: '',
        status: ''
      },
      sortKey: 'id',
      sortAsc: false,
      fieldCatalog: {
        'trip_date': { type: 'date' },
        'carrier_id': { type: 'number' },
        'carrier_name': { type: 'string' },
        'concept_name': { type: 'string' },
        'concept_key': { type: 'string' },
        'zone_id': { type: 'number' },
        'vehicle_type': { type: 'string' },
        'model_year': { type: 'number' },
        'kilometers': { type: 'number' },
        'delivered_packages': { type: 'number' },
        'absent_packages': { type: 'number' },
        'stops': { type: 'number' },
        'is_remote_zone': { type: 'boolean' },
        'total_packages': { type: 'number' },
      },
      form: {
        id: null,
        name: '',
        priority: 0,
        is_modifier: false,
        active: true,
        action_type: 'FIXED_AMOUNT',
        action_payload: { amount: 0 },
        conditions: [],
      },
      // Simulator data
      simulatorRuleId: null,
      simulatorRuleData: null,
      simContext: {
        trip_date: new Date().toISOString().slice(0,10),
        carrier_id: 1,
        concept_name: '',
        concept_key: '',
        zone_id: null,
        vehicle_type: 'general',
        model_year: 2020,
        kilometers: 0,
        delivered_packages: 0,
        absent_packages: 0,
        stops: 0,
        is_remote_zone: false,
        total_packages: 0
      },
      simResult: null,
    };
  },
  computed: {
    numericFields() {
      const res = {};
      for (const [key, val] of Object.entries(this.fieldCatalog)) {
        if (val.type === 'number') res[key] = val;
      }
      return res;
    },
    filteredAndSortedRules() {
      let result = this.rules;

      // Filtering
      if (this.filters.id) {
        result = result.filter(r => String(r.id).includes(this.filters.id));
      }
      if (this.filters.name) {
        const query = this.filters.name.toLowerCase();
        result = result.filter(r => String(r.name).toLowerCase().includes(query));
      }
      if (this.filters.type) {
        result = result.filter(r => {
          const t = r.is_modifier ? 'modifier' : 'base';
          return t.includes(this.filters.type.toLowerCase());
        });
      }
      if (this.filters.priority !== '') {
        result = result.filter(r => String(r.priority).includes(this.filters.priority));
      }
      if (this.filters.action) {
        const query = this.filters.action.toLowerCase();
        result = result.filter(r => String(r.action_type).toLowerCase().includes(query));
      }
      if (this.filters.status !== '') {
        const statusBool = this.filters.status === 'true';
        result = result.filter(r => r.active === statusBool);
      }

      // Sorting
      result = result.sort((a, b) => {
        let valA, valB;
        switch (this.sortKey) {
          case 'id': valA = a.id; valB = b.id; break;
          case 'name': valA = a.name.toLowerCase(); valB = b.name.toLowerCase(); break;
          case 'type': valA = a.is_modifier; valB = b.is_modifier; break;
          case 'priority': valA = a.priority; valB = b.priority; break;
          case 'action': valA = a.action_type; valB = b.action_type; break;
          case 'status': valA = a.active; valB = b.active; break;
          default: valA = a.id; valB = b.id; break;
        }

        if (valA < valB) return this.sortAsc ? -1 : 1;
        if (valA > valB) return this.sortAsc ? 1 : -1;
        return 0;
      });

      return result;
    }
  },
  mounted() {
    this.fetchRules();
  },
  methods: {
    sortBy(key) {
      if (this.sortKey === key) {
        this.sortAsc = !this.sortAsc;
      } else {
        this.sortKey = key;
        this.sortAsc = true;
      }
    },
    sortIcon(key) {
      if (this.sortKey !== key) return 'fa-sort text-muted';
      return this.sortAsc ? 'fa-sort-up text-primary' : 'fa-sort-down text-primary';
    },
    resetFilters() {
      this.filters = {
        id: '',
        name: '',
        type: '',
        priority: '',
        action: '',
        status: ''
      };
    },
    async fetchRules() {
      this.loading = true;
      try {
        const res = await fetch(`${this.apiBase}/rules`, {
          headers: {'Accept': 'application/json'}
        });
        this.rules = await res.json();
      } catch (err) {
        alert("Error cargando reglas");
      }
      this.loading = false;
    },
    async toggleActive(id) {
      await fetch(`${this.apiBase}/rules/${id}/toggle`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': window.RuleEngineConfig.csrf
        }
      });
      this.fetchRules();
    },
    async deleteRule(id) {
      if(!confirm("¿Seguro de borrar esta regla de liquidación lógica?")) return;
      await fetch(`${this.apiBase}/rules/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': window.RuleEngineConfig.csrf
        }
      });
      this.fetchRules();
    },
    createNewRule() {
      this.form = {
        id: null,
        name: '',
        priority: 0,
        is_modifier: false,
        active: true,
        action_type: 'FIXED_AMOUNT',
        action_payload: { amount: 0 },
        conditions: [],
      };
      this.currentView = 'form';
    },
    editRule(rule) {
      this.form = JSON.parse(JSON.stringify(rule)); // deep clone
      this.currentView = 'form';
    },
    cloneRule(rule) {
      this.form = JSON.parse(JSON.stringify(rule)); // deep clone
      this.form.id = null; // eliminate ID to create instead of edit
      this.form.name = this.form.name + ' (Copia)';
      this.currentView = 'form';
    },
    initPayload() {
      const pay = {};
      if(this.form.action_type === 'FIXED_AMOUNT') {
         pay.amount = 0;
      } else if(this.form.action_type === 'MULTIPLIER') {
         pay.multiplier_rate = 0;
         pay.multiply_by_field = 'kilometers';
      } else if(this.form.action_type === 'BASE_PLUS_MULTIPLIER') {
         pay.base_amount = 0;
         pay.multiplier_rate = 0;
         pay.multiply_by_field = 'kilometers';
      } else if(this.form.action_type === 'THRESHOLD_EXCESS') {
         pay.threshold_limit = 0;
         pay.excess_multiplier_rate = 0;
         pay.evaluate_field = 'kilometers';
      }
      this.form.action_payload = pay;
    },
    async saveRule() {
      // pre-process condition values specially IN
      const payloadDto = JSON.parse(JSON.stringify(this.form));
      payloadDto.conditions.forEach(c => {
         if(c.operator === 'IN' && typeof c.value === 'string') {
             // Basic naive parse of comma separated into array
             c.value = c.value.replace(/\[|\]/g, '').split(',').map(x => {
                 let t = x.trim();
                 if(!isNaN(t) && t !== '') return Number(t);
                 return t;
             });
         }
      });

      this.saving = true;
      const url = this.form.id ? `${this.apiBase}/rules/${this.form.id}` : `${this.apiBase}/rules`;
      const method = this.form.id ? 'PUT' : 'POST';

      try {
        const res = await fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.RuleEngineConfig.csrf
          },
          body: JSON.stringify(payloadDto)
        });
        const data = await res.json();
        if(res.ok && data.success) {
           this.fetchRules();
           this.currentView = 'list';
        } else {
           alert("Error de validación: " + JSON.stringify(data.errors || data.error));
        }
      } catch (err) {
        alert("Error de red guardando la regla");
      }
      this.saving = false;
    },
    openSimulator(rule) {
      if (rule) {
        this.simulatorRuleId = rule.id;
        this.simulatorRuleData = rule;
      } else {
        this.simulatorRuleId = null;
        this.simulatorRuleData = null;
      }
      this.simResult = null;
      this.currentView = 'simulate';
    },
    async runSimulation() {
      this.simulating = true;
      try {
        const body = {
           simulate_mode: this.simulatorRuleId ? 'single_rule' : 'full_engine',
           context: this.simContext
        };
        
        if (this.simulatorRuleData) {
            body.rule_data = this.simulatorRuleData;
        }

        const res = await fetch(`${this.apiBase}/simulate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.RuleEngineConfig.csrf
          },
          body: JSON.stringify(body)
        });
        const data = await res.json();
        
        if (!res.ok) {
           alert("Error en simulación: " + (data.message || data.error));
        } else {
           this.simResult = data;
        }
      } catch (err) {
        alert("Fallo de conexión al simular");
      }
      this.simulating = false;
    }
  }
}
</script>
