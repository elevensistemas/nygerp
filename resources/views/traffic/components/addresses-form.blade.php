@php
  $wrapperId ??= 'addressesWrapper';
  $addButtonId ??= 'addAddressBtn';
  $title ??= 'Direcciones';
  $description ??= null;
  $addButtonLabel ??= 'Agregar direccion';
  $collectionName ??= 'addresses';
  $addresses ??= [];
  $minItems ??= 2;
  $minItemsMessage ??= null;
  $newAddressDefaults ??= ['label' => '', 'address' => ''];
  // startEmpty=true evita precargar filas (para que inicie vacio)
  $startEmpty ??= false;
  $instanceId = 'addresses_' . uniqid();
  $builderConfig = [
    'instanceId' => $instanceId,
    'wrapperId' => $wrapperId,
    'addButtonId' => $addButtonId,
    'collectionName' => $collectionName,
    'addresses' => $addresses,
    'minItems' => $minItems,
    'minItemsMessage' => $minItemsMessage,
    'newAddressDefaults' => $newAddressDefaults,
    'mapboxToken' => config('services.mapbox.token'),
    'startEmpty' => $startEmpty,
  ];
@endphp

<div class="card-header bg-white d-flex align-items-center justify-content-between">
  <div>
    <h2 class="h5 mb-0">{{ $title }}</h2>
    @if($description)
      <small class="text-muted">{{ $description }}</small>
    @endif
  </div>
  <button type="button" class="btn btn-sm btn-outline-primary" id="{{ $addButtonId }}">
    <i class="fa-solid fa-plus me-1"></i> {{ $addButtonLabel }}
  </button>
</div>
<div class="card-body py-0">
  <div id="{{ $wrapperId }}" class="addresses-form-wrapper"></div>
</div>

@push('scripts')
  <script>
    window.__addressesFormBuilderConfigs = window.__addressesFormBuilderConfigs || [];
    window.__addressesFormBuilderConfigs.push(@json($builderConfig, JSON_UNESCAPED_UNICODE));
  </script>
@endpush

@once('addresses-form-styles')
  @push('styles')
    <style>
      .addresses-card.address-status-valid > .card-header {
        background: rgba(25, 135, 84, 0.12) !important;
      }
      .addresses-card.address-status-invalid > .card-header {
        background: rgba(255, 153, 0, 0.18) !important;
      }
      .addresses-card.address-status-valid {
        border-left: 4px solid rgba(25, 135, 84, 0.5);
      }
      .addresses-card.address-status-invalid {
        border-left: 4px solid rgba(255, 153, 0, 0.6);
      }
      .address-mini-map {
        height: 160px;
        width: 160px;
        display: inline-block;
        overflow: hidden;
        background: #f8f9fa;
      }
    </style>
  @endpush
@endonce

@once('addresses-form-builder-script')
  @push('scripts')
    <script>
      (function () {
        const cloneAddress = (source) => {
          const result = {};
          if (!source) return result;
          Object.keys(source).forEach((key) => {
            result[key] = source[key];
          });
          return result;
        };

        class TrafficAddressesBuilder {
          constructor(config) {
            this.wrapper = document.getElementById(config.wrapperId);
            if (!this.wrapper) return;
            this.addButton = config.addButtonId ? document.getElementById(config.addButtonId) : null;
            this.collectionName = config.collectionName || 'addresses';
            this.startEmpty = !!config.startEmpty;
            this.minItems = Number(config.minItems) || 1;
            this.minItemsMessage = config.minItemsMessage || `Se requieren al menos ${this.minItems} direcciones.`;
            this.instanceId = config.instanceId || `addresses-${Date.now()}`;
            this.mapboxToken = config.mapboxToken || '';
            this.newAddressDefaults = cloneAddress(config.newAddressDefaults || {});
            this.addresses = Array.isArray(config.addresses)
              ? config.addresses.map(cloneAddress).filter(Boolean)
              : [];
            this.render();
            if (this.addButton) {
              this.addButton.addEventListener('click', () => this.addAddress());
            }
            window.addressesFormBuilders = window.addressesFormBuilders || {};
            window.addressesFormBuilders[config.wrapperId] = this;
          }

          buildFieldName(index, field) {
            return `${this.collectionName}[${index}][${field}]`;
          }

          addAddress() {
            this.syncFromDom();
            this.addresses.push(cloneAddress(this.newAddressDefaults));
            this.render();
          }

          addAddresses(addressList) {
            if (!Array.isArray(addressList) || !addressList.length) return;
            this.syncFromDom();
            addressList.forEach((addr) => {
              this.addresses.push(cloneAddress(Object.assign({}, this.newAddressDefaults, addr)));
            });
            this.render();
          }

          removeAddress(index) {
            if (this.addresses.length <= this.minItems) {
              if (this.minItemsMessage) {
                if (typeof window.nygAlert === 'function') {
                  window.nygAlert(this.minItemsMessage, 'warning');
                } else {
                  console.warn(this.minItemsMessage);
                }
              }
              return;
            }

            this.syncFromDom();
            this.addresses.splice(index, 1);
            this.render();
          }

          emitChange() {
            if (!this.wrapper) return;
            const current = this.collectFromDom();
            const event = new CustomEvent('addresses:updated', {
              detail: { addresses: current.length ? current : this.addresses },
            });
            this.wrapper.dispatchEvent(event);
          }

          collectFromDom() {
            const cards = this.wrapper.querySelectorAll('.addresses-card');
            return Array.from(cards).map((card) => {
              const entry = {};
              card.querySelectorAll('[data-field]').forEach((input) => {
                entry[input.dataset.field] = input.value ?? '';
              });
              return entry;
            });
          }

          syncFromDom() {
            const cards = this.wrapper.querySelectorAll('.addresses-card');
            this.addresses = Array.from(cards).map((card) => {
              const entry = {};
              card.querySelectorAll('[data-field]').forEach((input) => {
                entry[input.dataset.field] = input.value ?? '';
              });
              return entry;
            });
          }

          render() {
            if (!this.wrapper) return;
            if (!this.addresses.length && !this.startEmpty) {
              const fallback = Math.max(this.minItems, 1);
              for (let i = 0; i < fallback; i += 1) {
                this.addresses.push(cloneAddress(this.newAddressDefaults));
              }
            }

            this.wrapper.innerHTML = '';
            this.addresses.forEach((address, index) => {
              const card = this.buildCard(address, index);
              this.wrapper.appendChild(card);
              this.initializeMiniMap(card, address);
            });
            this.emitChange();
          }

          buildCard(address, index) {
            const card = document.createElement('div');
            card.className = 'card border rounded mb-3 addresses-card';
            card.dataset.index = index;

            const status = this.resolveStatus(address);
            if (status) {
              card.classList.add(`address-status-${status}`);
            }

            const hasValue = Boolean((address.address || '').toString().trim() || (address.label || '').toString().trim());
            const bodyHidden = hasValue;

            const header = document.createElement('div');
            header.className = 'card-header bg-white d-flex align-items-center justify-content-between gap-3';

            const headerTitle = document.createElement('div');
            const title = document.createElement('strong');
            title.className = 'mb-0';
            title.textContent = `Dirección ${index + 1}`;
            const preview = document.createElement('p');
            preview.className = 'mb-0 small text-muted addresses-card-preview';
            const makePreview = () => {
              const contact = (contactField.input.value || '').trim();
              const phone = (phoneField.input.value || '').trim();
              const addr = (addressField.input.value || '').trim();
              const labelVal = (labelField.input.value || '').trim();
              const contactInfo = contact || phone ? ` — ${contact}${phone ? ' (' + phone + ')' : ''}` : '';
              preview.textContent = (addr || labelVal || `Dirección ${index + 1}`) + contactInfo;
            };
            preview.textContent = address.address || address.label || `Dirección ${index + 1}`;
            headerTitle.appendChild(title);
            headerTitle.appendChild(preview);

            const actions = document.createElement('div');
            actions.className = 'd-flex gap-1 align-items-center';
            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.className = 'btn btn-sm btn-outline-secondary';
            toggleButton.setAttribute('aria-expanded', (!bodyHidden).toString());
            const toggleIcon = document.createElement('i');
            toggleIcon.className = `fa-solid ${bodyHidden ? 'fa-chevron-down' : 'fa-chevron-up'}`;
            toggleButton.appendChild(toggleIcon);
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-sm btn-link text-danger';
            deleteButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
            if (this.addresses.length <= this.minItems) {
              deleteButton.disabled = true;
              if (this.minItemsMessage) {
                deleteButton.title = this.minItemsMessage;
              }
            }
            actions.appendChild(toggleButton);
            actions.appendChild(deleteButton);

            header.appendChild(headerTitle);
            header.appendChild(actions);

            const body = document.createElement('div');
            body.className = 'card-body';
            if (bodyHidden) {
              body.classList.add('d-none');
            }

            const row = document.createElement('div');
            row.className = 'row g-3';
            const labelField = this.createInputColumn('Etiqueta', 'label', index, address.label, 'col-md-4');
            const addressField = this.createAddressColumn(index, address);
            const contactField = this.createInputColumn('Contacto', 'contact_name', index, address.contact_name, 'col-md-4');
            const phoneField = this.createInputColumn('Telefono', 'contact_phone', index, address.contact_phone, 'col-md-4');
            const cityField = this.createInputColumn('Ciudad', 'city', index, address.city, 'col-md-4');
            const postalField = this.createInputColumn('Codigo postal', 'postal_code', index, address.postal_code, 'col-md-4');
            row.appendChild(labelField.col);
            row.appendChild(addressField.col);
            row.appendChild(contactField.col);
            row.appendChild(phoneField.col);
            row.appendChild(cityField.col);
            row.appendChild(postalField.col);

            const notesField = this.createTextareaColumn('Notas', 'notes', index, address.notes, 'col-12');
            const latField = this.createHiddenField(index, 'latitude', address.latitude);
            const lngField = this.createHiddenField(index, 'longitude', address.longitude);

            body.appendChild(latField);
            body.appendChild(lngField);
            body.appendChild(row);
            body.appendChild(notesField.col);

            const miniMap = document.createElement('div');
            miniMap.className = 'address-mini-map border rounded mt-2';
            miniMap.dataset.mapKey = `${this.instanceId}-${index}`;
            body.appendChild(miniMap);

            const updatePreview = makePreview;

            const setToggleIcon = (expanded) => {
              toggleIcon.className = `fa-solid ${expanded ? 'fa-chevron-up' : 'fa-chevron-down'}`;
              toggleButton.setAttribute('aria-expanded', expanded.toString());
            };

          toggleButton.addEventListener('click', () => {
            const isNowExpanded = body.classList.toggle('d-none');
            setToggleIcon(!isNowExpanded);
            if (!isNowExpanded && card.__miniMap) {
              setTimeout(() => {
                card.__miniMap.invalidateSize();
              }, 0);
            }
          });

            deleteButton.addEventListener('click', () => this.removeAddress(index));

            labelField.input.addEventListener('input', updatePreview);
            addressField.input.addEventListener('input', () => {
              updatePreview();
              const latInput = card.querySelector('[data-field="latitude"]');
              const lngInput = card.querySelector('[data-field="longitude"]');
              if (latInput) latInput.value = '';
              if (lngInput) lngInput.value = '';
              this.applyCardStatus(card, null);
              this.initializeMiniMap(card, {});
              this.emitChange();
            });
            contactField.input.addEventListener('input', updatePreview);
            phoneField.input.addEventListener('input', updatePreview);

            this.setupAutocompleteForInput(addressField.input, updatePreview);

            card.appendChild(header);
            card.appendChild(body);

            return card;
          }

          createInputColumn(labelText, field, index, value, colClasses) {
            const col = document.createElement('div');
            col.className = colClasses;
            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = labelText;
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control';
            input.name = this.buildFieldName(index, field);
            input.dataset.field = field;
            input.value = value || '';
            col.appendChild(label);
            col.appendChild(input);
            return { col, input };
          }

          createTextareaColumn(labelText, field, index, value, colClasses) {
            const col = document.createElement('div');
            col.className = colClasses;
            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = labelText;
            const textarea = document.createElement('textarea');
            textarea.className = 'form-control';
            textarea.rows = 2;
            textarea.name = this.buildFieldName(index, field);
            textarea.dataset.field = field;
            textarea.value = value || '';
            col.appendChild(label);
            col.appendChild(textarea);
            return { col, input: textarea };
          }

          createAddressColumn(index, address) {
            const col = document.createElement('div');
            col.className = 'col-md-8';
            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = 'Direccion *';
            const wrapper = document.createElement('div');
            wrapper.className = 'position-relative js-address-autocomplete-wrapper';
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control js-address-input';
            input.autocomplete = 'off';
            input.required = true;
            input.name = this.buildFieldName(index, 'address');
            input.dataset.field = 'address';
            input.value = address.address || '';
            const suggestions = document.createElement('div');
            suggestions.className = 'list-group position-absolute w-100 js-address-suggestions';
            suggestions.style.zIndex = '1000';
            suggestions.style.maxHeight = '240px';
            suggestions.style.overflowY = 'auto';
            suggestions.style.top = '100%';
            suggestions.style.left = '0';
            wrapper.appendChild(input);
            wrapper.appendChild(suggestions);
            col.appendChild(label);
            col.appendChild(wrapper);
            return { col, input };
          }

          createHiddenField(index, field, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = this.buildFieldName(index, field);
            input.dataset.field = field;
            input.value = value || '';
            return input;
          }

          resolveStatus(address) {
            if (address && Object.prototype.hasOwnProperty.call(address, 'is_valid')) {
              return address.is_valid ? 'valid' : 'invalid';
            }
            return null;
          }

          applyCardStatus(card, status) {
            if (!card) return;
            card.classList.remove('address-status-valid', 'address-status-invalid');
            if (status) {
              card.classList.add(`address-status-${status}`);
            }
          }

          initializeMiniMap(card, address) {
            const mapWrapper = card ? card.querySelector('.address-mini-map') : null;
            if (!mapWrapper) return;

            const latRaw = address['lat'] ?? address['latitude'] ?? null;
            const lngRaw = address['lng'] ?? address['longitude'] ?? null;
            const lat = parseFloat(latRaw);
            const lng = parseFloat(lngRaw);
            const isValid = address && Object.prototype.hasOwnProperty.call(address, 'is_valid')
              ? !!address.is_valid
              : null;

            if (!window.L) {
              mapWrapper.classList.add('d-none');
              if (!mapWrapper.dataset.mapPending) {
                mapWrapper.dataset.mapPending = '1';
                setTimeout(() => {
                  mapWrapper.dataset.mapPending = '';
                  this.initializeMiniMap(card, address);
                }, 500);
              }
              return;
            }

            mapWrapper.classList.remove('d-none');
            if (isValid === false) {
              if (card.__miniMap) {
                card.__miniMap.remove();
                card.__miniMap = null;
                card.__miniMarker = null;
              }
              mapWrapper.innerHTML = '<div class="text-warning small p-2">Direccion no encontrada.</div>';
              return;
            }
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
              if (card.__miniMap) {
                card.__miniMap.remove();
                card.__miniMap = null;
                card.__miniMarker = null;
              }
              mapWrapper.innerHTML = '<div class="text-muted small p-2">Sin coordenadas para previsualizar.</div>';
              return;
            }

            if (!card.__miniMap) {
              mapWrapper.innerHTML = '';
              const map = window.L.map(mapWrapper, {
                attributionControl: false,
                zoomControl: false,
              }).setView([parseFloat(lat), parseFloat(lng)], 18);

              window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 20,
              }).addTo(map);

              card.__miniMarker = window.L.marker([parseFloat(lat), parseFloat(lng)]).addTo(map);
              card.__miniMap = map;
              setTimeout(() => map.invalidateSize(), 0);
              return;
            }

            card.__miniMap.setView([parseFloat(lat), parseFloat(lng)], 18);
            if (card.__miniMarker) {
              card.__miniMarker.setLatLng([parseFloat(lat), parseFloat(lng)]);
            } else {
              card.__miniMarker = window.L.marker([parseFloat(lat), parseFloat(lng)]).addTo(card.__miniMap);
            }
            setTimeout(() => card.__miniMap.invalidateSize(), 0);
          }

          setupAutocompleteForInput(input, updatePreview) {
            if (!this.mapboxToken) return;
            const wrapper = input.closest('.js-address-autocomplete-wrapper');
            if (!wrapper) return;
            const suggestionsList = wrapper.querySelector('.js-address-suggestions');
            if (!suggestionsList) return;

            const fetchSuggestions = async () => {
              const query = input.value.trim();
              suggestionsList.innerHTML = '';
              if (query.length < 3) return;

              const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json` +
                `?access_token=${this.mapboxToken}` +
                '&country=AR' +
                '&language=es' +
                '&autocomplete=true' +
                '&limit=5';

              try {
                const response = await fetch(url);
                if (!response.ok) {
                  console.error('Error en geocoding:', response.status);
                  return;
                }

                const data = await response.json();
                if (!data.features || !data.features.length) return;

                data.features.forEach((feature) => {
                  const item = document.createElement('button');
                  item.type = 'button';
                  item.className = 'list-group-item list-group-item-action';
                  item.textContent = feature.place_name || '';
                  item.addEventListener('click', () => {
                    input.value = feature.place_name || '';
                    updatePreview();
                    const coords = feature.geometry?.coordinates;
                    if (Array.isArray(coords) && coords.length === 2) {
                      const card = input.closest('.addresses-card');
                      const latInput = card?.querySelector('[data-field="latitude"]');
                      const lngInput = card?.querySelector('[data-field="longitude"]');
                      if (latInput) latInput.value = coords[1];
                      if (lngInput) lngInput.value = coords[0];
                      this.applyCardStatus(card, 'valid');
                      this.initializeMiniMap(card, {
                        latitude: coords[1],
                        longitude: coords[0],
                      });
                    }
                    this.emitChange();
                    suggestionsList.innerHTML = '';
                  });
                  suggestionsList.appendChild(item);
                });
              } catch (error) {
                console.error('Error en autocomplete Mapbox:', error);
              }
            };

            input.addEventListener('input', fetchSuggestions);
            document.addEventListener('click', (event) => {
              if (!wrapper.contains(event.target)) {
                suggestionsList.innerHTML = '';
              }
            });
          }
        }

        document.addEventListener('DOMContentLoaded', () => {
          const configs = window.__addressesFormBuilderConfigs || [];
          window.__addressesFormBuilderConfigs = [];
          configs.forEach((config) => new TrafficAddressesBuilder(config));
        });
      })();
    </script>
  @endpush
@endonce
