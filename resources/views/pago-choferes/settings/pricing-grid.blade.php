<div id="pricing-pivot-app" class="col-12">
  <div class="card card-body driver-settings-subcard h-100" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 mb-0">Matriz de Valores (Overrides)</h3>
      <div class="text-primary small fw-bold" v-if="saveStatus === 'saving'">Guardando...</div>
      <div class="text-success small fw-bold" v-if="saveStatus === 'saved'">Guardado <i class="fa-solid fa-check"></i></div>
      <div class="text-danger small fw-bold" v-if="saveStatus === 'error'">Error al guardar</div>
    </div>
    <p class="small text-muted mb-3">Precedencia de lectura: concepto general -> zona (vehiculo general) -> zona + vehiculo -> zona + vehiculo + año.</p>

    <div class="row g-2 mb-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label small mb-1">Agrupar grilla por</label>
        <select v-model="groupBy" class="form-select form-select-sm no-select2">
          <option value="zone">Zona</option>
          <option value="concept">Concepto</option>
          <option value="vehicle">Tipo de Transporte</option>
        </select>
      </div>
      <div class="col-md-7">
        <label class="form-label small mb-1">Seleccionar @{{ groupLabel }}</label>
        <select v-model="groupValue" class="form-select form-select-sm no-select2">
          <option value="">Seleccione...</option>
          <option v-for="opt in groupOptions" :key="opt.id" :value="opt.id">@{{ opt.name }}</option>
        </select>
      </div>
    </div>

    <div class="table-responsive driver-settings-scroller" v-if="groupValue && rows.length > 0 && cols.length > 0">
      <table class="table table-sm table-bordered align-middle style-excel mb-0">
        <thead>
          <tr>
            <th class="bg-light stick-left" style="min-width: 140px; z-index: 2">@{{ rowLabel }} \ @{{ colLabel }}</th>
            <th class="bg-light text-center" v-for="col in cols" :key="col.id" style="min-width: 130px;">@{{ col.name }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="bg-light fw-bold stick-left">@{{ row.name }}</td>
            <td v-for="col in cols" :key="col.id" class="p-0 position-relative">
              <template v-if="cellUsesModelYear(row.id, col.id)">
                <button
                  type="button"
                  class="btn btn-link btn-sm w-100 h-100 text-decoration-none text-start px-2 py-2 year-value-trigger"
                  @click="openYearValuesModal(row.id, col.id)"
                >
                  <span class="d-block fw-semibold text-primary">Por año</span>
                  <span class="small text-muted">@{{ getYearValueSummary(row.id, col.id) }}</span>
                </button>
              </template>
              <input
                v-else
                type="number"
                step="0.01"
                min="0"
                class="form-control form-control-sm border-0 bg-transparent text-end"
                :value="getDisplayCellValue(row.id, col.id)"
                @input="updateCellDraft(row.id, col.id, $event.target.value)"
                @blur="commitCell(row.id, col.id, $event.target.value)"
                @focus="startCellEdit(row.id, col.id); $event.target.select()"
                @keydown.enter.prevent="$event.target.blur()"
                @keydown.esc.prevent="cancelCellEdit(row.id, col.id, $event.target)"
                :class="{
                  'text-primary fw-bold': cellHasValue(row.id, col.id),
                  'driver-settings-cell-saving': isCellSaving(row.id, col.id)
                }"
                placeholder="-"
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="text-muted small text-center mt-5 mb-5 p-4 bg-light rounded" style="border: 2px dashed #dbe3f1">
      Seleccione una opcion arriba para cargar la grilla.
    </div>

    <div class="modal fade" id="zoneConceptYearValuesModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1">Valores por año</h5>
              <div class="small text-muted" v-if="yearModalContext">
                @{{ yearModalContext.zone_name }} · @{{ yearModalContext.vehicle_label }} · @{{ yearModalContext.concept_name }}
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 align-items-end mb-3">
              <div class="col-md-5">
                <label class="form-label">Año</label>
                <input type="number" min="1900" max="2100" step="1" class="form-control" v-model="yearForm.model_year">
              </div>
              <div class="col-md-5">
                <label class="form-label">Valor</label>
                <input type="number" min="0" step="0.01" class="form-control" v-model="yearForm.amount">
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-primary w-100" @click="addYearValueRow">Agregar</button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Año</th>
                    <th>Valor</th>
                    <th class="text-end"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in yearModalRows" :key="row.model_year">
                    <td>@{{ row.model_year }}</td>
                    <td>$ @{{ formatAmount(row.amount) }}</td>
                    <td class="text-end">
                      <button type="button" class="btn btn-sm btn-outline-danger" @click="removeYearValueRow(index)">Eliminar</button>
                    </td>
                  </tr>
                  <tr v-if="yearModalRows.length === 0">
                    <td colspan="3" class="text-center text-muted py-3">Sin valores cargados para esta combinacion.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="button" class="btn btn-primary" @click="saveYearValueRows">Guardar valores</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  [v-cloak] { display: none; }
  .style-excel td { padding: 0 !important; vertical-align: middle; }
  .style-excel input { outline: none; box-shadow: none !important; border-radius: 0; padding-right: 8px; width: 100%; box-sizing: border-box; }
  .style-excel input:focus { background-color: #eaf2ff !important; outline: 2px solid var(--ds-accent); z-index: 10; position: relative; }
  .style-excel input.driver-settings-cell-saving { background-color: #f5f9ff !important; }
  .stick-left { position: sticky; left: 0; background: #fff; border-right: 2px solid var(--ds-border); }
  th.stick-left { background: #f8fafc; z-index: 20; }
  .year-value-trigger { border-radius: 0; min-height: 46px; }
</style>
