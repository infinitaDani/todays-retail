<x-layouts.tenant
    title="Floor Plans"
    subtitle="Distribución visual de estructuras y accesorios por sucursal, piso o zona"
>
    <div class="tr-card mb-3">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-5 col-xl-4">
                <label class="form-label" for="branch_id">Sucursal</label>

                <select
                    class="form-select"
                    id="branch_id"
                    name="branch_id"
                >
                    @foreach ($branches as $branch)
                        <option
                            value="{{ $branch->id }}"
                            @selected($selectedBranch?->id === $branch->id)
                        >
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-5 col-xl-4">
                <label class="form-label" for="floor_plan_id">
                    Floor Plan / piso / zona
                </label>

                <select
                    class="form-select"
                    id="floor_plan_id"
                    name="floor_plan_id"
                    @disabled($floorPlans->isEmpty())
                >
                    @forelse ($floorPlans as $option)
                        <option
                            value="{{ $option->id }}"
                            @selected($floorPlan?->id === $option->id)
                        >
                            {{ $option->name }}{{ $option->is_active ? '' : ' · Inactivo' }}
                        </option>
                    @empty
                        <option value="">Sin Floor Plans</option>
                    @endforelse
                </select>
            </div>

            <div class="col-auto">
                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Abrir
                </button>
            </div>

            @if ($canManage)
                <div class="col-auto ms-md-auto">
                    <a
                        class="btn btn-light"
                        href="{{ route('merchandising.fixture-types.index') }}"
                    >
                        <i data-lucide="blocks"></i>
                        Administrar elementos
                    </a>
                </div>
            @endif
        </form>
    </div>

    @if (! $selectedBranch)
        <div class="alert alert-info">
            No hay una sucursal activa disponible para mostrar Floor Plans.
        </div>
    @else
        @if ($canManage)
            <div class="mb-3">
                <button
                    class="btn btn-outline-primary"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#new-floor-plan"
                    aria-expanded="{{ $floorPlans->isEmpty() ? 'true' : 'false' }}"
                    aria-controls="new-floor-plan"
                >
                    <i data-lucide="plus"></i>
                    Nuevo Floor Plan
                </button>
            </div>

            <div
                class="collapse {{ $floorPlans->isEmpty() ? 'show' : '' }} mb-3"
                id="new-floor-plan"
            >
                <div class="tr-card">
                    <h5>
                        Crear Floor Plan para {{ $selectedBranch->name }}
                    </h5>

                    <form
                        class="row g-3 align-items-end"
                        method="POST"
                        action="{{ route('merchandising.floor-plans.store') }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="branch_id"
                            value="{{ $selectedBranch->id }}"
                        >

                        <div class="col-md-5">
                            <label
                                class="form-label"
                                for="new-plan-name"
                            >
                                Nombre
                            </label>

                            <input
                                class="form-control"
                                id="new-plan-name"
                                name="name"
                                value="{{ old('name', 'Floor Plan · ' . $selectedBranch->name) }}"
                                required
                            >
                        </div>

                        <div class="col-sm-4 col-md-2">
                            <label
                                class="form-label"
                                for="new-plan-width"
                            >
                                Ancho
                            </label>

                            <input
                                class="form-control"
                                id="new-plan-width"
                                type="number"
                                name="canvas_width"
                                min="400"
                                max="5000"
                                value="{{ old('canvas_width', 1200) }}"
                                required
                            >
                        </div>

                        <div class="col-sm-4 col-md-2">
                            <label
                                class="form-label"
                                for="new-plan-height"
                            >
                                Alto
                            </label>

                            <input
                                class="form-control"
                                id="new-plan-height"
                                type="number"
                                name="canvas_height"
                                min="300"
                                max="5000"
                                value="{{ old('canvas_height', 700) }}"
                                required
                            >
                        </div>

                        <div class="col-sm-4 col-md-1">
                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input"
                                    id="new-plan-active"
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    checked
                                >

                                <label
                                    class="form-check-label"
                                    for="new-plan-active"
                                >
                                    Activo
                                </label>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <button
                                class="btn btn-primary w-100"
                                type="submit"
                            >
                                Crear
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if (! $floorPlan)
            <div class="alert alert-info">
                @if ($canManage)
                    Esta sucursal todavía no tiene Floor Plans. Crea el primero para comenzar.
                @else
                    Esta sucursal todavía no tiene un Floor Plan disponible.
                @endif
            </div>
        @else
            @php
                $initialLayout = $floorPlan->items->map(function ($item) {
                    return [
                        'client_key' => 'item-' . $item->id,
                        'parent_client_key' => $item->parent_item_id
                            ? 'item-' . $item->parent_item_id
                            : null,
                        'fixture_type_id' => $item->fixture_type_id,
                        'label' => $item->label,
                        'position_x' => (float) $item->position_x,
                        'position_y' => (float) $item->position_y,
                        'width' => (float) $item->width,
                        'height' => (float) $item->height,
                        'rotation' => (float) $item->rotation,
                    ];
                })->values();
            @endphp

            @if ($canManage)
                <form
                    id="floor-plan-form"
                    method="POST"
                    action="{{ route('merchandising.floor-plans.update', $floorPlan) }}"
                >
                    @csrf
                    @method('PUT')

                    <div class="tr-card mb-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label
                                    class="form-label"
                                    for="plan-name"
                                >
                                    Nombre
                                </label>

                                <input
                                    class="form-control"
                                    id="plan-name"
                                    name="name"
                                    value="{{ old('name', $floorPlan->name) }}"
                                    required
                                >
                            </div>

                            <div class="col-sm-4 col-md-2">
                                <label
                                    class="form-label"
                                    for="plan-width"
                                >
                                    Ancho
                                </label>

                                <input
                                    class="form-control"
                                    id="plan-width"
                                    type="number"
                                    name="canvas_width"
                                    min="400"
                                    max="5000"
                                    value="{{ old('canvas_width', $floorPlan->canvas_width) }}"
                                    required
                                >
                            </div>

                            <div class="col-sm-4 col-md-2">
                                <label
                                    class="form-label"
                                    for="plan-height"
                                >
                                    Alto
                                </label>

                                <input
                                    class="form-control"
                                    id="plan-height"
                                    type="number"
                                    name="canvas_height"
                                    min="300"
                                    max="5000"
                                    value="{{ old('canvas_height', $floorPlan->canvas_height) }}"
                                    required
                                >
                            </div>

                            <div class="col-sm-4 col-md-1">
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        id="plan-active"
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        @checked(old('is_active', $floorPlan->is_active))
                                    >

                                    <label
                                        class="form-check-label"
                                        for="plan-active"
                                    >
                                        Activo
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <button
                                    class="btn btn-primary w-100"
                                    type="submit"
                                >
                                    <i data-lucide="save"></i>
                                    Guardar
                                </button>
                            </div>
                        </div>
                    </div>

                    <input
                        id="floor-plan-layout"
                        type="hidden"
                        name="layout"
                        value="{{ json_encode($initialLayout) }}"
                    >
                </form>
            @endif

            <div class="row g-3">
                @if ($canManage)
                    <div class="col-xl-3">
                        <div class="tr-card mb-3">
                            <h5 class="mb-3">
                                Elementos
                            </h5>

                            <p class="text-muted small">
                                Selecciona un elemento para colocarlo y arrástralo dentro del canvas.
                            </p>

                            @foreach ([
                                'structure' => 'Estructuras',
                                'accessory' => 'Accesorios',
                            ] as $category => $label)
                                <h6 class="text-uppercase text-muted mt-4 mb-2">
                                    {{ $label }}
                                </h6>

                                <div class="d-grid gap-2">
                                    @forelse ($fixtureTypes->where('category', $category) as $fixtureType)
                                        <button
                                            class="btn btn-light border text-start d-flex align-items-center gap-2 fixture-palette-item"
                                            type="button"
                                            data-fixture-id="{{ $fixtureType->id }}"
                                            data-fixture-name="{{ $fixtureType->name }}"
                                            data-fixture-category="{{ $fixtureType->category }}"
                                            data-fixture-icon="{{ $fixtureType->iconUrl() }}"
                                        >
                                            <x-merchandising.fixture-icon
                                                :fixture-type="$fixtureType"
                                                :size="44"
                                            />

                                            <span>
                                                {{ $fixtureType->name }}
                                            </span>
                                        </button>
                                    @empty
                                        <div class="text-muted small">
                                            No hay {{ mb_strtolower($label) }} activos.
                                        </div>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>

                        <div
                            class="tr-card"
                            id="fixture-properties"
                        >
                            <h5 class="mb-3">
                                Propiedades
                            </h5>

                            <p
                                class="text-muted small mb-0"
                                id="fixture-properties-empty"
                            >
                                Selecciona un elemento del canvas.
                            </p>

                            <div
                                class="d-none"
                                id="fixture-properties-fields"
                            >
                                <div class="mb-3">
                                    <label
                                        class="form-label"
                                        for="fixture-label"
                                    >
                                        Etiqueta
                                    </label>

                                    <input
                                        class="form-control"
                                        id="fixture-label"
                                        maxlength="150"
                                    >
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <label
                                            class="form-label"
                                            for="fixture-width"
                                        >
                                            Ancho %
                                        </label>

                                        <input
                                            class="form-control"
                                            id="fixture-width"
                                            type="number"
                                            min="4"
                                            max="40"
                                            step="0.5"
                                        >
                                    </div>

                                    <div class="col-6">
                                        <label
                                            class="form-label"
                                            for="fixture-height"
                                        >
                                            Alto %
                                        </label>

                                        <input
                                            class="form-control"
                                            id="fixture-height"
                                            type="number"
                                            min="6"
                                            max="50"
                                            step="0.5"
                                        >
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label
                                        class="form-label"
                                        for="fixture-rotation"
                                    >
                                        Rotación
                                    </label>

                                    <input
                                        class="form-control"
                                        id="fixture-rotation"
                                        type="number"
                                        min="-360"
                                        max="360"
                                        step="1"
                                    >
                                </div>

                                <div
                                    class="mt-3 d-none"
                                    id="fixture-parent-field"
                                >
                                    <label
                                        class="form-label"
                                        for="fixture-parent"
                                    >
                                        Estructura contenedora
                                    </label>

                                    <select
                                        class="form-select"
                                        id="fixture-parent"
                                    >
                                        <option value="">
                                            Sin asociar
                                        </option>
                                    </select>

                                    <div class="form-text">
                                        Solo aparecen estructuras de este Floor Plan.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="{{ $canManage ? 'col-xl-9' : 'col-12' }}">
                    <div class="tr-card">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">
                                    {{ $floorPlan->name }}
                                </h5>

                                <div class="text-muted small">
                                    {{ $selectedBranch->name }}
                                    ·
                                    {{ $floorPlan->canvas_width }} × {{ $floorPlan->canvas_height }}
                                </div>
                            </div>

                            <span class="badge badge-soft-{{ $floorPlan->is_active ? 'success' : 'warning' }}">
                                {{ $floorPlan->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>

                        <div
                            id="floor-plan-canvas"
                            class="floor-plan-canvas position-relative border rounded overflow-hidden"
                            aria-label="Canvas del Floor Plan"
                            style="aspect-ratio: {{ $floorPlan->canvas_width }} / {{ $floorPlan->canvas_height }};"
                        >
                            @foreach ($floorPlan->items as $item)
                                @php
                                    $fixtureType = $item->fixtureType;
                                @endphp

                                @if ($fixtureType)
                                    <div
                                        class="floor-plan-item {{ $canManage ? 'is-editable' : '' }} position-absolute border rounded bg-white shadow-sm"
                                        @if ($canManage)
                                            tabindex="0"
                                        @endif
                                        data-client-key="item-{{ $item->id }}"
                                        data-fixture-id="{{ $fixtureType->id }}"
                                        data-fixture-category="{{ $fixtureType->category }}"
                                        data-label="{{ $item->label ?: $fixtureType->name }}"
                                        data-icon="{{ $fixtureType->iconUrl() }}"
                                        data-parent-key="{{ $item->parent_item_id ? 'item-' . $item->parent_item_id : '' }}"
                                        data-x="{{ $item->position_x }}"
                                        data-y="{{ $item->position_y }}"
                                        data-width="{{ $item->width }}"
                                        data-height="{{ $item->height }}"
                                        data-rotation="{{ $item->rotation }}"
                                        style="
                                            left: {{ $item->position_x }}%;
                                            top: {{ $item->position_y }}%;
                                            width: {{ $item->width }}%;
                                            height: {{ $item->height }}%;
                                            transform: rotate({{ $item->rotation }}deg);
                                        "
                                    >
                                        @if ($canManage)
                                            <button
                                                class="floor-plan-remove btn btn-sm btn-danger rounded-circle"
                                                type="button"
                                                aria-label="Quitar {{ $fixtureType->name }}"
                                            >
                                                ×
                                            </button>
                                        @endif

                                        @if ($fixtureType->iconUrl())
                                            <img
                                                class="w-100 h-100 p-2"
                                                src="{{ $fixtureType->iconUrl() }}"
                                                alt="{{ $fixtureType->name }}"
                                                style="object-fit: contain;"
                                                draggable="false"
                                                onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');"
                                            >

                                            <span class="floor-plan-placeholder d-none">
                                                {{ $fixtureType->name }}
                                            </span>
                                        @else
                                            <span class="floor-plan-placeholder">
                                                {{ $fixtureType->name }}
                                            </span>
                                        @endif

                                        <span
                                            class="fixture-parent-indicator badge bg-info-subtle text-info-emphasis"
                                            title="Asociado a una estructura"
                                        >
                                            <i data-lucide="link-2"></i>
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if ($canManage)
                            <p class="text-muted small mb-0 mt-2">
                                Arrastra para mover. Ajusta tamaño, rotación y estructura contenedora desde Propiedades.
                            </p>
                        @else
                            <p class="text-muted small mb-0 mt-2">
                                Vista del Floor Plan de la sucursal.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif

    @push('page-scripts')
        <style>
            .floor-plan-canvas {
                min-height: 520px;
                background-color: var(--bs-tertiary-bg);
                background-image:
                    linear-gradient(rgba(100, 116, 139, 0.1) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(100, 116, 139, 0.1) 1px, transparent 1px);
                background-size: 24px 24px;
            }

            .floor-plan-item {
                min-width: 56px;
                min-height: 64px;
                user-select: none;
            }

            .floor-plan-item.is-editable {
                cursor: grab;
                touch-action: none;
            }

            .floor-plan-item.is-editable:active {
                cursor: grabbing;
            }

            .floor-plan-item.is-selected {
                border-color: var(--bs-primary) !important;
                box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.2) !important;
            }

            .floor-plan-remove {
                position: absolute;
                z-index: 3;
                top: -9px;
                right: -9px;
                width: 24px;
                height: 24px;
                padding: 0;
                line-height: 20px;
            }

            .fixture-parent-indicator {
                display: none;
                position: absolute;
                z-index: 2;
                right: 4px;
                bottom: 4px;
                padding: 3px;
            }

            .floor-plan-item.has-parent .fixture-parent-indicator {
                display: inline-flex;
            }

            .fixture-parent-indicator svg {
                width: 13px;
                height: 13px;
            }

            .floor-plan-placeholder {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
                padding: 8px;
                color: var(--bs-secondary-color);
                text-align: center;
                font-size: 0.7rem;
            }
        </style>

        <script>
            (() => {
                const canManage = @json($canManage);
                const branchSelect = document.getElementById('branch_id');
                const floorPlanSelect = document.getElementById('floor_plan_id');

                branchSelect?.addEventListener('change', () => {
                    floorPlanSelect?.removeAttribute('name');
                });

                if (!canManage) {
                    return;
                }

                const canvas = document.getElementById('floor-plan-canvas');
                const form = document.getElementById('floor-plan-form');
                const layoutInput = document.getElementById('floor-plan-layout');
                const emptyProperties = document.getElementById('fixture-properties-empty');
                const propertyFields = document.getElementById('fixture-properties-fields');
                const labelInput = document.getElementById('fixture-label');
                const widthInput = document.getElementById('fixture-width');
                const heightInput = document.getElementById('fixture-height');
                const rotationInput = document.getElementById('fixture-rotation');
                const parentField = document.getElementById('fixture-parent-field');
                const parentSelect = document.getElementById('fixture-parent');

                if (!canvas || !form || !layoutInput) {
                    return;
                }

                let selectedItem = null;

                const clamp = (value, minimum, maximum) => {
                    return Math.min(
                        Math.max(value, minimum),
                        maximum,
                    );
                };

                const clientKey = () => {
                    if (
                        window.crypto
                        && typeof window.crypto.randomUUID === 'function'
                    ) {
                        return `new-${window.crypto.randomUUID()}`;
                    }

                    return `new-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                };

                const fallback = (name) => {
                    const element = document.createElement('span');

                    element.className = 'floor-plan-placeholder';
                    element.textContent = name;

                    return element;
                };

                const image = (source, name) => {
                    if (!source) {
                        return fallback(name);
                    }

                    const element = document.createElement('img');

                    element.className = 'w-100 h-100 p-2';
                    element.src = source;
                    element.alt = name;
                    element.draggable = false;
                    element.style.objectFit = 'contain';

                    element.addEventListener('error', () => {
                        element.replaceWith(fallback(name));
                    });

                    return element;
                };

                const positionItem = (item) => {
                    item.style.left = `${item.dataset.x}%`;
                    item.style.top = `${item.dataset.y}%`;
                    item.style.width = `${item.dataset.width}%`;
                    item.style.height = `${item.dataset.height}%`;
                    item.style.transform = `rotate(${item.dataset.rotation}deg)`;

                    item.classList.toggle(
                        'has-parent',
                        Boolean(item.dataset.parentKey),
                    );
                };

                const clearSelection = () => {
                    selectedItem?.classList.remove('is-selected');
                    selectedItem = null;

                    emptyProperties.classList.remove('d-none');
                    propertyFields.classList.add('d-none');
                };

                const refreshParentOptions = () => {
                    if (!selectedItem) {
                        return;
                    }

                    const selectedParent = selectedItem.dataset.parentKey || '';

                    parentSelect.replaceChildren(
                        new Option('Sin asociar', ''),
                    );

                    canvas
                        .querySelectorAll('.floor-plan-item')
                        .forEach((item) => {
                            if (item.dataset.fixtureCategory !== 'structure') {
                                return;
                            }

                            parentSelect.add(
                                new Option(
                                    item.dataset.label,
                                    item.dataset.clientKey,
                                ),
                            );
                        });

                    parentSelect.value = selectedParent;

                    const isAccessory =
                        selectedItem.dataset.fixtureCategory === 'accessory';

                    if (!isAccessory) {
                        selectedItem.dataset.parentKey = '';
                        positionItem(selectedItem);
                    }

                    parentField.classList.toggle(
                        'd-none',
                        !isAccessory,
                    );
                };

                const selectItem = (item) => {
                    selectedItem?.classList.remove('is-selected');

                    selectedItem = item;
                    selectedItem.classList.add('is-selected');

                    emptyProperties.classList.add('d-none');
                    propertyFields.classList.remove('d-none');

                    labelInput.value = item.dataset.label;
                    widthInput.value = item.dataset.width;
                    heightInput.value = item.dataset.height;
                    rotationInput.value = item.dataset.rotation;

                    refreshParentOptions();
                };

                const removeItem = (item) => {
                    const removedKey = item.dataset.clientKey;

                    canvas
                        .querySelectorAll('.floor-plan-item')
                        .forEach((candidate) => {
                            if (candidate.dataset.parentKey === removedKey) {
                                candidate.dataset.parentKey = '';
                                positionItem(candidate);
                            }
                        });

                    if (selectedItem === item) {
                        clearSelection();
                    }

                    item.remove();

                    if (selectedItem) {
                        refreshParentOptions();
                    }
                };

                const bindItem = (item) => {
                    const removeButton = item.querySelector(
                        '.floor-plan-remove',
                    );

                    removeButton?.addEventListener('click', (event) => {
                        event.stopPropagation();
                        removeItem(item);
                    });

                    item.addEventListener('click', () => {
                        selectItem(item);
                    });

                    item.addEventListener('pointerdown', (event) => {
                        if (event.target.closest('.floor-plan-remove')) {
                            return;
                        }

                        event.preventDefault();

                        selectItem(item);

                        const canvasRectangle =
                            canvas.getBoundingClientRect();

                        const itemRectangle =
                            item.getBoundingClientRect();

                        const offsetX =
                            event.clientX - itemRectangle.left;

                        const offsetY =
                            event.clientY - itemRectangle.top;

                        item.setPointerCapture(event.pointerId);

                        const move = (moveEvent) => {
                            const width =
                                Number(item.dataset.width);

                            const height =
                                Number(item.dataset.height);

                            const x = (
                                (
                                    moveEvent.clientX
                                    - canvasRectangle.left
                                    - offsetX
                                )
                                / canvasRectangle.width
                            ) * 100;

                            const y = (
                                (
                                    moveEvent.clientY
                                    - canvasRectangle.top
                                    - offsetY
                                )
                                / canvasRectangle.height
                            ) * 100;

                            item.dataset.x = clamp(
                                x,
                                0,
                                100 - width,
                            ).toFixed(3);

                            item.dataset.y = clamp(
                                y,
                                0,
                                100 - height,
                            ).toFixed(3);

                            positionItem(item);
                        };

                        const stop = () => {
                            item.removeEventListener(
                                'pointermove',
                                move,
                            );

                            item.removeEventListener(
                                'pointerup',
                                stop,
                            );

                            item.removeEventListener(
                                'pointercancel',
                                stop,
                            );
                        };

                        item.addEventListener(
                            'pointermove',
                            move,
                        );

                        item.addEventListener(
                            'pointerup',
                            stop,
                        );

                        item.addEventListener(
                            'pointercancel',
                            stop,
                        );
                    });

                    positionItem(item);
                };

                const createItem = (fixture) => {
                    const count =
                        canvas.querySelectorAll(
                            '.floor-plan-item',
                        ).length;

                    const item =
                        document.createElement('div');

                    const removeButton =
                        document.createElement('button');

                    const parentIndicator =
                        document.createElement('span');

                    item.className =
                        'floor-plan-item is-editable position-absolute border rounded bg-white shadow-sm';

                    item.tabIndex = 0;
                    item.dataset.clientKey = clientKey();
                    item.dataset.fixtureId = fixture.id;
                    item.dataset.fixtureCategory = fixture.category;
                    item.dataset.label = fixture.name;
                    item.dataset.icon = fixture.icon;
                    item.dataset.parentKey = '';
                    item.dataset.x =
                        String(3 + ((count * 4) % 68));
                    item.dataset.y =
                        String(4 + ((count * 5) % 62));
                    item.dataset.width = '12';
                    item.dataset.height = '18';
                    item.dataset.rotation = '0';

                    removeButton.className =
                        'floor-plan-remove btn btn-sm btn-danger rounded-circle';

                    removeButton.type = 'button';
                    removeButton.ariaLabel =
                        `Quitar ${fixture.name}`;
                    removeButton.textContent = '×';

                    parentIndicator.className =
                        'fixture-parent-indicator badge bg-info-subtle text-info-emphasis';

                    parentIndicator.title =
                        'Asociado a una estructura';

                    parentIndicator.textContent = '↗';

                    item.append(
                        removeButton,
                        image(
                            fixture.icon,
                            fixture.name,
                        ),
                        parentIndicator,
                    );

                    positionItem(item);
                    bindItem(item);

                    canvas.append(item);

                    selectItem(item);
                };

                document
                    .querySelectorAll('.fixture-palette-item')
                    .forEach((button) => {
                        button.addEventListener('click', () => {
                            createItem({
                                id: button.dataset.fixtureId,
                                name: button.dataset.fixtureName,
                                category:
                                    button.dataset.fixtureCategory,
                                icon: button.dataset.fixtureIcon,
                            });
                        });
                    });

                canvas
                    .querySelectorAll('.floor-plan-item')
                    .forEach(bindItem);

                labelInput.addEventListener('input', () => {
                    if (!selectedItem) {
                        return;
                    }

                    selectedItem.dataset.label =
                        labelInput.value.trim();

                    refreshParentOptions();
                });

                widthInput.addEventListener('input', () => {
                    if (
                        !selectedItem
                        || widthInput.value === ''
                    ) {
                        return;
                    }

                    selectedItem.dataset.width = String(
                        clamp(
                            Number(widthInput.value),
                            4,
                            40,
                        ),
                    );

                    positionItem(selectedItem);
                });

                heightInput.addEventListener('input', () => {
                    if (
                        !selectedItem
                        || heightInput.value === ''
                    ) {
                        return;
                    }

                    selectedItem.dataset.height = String(
                        clamp(
                            Number(heightInput.value),
                            6,
                            50,
                        ),
                    );

                    positionItem(selectedItem);
                });

                rotationInput.addEventListener('input', () => {
                    if (
                        !selectedItem
                        || rotationInput.value === ''
                    ) {
                        return;
                    }

                    selectedItem.dataset.rotation = String(
                        clamp(
                            Number(rotationInput.value),
                            -360,
                            360,
                        ),
                    );

                    positionItem(selectedItem);
                });

                parentSelect.addEventListener('change', () => {
                    if (!selectedItem) {
                        return;
                    }

                    selectedItem.dataset.parentKey =
                        parentSelect.value;

                    positionItem(selectedItem);
                });

                form.addEventListener('submit', () => {
                    const items = Array.from(
                        canvas.querySelectorAll(
                            '.floor-plan-item',
                        ),
                    ).map((item) => {
                        return {
                            client_key:
                                item.dataset.clientKey,
                            parent_client_key:
                                item.dataset.parentKey || null,
                            fixture_type_id:
                                Number(item.dataset.fixtureId),
                            label:
                                item.dataset.label,
                            position_x:
                                Number(item.dataset.x),
                            position_y:
                                Number(item.dataset.y),
                            width:
                                Number(item.dataset.width),
                            height:
                                Number(item.dataset.height),
                            rotation:
                                Number(item.dataset.rotation),
                        };
                    });

                    layoutInput.value =
                        JSON.stringify(items);
                });
            })();
        </script>
    @endpush
</x-layouts.tenant>