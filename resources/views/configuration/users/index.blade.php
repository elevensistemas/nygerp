@extends('layouts.app')
@section('title','Usuarios')
@section('content')
@php
  use App\Models\User;
  $currentUser = $currentUser ?? auth()->user();
  $canManageUsers = $canManageUsers ?? $currentUser->isAdminOrSuper();
  $roleLabels = $roles ?? User::roleLabels();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h5 mb-0">{{ $canManageUsers ? 'Administrar usuarios' : 'Mi usuario' }}</h1>
    @unless($canManageUsers)
      <p class="text-muted small mb-0">Solo puedes modificar tus datos y cambiar tu contraseña.</p>
    @endunless
  </div>
  <div class="d-flex gap-2 flex-wrap">
    @if($canManageUsers)
      <form method="get" action="{{ route('users.index') }}" class="d-flex">
        <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Buscar por nombre o email">
        <button class="btn btn-sm btn-outline-secondary ms-2">Buscar</button>
      </form>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" id="btnNewUser">
        Nuevo usuario
      </button>
    @endif
  </div>
</div>

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="table-responsive bg-white rounded shadow-sm">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Usuario</th>
        <th>Email</th>
        <th>Rol</th>
        <th>Estado</th>
        <th>Creado</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($users as $user)
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              @if($user->avatar_path)
                <img src="{{ asset('storage/' . $user->avatar_path) }}" alt="Avatar" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
              @else
                <span class="avatar-circle" style="width:32px;height:32px;font-size:0.85rem;">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
              @endif
              <div>
                <div class="fw-semibold">{{ $user->name }}</div>
                @if($user->id === 1)
                  <span class="badge bg-secondary ms-2">Admin</span>
                @endif
              </div>
            </div>
          </td>
          <td>{{ $user->email }}</td>
          <td>{{ $roleLabels[$user->role] ?? ucfirst($user->role) }}</td>
          <td>
            @if($user->hasAcceptedTerms())
              <span class="badge bg-success">Aceptado</span>
            @else
              <span class="badge bg-warning text-dark">Pendiente</span>
            @endif
          </td>
          <td>{{ optional($user->created_at)->format('d/m/Y H:i') }}</td>
          <td class="text-end">
            @if($canManageUsers || $currentUser->id === $user->id)
              <button class="btn btn-sm btn-outline-secondary btn-edit-user" data-id="{{ $user->id }}">Editar</button>
            @endif
            @if($canManageUsers)
              <form method="post"
                    action="{{ route('confirmations.resend', $user) }}"
                    class="d-inline">
                @csrf
                <button class="btn btn-sm btn-outline-primary" type="submit">Reenviar email</button>
              </form>
            @endif
            @if($canManageUsers && $user->needsAcceptance())
              <button
                type="button"
                class="btn btn-sm btn-outline-success btn-validate-user"
                data-id="{{ $user->id }}"
                data-name="{{ $user->name }}"
                data-url="{{ route('users.validate', $user) }}"
              >
                Validar
              </button>
            @endif
            @if($canManageUsers && $user->id !== 1)
              <form method="post" action="{{ route('users.destroy', $user) }}" class="d-inline" data-confirm="Eliminar usuario?">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
            @elseif(!$canManageUsers && $currentUser->id === $user->id)
              <span class="badge bg-light text-dark border">Solo tú</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No se encontraron usuarios.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $users->links() }}</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="{{ route('users.store') }}" id="userForm" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nuevo usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Imagen de usuario</label>
            <div class="d-flex align-items-center gap-3">
              <div class="avatar-circle" id="userAvatarPreview">
                <i class="fa-solid fa-user"></i>
              </div>
              <div class="flex-grow-1">
                <input type="file" name="avatar" class="form-control" accept="image/*">
                <div class="form-text">JPG o PNG. Max 2 MB.</div>
              </div>
            </div>
          </div>
          @if($canManageUsers)
            <div class="mb-3">
              <label class="form-label">Rol</label>
              <select name="role" class="form-select" required>
                @foreach($roleLabels as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3 transportista-fields d-none" id="transportistaFields">
              <div class="mb-3">
                <label class="form-label">Proveedor asociado</label>
                <select name="profile_supplier_id" class="form-select">
                  <option value="">Sin proveedor</option>
                  @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Licencia</label>
                  <input name="profile_license" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Base operativa</label>
                  <input name="profile_base_location" class="form-control">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="profile_notes" class="form-control" rows="2"></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Color de transportista</label>
                <input class="form-control form-control-color" type="color" name="profile_color" value="#2563eb">
                <div class="form-text">Se usa en mapas y listados de rutas.</div>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="profile_active" id="transportistaActive" checked>
                <label class="form-check-label" for="transportistaActive">Transportista activo</label>
              </div>
            </div>
          @endif
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
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
  const csrfToken = "{{ csrf_token() }}";
  const modalEl = document.getElementById('userModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('userForm');
  const titleEl = modalEl.querySelector('.modal-title');
  const roleField = form.querySelector('[name="role"]');
  const roleSelect = roleField;
  const transportistaFields = document.getElementById('transportistaFields');
  const supplierSelect = transportistaFields ? transportistaFields.querySelector('[name="profile_supplier_id"]') : null;
  const licenseField = transportistaFields ? transportistaFields.querySelector('[name="profile_license"]') : null;
  const baseField = transportistaFields ? transportistaFields.querySelector('[name="profile_base_location"]') : null;
  const notesField = transportistaFields ? transportistaFields.querySelector('[name="profile_notes"]') : null;
  const colorField = transportistaFields ? transportistaFields.querySelector('[name="profile_color"]') : null;
  const activeSwitch = transportistaFields ? transportistaFields.querySelector('[name="profile_active"]') : null;
  const avatarInput = form.querySelector('input[name="avatar"]');
  const avatarPreview = document.getElementById('userAvatarPreview');
  const storageBase = "{{ asset('storage') }}";

  const setAvatarPreview = (path, name) => {
    if (!avatarPreview) return;
    const initial = (name || 'U').trim().charAt(0).toUpperCase();
    if (path) {
      avatarPreview.innerHTML = `<img src="${storageBase}/${path}" alt="Avatar" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">`;
      return;
    }
    avatarPreview.textContent = initial;
  };

  if (avatarInput) {
    avatarInput.addEventListener('change', () => {
      const file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        if (avatarPreview) {
          avatarPreview.innerHTML = `<img src="${reader.result}" alt="Avatar" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">`;
        }
      };
      reader.readAsDataURL(file);
    });
  }

  const setPasswordRequired = (required) => {
    form.querySelectorAll('input[name="password"], input[name="password_confirmation"]').forEach(input => {
      if (required) {
        input.setAttribute('required', 'required');
      } else {
        input.removeAttribute('required');
      }
      input.value = '';
    });
  };

  const resetForm = () => {
    form.reset();
    const methodField = form.querySelector('input[name="_method"]');
    if (methodField) {
      methodField.remove();
    }
    if (avatarInput) {
      avatarInput.value = '';
    }
    setAvatarPreview(null, '');
  };

  const defaultRoleValue = () => {
    if (! roleField || ! roleField.options.length) {
      return '';
    }
    const preferred = Array.from(roleField.options).find(opt => opt.value === '{{ User::ROLE_USER }}');
    return (preferred || roleField.options[0]).value;
  };

  const fillRole = (role) => {
    if (! roleField) return;
    const roleValue = role || '';
    const exists = roleValue && Array.from(roleField.options).some(opt => opt.value === roleValue);
    if (roleValue && !exists) {
      const opt = document.createElement('option');
      opt.value = roleValue;
      opt.textContent = roleValue;
      roleField.appendChild(opt);
    }
    const value = roleValue || defaultRoleValue();
    if (value) {
      roleField.value = value;
    }
  };

  const fillProfileData = (profile = null) => {
    if (! transportistaFields) {
      return;
    }
    if (supplierSelect) {
      const supplierId = profile?.supplier_id ?? '';
      if (supplierId) {
        const exists = Array.from(supplierSelect.options).some(opt => String(opt.value) === String(supplierId));
        if (!exists) {
          const opt = document.createElement('option');
          opt.value = supplierId;
          opt.textContent = profile?.supplier?.name || `Proveedor #${supplierId}`;
          supplierSelect.appendChild(opt);
        }
      }
      supplierSelect.value = supplierId;
    }
    licenseField && (licenseField.value = profile?.license_number ?? '');
    baseField && (baseField.value = profile?.base_location ?? '');
    notesField && (notesField.value = profile?.notes ?? '');
    colorField && (colorField.value = profile?.color ?? '#2563eb');
    if (activeSwitch) {
      activeSwitch.checked = profile ? profile.is_active : true;
    }
  };

  const toggleTransportistaField = () => {
    if (! transportistaFields) {
      return;
    }
    const show = roleSelect && roleSelect.value === 'transportista';
    transportistaFields.classList.toggle('d-none', !show);
    if (!show) {
      fillProfileData();
    }
  };
  roleSelect?.addEventListener('change', toggleTransportistaField);
  roleSelect?.addEventListener('input', toggleTransportistaField);
  toggleTransportistaField();
  const newUserBtn = document.getElementById('btnNewUser');
  if (newUserBtn) {
      newUserBtn.addEventListener('click', () => {
        resetForm();
        form.action = "{{ route('users.store') }}";
        titleEl.textContent = 'Nuevo usuario';
        setPasswordRequired(true);
        fillRole('');
        fillProfileData();
        setAvatarPreview(null, '');
        toggleTransportistaField();
        roleSelect && roleSelect.dispatchEvent(new Event('change'));
      });
  }

  document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', () => {
      const userId = btn.dataset.id;
      fetch(`/users/${userId}/edit`)
        .then(response => {
          if (!response.ok) {
            throw new Error('No se pudo cargar el usuario');
          }
          return response.json();
        })
          .then(data => {
            resetForm();
            form.action = `/users/${userId}`;
            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'PUT';
            form.prepend(methodField);
            titleEl.textContent = 'Editar usuario';
            setPasswordRequired(false);
            form.querySelector('[name="name"]').value = data.name ?? '';
            form.querySelector('[name="email"]').value = data.email ?? '';
            setAvatarPreview(data.avatar_path ?? null, data.name ?? '');
            fillRole(data.role);
            fillProfileData(data.transportista_profile ?? data.transportistaProfile ?? null);
            toggleTransportistaField();
            roleSelect && roleSelect.dispatchEvent(new Event('change'));
            modal.show();
          })
        .catch(() => {
          window.nygAlert('No se pudo cargar el usuario', 'error');
        });
    });
  });
  const attachValidationButtons = () => {
    document.querySelectorAll('.btn-validate-user').forEach(btn => {
      btn.addEventListener('click', () => {
        const userName = btn.dataset.name;
        const url = btn.dataset.url;
        nygConfirm({
          title: 'Validar usuario manualmente',
          text: `Validar ${userName} sin confirmación por email. No quedará registrada la aceptación de términos y condiciones.`,
          icon: 'warning',
          confirmButtonText: 'Validar de todos modos',
        }).then(result => {
          if (! result.isConfirmed) {
            return;
          }

          fetch(url, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
          })
            .then(async response => {
              if (! response.ok) {
                throw response;
              }
              return response.json();
            })
            .then(data => {
              window.nygAlert(data.message ?? 'Usuario validado manualmente.', 'success')
                .then(() => location.reload());
            })
            .catch(async error => {
              let message = 'No se pudo validar el usuario.';
              if (error?.json) {
                const json = await error.json().catch(() => null);
                if (json?.message) {
                  message = json.message;
                }
              }
              window.nygAlert(message, 'error');
            });
        });
      });
    });
  };

  attachValidationButtons();
});
</script>
@endpush
