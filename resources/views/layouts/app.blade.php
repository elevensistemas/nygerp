<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title','NyG ERP')</title>
  <link rel="icon" type="image/png" href="{{ asset('img/nyg.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      @php
        $newDaysSetting = \App\Models\DriverImportSetting::where('setting_key', 'driver_new_days')->first();
        $newColorSetting = \App\Models\DriverImportSetting::where('setting_key', 'driver_new_color')->first();
        $newDaysVal = $newDaysSetting ? (int) $newDaysSetting->setting_value : 30;
        $newColorVal = $newColorSetting ? (string) $newColorSetting->setting_value : '#28a745';
        $hex = ltrim($newColorVal, '#');
        if (strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            $r = 40; $g = 167; $b = 69;
        }
      @endphp
      --new-driver-color: {{ $newColorVal }};
      --new-driver-color-rgb: {{ "$r, $g, $b" }};
      --new-driver-days: {{ $newDaysVal }};
    }
    
    .new-driver-row {
      background-color: rgba(var(--new-driver-color-rgb), 0.08) !important;
    }
    .new-driver-badge {
      background-color: rgba(var(--new-driver-color-rgb), 0.15) !important;
      color: var(--new-driver-color) !important;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.15rem 0.35rem;
      border-radius: 6px;
      margin-left: 0.4rem;
      display: inline-flex;
      align-items: center;
      gap: 3px;
      border: 1px solid rgba(var(--new-driver-color-rgb), 0.25);
    }
  </style>

  <style>
    :root {
      --nyg-mustard: #d4a017;
      --nyg-ink: #111827;
      --nyg-slate: #4b5563;
      --nyg-surface: #ffffff;
      --nyg-border: #e5e7eb;
      --nyg-bg: #f4f5f7;
      --nyg-nav-height: 64px;
      --nyg-radius: 14px;
    }
    body {
      min-height: 100vh;
      background:
        radial-gradient(circle at top right, rgba(212, 160, 23, 0.12), transparent 45%),
        radial-gradient(circle at 10% 30%, rgba(17, 24, 39, 0.06), transparent 45%),
        var(--nyg-bg);
      color: var(--nyg-ink);
      font-family: 'Manrope', 'Segoe UI', sans-serif;
    }
    .app-layout { min-height: calc(100vh - var(--nyg-nav-height)); }
    @media (min-width: 992px) {
      .sidebar-panel {
        position: sticky;
        top: calc(var(--nyg-nav-height) + 12px);
        max-height: calc(100vh - var(--nyg-nav-height) - 24px);
        overflow-y: auto;
      }
    }
    .sidebar-panel {
      border-radius: var(--nyg-radius);
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(226, 232, 240, 0.9);
    }
    .sidebar-user {
      background: linear-gradient(145deg, rgba(212, 160, 23, 0.16), rgba(17, 24, 39, 0.03));
    }
    .sidebar-user .avatar-circle {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #111;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    .list-group-item .chev { transition: transform .2s ease; }
    .list-group-item[aria-expanded="true"] .chev { transform: rotate(90deg); }
    .list-group-item-action { border-left: 4px solid transparent; }
    .list-group-item-action:hover { background: rgba(212, 160, 23, 0.12); color: #111; }
    .list-group-item.active,
    .list-group-item.active:hover {
      background: rgba(212, 160, 23, 0.25);
      border-left-color: var(--nyg-mustard);
      color: #111;
      font-weight: 600;
    }
    .navbar-nyg {
      background: var(--nyg-surface);
      border-bottom: 1px solid var(--nyg-border);
      height: var(--nyg-nav-height);
    }
    .navbar-nyg .navbar-brand,
    .navbar-nyg .nav-link { color: #111 !important; }
    .navbar-nyg .nav-link:hover { color: #000 !important; }
    .navbar-brand .brand-logo { height: 36px; }
    .navbar-brand span { letter-spacing: 0.02em; }
    .content-card {
      background: var(--nyg-surface);
      border-radius: var(--nyg-radius);
      border: 1px solid rgba(226, 232, 240, 0.9);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
    }
    .card {
      border-radius: calc(var(--nyg-radius) - 2px);
      border: 1px solid rgba(226, 232, 240, 0.9);
      box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
    }
    .card-header {
      background: #fff;
      border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    }
    .btn {
      border-radius: 10px;
    }
    .btn-outline-primary,
    .btn-outline-secondary,
    .btn-outline-warning,
    .btn-outline-info,
    .btn-outline-success,
    .btn-outline-danger {
      border-width: 1.5px;
    }
    .form-control,
    .form-select {
      border-radius: 10px;
      border-color: #dfe3ea;
    }
    .form-control:focus,
    .form-select:focus {
      border-color: var(--nyg-mustard);
      box-shadow: 0 0 0 0.2rem rgba(212, 160, 23, 0.15);
    }
    .table > :not(caption) > * > * {
      border-color: rgba(226, 232, 240, 0.9);
    }
    .table thead th {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--nyg-slate);
      background: #f8fafc;
    }
    .badge {
      border-radius: 999px;
      font-weight: 600;
    }
    h1.h3,
    h2.h5,
    h3.h6 {
      letter-spacing: 0.01em;
    }
    .page-header {
      background: #fff;
      border-radius: var(--nyg-radius);
      border: 1px solid rgba(226, 232, 240, 0.9);
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      box-shadow: 0 14px 26px rgba(15, 23, 42, 0.06);
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .page-header .title-block h1,
    .page-header .title-block h2 {
      margin-bottom: 0.1rem;
    }
    .page-header .title-block p {
      margin-bottom: 0;
      color: var(--nyg-slate);
    }
    .page-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .map-frame {
      border-radius: 12px;
      border: 1px solid rgba(226, 232, 240, 0.9);
      overflow: hidden;
    }
    .select2-container .select2-selection--single {
      height: calc(2.25rem + 2px);
      padding: 0.375rem 0.75rem;
      border: 1px solid #ced4da;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 100%;
      right: 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: calc(2.25rem - 0.75rem);
    }
    .select2-container--default .select2-selection--multiple {
      min-height: calc(2.25rem + 2px);
      border: 1px solid #ced4da;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
      display: flex;
      flex-wrap: wrap;
      gap: 0.25rem;
      padding: 0.375rem 0.75rem;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
      margin: 0;
      padding: 0.125rem 0.5rem;
      border-radius: 0.25rem;
    }
    .carrier-color-dot {
      display: inline-block;
      width: 14px;
      height: 14px;
      border-radius: 50%;
      border: 2px solid #ffffff;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
      vertical-align: middle;
      margin-right: 6px;
    }
    .read-only-disabled {
      opacity: 0.6 !important;
      cursor: not-allowed !important;
    }
    .readonly-banner {
      border: 1px dashed #d4a017;
      background: rgba(212, 160, 23, 0.08);
    }
  </style>
  @stack('styles')
  <style>
    :root {
      --nyg-mustard: #ffc107;
    }
    .sidebar-panel {
      border-radius: var(--nyg-radius) !important;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
      border: 1px solid #333 !important;
      background: #111827 !important;
      color: #e5e7eb !important;
    }
    @media (min-width: 992px) {
      .sidebar-panel.offcanvas-lg {
        position: sticky !important;
        top: 20px !important;
        height: calc(100vh - 40px) !important;
        overflow-y: auto !important;
      }
    }
    .sidebar-user {
      border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
      background: transparent !important;
      padding-bottom: 1rem !important;
      margin-bottom: 0.5rem;
    }
    .sidebar-user .fw-semibold {
      color: #ffffff !important;
    }
    .sidebar-user .text-muted {
      color: #9ca3af !important;
    }
    .sidebar-user .avatar-circle {
      background: var(--nyg-mustard) !important;
      box-shadow: 0 4px 10px rgba(255, 193, 7, 0.2) !important;
      color: #111827 !important;
    }
    .list-group-flush > .list-group-item {
      border-width: 0 !important;
      background: transparent !important;
      color: #e5e7eb !important;
    }
    .list-group-item:not(.list-group-item-action) {
      font-size: 0.75rem;
      text-transform: uppercase;
      color: #6b7280 !important;
      letter-spacing: 0.05em;
      padding-top: 1rem;
      padding-bottom: 0.25rem;
      margin: 0 12px;
      background: transparent !important;
    }
    .list-group-item-action {
      border-radius: 10px !important;
      margin: 2px 12px !important;
      width: auto !important;
      border-left: none !important;
      transition: all 0.25s ease !important;
      color: #d1d5db !important;
      font-weight: 500 !important;
    }
    .list-group-item-action:hover {
      background: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      transform: translateX(4px);
    }
    .list-group-item.active,
    .list-group-item.active:hover {
      background: var(--nyg-mustard) !important;
      color: #111827 !important;
      box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3) !important;
      font-weight: 700 !important;
      transform: none !important;
    }
    .list-group-item i {
      width: 24px;
      text-align: center;
      font-size: 1.1rem;
      color: #9ca3af;
      transition: color 0.25s ease;
    }
    .list-group-item-action:hover i {
      color: var(--nyg-mustard) !important;
    }
    .list-group-item.active i {
      color: #111827 !important;
    }
    .list-group-item .chev { transition: transform .2s ease; font-size: 0.85rem; color: #6b7280; }
    .list-group-item.active .chev { color: #111827 !important; }
    .list-group-item[aria-expanded="true"] .chev { transform: rotate(90deg); }
    .collapse .list-group-item-action {
      padding-left: 2.5rem !important;
      font-size: 0.95rem;
    }
    .collapse .collapse .list-group-item-action {
      padding-left: 3.5rem !important;
      font-size: 0.9rem;
    }
    .navbar-nyg {
      background: #111827 !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
      height: var(--nyg-nav-height);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2) !important;
    }
    .navbar-brand span {
      color: #ffffff !important;
    }
    .navbar-brand .brand-logo {
      filter: brightness(0) invert(1);
    }
    .navbar-toggler-icon {
      filter: invert(1);
    }
    .text-primary {
      color: var(--nyg-mustard) !important;
    }
    
    /* Button Overrides for Brand Colors */
    .btn-primary {
      background-color: var(--nyg-mustard) !important;
      border-color: var(--nyg-mustard) !important;
      color: #111827 !important;
      font-weight: 600;
      box-shadow: 0 4px 10px rgba(255, 193, 7, 0.2);
    }
    .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
      background-color: #e5ad06 !important; /* darker yellow */
      border-color: #e5ad06 !important;
      color: #000000 !important;
      box-shadow: 0 6px 14px rgba(255, 193, 7, 0.3);
    }
    .btn-secondary, .btn-outline-primary, .btn-default {
      background-color: #ffffff !important;
      border-color: var(--nyg-mustard) !important;
      color: #111827 !important;
      font-weight: 600;
    }
    .btn-secondary:hover, .btn-outline-primary:hover, .btn-default:hover,
    .btn-secondary:focus, .btn-outline-primary:focus, .btn-default:focus,
    .btn-secondary:active, .btn-outline-primary:active, .btn-default:active {
      background-color: var(--nyg-mustard) !important;
      color: #111827 !important;
    }
    
    /* Custom Scrollbar for Sidebar */
    .sidebar-panel::-webkit-scrollbar {
      width: 5px;
    }
    .sidebar-panel::-webkit-scrollbar-track {
      background: transparent;
    }
    .sidebar-panel::-webkit-scrollbar-thumb {
      background-color: rgba(255, 193, 7, 0.15);
      border-radius: 10px;
    }
    .sidebar-panel::-webkit-scrollbar-thumb:hover {
      background-color: rgba(255, 193, 7, 0.5);
    }
    
    /* Global scrollbar for the rest of the app */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    ::-webkit-scrollbar-track {
      background: transparent;
    }
    ::-webkit-scrollbar-thumb {
      background-color: #cbd5e1;
      border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background-color: #94a3b8;
    }
  </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100 @auth {{ auth()->user() && auth()->user()->isReadOnly() ? 'role-readonly' : '' }} @endauth">

  <nav class="navbar navbar-expand-lg navbar-light navbar-nyg">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center gap-2" href="/">
        <img class="brand-logo" src="{{ asset('img/nyg.png') }}" alt="NyG Transporte">
        <span class="fw-semibold">NyG Transporte</span>
      </a>
      <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenuOffcanvas" aria-controls="sidebarMenuOffcanvas">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="topNav"></div>
    </div>
  </nav>

  <div class="container-fluid flex-grow-1 py-3 app-layout">

    {{-- Si el usuario esta logueado --}}
    @auth
    @php
      $currentDocument = request()->routeIs('documents.show') ? request()->route('document') : null;
      $documentScope = $currentDocument->scope ?? null;

      $isSaleList = request()->is('documents') && request('scope') === 'sale';
      $isSaleCreate = request()->is('documents/create') && request('scope') === 'sale';
      $isSaleDocumentShow = $documentScope === 'sale';

      $isPurchaseList = request()->is('documents') && request('scope') === 'purchase';
      $isPurchaseCreate = request()->is('documents/create') && request('scope') === 'purchase';
      $isPurchaseDocumentShow = $documentScope === 'purchase';

      $ventasActive = $isSaleList || $isSaleCreate || $isSaleDocumentShow
        || request()->routeIs('customers.*', 'cc.customers.*');
      $comprasActive = $isPurchaseList || $isPurchaseCreate || $isPurchaseDocumentShow
        || request()->routeIs('suppliers.*', 'cc.suppliers.*', 'purchases.spending');
      $contaActive = request()->routeIs('ledger.*', 'accounts.*', 'cost-centers.*');

      $dashboardActive = request()->routeIs('dashboard');
      $trafficActive = request()->routeIs('traffic.*');
      $driverPaymentsActive = request()->routeIs('pago-choferes.*');
      $driverPaymentsReceiptsActive = request()->routeIs('pago-choferes.recibos.*');
      $driverPaymentsAgendaActive = request()->routeIs('pago-choferes.agenda.*');
      $driverPaymentsImportActive = request()->routeIs('pago-choferes.import.*');
      $driverPaymentsSheetsActive = request()->routeIs('pago-choferes.planillas.*');
      $driverPaymentsReportsActive = request()->routeIs('pago-choferes.reportes.*');
      $driverPaymentsSettingsActive = request()->routeIs('pago-choferes.settings.*');
      $solverActive = request()->routeIs('traffic.solver.*');
      $importConfigActive = request()->routeIs('traffic.loose.import-config', 'traffic.transportistas.import-config*', 'traffic.transportes.import-config*');
      $transportistasCrudActive = request()->routeIs('traffic.transportistas.*') && !request()->routeIs('traffic.transportistas.import-config*');
      $transportesCrudActive = request()->routeIs('traffic.transportes.*') && !request()->routeIs('traffic.transportes.import-config*');
      $banksCrudActive = request()->routeIs('traffic.banks.*');
      $maintenanceActive = request()->routeIs('traffic.transportistas.*','traffic.transportes.*','traffic.delivery-reasons.*','traffic.locations.*','traffic.zones.*', 'traffic.banks.*')
        && !request()->routeIs('traffic.transportistas.import-config*','traffic.transportes.import-config*');
      $configActive = request()->routeIs('users.*') || request()->routeIs('terms.*') || request()->routeIs('confirmations.*') || request()->routeIs('config.parameters.*');
      $currentUser = auth()->user();
      $isReadOnlyUser = $currentUser && $currentUser->isReadOnly();
      $isTransportista = $currentUser && $currentUser->isTransportista();
      $canManageDriverPaymentSettings = $currentUser && ! $currentUser->isReadOnly() && ! $currentUser->isTransportista();
      $isLimitedTransportista = $isTransportista && ! ($currentUser && $currentUser->isAdminOrSuper());
      $hideSidebar = $hideSidebar ?? false;
      $tProfile = null;
      if ($currentUser && $isTransportista) {
          $tProfile = $currentUser->transportistaProfile ?? \App\Models\Transportista::where('email', $currentUser->email)->first();
      }
      $portalVisibility = $tProfile ? ($tProfile->portal_visibility ?? 'all') : 'all';
    @endphp
    @if($hideSidebar)
      <div class="row">
        <main class="col-12">
      <div class="bg-white rounded shadow-sm p-3 h-100">
        @if(session('ok'))
          <div class="alert alert-success">{{ session('ok') }}</div>
        @endif
        @if(session('info'))
          <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        @if($isReadOnlyUser)
          <div class="alert alert-warning readonly-banner">
            Estas navegando con un rol de solo lectura. Las acciones de crear, editar o eliminar estan deshabilitadas.
          </div>
        @endif
        @yield('content')
      </div>
        </main>
      </div>
    @else
    <div class="row g-3 flex-lg-nowrap">
      <aside class="col-lg-3 col-xl-2 col-12 mb-3 mb-lg-0">
        <div class="bg-white border rounded shadow-sm sidebar-panel h-100 p-0 offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenuOffcanvas" aria-labelledby="sidebarMenuOffcanvasLabel">
          <div class="offcanvas-header d-lg-none border-bottom border-secondary">
            <h5 class="offcanvas-title text-white fw-bold" id="sidebarMenuOffcanvasLabel">Menú</h5>
            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenuOffcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body p-0 d-block">
            <div class="list-group list-group-flush w-100" id="sidebarMenu">
          <div class="sidebar-user px-3 py-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
              @if(!empty($currentUser->avatar_path))
                <img src="{{ asset('storage/' . $currentUser->avatar_path) }}" alt="Avatar" class="rounded-circle" style="width:44px;height:44px;object-fit:cover;">
              @else
                <div class="avatar-circle">
                  {{ strtoupper(mb_substr($currentUser->name ?? 'U', 0, 1)) }}
                </div>
              @endif
              <div>
                <div class="fw-semibold">{{ $currentUser->name ?? 'Usuario' }}</div>
                <div class="text-muted small">
                  {{ $isLimitedTransportista ? 'Transportista' : ($currentUser->isAdminOrSuper() ? 'Administrador' : ($isReadOnlyUser ? 'Solo lectura' : 'Operador')) }}
                </div>
              </div>
            </div>
          </div>

          {{-- 🔧 MOSTRAR INICIO / AGENDA SOLO SI NO ES TRANSPORTISTA LIMITADO --}}
          @if(! $isLimitedTransportista)
            <div class="list-group-item fw-bold">Inicio</div>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $dashboardActive ? 'active' : '' }}"
               href="{{ route('dashboard') }}">
              <i class="fa-solid fa-calendar-check"></i>
              <span>Agenda de pagos</span>
            </a>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ request()->routeIs('graphs.index') ? 'active' : '' }}"
               href="{{ route('graphs.index') }}">
              <i class="fa-solid fa-chart-pie"></i>
              <span>Graficos</span>
            </a>
          @endif

          @if(! $isLimitedTransportista)
          {{-- VENTAS --}}
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $ventasActive ? 'active' : '' }}"
             data-bs-toggle="collapse" href="#ventasCollapse" role="button"
             aria-expanded="{{ $ventasActive ? 'true' : 'false' }}" aria-controls="ventasCollapse">
            <span class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-cash-register"></i>
              <span>Ventas</span>
            </span>
            <span class="chev">></span>
          </a>
          <div class="collapse {{ $ventasActive ? 'show' : '' }}" id="ventasCollapse" data-bs-parent="#sidebarMenu">
            <div class="list-group list-group-flush">
              {{-- Listado y alta de comprobantes de ventas (pasamos scope por query) --}}
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ ($isSaleList || $isSaleDocumentShow) ? 'active' : '' }}"
                 href="{{ url('/documents?scope=sale') }}">
                <i class="fa-solid fa-file-invoice"></i>
                <span>Ingreso comprobantes (ventas)</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $isSaleCreate ? 'active' : '' }}"
                 href="{{ url('/documents/create?scope=sale') }}">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Agregar comprobante (venta)</span>
              </a>
              {{-- Clientes (CRUD) --}}
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('customers.*') ? 'active' : '' }}"
                 href="{{ route('customers.index', [], false) }}">
                <i class="fa-solid fa-users"></i>
                <span>Clientes</span>
              </a>
              {{-- Cuenta corriente de clientes --}}
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('cc.customers.*') ? 'active' : '' }}"
                 href="{{ route('cc.customers.index', [], false) }}">
                <i class="fa-solid fa-address-book"></i>
                <span>Cuenta corriente (clientes)</span>
              </a>
            </div>
          </div>

          {{-- COMPRAS --}}
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $comprasActive ? 'active' : '' }}"
             data-bs-toggle="collapse" href="#comprasCollapse" role="button"
             aria-expanded="{{ $comprasActive ? 'true' : 'false' }}" aria-controls="comprasCollapse">
            <span class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-cart-shopping"></i>
              <span>Compras</span>
            </span>
            <span class="chev">></span>
          </a>
          <div class="collapse {{ $comprasActive ? 'show' : '' }}" id="comprasCollapse" data-bs-parent="#sidebarMenu">
            <div class="list-group list-group-flush">
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ ($isPurchaseList || $isPurchaseDocumentShow) ? 'active' : '' }}"
                 href="{{ url('/documents?scope=purchase') }}">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Ingreso comprobantes (compras)</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $isPurchaseCreate ? 'active' : '' }}"
                 href="{{ url('/documents/create?scope=purchase') }}">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Agregar comprobante (compra)</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                 href="{{ route('suppliers.index', [], false) }}">
                <i class="fa-solid fa-truck-field"></i>
                <span>Proveedores</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('cc.suppliers.*') ? 'active' : '' }}"
                 href="{{ route('cc.suppliers.index', [], false) }}">
                <i class="fa-solid fa-address-book"></i>
                <span>Cuenta corriente (proveedores)</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('purchases.spending') ? 'active' : '' }}"
                 href="{{ route('purchases.spending', [], false) }}">
                <i class="fa-solid fa-money-bill-wave"></i>
                <span>Gastos / análisis</span>
              </a>
            </div>
          </div>

          {{-- CONTABILIDAD --}}
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $contaActive ? 'active' : '' }}"
             data-bs-toggle="collapse" href="#contaCollapse" role="button"
             aria-expanded="{{ $contaActive ? 'true' : 'false' }}" aria-controls="contaCollapse">
            <span class="d-flex align-items-center gap-2"><i class="fa-solid fa-calculator"></i><span>Contabilidad</span></span>
            <span class="chev">></span>
          </a>
          <div class="collapse {{ $contaActive ? 'show' : '' }}" id="contaCollapse" data-bs-parent="#sidebarMenu">
            <div class="list-group list-group-flush">
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('ledger.index') ? 'active' : '' }}"
                 href="{{ route('ledger.index', [], false) }}">
                <i class="fa-solid fa-book-open"></i>
                <span>Ver asientos</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('ledger.create') ? 'active' : '' }}"
                 href="{{ route('ledger.create', [], false) }}">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Ingresar asiento</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('accounts.*') ? 'active' : '' }}"
                 href="{{ route('accounts.index', [], false) }}">
                <i class="fa-solid fa-list"></i>
                <span>Cuentas contables (CRUD)</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('cost-centers.*') ? 'active' : '' }}"
                 href="{{ route('cost-centers.index', [], false) }}">
                <i class="fa-solid fa-layer-group"></i>
                <span>Centros de costo (CRUD)</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('ledger.lists') ? 'active' : '' }}"
                 href="{{ route('ledger.lists', [], false) }}">
                <i class="fa-solid fa-list-ul"></i>
                <span>Listas de asientos</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('ledger.analytics') ? 'active' : '' }}"
                 href="{{ route('ledger.analytics', [], false) }}">
                <i class="fa-solid fa-chart-simple"></i>
                <span>Mayor anal?tico</span>
              </a>
            </div>
          </div>

          {{-- PAGOS --}}
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $driverPaymentsActive ? 'active' : '' }}"
             data-bs-toggle="collapse" href="#paymentsCollapse" role="button"
             aria-expanded="{{ $driverPaymentsActive ? 'true' : 'false' }}" aria-controls="paymentsCollapse">
            <span class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-money-check-dollar"></i>
              <span>Pagos</span>
            </span>
            <span class="chev">></span>
          </a>
          <div class="collapse {{ $driverPaymentsActive ? 'show' : '' }}" id="paymentsCollapse" data-bs-parent="#sidebarMenu">
            <div class="list-group list-group-flush">
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $driverPaymentsReceiptsActive ? 'active' : '' }}"
                 href="{{ route('pago-choferes.recibos.index', [], false) }}">
                <i class="fa-solid fa-receipt"></i>
                <span>Pago a choferes</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $driverPaymentsAgendaActive ? 'active' : '' }}"
                 href="{{ route('pago-choferes.agenda.index', [], false) }}">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Agenda de vencimientos</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $driverPaymentsImportActive ? 'active' : '' }}"
                 href="{{ route('pago-choferes.import.create', [], false) }}">
                <i class="fa-solid fa-file-import"></i>
                <span>Importar Excel tráfico</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $driverPaymentsSheetsActive ? 'active' : '' }}"
                 href="{{ route('pago-choferes.planillas.index', [], false) }}">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Planillas de pago</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $driverPaymentsReportsActive ? 'active' : '' }}"
                 href="{{ route('pago-choferes.reportes.index', [], false) }}">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reportes</span>
              </a>
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.transportistas.*') ? 'active' : '' }}"
                 href="{{ route('traffic.transportistas.index', [], false) }}">
                <i class="fa-solid fa-id-card"></i>
                <span>Choferes / transportistas</span>
              </a>
              @if(Route::has('pago-choferes.adelantos.index'))
              <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('pago-choferes.adelantos.*') ? 'active' : '' }}"
                 href="{{ route('pago-choferes.adelantos.index', [], false) }}">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                <span>Solicitudes de adelanto</span>
              </a>
              @endif
              @if($canManageDriverPaymentSettings && ! ($currentUser && $currentUser->isAdminOrSuper()))
              @endif
              @if($currentUser && $currentUser->isAdminOrSuper())
                @if(Route::has('pago-choferes.reglas.index'))
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('pago-choferes.reglas.index') ? 'active' : '' }}"
                   href="{{ route('pago-choferes.reglas.index', [], false) }}">
                  <i class="fa-solid fa-cogs"></i>
                  <span>Motor de Reglas</span>
                </a>
                @endif
              @endif
            </div>
          </div>
            @endif

            {{-- MENU CHOFER (PORTAL) --}}
            @if($isTransportista && Route::has('portal-choferes.liquidaciones.index') && (!$isLimitedTransportista || in_array($portalVisibility, ['all', 'settlements_only'])))
              <div class="list-group-item fw-bold">Portal Chofer</div>
              <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ request()->routeIs('portal-choferes.liquidaciones.*') ? 'active' : '' }}"
                 href="{{ route('portal-choferes.liquidaciones.index', [], false) }}">
                <i class="fa-solid fa-file-invoice" style="color: var(--nyg-mustard);"></i>
                <span class="fw-bold">Mis Liquidaciones</span>
              </a>
              @if(Route::has('portal-choferes.adelantos.index'))
              <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ request()->routeIs('portal-choferes.adelantos.*') ? 'active' : '' }}"
                 href="{{ route('portal-choferes.adelantos.index', [], false) }}">
                <i class="fa-solid fa-hand-holding-dollar" style="color: var(--nyg-mustard);"></i>
                <span class="fw-bold">Mis Adelantos</span>
              </a>
              @endif
            @endif

          @if(!$isLimitedTransportista || in_array($portalVisibility, ['all', 'routes_only']))
            {{-- TRAFICO / GESTION --}}
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $trafficActive ? 'active' : '' }}"
             data-bs-toggle="collapse" href="#trafficCollapse" role="button"
             aria-expanded="{{ $trafficActive ? 'true' : 'false' }}" aria-controls="trafficCollapse">
            <span class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-truck-fast"></i>
              <span>Tráfico y logística</span>
            </span>
            <span class="chev">></span>
          </a>
          <div class="collapse {{ $trafficActive ? 'show' : '' }}" id="trafficCollapse" data-bs-parent="#sidebarMenu">
            <div class="list-group list-group-flush">
              <a class="d-none list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.dashboard') ? 'active' : '' }}"
                 href="{{ route('traffic.dashboard', [], false) }}">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Panel de tráfico</span>
              </a>
              @if($isLimitedTransportista && in_array($portalVisibility, ['all', 'routes_only']))
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.routes.index') ? 'active' : '' }}"
                   href="{{ route('traffic.routes.index', [], false) }}">
                  <i class="fa-solid fa-route"></i>
                  <span>Mis rutas</span>
                </a>
              @elseif(!$isLimitedTransportista)
               
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ps-4 {{ $maintenanceActive ? 'active' : '' }}"
                   data-bs-toggle="collapse" href="#maintenanceCollapse" role="button"
                   aria-expanded="{{ $maintenanceActive ? 'true' : 'false' }}"
                   aria-controls="maintenanceCollapse">
                  <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    <span>Mantenimiento</span>
                  </span>
                  <span class="chev">></span>
                </a>
                <div class="collapse {{ $maintenanceActive ? 'show' : '' }}"
                     id="maintenanceCollapse" data-bs-parent="#trafficCollapse">
                  <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ $transportistasCrudActive ? 'active' : '' }}"
                       href="{{ route('traffic.transportistas.index', [], false) }}">
                      <i class="fa-solid fa-people-carry-box"></i>
                      <span>Transportistas</span>
                    </a>
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ $transportesCrudActive ? 'active' : '' }}"
                       href="{{ route('traffic.transportes.index', [], false) }}">
                      <i class="fa-solid fa-truck-moving"></i>
                      <span>Transportes</span>
                    </a>
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ $banksCrudActive ? 'active' : '' }}"
                       href="{{ route('traffic.banks.index', [], false) }}">
                      <i class="fa-solid fa-building-columns"></i>
                      <span>Bancos</span>
                    </a>
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.zones.*') ? 'active' : '' }}"
                       href="{{ route('traffic.zones.index', [], false) }}">
                      <i class="fa-solid fa-draw-polygon"></i>
                      <span>Zonas</span>
                    </a>
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.delivery-reasons.*') ? 'active' : '' }}"
                       href="{{ route('traffic.delivery-reasons.index', [], false) }}">
                      <i class="fa-solid fa-circle-question"></i>
                      <span>Motivos no entrega</span>
                    </a>
                    @if(! $isLimitedTransportista)
                      <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.locations.*') ? 'active' : '' }}"
                         href="{{ route('traffic.locations.index', [], false) }}">
                        <i class="fa-solid fa-warehouse"></i>
                        <span>Depósitos</span>
                      </a>
                    @endif
                  </div>
                </div>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.loose.*') ? 'active' : '' }}"
                   href="{{ route('traffic.loose.index', [], false) }}">
                  <i class="fa-solid fa-map-pin"></i>
                  <span>Pedidos</span>
                </a>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.planilla-choferes.index') ? 'active' : '' }}"
                   href="{{ route('traffic.planilla-choferes.index', [], false) }}">
                  <i class="fa-solid fa-table"></i>
                  <span>Planilla diaria choferes</span>
                </a>
                
                <a class="d-none list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ $solverActive ? 'active' : '' }}"
                   href="{{ route('traffic.solver.index', [], false) }}">
                  <i class="fa-solid fa-wand-magic-sparkles"></i>
                  <span>Crear rutas</span>
                </a>
                
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.routes.index') ? 'active' : '' }}"
                   href="{{ route('traffic.routes.index', [], false) }}">
                  <i class="fa-solid fa-route"></i>
                  <span>Rutas planificadas</span>
                </a>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.routes.report') ? 'active' : '' }}"
                   href="{{ route('traffic.routes.report', [], false) }}">
                  <i class="fa-solid fa-map-location-dot"></i>
                  <span>Reporte de rutas</span>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ps-4 {{ $importConfigActive ? 'active' : '' }}"
                   data-bs-toggle="collapse" href="#trafficImportConfigCollapse" role="button"
                   aria-expanded="{{ $importConfigActive ? 'true' : 'false' }}" aria-controls="trafficImportConfigCollapse">
                  <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-gear"></i>
                    <span>Configuración</span>
                  </span>
                  <span class="chev">></span>
                </a>
                <div class="collapse {{ $importConfigActive ? 'show' : '' }}" id="trafficImportConfigCollapse" data-bs-parent="#trafficCollapse">
                  <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.loose.import-config') ? 'active' : '' }}"
                       href="{{ route('traffic.loose.import-config', [], false) }}">
                      <i class="fa-solid fa-file-import"></i>
                      <span>Importación pedidos</span>
                    </a>
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.transportistas.import-config*') ? 'active' : '' }}"
                       href="{{ route('traffic.transportistas.import-config', [], false) }}">
                      <i class="fa-solid fa-people-carry-box"></i>
                      <span>Importación choferes</span>
                    </a>
                    <a class="list-group-item list-group-item-action ps-5 d-flex align-items-center gap-2 {{ request()->routeIs('traffic.transportes.import-config*') ? 'active' : '' }}"
                       href="{{ route('traffic.transportes.import-config', [], false) }}">
                      <i class="fa-solid fa-truck-moving"></i>
                      <span>Importación transporte</span>
                    </a>
                  </div>
                </div>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 d-none {{ request()->routeIs('traffic.orders.*') ? 'active' : '' }}"
                   href="{{ route('traffic.orders.index', [], false) }}">
                  <i class="fa-solid fa-list-check"></i>
                  <span>Pedidos</span>
                </a>
              @endif
            </div>
          </div>
          @endif

          {{-- CONFIGURACION --}}
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $configActive ? 'active' : '' }}"
             data-bs-toggle="collapse" href="#configCollapse" role="button"
             aria-expanded="{{ $configActive ? 'true' : 'false' }}" aria-controls="configCollapse">
            <span class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-gears"></i>
              <span>Configuración</span>
            </span>
            <span class="chev">></span>
          </a>
          <div class="collapse {{ $configActive ? 'show' : '' }}" id="configCollapse" data-bs-parent="#sidebarMenu">
            <div class="list-group list-group-flush">
              @if($currentUser && $currentUser->isAdminOrSuper())
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('users.*') ? 'active' : '' }}"
                   href="{{ route('users.index', [], false) }}">
                  <i class="fa-solid fa-user-lock"></i>
                  <span>Usuarios (CRUD)</span>
                </a>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('confirmations.*') ? 'active' : '' }}"
                   href="{{ route('confirmations.pending', [], false) }}">
                  <i class="fa-solid fa-envelope-circle-check"></i>
                  <span>Confirmaciones pendientes</span>
                </a>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('terms.*') ? 'active' : '' }}"
                   href="{{ route('terms.edit', [], false) }}">
                  <i class="fa-solid fa-file-contract"></i>
                  <span>Términos y condiciones</span>
                </a>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('config.parameters.*') ? 'active' : '' }}"
                   href="{{ route('config.parameters.index', [], false) }}">
                  <i class="fa-solid fa-sliders-h"></i>
                  <span>Parámetros generales</span>
                </a>
              @elseif($isLimitedTransportista)
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('password.request') ? 'active' : '' }}"
                   href="{{ route('password.request', [], false) }}">
                  <i class="fa-solid fa-key"></i>
                  <span>Cambiar contraseña</span>
                </a>
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('terms.view') ? 'active' : '' }}"
                   href="{{ route('terms.view', [], false) }}">
                  <i class="fa-solid fa-file-contract"></i>
                  <span>Ver términos y condiciones</span>
                </a>
              @else
                <a class="list-group-item list-group-item-action ps-4 d-flex align-items-center gap-2 {{ request()->routeIs('users.*') ? 'active' : '' }}"
                   href="{{ route('users.index', [], false) }}">
                  <i class="fa-solid fa-user-lock"></i>
                  <span>Mi usuario</span>
                </a>
              @endif
            </div>
          </div>

          <form id="sidebarLogout" method="POST" action="{{ route('logout') }}" class="m-0 mt-3 mb-3">
            @csrf
            <button class="list-group-item list-group-item-action d-flex align-items-center gap-2 text-danger border-0 bg-transparent" type="submit">
              <i class="fa-solid fa-right-from-bracket"></i>
              <span>Salir</span>
            </button>
          </form>

            </div>
          </div>
        </div>
      </aside>

      <main class="col-lg-9 col-xl-10 col-12">
        <div class="content-card bg-white border rounded shadow-sm p-4 h-100">
        @if(session('ok'))
          <div class="alert alert-success">{{ session('ok') }}</div>
        @endif
        @if(session('info'))
          <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        @if(isset($isReadOnlyUser) && $isReadOnlyUser)
          <div class="alert alert-warning readonly-banner">
            Estas navegando con un rol de solo lectura. Las acciones de crear, editar o eliminar estan deshabilitadas.
          </div>
        @endif
        @yield('content')
        </div>
      </main>
    </div>
    @endif
    @endauth

    {{-- Si el usuario NO esta logueado (login o register) --}}
    @guest
    <div class="row">
      <main class="col-12 p-3">
        @yield('content')
      </main>
    </div>
    @endguest

  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    (function ($) {
      const isReadOnlyUser = @json($isReadOnlyUser ?? false);
      window.nygReadOnly = isReadOnlyUser;

      const initSelect2 = (context) => {
        $(context).find('select:not(.no-select2)').each(function () {
          const $select = $(this);
          if ($select.data('select2')) {
            return;
          }
          const parentModal = $select.closest('.modal');
          const placeholder = $select.attr('placeholder')
            || $select.data('placeholder')
            || ($select.find('option[value=""]').length ? $select.find('option[value=""]').first().text().trim() : '');
          $select.select2({
            width: '100%',
            dropdownParent: parentModal.length ? parentModal : $(document.body),
            placeholder: placeholder || undefined,
            allowClear: ! $select.prop('required') && !$select.attr('multiple') && placeholder,
          });
        });
      };

      const blockReadOnlyActions = () => {
        if (!isReadOnlyUser) {
          return;
        }

        const showReadOnlyWarning = () => {
          return Swal.fire({
            icon: 'info',
            text: 'Tu rol es de solo lectura. No puedes crear, editar ni eliminar.',
          });
        };

        const disableElement = ($el) => {
          if ($el.hasClass('read-only-disabled')) {
            return;
          }
          $el.addClass('read-only-disabled');
          $el.attr('aria-disabled', 'true');
          $el.on('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            showReadOnlyWarning();
          });
        };

        $('form').each(function () {
          const $form = $(this);
          const method = ($form.attr('method') || 'GET').toUpperCase();
          const spoofed = ($form.find('input[name="_method"]').val() || '').toUpperCase();
          const effectiveMethod = spoofed || method;

          if (effectiveMethod !== 'GET') {
            disableElement($form);
            $form.on('submit', function (event) {
              event.preventDefault();
              showReadOnlyWarning();
            });
            $form.find('button, input[type="submit"]').each(function () {
              disableElement($(this));
            });
          }
        });

        const writeHrefPattern = /\/(create|edit|duplicate|destroy|reassign|regenerate|send|start|import)(\/|$|\?)/i;
        $('a.btn, a.list-group-item-action, a[data-bs-toggle="modal"], button[data-bs-toggle="modal"]').each(function () {
          const href = $(this).attr('href') || '';
          if (writeHrefPattern.test(href)) {
            disableElement($(this));
          }
        });
        $('button[data-bs-toggle="modal"]').each(function () {
          disableElement($(this));
        });
        $('[data-readonly-block="true"]').each(function () {
          disableElement($(this));
        });
        $('button').each(function () {
          const $btn = $(this);
          const label = ($btn.text() || '').toLowerCase();
          const classes = ($btn.attr('class') || '').toLowerCase();
          const matchesWriteIntent = /(nuevo|nueva|crear|guardar|editar|eliminar|borrar|actualizar|duplicar|enviar|generar|asignar|asociar)/;
          if (matchesWriteIntent.test(label) || matchesWriteIntent.test(classes)) {
            disableElement($btn);
          }
        });
      };

      const initConfirmations = () => {
        $(document).on('submit', 'form[data-confirm]', function (event) {
          const form = this;
          if (form.dataset.confirmed === 'true') {
            return;
          }
          event.preventDefault();
          const message = form.getAttribute('data-confirm') || 'Confirmar accion?';
          Swal.fire({
            icon: 'warning',
            text: message,
            confirmButtonText: 'Si, continuar',
            cancelButtonText: 'Cancelar',
            showCancelButton: true,
            focusCancel: true,
          }).then((result) => {
            if (result.isConfirmed) {
              form.dataset.confirmed = 'true';
              form.submit();
            }
          });
        });
      };

      $(document).ready(function () {
        initSelect2(document);
        blockReadOnlyActions();
        initConfirmations();

        $(document).on('shown.bs.modal', '.modal', function () {
          initSelect2(this);
          blockReadOnlyActions();
        });
      });

      // Manejo global de expiración de sesión (419 / CSRF Token Mismatch) y Keep-Alive
      window.isSessionExpiredModalOpen = false;
      window.handleSessionExpired = function () {
        if (window.isSessionExpiredModalOpen) return;
        window.isSessionExpiredModalOpen = true;

        Swal.fire({
          icon: 'warning',
          title: 'Sesión Expirada',
          text: 'Su sesión ha finalizado por inactividad. Por favor, vuelva a iniciar sesión para continuar.',
          confirmButtonText: 'Ir a Iniciar Sesión',
          allowOutsideClick: false,
          allowEscapeKey: false
        }).then(() => {
          window.location.reload();
        });
      };

      // Interceptor global para llamadas AJAX de jQuery
      $(document).ajaxError(function (event, jqXHR) {
        const message = (jqXHR.responseJSON && jqXHR.responseJSON.message) || jqXHR.responseText || '';
        if (jqXHR.status === 419 || message.includes('CSRF token mismatch')) {
          window.handleSessionExpired();
        }
      });

      // Interceptor global para llamadas native fetch()
      const originalFetch = window.fetch;
      window.fetch = async function (...args) {
        try {
          const response = await originalFetch(...args);
          if (response.status === 419) {
            window.handleSessionExpired();
          }
          return response;
        } catch (err) {
          throw err;
        }
      };

      window.nygAlert = function (message, type = 'warning') {
        return Swal.fire({
          icon: type,
          text: message,
        });
      };

      window.nygConfirm = function (options = {}) {
        const defaults = {
          icon: 'warning',
          text: '',
          confirmButtonText: 'Confirmar',
          cancelButtonText: 'Cancelar',
          showCancelButton: true,
        };
        return Swal.fire(Object.assign({}, defaults, options));
      };
    })(jQuery);
  </script>
  @stack('scripts')

</body>
</html>
