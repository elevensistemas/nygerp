<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CurrentAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GraphController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentOrderController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TermAcceptanceController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\SystemParameterController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TrafficController;
use App\Http\Controllers\DeliveryReasonController;
use App\Http\Controllers\TrafficOrderController;
use App\Http\Controllers\TrafficRouteController;
use App\Http\Controllers\TrafficSolverController;
use App\Http\Controllers\TrafficLooseStopController;
use App\Http\Controllers\TrafficLooseStopImportFormatController;
use App\Http\Controllers\TransportistaController;
use App\Http\Controllers\TransportistaImportFormatController;
use App\Http\Controllers\TransporteController;
use App\Http\Controllers\TransporteImportFormatController;
use App\Http\Controllers\TrafficZoneController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\ReciboChoferController;
use App\Http\Controllers\ReciboChoferItemController;
use App\Http\Controllers\DriverExcelImportController;
use App\Http\Controllers\DriverPaymentAgendaController;
use App\Http\Controllers\PlanillaPagoChoferController;
use App\Http\Controllers\PlanillaPagoConciliacionController;
use App\Http\Controllers\DriverLiquidationSettingsController;
use App\Http\Controllers\DriverPaymentReportController;
use App\Http\Controllers\DriverPortalController;
use App\Http\Controllers\SettlementRuleController;
use App\Http\Controllers\TransportistaPaymentMethodController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AdminAdvanceRequestController;
use App\Http\Controllers\DriverAdvanceRequestController;
use App\Http\Controllers\DriverLogisticsRecordController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::get('/terms/pending', function () {
        return view('auth.pending');
    })->name('terms.pending');
});

Route::get('/terms/accept/{token}', [TermAcceptanceController::class, 'accept'])->name('terms.accept');
Route::post('/terms/accept/{token}', [TermAcceptanceController::class, 'store'])->name('terms.accept.store');

Route::middleware(['auth', 'terms.accepted', 'transportista.restrict', 'readonly.block'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/terms/view', [TermController::class, 'view'])->name('terms.view');

    // Documentos (se filtra ventas/compras por query string scope)
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/purchases/spending', [DocumentController::class, 'spending'])->name('purchases.spending');

    // --- VENTAS / COMPRAS ---
    Route::resource('customers', CustomerController::class)->except(['create', 'show']);
    Route::resource('suppliers', SupplierController::class)->except(['create', 'show']);
    Route::resource('products', ProductController::class)->except(['create', 'show']);
    Route::resource('payment-terms', PaymentTermController::class)->except(['create', 'show']);
    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('cost-centers', CostCenterController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('taxes', TaxController::class)->except(['create', 'edit']);
    Route::resource('payment-methods', PaymentMethodController::class)->except(['create', 'edit']);
    Route::post('/users/{user}/validate', [UserController::class, 'validateUser'])->name('users.validate');
    Route::resource('users', UserController::class)->except(['create', 'show']);

    // Cuenta corriente
    Route::get('/cc/customers', [CurrentAccountController::class, 'indexCustomers'])->name('cc.customers.index');
    Route::get('/cc/suppliers', [CurrentAccountController::class, 'indexSuppliers'])->name('cc.suppliers.index');
    Route::get('/current-account/{role}/{party}', [CurrentAccountController::class, 'show'])->name('cc.show');

    // --- CONTABILIDAD ---
    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/ledger/create', [LedgerController::class, 'create'])->name('ledger.create');
    Route::post('/ledger', [LedgerController::class, 'store'])->name('ledger.store');
    Route::post('/ledger/rebuild', [LedgerController::class, 'rebuild'])->name('ledger.rebuild');
    Route::get('/ledger/lists', [LedgerController::class, 'lists'])->name('ledger.lists');          // listado por periodos
    Route::get('/ledger/analytics', [LedgerController::class, 'analytics'])->name('ledger.analytics'); // mayor analitico
    Route::get('/ledger/analytics/pdf', [LedgerController::class, 'analyticsPdf'])->name('ledger.analytics.pdf');

    // Graficos y exportaciones
    Route::get('/graphs', [GraphController::class, 'index'])->name('graphs.index');
    Route::get('/exports/payments/csv', [DashboardController::class, 'exportPaymentsCsv'])->name('exports.payments.csv');

    // Ordenes de pago
    Route::get('/payments/create', [PaymentOrderController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentOrderController::class, 'store'])->name('payments.store');
    Route::get('/payments/pending', [PaymentOrderController::class, 'pending'])->name('payments.pending');
    Route::get('/payments/{document}/pdf', [PaymentOrderController::class, 'pdf'])->name('payments.pdf');
    // Imputaciones (crear allocation desde el detalle de un comprobante)
    Route::post('/allocations', [\App\Http\Controllers\AllocationController::class, 'store'])->name('allocations.store');

    // --- TRAFICO / LOGISTICA ---
    Route::prefix('traffic')->name('traffic.')->group(function () {
        Route::get('/', [TrafficController::class, 'index'])->name('dashboard');
        Route::get('/solver', [TrafficSolverController::class, 'index'])->name('solver.index');
        Route::match(['get', 'post'], '/solver/preview', [TrafficSolverController::class, 'preview'])->name('solver.preview');
        Route::post('/solver/preview-file', [TrafficSolverController::class, 'previewFile'])->name('solver.preview.file');
        Route::post('/solver/generate', [TrafficSolverController::class, 'generate'])->name('solver.generate');
        Route::get('/loose-stops', [TrafficLooseStopController::class, 'index'])->name('loose.index');
        Route::get('/loose-stops/template', [TrafficLooseStopController::class, 'template'])->name('loose.template');
        Route::post('/loose-stops/import', [TrafficLooseStopController::class, 'store'])->name('loose.store');
        Route::match(['get', 'post'], '/loose-stops/preview', [TrafficLooseStopController::class, 'preview'])->name('loose.preview');
        Route::delete('/loose-stops/bulk', [TrafficLooseStopController::class, 'bulkDestroy'])->name('loose.destroy.bulk');
        Route::delete('/loose-stops/{stop}', [TrafficLooseStopController::class, 'destroy'])->name('loose.destroy');
        Route::get('/loose-stops/import-config', [TrafficLooseStopImportFormatController::class, 'index'])->name('loose.import-config');
        Route::post('/loose-stops/import-config', [TrafficLooseStopImportFormatController::class, 'store'])->name('loose.import-config.store');
        Route::delete('/loose-stops/import-config/{format}', [TrafficLooseStopImportFormatController::class, 'destroy'])->name('loose.import-config.destroy');
        Route::get('/transportistas/import-config', [TransportistaImportFormatController::class, 'index'])->name('transportistas.import-config');
        Route::post('/transportistas/import-config', [TransportistaImportFormatController::class, 'store'])->name('transportistas.import-config.store');
        Route::get('/transportes/import-config', [TransporteImportFormatController::class, 'index'])->name('transportes.import-config');
        Route::post('/transportes/import-config', [TransporteImportFormatController::class, 'store'])->name('transportes.import-config.store');

        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

        Route::get('/zones', [TrafficZoneController::class, 'index'])->name('zones.index');
        Route::post('/zones', [TrafficZoneController::class, 'store'])->name('zones.store');
        Route::put('/zones/{zone}', [TrafficZoneController::class, 'update'])->name('zones.update');
        Route::delete('/zones/{zone}', [TrafficZoneController::class, 'destroy'])->name('zones.destroy');
        Route::patch('/zones/{zone}/update-svs', [TrafficZoneController::class, 'updateSvs'])->name('zones.update-svs');

        Route::get('/transportistas', [TransportistaController::class, 'index'])->name('transportistas.index');
        Route::get('/transportistas/export', [TransportistaController::class, 'export'])->name('transportistas.export');
        Route::post('/transportistas', [TransportistaController::class, 'store'])->name('transportistas.store');
        Route::post('/transportistas/import', [TransportistaController::class, 'import'])->name('transportistas.import');
        Route::put('/transportistas/{transportista}', [TransportistaController::class, 'update'])->name('transportistas.update');
        Route::patch('/transportistas/{transportista}/toggle-active', [TransportistaController::class, 'toggleActive'])->name('transportistas.toggle-active');
        Route::delete('/transportistas/{transportista}', [TransportistaController::class, 'destroy'])->name('transportistas.destroy');
        Route::delete('/transportistas/bulk', [TransportistaController::class, 'bulkDestroy'])->name('transportistas.bulk.destroy');
        Route::get('/transportistas/{transportista}/transportes', [TransportistaController::class, 'getTransportes'])->name('transportistas.transportes.list');
        Route::get('/transportistas/{transportista}/payment-methods', [TransportistaPaymentMethodController::class, 'index'])->name('transportistas.payment-methods.list');
        Route::post('/transportistas/{transportista}/payment-methods', [TransportistaPaymentMethodController::class, 'store'])->name('transportistas.payment-methods.store');
        Route::put('/transportista-payment-methods/{paymentMethod}', [TransportistaPaymentMethodController::class, 'update'])->name('transportistas.payment-methods.update');
        Route::delete('/transportista-payment-methods/{paymentMethod}', [TransportistaPaymentMethodController::class, 'destroy'])->name('transportistas.payment-methods.destroy');
        Route::patch('/transportista-payment-methods/{paymentMethod}/set-default', [TransportistaPaymentMethodController::class, 'setDefault'])->name('transportistas.payment-methods.set-default');
        Route::get('/transportistas/{transportista}/zones', [TransportistaController::class, 'getZones'])->name('transportistas.zones.list');
        Route::post('/transportistas/{transportista}/zones', [TransportistaController::class, 'storeZone'])->name('transportistas.zones.store');
        Route::put('/transportistas/{transportista}/zones/{zone}', [TransportistaController::class, 'updateZone'])->name('transportistas.zones.update');
        Route::delete('/transportistas/{transportista}/zones/{zone}', [TransportistaController::class, 'destroyZone'])->name('transportistas.zones.destroy');
        Route::get('/transportistas/{transportista}/shared-zones', [TransportistaController::class, 'getSharedZones'])->name('transportistas.zones.shared.list');
        Route::post('/transportistas/{transportista}/shared-zones', [TransportistaController::class, 'syncSharedZones'])->name('transportistas.zones.shared.sync');

        Route::get('/bancos', [BankController::class, 'index'])->name('banks.index');
        Route::post('/bancos', [BankController::class, 'store'])->name('banks.store');
        Route::put('/bancos/{bank}', [BankController::class, 'update'])->name('banks.update');
        Route::delete('/bancos/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');

        Route::get('/transportes', [TransporteController::class, 'index'])->name('transportes.index');
        Route::post('/transportes', [TransporteController::class, 'store'])->name('transportes.store');
        Route::get('/transportes/{transporte}/edit', [TransporteController::class, 'edit'])->name('transportes.edit');
        Route::put('/transportes/{transporte}', [TransporteController::class, 'update'])->name('transportes.update');
        Route::delete('/transportes/{transporte}', [TransporteController::class, 'destroy'])->name('transportes.destroy');
        Route::patch('/transportes/{transporte}/set-default', [TransporteController::class, 'setDefault'])->name('transportes.setDefault');
        Route::get('/transportistas/{transportista}/transportes-list', [TransporteController::class, 'listByCarrier'])
            ->name('transportistas.transportes');

        Route::get('/planilla-choferes', [DriverLogisticsRecordController::class, 'index'])->name('planilla-choferes.index');
        Route::post('/planilla-choferes/save', [DriverLogisticsRecordController::class, 'save'])->name('planilla-choferes.save');
        Route::get('/planilla-choferes/{id}/logs', [DriverLogisticsRecordController::class, 'logs'])->name('planilla-choferes.logs');
        Route::get('/planilla-choferes/conceptos', [DriverLogisticsRecordController::class, 'getConcepts'])->name('planilla-choferes.concepts');
        Route::get('/planilla-choferes/transportistas-list', [DriverLogisticsRecordController::class, 'getCarriers'])->name('planilla-choferes.carriers-list');
        Route::post('/planilla-choferes/clean-duplicates', [DriverLogisticsRecordController::class, 'cleanDuplicates'])->name('planilla-choferes.clean-duplicates');
        Route::delete('/planilla-choferes/{record}', [DriverLogisticsRecordController::class, 'destroy'])->name('planilla-choferes.destroy');
        Route::get('/delivery-reasons', [DeliveryReasonController::class, 'index'])->name('delivery-reasons.index');
        Route::post('/delivery-reasons', [DeliveryReasonController::class, 'store'])->name('delivery-reasons.store');
        Route::put('/delivery-reasons/{deliveryReason}', [DeliveryReasonController::class, 'update'])->name('delivery-reasons.update');
        Route::delete('/delivery-reasons/{deliveryReason}', [DeliveryReasonController::class, 'destroy'])->name('delivery-reasons.destroy');

        Route::get('/routes', [TrafficRouteController::class, 'index'])->name('routes.index');
        Route::delete('/routes', [TrafficRouteController::class, 'destroyAll'])->name('routes.destroyAll');
        Route::get('/routes/create', [TrafficRouteController::class, 'create'])->name('routes.create');
        Route::post('/routes', [TrafficRouteController::class, 'store'])->name('routes.store');
        Route::get('/routes/{route}', [TrafficRouteController::class, 'show'])->name('routes.show');
        Route::get('/routes/report/all', [TrafficRouteController::class, 'report'])->name('routes.report');
        Route::get('/routes/{route}/edit', [TrafficRouteController::class, 'edit'])->name('routes.edit');
        Route::put('/routes/{route}', [TrafficRouteController::class, 'update'])->name('routes.update');
        Route::post('/routes/{route}/regenerate', [TrafficRouteController::class, 'regenerate'])->name('routes.regenerate');
        Route::post('/routes/{route}/duplicate', [TrafficRouteController::class, 'duplicate'])->name('routes.duplicate');
        Route::post('/routes/{route}/start', [TrafficRouteController::class, 'start'])->name('routes.start');
        Route::post('/routes/{route}/send', [TrafficRouteController::class, 'send'])->name('routes.send');
        Route::post('/routes/send-batch', [TrafficRouteController::class, 'sendBatch'])->name('routes.sendBatch');
        Route::patch('/routes/{route}/stops/{stop}', [TrafficRouteController::class, 'updateStop'])->name('routes.stops.update');
        Route::post('/routes/{route}/stops/{stop}/photos', [TrafficRouteController::class, 'uploadStopPhotos'])->name('routes.stops.photos.store');
        Route::delete('/routes/{route}/stops/{stop}/photos/{photo}', [TrafficRouteController::class, 'destroyStopPhoto'])->name('routes.stops.photos.destroy');
        Route::post('/routes/{route}/extras', [TrafficRouteController::class, 'addAdhocStop'])->name('routes.extras.store');
        Route::post('/routes/{route}/reassign', [TrafficRouteController::class, 'reassign'])->name('routes.reassign');
        Route::delete('/routes/{route}', [TrafficRouteController::class, 'destroy'])->name('routes.destroy');
        Route::get('/routes/{route}/export', [TrafficRouteController::class, 'export'])->name('routes.export');
        Route::get('/routes/{route}/print', [TrafficRouteController::class, 'print'])->name('routes.print');
        Route::get('/orders', [TrafficOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [TrafficOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders/import-addresses/preview', [TrafficOrderController::class, 'previewAddresses'])->name('orders.import.preview');
        Route::post('/orders/import-addresses', [TrafficOrderController::class, 'importAddresses'])->name('orders.import');
        Route::post('/orders', [TrafficOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [TrafficOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/route', [TrafficOrderController::class, 'route'])->name('orders.route');
        Route::delete('/orders/{order}', [TrafficOrderController::class, 'destroy'])->name('orders.destroy');
    });

    Route::prefix('pago-choferes')->name('pago-choferes.')->group(function () {
        Route::get('/agenda', [DriverPaymentAgendaController::class, 'index'])->name('agenda.index');
        Route::get('/recibos', [ReciboChoferController::class, 'index'])->name('recibos.index');
        Route::delete('/recibos/bulk', [ReciboChoferController::class, 'bulkDestroy'])->name('recibos.bulk-destroy');
        Route::delete('/recibos/cargados', [ReciboChoferController::class, 'destroyAllCargados'])->name('recibos.destroy-all-cargados');
        Route::get('/recibos/{recibo}', [ReciboChoferController::class, 'show'])->name('recibos.show');
        Route::get('/adelantos', [AdminAdvanceRequestController::class, 'index'])->name('adelantos.index');
        Route::post('/adelantos/{advanceRequest}/resolver', [AdminAdvanceRequestController::class, 'resolve'])->name('adelantos.resolve');
        Route::put('/recibos/{recibo}', [ReciboChoferController::class, 'update'])->name('recibos.update');
        Route::delete('/recibos/{recibo}', [ReciboChoferController::class, 'destroy'])->name('recibos.destroy');
        Route::post('/recibos/{recibo}/anular', [ReciboChoferController::class, 'anular'])->name('recibos.anular');
        Route::post('/recibos/{recibo}/recalcular', [ReciboChoferController::class, 'recalculate'])->name('recibos.recalculate');
        Route::post('/recibos/{recibo}/desvincular-factura', [ReciboChoferController::class, 'unlinkInvoice'])->name('recibos.unlink-invoice');
        Route::get('/recibos/{recibo}/print', [ReciboChoferController::class, 'print'])->name('recibos.print');
        Route::get('/recibos/{recibo}/factura', [ReciboChoferController::class, 'downloadInvoice'])->name('recibos.factura.download');

        Route::post('/recibos/{recibo}/items', [ReciboChoferItemController::class, 'store'])->name('items.store');
        Route::put('/items/{item}', [ReciboChoferItemController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}', [ReciboChoferItemController::class, 'destroy'])->name('items.destroy');

        Route::get('/importar', [DriverExcelImportController::class, 'create'])->name('import.create');
        Route::post('/importar', [DriverExcelImportController::class, 'store'])->name('import.store');
        Route::post('/importar/logistica', [DriverExcelImportController::class, 'importFromLogistics'])->name('import.logistics');
        Route::post('/importar/chunk', [DriverExcelImportController::class, 'importChunk'])->name('import.chunk');
        Route::post('/importar/finish', [DriverExcelImportController::class, 'finishImport'])->name('import.finish');
        Route::get('/importar/{run}', [DriverExcelImportController::class, 'show'])->name('import.show');
        Route::get('/importar/{run}/errores', [DriverExcelImportController::class, 'downloadErrors'])->name('import.errors');

        Route::get('/planillas', [PlanillaPagoChoferController::class, 'index'])->name('planillas.index');
        Route::get('/planillas/crear', [PlanillaPagoChoferController::class, 'create'])->name('planillas.create');
        Route::post('/planillas', [PlanillaPagoChoferController::class, 'store'])->name('planillas.store');
        Route::get('/planillas/{planilla}', [PlanillaPagoChoferController::class, 'show'])->name('planillas.show');
        Route::post('/planillas/{planilla}/confirmar', [PlanillaPagoChoferController::class, 'confirm'])->name('planillas.confirm');
        Route::post('/planillas/{planilla}/cerrar', [PlanillaPagoChoferController::class, 'close'])->name('planillas.close');
        Route::delete('/planillas/{planilla}', [PlanillaPagoChoferController::class, 'destroy'])->name('planillas.destroy');
        Route::get('/planillas/{planilla}/print', [PlanillaPagoChoferController::class, 'print'])->name('planillas.print');
        Route::get('/planillas/{planilla}/export-banco', [PlanillaPagoConciliacionController::class, 'exportBankTemplate'])->name('planillas.export-bank');
        Route::get('/planillas/{planilla}/export-santander', [PlanillaPagoConciliacionController::class, 'exportSantander'])->name('planillas.export-santander');
        Route::post('/planillas/test-santander-export', [PlanillaPagoConciliacionController::class, 'exportSantanderSandbox'])->name('planillas.export-santander-sandbox');
        Route::post('/planillas/{planilla}/import-banco', [PlanillaPagoConciliacionController::class, 'importBankResults'])->name('planillas.import-bank');
        Route::post('/planillas/{planilla}/todos-pagados', [PlanillaPagoConciliacionController::class, 'markAllPaid'])->name('planillas.todos-pagados');
        Route::post('/planillas-link/{link}/resultado-manual', [PlanillaPagoConciliacionController::class, 'updateManualResult'])->name('planillas.manual-result');

        Route::get('/reportes', [DriverPaymentReportController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/{report}/pdf', [DriverPaymentReportController::class, 'pdf'])->name('reportes.pdf');

        Route::get('/reglas', [SettlementRuleController::class, 'index'])->name('reglas.index');
        Route::prefix('/reglas/api')->name('reglas.api.')->group(function () {
            Route::get('/rules', [SettlementRuleController::class, 'getRules'])->name('rules.index');
            Route::get('/options', [SettlementRuleController::class, 'getOptions'])->name('options.index');
            Route::post('/rules', [SettlementRuleController::class, 'store'])->name('rules.store');
            Route::put('/rules/{id}', [SettlementRuleController::class, 'update'])->name('rules.update');
            Route::delete('/rules/{id}', [SettlementRuleController::class, 'destroy'])->name('rules.destroy');
            Route::patch('/rules/{id}/toggle', [SettlementRuleController::class, 'toggle'])->name('rules.toggle');
            Route::post('/simulate', [SettlementRuleController::class, 'simulate'])->name('simulate');
        });

        Route::get('/configuracion', [DriverLiquidationSettingsController::class, 'index'])->name('settings.index');
        Route::post('/configuracion', [DriverLiquidationSettingsController::class, 'store'])->name('settings.store');
        Route::post('/configuracion/default-period', [DriverLiquidationSettingsController::class, 'storeDefaultPeriodType'])->name('settings.default-period.store');
        Route::post('/configuracion/logistics-import-mode', [DriverLiquidationSettingsController::class, 'storeLogisticsImportMode'])->name('settings.logistics-import-mode.store');
        Route::post('/configuracion/new-drivers', [DriverLiquidationSettingsController::class, 'storeNewDriversConfig'])->name('settings.new-drivers.store');
        Route::post('/configuracion/vehicle-maps', [DriverLiquidationSettingsController::class, 'storeVehicleMap'])->name('settings.vehicle-maps.store');
        Route::post('/configuracion/vehicle-aliases', [DriverLiquidationSettingsController::class, 'storeVehicleAliases'])->name('settings.vehicle-aliases.store');
        Route::put('/configuracion/{setting}', [DriverLiquidationSettingsController::class, 'update'])->name('settings.update');
        Route::delete('/configuracion/{setting}', [DriverLiquidationSettingsController::class, 'destroy'])->name('settings.destroy');
        Route::delete('/configuracion/vehicle-maps/{map}', [DriverLiquidationSettingsController::class, 'destroyVehicleMap'])->name('settings.vehicle-maps.destroy');
        Route::post('/configuracion/zonas', [DriverLiquidationSettingsController::class, 'storeZone'])->name('settings.zones.store');
        Route::put('/configuracion/zonas/{zone}/tipo', [DriverLiquidationSettingsController::class, 'updateZoneType'])->name('settings.zones.type.update');
        Route::put('/configuracion/zonas/{zone}/km-excedido', [DriverLiquidationSettingsController::class, 'updateZoneKmExcess'])->name('settings.zones.km-excess.update');
        Route::post('/configuracion/conceptos', [DriverLiquidationSettingsController::class, 'storeConcept'])->name('settings.concepts.store');
        Route::put('/configuracion/conceptos/{concept}', [DriverLiquidationSettingsController::class, 'updateConcept'])->name('settings.concepts.update');
        Route::delete('/configuracion/conceptos/{concept}', [DriverLiquidationSettingsController::class, 'destroyConcept'])->name('settings.concepts.destroy');
        Route::post('/configuracion/flotas', [DriverLiquidationSettingsController::class, 'storeFleet'])->name('settings.fleets.store');
        Route::put('/configuracion/flotas/{fleet}', [DriverLiquidationSettingsController::class, 'updateFleet'])->name('settings.fleets.update');
        Route::delete('/configuracion/flotas/{fleet}', [DriverLiquidationSettingsController::class, 'destroyFleet'])->name('settings.fleets.destroy');
        Route::post('/configuracion/tipos-pago', [DriverLiquidationSettingsController::class, 'storePaymentType'])->name('settings.payment-types.store');
        Route::put('/configuracion/tipos-pago/{paymentType}', [DriverLiquidationSettingsController::class, 'updatePaymentType'])->name('settings.payment-types.update');
        Route::delete('/configuracion/tipos-pago/{paymentType}', [DriverLiquidationSettingsController::class, 'destroyPaymentType'])->name('settings.payment-types.destroy');
        Route::post('/configuracion/relaciones', [DriverLiquidationSettingsController::class, 'upsertZoneConcept'])->name('settings.zone-concepts.store');
        Route::delete('/configuracion/relaciones/{zoneConcept}', [DriverLiquidationSettingsController::class, 'destroyZoneConcept'])->name('settings.zone-concepts.destroy');
        Route::post('/configuracion/relaciones/anio', [DriverLiquidationSettingsController::class, 'storeZoneConceptYearValues'])->name('settings.zone-concepts.year-values.store');
        Route::post('/configuracion/package-rate-default', [DriverLiquidationSettingsController::class, 'storePackageRateDefault'])->name('settings.package-rate-default.store');
        Route::post('/configuracion/km-ranges', [DriverLiquidationSettingsController::class, 'storeKmRange'])->name('settings.km-ranges.store');
        Route::delete('/configuracion/km-ranges/{range}', [DriverLiquidationSettingsController::class, 'destroyKmRange'])->name('settings.km-ranges.destroy');
        Route::post('/configuracion/ajustes', [DriverLiquidationSettingsController::class, 'storeAdjustmentRule'])->name('settings.adjustments.store');
        Route::put('/configuracion/ajustes/{rule}', [DriverLiquidationSettingsController::class, 'updateAdjustmentRule'])->name('settings.adjustments.update');
        Route::delete('/configuracion/ajustes/{rule}', [DriverLiquidationSettingsController::class, 'destroyAdjustmentRule'])->name('settings.adjustments.destroy');
    });

    Route::prefix('portal-choferes')->name('portal-choferes.')->group(function () {
        Route::get('/liquidaciones', [DriverPortalController::class, 'index'])->name('liquidaciones.index');
        Route::get('/liquidaciones/{recibo}', [DriverPortalController::class, 'show'])->name('liquidaciones.show');
        Route::post('/liquidaciones/items/{item}/estado', [DriverPortalController::class, 'updateItemStatus'])->name('liquidaciones.items.status.update');
        Route::post('/liquidaciones/{recibo}/solicitar-contacto', [DriverPortalController::class, 'submitContactRequest'])->name('liquidaciones.contact-request.store');
        Route::post('/liquidaciones/{recibo}/factura', [DriverPortalController::class, 'uploadInvoice'])->name('liquidaciones.factura.store');
        Route::get('/liquidaciones/{recibo}/factura', [DriverPortalController::class, 'downloadInvoice'])->name('liquidaciones.factura.download');
        Route::delete('/liquidaciones/{recibo}/factura', [DriverPortalController::class, 'deleteInvoice'])->name('liquidaciones.factura.delete');
        
        Route::get('/adelantos', [DriverAdvanceRequestController::class, 'index'])->name('adelantos.index');
        Route::post('/adelantos', [DriverAdvanceRequestController::class, 'store'])->name('adelantos.store');
        Route::post('/adelantos/{advanceRequest}/aceptar-contraoferta', [DriverAdvanceRequestController::class, 'acceptCounterOffer'])->name('adelantos.accept-counter');
        Route::post('/adelantos/{advanceRequest}/rechazar-contraoferta', [DriverAdvanceRequestController::class, 'rejectCounterOffer'])->name('adelantos.reject-counter');
    });
});

Route::middleware(['auth', 'terms.accepted', 'admin', 'readonly.block'])->group(function () {
    Route::get('/config/general', [SystemParameterController::class, 'index'])->name('config.parameters.index');
    Route::put('/config/general/{parameter}', [SystemParameterController::class, 'update'])->name('config.parameters.update');
    Route::get('/config/terms', [TermController::class, 'edit'])->name('terms.edit');
    Route::put('/config/terms', [TermController::class, 'update'])->name('terms.update');
    Route::get('/config/confirmations', [ConfirmationController::class, 'index'])->name('confirmations.pending');
    Route::post('/config/confirmations/{user}/resend', [ConfirmationController::class, 'resend'])->name('confirmations.resend');
});



Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/__clear', function (Request $request) {
        $key = $request->query('key');
        $expectedKey = env('MAINTENANCE_KEY', config('app.maintenance_key'));

        if ($expectedKey && $key !== $expectedKey) {
            abort(403, 'Forbidden');
        }

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('storage:link');

        return response('ok', 200);
    })->middleware('throttle:2,1');

    Route::get('/__migrate', function (Request $request) {
        $key = $request->input('key');
        $expectedKey = env('MAINTENANCE_KEY', config('app.maintenance_key'));

        if ($expectedKey && $key !== $expectedKey) {
            abort(403, 'Forbidden');
        }

        Artisan::call('migrate', ['--force' => true]);

        return response(Artisan::output(), 200);
    })->middleware('throttle:2,1');
});
