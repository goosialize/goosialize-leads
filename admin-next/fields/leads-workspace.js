(() => {
    'use strict';

    const TAG = window.__GRAV_FIELD_TAG;

    if (
        typeof TAG !== 'string'
        || !TAG.includes('-')
        || customElements.get(TAG)
    ) {
        return;
    }

    class GoosializeLeadsWorkspaceField extends HTMLElement {
        constructor() {
            super();

            this._field = {};
            this._value = null;

            this.rows = [];
            this.meta = {};
            this.capabilities = {
                write: false,
                delete: false,
                restore: false,
            };

            this.currentPage = 1;
            this.pageSize = 100;
            this.sortMode = 'name_asc';
            this.viewMode = '100';

            this.operations = [];
            this.operationsMeta = {};
            this.operationsAvailable = false;

            this.loading = false;
            this.error = null;

            this.editor = null;
            this.editorInitial = null;
            this.editorLoading = false;
            this.editorSaving = false;
            this.editorError = null;

            this.filters = {
                search: '',
                status: '',
                source: '',
                formResource: '',
                state: '',
                dateFrom: '',
                dateTo: '',
            };

            this._filterListener =
                (event) => this.onFilterEvent(event);

            this._pageActionListener =
                (event) => this.onPageAction(event);

            this._filterSyncTimer = null;
            this._nativeFilterLayoutObserver = null;

        }

        set field(value) {
            this._field =
                value && typeof value === 'object'
                    ? value
                    : {};

            this.render();
        }

        get field() {
            return this._field;
        }

        set value(value) {
            this._value = value;
        }

        get value() {
            return this._value;
        }

        connectedCallback() {
            document.addEventListener(
                'input',
                this._filterListener
            );

            document.addEventListener(
                'change',
                this._filterListener
            );

            window.addEventListener(
                'grav:plugin-page-action',
                this._pageActionListener
            );

            this.observeNativeFilterLayout();
            this.scheduleNativeFilterSync();
            this.load();
        }

        disconnectedCallback() {
            document.removeEventListener(
                'input',
                this._filterListener
            );

            document.removeEventListener(
                'change',
                this._filterListener
            );

            window.removeEventListener(
                'grav:plugin-page-action',
                this._pageActionListener
            );

            if (this._filterSyncTimer !== null) {
                window.clearTimeout(
                    this._filterSyncTimer
                );
                this._filterSyncTimer = null;
            }

            this._nativeFilterLayoutObserver?.disconnect();
            this._nativeFilterLayoutObserver = null;
        }

        apiUrl(path) {
            const base =
                window.__GRAV_API_SERVER_URL || '';

            const prefix =
                window.__GRAV_API_PREFIX || '/api/v1';

            return `${base}${prefix}${path}`;
        }

        apiHeaders() {
            const headers = {
                Accept: 'application/json',
            };

            const token =
                window.__GRAV_API_TOKEN;

            if (token) {
                headers['X-API-Token'] = token;
            }

            return headers;
        }

        async apiGet(path) {
            const response = await fetch(
                this.apiUrl(path),
                {
                    method: 'GET',
                    headers: this.apiHeaders(),
                    credentials: 'same-origin',
                }
            );

            if (!response.ok) {
                const error = new Error(
                    `Request failed with HTTP ${response.status}`
                );

                error.status = response.status;

                throw error;
            }

            return response.json();
        }

        async apiPost(path, body) {
            const response = await fetch(
                this.apiUrl(path),
                {
                    method: 'POST',
                    headers: {
                        ...this.apiHeaders(),
                        'Content-Type':
                            'application/json',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: JSON.stringify(body),
                }
            );

            let payload = null;

            try {
                payload =
                    await response.json();
            } catch (_error) {
                payload = null;
            }

            if (!response.ok) {
                const error =
                    new Error(
                        payload?.message
                        || `Request failed with HTTP ${response.status}`
                    );

                error.status =
                    response.status;

                error.code =
                    payload?.code ?? null;

                throw error;
            }

            return payload;
        }

        async apiPatch(path, body) {
            const response = await fetch(
                this.apiUrl(path),
                {
                    method: 'PATCH',
                    headers: {
                        ...this.apiHeaders(),
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: JSON.stringify(body),
                }
            );

            let payload = null;

            try {
                payload = await response.json();
            } catch (_error) {
                payload = null;
            }

            if (!response.ok) {
                const error = new Error(
                    payload?.message
                    || `Request failed with HTTP ${response.status}`
                );

                error.status = response.status;
                error.code = payload?.code ?? null;
                throw error;
            }

            return payload;
        }

        async load(preservePage = false) {
            if (this.loading) {
                return;
            }

            this.loading = true;
            this.error = null;

            this.render();

            try {
                const payload = await this.apiGet(
                    '/goosialize-leads'
                );

                this.rows =
                    Array.isArray(
                        payload?.data
                    )
                        ? payload.data
                        : [];

                if (!preservePage) {
                    this.currentPage = 1;
                }

                this.meta =
                    payload?.meta
                    && typeof payload.meta === 'object'
                        ? payload.meta
                        : {};

                this.syncNativeSourceOptions();
                this.syncNativeFormResourceOptions();

                const capabilities =
                    this.meta?.capabilities;

                this.capabilities = {
                    write:
                        capabilities?.write === true,
                    delete:
                        capabilities?.delete === true,
                    restore:
                        capabilities?.restore === true,
                };

                await this.loadOperations();
            } catch (error) {
                this.rows = [];
                this.meta = {};
                this.capabilities = {
                    write: false,
                    delete: false,
                    restore: false,
                };

                this.error =
                    error instanceof Error
                        ? error.message
                        : 'Lead data could not be loaded.';
            } finally {
                this.loading = false;

                this.render();
                this.scheduleNativeFilterSync();
            }
        }

        async loadOperations() {
            this.operations = [];
            this.operationsMeta = {};
            this.operationsAvailable = false;

            try {
                const payload = await this.apiGet(
                    '/goosialize-leads/notification-operations'
                );

                this.operations = Array.isArray(payload?.data)
                    ? payload.data
                    : [];

                this.operationsMeta =
                    payload?.meta
                    && typeof payload.meta === 'object'
                        ? payload.meta
                        : {};

                this.operationsAvailable = true;
            } catch (error) {
                if (
                    error
                    && (
                        error.status === 401
                        || error.status === 403
                        || error.status === 404
                    )
                ) {
                    return;
                }

                console.warn(
                    'Goosialize Leads notification operations unavailable.',
                    error
                );
            }
        }

        normalizeNativeFieldName(name) {
            if (typeof name !== 'string') {
                return null;
            }

            const normalized = name
                .replace(/^data\[/, '')
                .replace(/\]$/g, '')
                .replace(/\]\[/g, '.');

            const map = {
                'filters.search': 'search',
                'filters.status': 'status',
                'filters.source': 'source',
                'filters.form_resource': 'formResource',
                'filters.state': 'state',
                'filters.date_from': 'dateFrom',
                'filters.date_to': 'dateTo',
            };

            return map[normalized] ?? null;
        }

        nativeFilterKey(control) {
            if (!control) {
                return null;
            }

            const operationalKey =
                control.dataset?.leadsFilter;

            if (
                [
                    'search',
                    'status',
                    'source',
                    'formResource',
                    'state',
                    'dateFrom',
                    'dateTo',
                ].includes(operationalKey)
            ) {
                return operationalKey;
            }

            const byName =
                this.normalizeNativeFieldName(
                    control.name
                );

            if (byName) {
                return byName;
            }

            const labels = {
                'search': 'search',
                'status': 'status',
                'source': 'source',
                'form / resource': 'formResource',
                'state': 'state',
                'created from': 'dateFrom',
                'created to': 'dateTo',
            };

            let node = control.parentElement;

            for (
                let depth = 0;
                node && depth < 6;
                depth += 1
            ) {
                const label =
                    node.querySelector('label');

                if (label) {
                    const text =
                        label.textContent
                            ?.trim()
                            .toLocaleLowerCase();

                    if (
                        text
                        && labels[text]
                    ) {
                        return labels[text];
                    }
                }

                node = node.parentElement;
            }

            return null;
        }

        nativeFilterContainer(control) {
            let node = control?.parentElement ?? null;

            for (
                let depth = 0;
                node && depth < 8;
                depth += 1
            ) {
                if (node.querySelector?.('label')) {
                    return node;
                }

                node = node.parentElement;
            }

            return control?.parentElement ?? null;
        }

        nativeDateTimeValue(control) {
            const direct =
                typeof control?.value === 'string'
                    ? control.value.trim()
                    : '';

            if (direct !== '') {
                return direct;
            }

            const container =
                this.nativeFilterContainer(control);

            if (!container?.querySelectorAll) {
                return '';
            }

            for (const input of container.querySelectorAll('input')) {
                if (
                    typeof input.value === 'string'
                    && input.value.trim() !== ''
                ) {
                    return input.value.trim();
                }
            }

            const parts = {};
            let empty = true;

            for (
                const segment of container.querySelectorAll(
                    '[role="spinbutton"]'
                )
            ) {
                const label = String(
                    segment.getAttribute('aria-label') || ''
                ).toLocaleLowerCase();
                const value = String(
                    segment.getAttribute('aria-valuenow')
                    || segment.textContent
                    || ''
                ).trim();
                const valueText = String(
                    segment.getAttribute('aria-valuetext') || ''
                ).trim().toLocaleLowerCase();

                if (valueText !== '' && valueText !== 'empty') {
                    empty = false;
                }

                for (const part of [
                    'year',
                    'month',
                    'day',
                    'hour',
                    'minute',
                ]) {
                    if (label.includes(part)) {
                        parts[part] = value;
                    }
                }
            }

            if (
                empty
                || !/^\d+$/.test(parts.year || '')
                || !/^\d+$/.test(parts.month || '')
                || !/^\d+$/.test(parts.day || '')
            ) {
                return '';
            }

            const pad = (value) =>
                String(value || '0').padStart(2, '0');

            return [
                String(parts.year).padStart(4, '0'),
                pad(parts.month),
                pad(parts.day),
            ].join('-')
                + 'T'
                + pad(parts.hour)
                + ':'
                + pad(parts.minute);
        }

        nativeFilterValue(control, key) {
            if (key === 'dateFrom' || key === 'dateTo') {
                return this.nativeDateTimeValue(control);
            }

            return typeof control?.value === 'string'
                ? control.value
                : '';
        }

        syncNativeFilters() {
            if (typeof document.querySelectorAll !== 'function') {
                return;
            }

            this.syncNativeSourceOptions();
            this.syncNativeFormResourceOptions();

            const seen = new Set();

            for (
                const control of document.querySelectorAll(
                    'input, select, [role="spinbutton"]'
                )
            ) {
                const key = this.nativeFilterKey(control);

                if (!key || seen.has(key)) {
                    continue;
                }

                const value =
                    this.nativeFilterValue(control, key);

                this.filters[key] = value;
                seen.add(key);
            }

            this.applyNativeSelectRowLayout();
            this.render();
        }

        syncNativeSourceOptions() {
            return this.syncNativeDynamicSelectOptions(
                'source',
                'source_options'
            );
        }

        syncNativeFormResourceOptions() {
            return this.syncNativeDynamicSelectOptions(
                'formResource',
                'form_resource_options'
            );
        }

        syncNativeDynamicSelectOptions(filterKey, metaKey) {
            if (typeof document.querySelectorAll !== 'function') {
                return false;
            }

            const options = this.meta?.[metaKey];

            if (!options || typeof options !== 'object') {
                return false;
            }

            const select = Array.from(
                document.querySelectorAll('select')
            ).find(
                (control) =>
                    this.nativeFilterKey(control) === filterKey
            );

            if (!select) {
                return false;
            }

            const entries = Object.entries(options).filter(
                ([value, label]) =>
                    typeof value === 'string'
                    && typeof label === 'string'
                    && (value === '' || value.length > 0)
            );

            const selected =
                this.filters[filterKey] !== ''
                && entries.some(
                    ([value]) => value === this.filters[filterKey]
                )
                    ? this.filters[filterKey]
                    : (
                        entries.some(
                            ([value]) => value === select.value
                        )
                            ? select.value
                            : ''
                    );

            const currentOptions = Array.from(
                select.options || select.children || []
            );

            const displayLabel = (value, label) =>
                value === 'public_api'
                    ? 'Public API'
                    : label;

            const optionsMatch =
                currentOptions.length === entries.length
                && entries.every(
                    ([value, label], index) =>
                        currentOptions[index]?.value === value
                        && currentOptions[index]?.textContent
                            === displayLabel(value, label)
                );

            if (!optionsMatch) {
                select.replaceChildren(
                    ...entries.map(([value, label]) => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = displayLabel(value, label);
                        return option;
                    })
                );
            }

            select.value = selected;

            this.filters[filterKey] = select.value;

            return true;
        }

        applyNativeSelectRowLayout() {
            if (typeof document.querySelectorAll !== 'function') {
                return false;
            }

            const controls = {};

            for (
                const control of document.querySelectorAll(
                    'input, select'
                )
            ) {
                const key = this.nativeFilterKey(control);

                if (
                    [
                        'status',
                        'source',
                        'state',
                        'formResource',
                    ].includes(key)
                    && !controls[key]
                ) {
                    controls[key] = control;
                }
            }

            if (
                !controls.status
                || !controls.source
                || !controls.state
                || !controls.formResource
            ) {
                return false;
            }

            let row = controls.status.parentElement;

            for (
                let depth = 0;
                row && depth < 10;
                depth += 1
            ) {
                if (
                    row.classList?.contains('grid')
                    && row.classList.contains('lg:grid-cols-2')
                    && row.contains(controls.source)
                    && row.contains(controls.state)
                    && row.contains(controls.formResource)
                ) {
                    row.classList.remove('lg:grid-cols-2');
                    row.classList.add('lg:grid-cols-4');
                    return true;
                }

                row = row.parentElement;
            }

            return false;
        }

        observeNativeFilterLayout() {
            if (
                typeof MutationObserver !== 'function'
                || !document.body
                || this._nativeFilterLayoutObserver
            ) {
                return;
            }

            this._nativeFilterLayoutObserver =
                new MutationObserver(() => {
                    this.syncNativeSourceOptions();
                    this.syncNativeFormResourceOptions();
                    this.applyNativeSelectRowLayout();
                });

            this._nativeFilterLayoutObserver.observe(
                document.body,
                {
                    subtree: true,
                    childList: true,
                    attributes: true,
                    attributeFilter: ['class'],
                }
            );
        }

        scheduleNativeFilterSync() {
            if (this._filterSyncTimer !== null) {
                window.clearTimeout?.(
                    this._filterSyncTimer
                );
            }

            if (typeof window.setTimeout !== 'function') {
                this.syncNativeFilters();
                return;
            }

            this._filterSyncTimer = window.setTimeout(
                () => {
                    this._filterSyncTimer = null;
                    this.syncNativeFilters();
                },
                0
            );
        }

        onPageAction(event) {
            const detail =
                event?.detail || {};

            const action =
                detail.action || {};

            if (detail.plugin !== 'goosialize-leads') {
                return;
            }

            if (action.id === 'reset_filters') {
                this.resetNativeFilters();
            } else if (action.id === 'refresh') {
                this.load(true);
            }
        }

        resetNativeFilters() {
            const defaults = {
                search: '',
                status: '',
                source: '',
                formResource: '',
                state: '',
                dateFrom: '',
                dateTo: '',
                sortMode: 'name_asc',
                viewMode: '100',
            };

            this.filters = {
                search: defaults.search,
                status: defaults.status,
                source: defaults.source,
                formResource: defaults.formResource,
                state: defaults.state,
                dateFrom: defaults.dateFrom,
                dateTo: defaults.dateTo,
            };
            this.sortMode = defaults.sortMode;
            this.viewMode = defaults.viewMode;
            this.pageSize = 100;

            this.currentPage = 1;

            if (typeof document.querySelectorAll === 'function') {
                for (
                    const control of document.querySelectorAll(
                        'input, select'
                    )
                ) {
                    const key = this.nativeFilterKey(control);

                    if (!key) {
                        continue;
                    }

                    control.value = defaults[key];
                    control.dispatchEvent(
                        new Event('input', {bubbles: true})
                    );
                    control.dispatchEvent(
                        new Event('change', {bubbles: true})
                    );
                }

                for (const key of ['dateFrom', 'dateTo']) {
                    const segment = Array.from(
                        document.querySelectorAll('[role="spinbutton"]')
                    ).find(
                        (control) =>
                            this.nativeFilterKey(control) === key
                    );
                    const container =
                        this.nativeFilterContainer(segment);
                    const clear = Array.from(
                        container?.querySelectorAll('button') || []
                    ).find((button) =>
                        /clear/i.test(
                            button.getAttribute('aria-label')
                            || button.title
                            || ''
                        )
                    );

                    clear?.click();
                }
            }

            this.render();
        }

        onFilterEvent(event) {
            const control = event?.target;

            if (!control) {
                return;
            }

            const key =
                this.nativeFilterKey(
                    control
                );

            if (!key) {
                return;
            }

            const value = this.nativeFilterValue(
                control,
                key
            );

            this.filters[key] = value;

            this.currentPage = 1;

            this.render();
        }

        text(value) {
            if (
                value === null
                || value === undefined
                || value === ''
            ) {
                return '—';
            }

            return String(value);
        }

        humanize(value) {
            if (
                value === null
                || value === undefined
                || value === ''
            ) {
                return '—';
            }

            return String(value)
                .replaceAll('_', ' ')
                .replaceAll('-', ' ')
                .replace(
                    /\b\w/g,
                    (character) =>
                        character.toUpperCase()
                );
        }

        formatDate(value) {
            if (
                value === null
                || value === undefined
                || value === ''
            ) {
                return '—';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return this.text(value);
            }

            return new Intl.DateTimeFormat(
                'en-GB',
                {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                    timeZone: 'UTC',
                }
            ).format(date);
        }

        parseFilterDate(value, endOfDay = false) {
            if (!value) {
                return null;
            }

            const parts =
                String(value).match(
                    /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?$/
                );

            if (!parts) {
                return null;
            }

            const hasTime = parts[4] !== undefined;
            const year = Number.parseInt(parts[1], 10);
            const month = Number.parseInt(parts[2], 10) - 1;
            const day = Number.parseInt(parts[3], 10);
            const hour = hasTime
                ? Number.parseInt(parts[4], 10)
                : endOfDay ? 23 : 0;
            const minute = hasTime
                ? Number.parseInt(parts[5], 10)
                : endOfDay ? 59 : 0;
            const second = hasTime
                ? Number.parseInt(parts[6] || '0', 10)
                : endOfDay ? 59 : 0;

            const parsed = new Date(
                year,
                month,
                day,
                hour,
                minute,
                second,
                !hasTime && endOfDay ? 999 : 0
            );

            if (
                Number.isNaN(parsed.getTime())
                || parsed.getFullYear() !== year
                || parsed.getMonth() !== month
                || parsed.getDate() !== day
                || parsed.getHours() !== hour
                || parsed.getMinutes() !== minute
                || parsed.getSeconds() !== second
            ) {
                return null;
            }

            return parsed;
        }

        filteredRows() {
            const search =
                this.filters.search
                    .trim()
                    .toLocaleLowerCase();

            const from =
                this.parseFilterDate(
                    this.filters.dateFrom,
                    false
                );

            const to =
                this.parseFilterDate(
                    this.filters.dateTo,
                    true
                );

            return this.rows.filter((row) => {
                if (
                    this.filters.status
                    && row.status
                        !== this.filters.status
                ) {
                    return false;
                }

                if (
                    this.filters.source
                    && row.source
                        !== this.filters.source
                ) {
                    return false;
                }

                if (
                    this.filters.formResource
                    && row.form_or_resource
                        !== this.filters.formResource
                ) {
                    return false;
                }

                if (
                    this.filters.state
                    && (
                        row.state
                        ?? 'active'
                    ) !== this.filters.state
                ) {
                    return false;
                }

                if (from || to) {
                    const created =
                        new Date(row.created_at);

                    if (
                        Number.isNaN(
                            created.getTime()
                        )
                        || (
                            from
                            && created < from
                        )
                        || (
                            to
                            && created > to
                        )
                    ) {
                        return false;
                    }
                }

                if (!search) {
                    return true;
                }

                const searchable = [
                    row.id,
                    row.name,
                    row.email,
                    row.phone,
                    row.source,
                    row.form_name,
                    row.resource_id,
                    row.form_or_resource,
                    row.status,
                ]
                    .filter(
                        (value) =>
                            value !== null
                            && value !== undefined
                    )
                    .join(' ')
                    .toLocaleLowerCase();

                return searchable.includes(search);
            });
        }

        presentedRows() {
            const rows = [...this.filteredRows()];
            const idCompare = (left, right) =>
                String(left?.id || '').localeCompare(
                    String(right?.id || '')
                );
            const createdTime = (row) => {
                const time = new Date(row?.created_at).getTime();
                return Number.isNaN(time) ? 0 : time;
            };
            const nameCompare = (left, right) =>
                String(left?.name || '').localeCompare(
                    String(right?.name || ''),
                    undefined,
                    {sensitivity: 'base'}
                );

            rows.sort((left, right) => {
                let primary = 0;

                if (this.sortMode === 'oldest') {
                    primary = createdTime(left) - createdTime(right);
                } else if (this.sortMode === 'name_asc') {
                    primary = nameCompare(left, right);
                } else if (this.sortMode === 'name_desc') {
                    primary = nameCompare(right, left);
                } else {
                    primary = createdTime(right) - createdTime(left);
                }

                return primary || idCompare(left, right);
            });

            return rows;
        }

        setSortMode(value) {
            if (
                !['newest', 'oldest', 'name_asc', 'name_desc']
                    .includes(value)
            ) {
                return;
            }

            this.sortMode = value;
            this.currentPage = 1;
            this.render();
        }

        setViewMode(value) {
            if (!['10', '20', '30', '50', '100', 'all'].includes(value)) {
                return;
            }

            // "All" means all filtered rows in the bounded collection
            // already loaded by this workspace, not all historical Leads.
            this.viewMode = value;
            this.pageSize = value === 'all'
                ? Number.POSITIVE_INFINITY
                : Number.parseInt(value, 10);
            this.currentPage = 1;
            this.render();
        }

        el(name, className = '') {
            const element =
                document.createElement(name);

            if (className) {
                element.className = className;
            }

            return element;
        }

        pagesIcon(name, size, className = '') {
            const nodes = {
                'circle-check': [
                    ['circle', {cx: '12', cy: '12', r: '10'}],
                    ['path', {d: 'm9 12 2 2 4-4'}],
                ],
                'circle-dashed': [
                    ['path', {d: 'M10.1 2.182a10 10 0 0 1 3.8 0'}],
                    ['path', {d: 'M13.9 21.818a10 10 0 0 1-3.8 0'}],
                    ['path', {d: 'M17.609 3.721a10 10 0 0 1 2.69 2.7'}],
                    ['path', {d: 'M2.182 13.9a10 10 0 0 1 0-3.8'}],
                    ['path', {d: 'M20.279 17.609a10 10 0 0 1-2.7 2.69'}],
                    ['path', {d: 'M21.818 10.1a10 10 0 0 1 0 3.8'}],
                    ['path', {d: 'M3.721 6.391a10 10 0 0 1 2.7-2.69'}],
                    ['path', {d: 'M6.391 20.279a10 10 0 0 1-2.69-2.7'}],
                ],
                'trash-2': [
                    ['path', {d: 'M10 11v6'}],
                    ['path', {d: 'M14 11v6'}],
                    ['path', {d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6'}],
                    ['path', {d: 'M3 6h18'}],
                    ['path', {d: 'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'}],
                ],
                'external-link': [
                    ['path', {d: 'M15 3h6v6'}],
                    ['path', {d: 'M10 14 21 3'}],
                    ['path', {d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'}],
                ],
                'rotate-ccw': [
                    ['path', {d: 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8'}],
                    ['path', {d: 'M3 3v5h5'}],
                ],
            }[name];
            const namespace = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(namespace, 'svg');

            for (const [attribute, value] of Object.entries({
                xmlns: namespace,
                width: String(size),
                height: String(size),
                viewBox: '0 0 24 24',
                fill: 'none',
                stroke: 'currentColor',
                'stroke-width': '2',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                class: [
                    'lucide-icon',
                    'lucide',
                    `lucide-${name}`,
                    className,
                ].filter(Boolean).join(' '),
                'aria-hidden': 'true',
            })) {
                svg.setAttribute(attribute, value);
            }

            for (const [tag, attributes] of nodes) {
                const node = document.createElementNS(namespace, tag);

                for (const [attribute, value] of Object.entries(attributes)) {
                    node.setAttribute(attribute, value);
                }

                svg.append(node);
            }

            return svg;
        }

        badge(value, status = false) {
            const statusTone =
                {
                    new:
                        'border-green-500/30 bg-green-500/10 text-green-400',
                    contacted:
                        'border-blue-500/30 bg-blue-500/10 text-blue-400',
                    qualified:
                        'border-amber-500/30 bg-amber-500/10 text-amber-400',
                    closed:
                        'border-slate-500/30 bg-slate-500/10 text-slate-400',
                }[
                    String(value ?? '')
                        .toLocaleLowerCase()
                ];

            const badge = this.el(
                'span',
                [
                    'inline-flex',
                    'items-center',
                    'rounded-md',
                    'border',
                    'px-2',
                    'py-0.5',
                    'text-xs',
                    'font-medium',
                    status
                        ? (
                            statusTone
                            || 'border-primary/30 bg-primary/10 text-primary'
                        )
                        : 'border-border bg-muted/50 text-muted-foreground',
                ].join(' ')
            );

            badge.textContent =
                status
                    ? this.humanize(value)
                    : this.text(value);

            return badge;
        }

        emptyState(message) {
            const box = this.el(
                'div',
                [
                    'flex',
                    'min-h-[140px]',
                    'items-center',
                    'justify-center',
                    'rounded-lg',
                    'border',
                    'border-border',
                    'bg-card',
                    'p-6',
                    'text-sm',
                    'text-muted-foreground',
                ].join(' ')
            );

            box.textContent = message;

            return box;
        }

        render() {
            this.replaceChildren();

            if (!this.isConnected) {
                return;
            }

            const root = this.el(
                'section',
                'space-y-4'
            );

            root.setAttribute(
                'aria-label',
                'Leads workspace'
            );

            if (this.loading) {
                root.append(
                    this.emptyState(
                        'Loading leads…'
                    )
                );

                this.append(root);
                return;
            }

            if (this.error) {
                const error =
                    this.emptyState(
                        this.error
                    );

                error.setAttribute(
                    'role',
                    'alert'
                );

                root.append(error);
                this.append(root);
                return;
            }

            if (this.editor || this.editorLoading) {
                root.append(this.buildEditor());
            } else {
                root.append(
                    this.buildTable()
                );

                if (
                    this.operationsAvailable
                    && this.operations.length > 0
                ) {
                    root.append(
                        this.buildOperations()
                    );
                }
            }

            this.append(root);
        }

        async editLead(lead) {
            if (
                this.capabilities.write !== true
                || lead?.state === 'deleted'
            ) {
                return;
            }

            const leadId =
                typeof lead?.id === 'string'
                    ? lead.id
                    : '';

            if (
                !/^[0-9a-f]{32}$/.test(
                    leadId
                )
            ) {
                window.__GRAV_TOAST?.error(
                    'Lead could not be opened.'
                );

                return;
            }

            this.editorLoading = true;
            this.editorError = null;
            this.render();

            try {
                const payload = await this.apiGet(
                    '/goosialize-leads/edit/'
                    + encodeURIComponent(leadId)
                    + '/form-data'
                );

                const data =
                    payload?.data
                    && typeof payload.data === 'object'
                        ? payload.data
                        : payload;

                this.editor = {
                    id: leadId,
                    lead_name: String(data?.lead_name || ''),
                    lead_email: String(data?.lead_email || ''),
                    lead_phone: String(data?.lead_phone || ''),
                    lead_source: String(data?.lead_source || ''),
                    lead_resource: String(data?.lead_resource || ''),
                    lead_created: String(data?.lead_created || ''),
                    status: String(data?.status || 'new'),
                    active: Number(data?.active) === 1,
                    revision: Number(data?.revision),
                };

                this.editorInitial =
                    this.editableSnapshot();
            } catch (error) {
                this.editor = null;
                this.editorInitial = null;
                window.__GRAV_TOAST?.error(
                    error instanceof Error
                        ? error.message
                        : 'Lead could not be opened.'
                );
            } finally {
                this.editorLoading = false;
                this.render();
            }
        }

        editableSnapshot() {
            if (!this.editor) {
                return null;
            }

            return JSON.stringify({
                status: this.editor.status,
                active: this.editor.active,
            });
        }

        editorIsDirty() {
            return this.editor !== null
                && this.editorInitial !== this.editableSnapshot();
        }

        async closeEditor() {
            if (this.editorIsDirty()) {
                const dialogs = window.__GRAV_DIALOGS;

                if (
                    !dialogs
                    || typeof dialogs.confirm !== 'function'
                ) {
                    window.__GRAV_TOAST?.error(
                        'Admin2 confirmation dialog is unavailable.'
                    );
                    return;
                }

                const leave = await dialogs.confirm({
                    title: 'Discard unsaved changes?',
                    message:
                        'Your Lead changes have not been saved.',
                    confirmLabel: 'Leave',
                    cancelLabel: 'Stay',
                });

                if (!leave) {
                    return;
                }
            }

            this.editor = null;
            this.editorInitial = null;
            this.editorError = null;
            this.render();
        }

        async saveEditor() {
            if (
                !this.editor
                || this.editorSaving
                || this.capabilities.write !== true
            ) {
                return;
            }

            this.editorSaving = true;
            this.editorError = null;
            this.render();

            try {
                await this.apiPatch(
                    '/goosialize-leads/edit/'
                    + encodeURIComponent(this.editor.id),
                    {
                        status: this.editor.status,
                        active: this.editor.active ? 1 : 0,
                        revision: this.editor.revision,
                    }
                );

                const preservedPage = this.currentPage;
                this.editor = null;
                this.editorInitial = null;
                await this.load();
                this.currentPage = preservedPage;
                this.render();
                window.__GRAV_TOAST?.success(
                    'Lead saved.'
                );
            } catch (error) {
                this.editorError =
                    error instanceof Error
                        ? error.message
                        : 'Lead could not be saved.';
            } finally {
                this.editorSaving = false;
                this.render();
            }
        }

        buildEditor() {
            if (this.editorLoading) {
                return this.emptyState('Loading Lead…');
            }

            if (!this.editor) {
                return this.emptyState('Lead could not be opened.');
            }

            const panel = this.el(
                'section',
                'space-y-5 rounded-lg border border-border bg-card p-5'
            );
            panel.setAttribute('aria-label', 'Edit Lead');

            const header = this.el(
                'div',
                'flex flex-wrap items-center justify-between gap-3'
            );
            const title = this.el(
                'h2',
                'text-lg font-semibold text-foreground'
            );
            title.textContent = `Edit Lead — ${this.text(this.editor.lead_name)}`;

            const back = this.el(
                'button',
                'inline-flex h-9 items-center gap-2 rounded-md border border-border px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
            );
            back.type = 'button';
            back.textContent = 'Back to Leads';
            back.addEventListener('click', () => this.closeEditor());
            header.append(title, back);
            panel.append(header);

            if (this.editorError) {
                const error = this.el(
                    'div',
                    'rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive'
                );
                error.setAttribute('role', 'alert');
                error.textContent = this.editorError;
                panel.append(error);
            }

            const details = this.el(
                'dl',
                'grid gap-4 md:grid-cols-2'
            );
            for (const [label, value] of [
                ['Name', this.editor.lead_name],
                ['Email', this.editor.lead_email],
                ['Phone', this.editor.lead_phone],
                ['Source', this.editor.lead_source],
                ['Form / Resource', this.editor.lead_resource],
                ['Created', this.editor.lead_created],
            ]) {
                const item = this.el('div', 'space-y-1');
                const term = this.el('dt', 'text-xs font-medium text-muted-foreground');
                const description = this.el('dd', 'text-sm text-foreground');
                term.textContent = label;
                description.textContent = this.text(value);
                item.append(term, description);
                details.append(item);
            }
            panel.append(details);

            const controls = this.el(
                'div',
                'grid gap-4 border-t border-border pt-5 md:grid-cols-2'
            );
            const fieldClass = 'h-10 w-full rounded-lg border border-input bg-muted/50 px-3 py-2 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

            const statusLabel = this.el('label', 'space-y-2');
            const statusText = this.el('span', 'block text-sm font-medium text-foreground');
            statusText.textContent = 'Status';
            const status = document.createElement('select');
            status.className = fieldClass;
            for (const value of ['new', 'contacted', 'qualified', 'closed']) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = this.humanize(value);
                status.append(option);
            }
            status.value = this.editor.status;
            status.addEventListener('change', () => {
                this.editor.status = status.value;
            });
            statusLabel.append(statusText, status);

            const activeLabel = this.el('label', 'space-y-2');
            const activeText = this.el('span', 'block text-sm font-medium text-foreground');
            activeText.textContent = 'State';
            const active = document.createElement('select');
            active.className = fieldClass;
            for (const [value, label] of [['1', 'Active'], ['0', 'Inactive']]) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                active.append(option);
            }
            active.value = this.editor.active ? '1' : '0';
            active.addEventListener('change', () => {
                this.editor.active = active.value === '1';
            });
            activeLabel.append(activeText, active);
            controls.append(statusLabel, activeLabel);
            panel.append(controls);

            const footer = this.el(
                'div',
                'flex flex-wrap justify-end gap-2 border-t border-border pt-4'
            );
            const cancel = this.el(
                'button',
                'inline-flex h-10 items-center rounded-md border border-border px-4 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
            );
            cancel.type = 'button';
            cancel.textContent = 'Back';
            cancel.disabled = this.editorSaving;
            cancel.addEventListener('click', () => this.closeEditor());

            const save = this.el(
                'button',
                'inline-flex h-10 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50'
            );
            save.type = 'button';
            save.textContent = this.editorSaving ? 'Saving…' : 'Save';
            save.disabled = this.editorSaving;
            save.addEventListener('click', () => this.saveEditor());
            footer.append(cancel, save);
            panel.append(footer);

            return panel;
        }

        async restoreLead(lead) {
            if (this.capabilities.restore !== true) {
                return;
            }

            const dialogs =
                window.__GRAV_DIALOGS;

            if (
                !dialogs
                || typeof dialogs.confirm
                    !== 'function'
            ) {
                window.__GRAV_TOAST?.error(
                    'Admin2 confirmation dialog is unavailable.'
                );

                return;
            }

            if (
                lead.state !== 'deleted'
            ) {
                return;
            }

            const confirmed =
                await dialogs.confirm({
                    title: 'Restore Lead?',
                    message:
                        `${this.text(lead.name)} will be restored to the active Lead workflow.`,
                    confirmLabel: 'Restore',
                    cancelLabel: 'Cancel',
                });

            if (!confirmed) {
                return;
            }

            const revision =
                Number.isInteger(
                    lead.metadata_revision
                )
                    ? lead.metadata_revision
                    : 0;

            const status =
                [
                    'new',
                    'contacted',
                    'qualified',
                    'closed',
                ].includes(lead.status)
                    ? lead.status
                    : 'new';

            try {
                await this.apiPost(
                    '/goosialize-leads/apply',
                    {
                        lead_id:
                            lead.id,
                        expected_revision:
                            revision,
                        status,
                        action: 'active',
                    }
                );

                window.__GRAV_TOAST?.success(
                    'Lead restored.'
                );

                await this.load();
            } catch (error) {
                if (
                    error?.status === 409
                ) {
                    window.__GRAV_TOAST?.warning(
                        'This Lead changed elsewhere. Reload and try again.'
                    );

                    await this.load();

                    return;
                }

                window.__GRAV_TOAST?.error(
                    error instanceof Error
                        ? error.message
                        : 'Lead could not be restored.'
                );
            }
        }

        async applyLeadStateAction(lead, action) {
            if (
                action !== 'delete'
                && this.capabilities.write !== true
            ) {
                return;
            }

            if (
                ![
                    'active',
                    'inactive',
                    'delete',
                ].includes(action)
            ) {
                window.__GRAV_TOAST?.error(
                    'Invalid Lead action.'
                );

                return;
            }

            if (
                action === 'delete'
            ) {
                await this.removeLead(lead);

                return;
            }

            const revision =
                Number.isInteger(
                    lead.metadata_revision
                )
                    ? lead.metadata_revision
                    : 0;

            const status =
                [
                    'new',
                    'contacted',
                    'qualified',
                    'closed',
                ].includes(lead.status)
                    ? lead.status
                    : 'new';

            try {
                await this.apiPost(
                    '/goosialize-leads/apply',
                    {
                        lead_id:
                            lead.id,
                        expected_revision:
                            revision,
                        status,
                        action,
                    }
                );

                window.__GRAV_TOAST?.success(
                    action === 'active'
                        ? 'Lead activated.'
                        : 'Lead set inactive.'
                );

                await this.load();
            } catch (error) {
                if (
                    error?.status === 409
                ) {
                    window.__GRAV_TOAST?.warning(
                        'This Lead changed elsewhere. Reload and try again.'
                    );

                    await this.load();

                    return;
                }

                window.__GRAV_TOAST?.error(
                    error instanceof Error
                        ? error.message
                        : 'Lead could not be updated.'
                );
            }
        }

        async removeLead(lead) {
            if (this.capabilities.delete !== true) {
                return;
            }

            const dialogs =
                window.__GRAV_DIALOGS;

            if (
                !dialogs
                || typeof dialogs.confirm
                    !== 'function'
            ) {
                window.__GRAV_TOAST?.error(
                    'Admin2 confirmation dialog is unavailable.'
                );

                return;
            }

            if (
                lead.state === 'deleted'
            ) {
                return;
            }

            const confirmed =
                await dialogs.confirm({
                    title: 'Delete Lead?',
                    message:
                        `${this.text(lead.name)} will be removed from the active Lead workflow. The original Lead record will be retained.`,
                    confirmLabel: 'Delete',
                    cancelLabel: 'Cancel',
                    variant: 'destructive',
                });

            if (!confirmed) {
                return;
            }

            const revision =
                Number.isInteger(
                    lead.metadata_revision
                )
                    ? lead.metadata_revision
                    : 0;

            const status =
                [
                    'new',
                    'contacted',
                    'qualified',
                    'closed',
                ].includes(lead.status)
                    ? lead.status
                    : 'new';

            try {
                await this.apiPost(
                    '/goosialize-leads/apply',
                    {
                        lead_id:
                            lead.id,
                        expected_revision:
                            revision,
                        status,
                        action: 'delete',
                    }
                );

                window.__GRAV_TOAST?.success(
                    'Lead deleted.'
                );

                await this.load();
            } catch (error) {
                if (
                    error?.status === 409
                ) {
                    window.__GRAV_TOAST?.warning(
                        'This Lead changed elsewhere. Reload and try again.'
                    );

                    await this.load();

                    return;
                }

                window.__GRAV_TOAST?.error(
                    error instanceof Error
                        ? error.message
                        : 'Lead could not be deleted.'
                );
            }
        }

        buildPresentationToolbar() {
            const toolbar = this.el(
                'div',
                'flex items-center justify-end gap-4'
            );

            toolbar.setAttribute(
                'data-goosialize-leads-presentation-toolbar',
                ''
            );

            const selectClass = [
                'h-8',
                'rounded-md',
                'border',
                'border-border',
                'bg-transparent',
                'ps-2',
                'pe-7',
                'py-0',
                'text-[0.75rem]',
                'shadow-sm',
                'focus-visible:outline-none',
                'focus-visible:ring-1',
                'focus-visible:ring-ring',
            ].join(' ');

            const control = (
                labelText,
                value,
                options,
                onChange
            ) => {
                const group = this.el(
                    'label',
                    'inline-flex items-center gap-1.5 text-[0.75rem] font-medium text-muted-foreground'
                );
                const label = document.createElement('span');
                const select = document.createElement('select');

                label.textContent = labelText;
                label.className =
                    'hidden md:inline';
                select.className = selectClass;
                select.value = value;
                select.setAttribute(
                    'data-leads-presentation-control',
                    labelText.toLocaleLowerCase()
                );

                for (const [optionValue, optionLabel] of options) {
                    const option = document.createElement('option');
                    option.value = optionValue;
                    option.textContent = optionLabel;
                    select.append(option);
                }

                select.value = value;
                select.addEventListener(
                    'change',
                    () => onChange(select.value)
                );
                group.append(label, select);

                return group;
            };

            toolbar.append(
                control(
                    'Sort',
                    this.sortMode,
                    [
                        ['name_asc', 'A/Z'],
                        ['name_desc', 'Z/A'],
                        ['newest', 'Newest'],
                        ['oldest', 'Oldest'],
                    ],
                    (value) => this.setSortMode(value)
                ),
                control(
                    'View',
                    this.viewMode,
                    [
                        ['10', '10'],
                        ['20', '20'],
                        ['30', '30'],
                        ['50', '50'],
                        ['100', '100'],
                        ['all', 'All'],
                    ],
                    (value) => this.setViewMode(value)
                )
            );

            return toolbar;
        }

        buildTable() {
            const filteredRows =
                this.presentedRows();

            const wrapper = this.el(
                'div',
                'space-y-3'
            );

            wrapper.append(
                this.buildPresentationToolbar()
            );

            if (filteredRows.length === 0) {
                wrapper.append(
                    this.emptyState(
                        this.rows.length === 0
                            ? 'No leads have been captured yet.'
                            : 'No leads match the active filters.'
                    )
                );

                return wrapper;
            }

            const showAll = this.viewMode === 'all';
            const totalPages = showAll
                ? 1
                : Math.max(
                    1,
                    Math.ceil(
                        filteredRows.length
                        / this.pageSize
                    )
                );

            if (this.currentPage > totalPages) {
                this.currentPage = totalPages;
            }

            if (this.currentPage < 1) {
                this.currentPage = 1;
            }

            const start =
                (this.currentPage - 1)
                * this.pageSize;

            const rows = showAll
                ? filteredRows
                : filteredRows.slice(
                    start,
                    start + this.pageSize
                );

            const card = this.el(
                'div',
                [
                    'overflow-hidden',
                    'rounded-lg',
                    'border',
                    'border-border',
                    'bg-card',
                ].join(' ')
            );

            const scroll = this.el(
                'div',
                'overflow-x-auto'
            );

            const table = this.el(
                'table',
                'w-full text-sm'
            );

            const thead =
                document.createElement('thead');

            thead.className =
                'bg-muted/50 text-muted-foreground';

            const headerRow =
                document.createElement('tr');

            const headers = [
                'Created',
                'Name',
                'Email',
                'Phone',
                'Source',
                'Form / Resource',
                'Status',
                'State',
                '',
            ];

            headers.forEach((label) => {
                const th =
                    document.createElement('th');

                th.scope = 'col';

                th.className = [
                    'px-4',
                    'py-3',
                    'text-left',
                    'text-xs',
                    'font-medium',
                    'whitespace-nowrap',
                ].join(' ');

                th.textContent = label;

                headerRow.append(th);
            });

            thead.append(headerRow);
            table.append(thead);

            const tbody =
                document.createElement('tbody');

            rows.forEach((lead) => {
                const tr =
                    document.createElement('tr');

                tr.className =
                    'border-t border-border';

                const created =
                    document.createElement('td');
                created.className =
                    'px-4 py-3 whitespace-nowrap';
                created.textContent =
                    this.formatDate(lead.created_at);
                tr.append(created);

                const contactValues = [
                    {
                        value:
                            this.text(lead.email),
                        className:
                            'px-4 py-3 whitespace-nowrap',
                    },
                    {
                        value:
                            this.text(lead.phone),
                        className:
                            'px-4 py-3 whitespace-nowrap text-muted-foreground',
                    },
                ];

                const name =
                    document.createElement('td');

                name.className =
                    'px-4 py-3 whitespace-nowrap font-medium';

                if (
                    this.capabilities.write === true
                    && lead.state !== 'deleted'
                ) {
                    const nameButton =
                        document.createElement('button');

                    nameButton.type = 'button';
                    nameButton.textContent =
                        this.text(lead.name);
                    nameButton.className = [
                        'min-w-0',
                        'cursor-pointer',
                        'text-left',
                        'font-medium',
                        'text-foreground',
                        'transition-colors',
                        'hover:text-primary',
                        'focus-visible:outline-none',
                        'focus-visible:ring-2',
                        'focus-visible:ring-ring',
                    ].join(' ');
                    nameButton.setAttribute(
                        'aria-label',
                        `Edit ${this.text(lead.name)}`
                    );
                    nameButton.addEventListener(
                        'click',
                        () => this.editLead(lead)
                    );
                    name.append(nameButton);
                } else {
                    name.textContent =
                        this.text(lead.name);
                }

                tr.append(name);

                contactValues.forEach(
                    ({value, className}) => {
                        const td =
                            document.createElement('td');
                        td.className = className;
                        td.textContent = value;
                        tr.append(td);
                    }
                );

                const source =
                    document.createElement('td');

                source.className =
                    'px-4 py-3 whitespace-nowrap';

                source.append(
                    this.badge(
                        this.humanize(
                            lead.source
                        )
                    )
                );

                const resource =
                    document.createElement('td');

                resource.className =
                    'px-4 py-3 whitespace-nowrap';

                resource.textContent =
                    this.humanize(
                        lead.form_or_resource
                        ?? lead.resource_id
                        ?? lead.form_name
                    );

                const status =
                    document.createElement('td');

                status.className =
                    'px-4 py-3 whitespace-nowrap';

                status.append(
                    this.badge(
                        lead.status,
                        true
                    )
                );

                const state =
                    document.createElement('td');

                state.className =
                    'px-4 py-3 whitespace-nowrap';

                state.append(
                    this.badge(
                        this.humanize(
                            lead.state
                            ?? 'active'
                        )
                    )
                );

                const manage =
                    document.createElement('td');

                manage.className =
                    'px-4 py-3 whitespace-nowrap';

                const actions =
                    this.el(
                        'div',
                        'ms-auto flex w-20 shrink-0 items-center justify-center gap-1'
                    );

                if (
                    lead.state === 'deleted'
                    && this.capabilities.restore === true
                ) {
                    const restoreButton =
                        document.createElement(
                            'button'
                        );

                    restoreButton.type =
                        'button';

                    const restoreIcon = this.pagesIcon(
                        'rotate-ccw',
                        12
                    );

                    restoreButton.append(
                        restoreIcon
                    );

                    restoreButton.setAttribute(
                        'aria-label',
                        'Restore lead'
                    );

                    restoreButton.title =
                        'Restore lead';

                    restoreButton.className = [
                        'inline-flex',
                        'h-6',
                        'w-6',
                        'items-center',
                        'justify-center',
                        'rounded',
                        'text-muted-foreground',
                        'transition-colors',
                        'hover:bg-accent',
                        'hover:text-foreground',
                    ].join(' ');

                    restoreButton.addEventListener(
                        'click',
                        () => {
                            this.restoreLead(
                                lead
                            );
                        }
                    );

                    actions.append(
                        restoreButton
                    );
                } else {
                    const isActive =
                        lead.state !== 'inactive';

                    if (this.capabilities.write === true) {
                        const editButton =
                            document.createElement('button');
                        editButton.type = 'button';
                        editButton.className = [
                            'inline-flex',
                            'h-6',
                            'w-6',
                            'items-center',
                            'justify-center',
                            'rounded',
                            'text-muted-foreground',
                            'transition-colors',
                            'hover:bg-accent',
                            'hover:text-foreground',
                        ].join(' ');
                        editButton.title = 'Edit lead';
                        editButton.setAttribute(
                            'aria-label',
                            'Edit lead'
                        );
                        editButton.append(
                            this.pagesIcon(
                                'external-link',
                                13
                            )
                        );
                        editButton.addEventListener(
                            'click',
                            () => this.editLead(lead)
                        );
                        actions.append(editButton);
                    }

                    const statusControl =
                        this.el(
                            'div',
                            [
                                'inline-flex',
                                'h-8',
                                'shrink-0',
                                'items-center',
                                'justify-center',
                            ].join(' ')
                        );

                    const stateIndicator =
                        document.createElement(
                            this.capabilities.write === true
                                ? 'button'
                                : 'span'
                        );

                    if (this.capabilities.write === true) {
                        stateIndicator.type = 'button';
                    }

                    stateIndicator.setAttribute(
                        'aria-label',
                        isActive ? 'Active' : 'Inactive'
                    );
                    stateIndicator.title =
                        isActive ? 'Active' : 'Inactive';
                    stateIndicator.className = [
                        'inline-flex',
                        'h-6',
                        'w-6',
                        'items-center',
                        'justify-center',
                        'rounded',
                        'transition-colors',
                        this.capabilities.write === true
                            ? 'hover:bg-accent'
                            : '',
                    ].join(' ');

                    const stateIcon = this.pagesIcon(
                        isActive
                            ? 'circle-check'
                            : 'circle-dashed',
                        14,
                        isActive
                            ? 'text-green-500'
                            : 'text-muted-foreground'
                    );
                    stateIndicator.append(stateIcon);

                    statusControl.append(
                        stateIndicator
                    );

                    if (this.capabilities.write === true) {
                        stateIndicator.addEventListener(
                            'click',
                            async () => {
                                if (stateIndicator.disabled) {
                                    return;
                                }

                                const nextAction = lead.state === 'inactive'
                                    ? 'active'
                                    : 'inactive';

                                stateIndicator.disabled = true;

                                stateIndicator.classList.add(
                                    'opacity-60',
                                    'cursor-wait'
                                );

                                try {
                                    await this.applyLeadStateAction(
                                        lead,
                                        nextAction
                                    );
                                } finally {
                                    stateIndicator.disabled = false;

                                    stateIndicator.classList.remove(
                                        'opacity-60',
                                        'cursor-wait'
                                    );
                                }
                            }
                        );
                    }

                    const deleteButton =
                        document.createElement(
                            'button'
                        );

                    deleteButton.type =
                        'button';

                    const deleteIcon = this.pagesIcon(
                        'trash-2',
                        12
                    );

                    deleteButton.append(
                        deleteIcon
                    );

                    deleteButton.setAttribute(
                        'aria-label',
                        'Delete lead'
                    );

                    deleteButton.title =
                        'Delete lead';

                    deleteButton.className = [
                        'inline-flex',
                        'h-6',
                        'w-6',
                        'items-center',
                        'justify-center',
                        'rounded',
                        'text-muted-foreground',
                        'transition-colors',
                        'hover:bg-destructive/10',
                        'hover:text-destructive',
                    ].join(' ');

                    deleteButton.addEventListener(
                        'click',
                        () => {
                            this.removeLead(
                                lead
                            );
                        }
                    );

                    actions.append(statusControl);

                    if (this.capabilities.delete === true) {
                        actions.append(
                            deleteButton
                        );
                    }
                }

                manage.append(
                    actions
                );

                tr.append(
                    source,
                    resource,
                    status,
                    state
                );

                tr.append(manage);

                tbody.append(tr);
            });

            table.append(tbody);
            scroll.append(table);
            const datasetFooter =
                this.buildDatasetFooter(
                    rows,
                    filteredRows
                );

            card.append(
                scroll,
                ...datasetFooter
            );

            wrapper.append(card);

            if (!showAll && totalPages > 1) {
                wrapper.append(
                    this.buildPagination(
                        totalPages
                    )
                );
            }

            return wrapper;
        }

        buildDatasetFooter(
            loadedRows,
            filteredRows
        ) {
            const counts = {
                active: 0,
                inactive: 0,
                deleted: 0,
            };

            for (const lead of filteredRows) {
                const state = ['active', 'inactive', 'deleted']
                    .includes(lead?.state)
                    ? lead.state
                    : 'active';

                counts[state] += 1;
            }

            const loaded = loadedRows.length;
            const total = filteredRows.length;
            const loadedRow = this.el(
                'div',
                'flex items-center gap-3 border-t border-border px-4 py-2 text-[0.6875rem] text-muted-foreground'
            );
            loadedRow.textContent =
                `${loaded} loaded of ${total}`;

            const statesRow = this.el(
                'div',
                'flex items-center gap-4 border-t border-border px-4 py-2 text-[0.6875rem] text-muted-foreground'
            );
            const values = [
                `${total} total`,
                `${counts.active} active`,
                `${counts.inactive} inactive`,
                `${counts.deleted} deleted`,
            ];

            values.forEach((value, index) => {
                if (index > 0) {
                    const separator = this.el(
                        'span',
                        'text-border'
                    );
                    separator.textContent = '|';
                    separator.setAttribute(
                        'aria-hidden',
                        'true'
                    );
                    statesRow.append(separator);
                }

                const item = document.createElement('span');
                item.textContent = value;
                statesRow.append(item);
            });

            return [
                loadedRow,
                statesRow
            ];
        }

        buildPagination(
            totalPages
        ) {
            const nav = this.el(
                'nav',
                [
                    'flex',
                    'flex-wrap',
                    'items-center',
                    'justify-end',
                    'gap-3',
                    'py-1',
                ].join(' ')
            );

            nav.setAttribute(
                'aria-label',
                'Leads pagination'
            );

            const controls = this.el(
                'div',
                'flex flex-wrap items-center gap-2'
            );

            const createButton = (
                label,
                page,
                disabled = false,
                current = false
            ) => {
                const button =
                    document.createElement(
                        'button'
                    );

                button.type = 'button';

                button.className = [
                    'inline-flex',
                    'min-h-9',
                    'items-center',
                    'justify-center',
                    'rounded-md',
                    'border',
                    'px-3',
                    'text-sm',
                    'font-medium',
                    current
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-border bg-card text-foreground hover:bg-muted',
                    disabled
                        ? 'cursor-not-allowed opacity-50'
                        : '',
                ]
                    .filter(Boolean)
                    .join(' ');

                button.textContent =
                    label;

                button.disabled =
                    disabled;

                if (current) {
                    button.setAttribute(
                        'aria-current',
                        'page'
                    );
                }

                button.addEventListener(
                    'click',
                    () => {
                        if (disabled) {
                            return;
                        }

                        this.currentPage =
                            page;

                        this.render();
                    }
                );

                return button;
            };

            controls.append(
                createButton(
                    'Previous',
                    this.currentPage - 1,
                    this.currentPage === 1
                )
            );

            for (
                let page = 1;
                page <= totalPages;
                page += 1
            ) {
                controls.append(
                    createButton(
                        String(page),
                        page,
                        false,
                        page
                            === this.currentPage
                    )
                );
            }

            controls.append(
                createButton(
                    'Next',
                    this.currentPage + 1,
                    this.currentPage
                        === totalPages
                )
            );

            nav.append(controls);

            return nav;
        }

        buildOperations() {
            const panel = this.el(
                'section',
                [
                    'rounded-lg',
                    'border',
                    'border-border',
                    'bg-card',
                    'overflow-hidden',
                ].join(' ')
            );

            const header = this.el(
                'div',
                [
                    'flex',
                    'flex-wrap',
                    'items-center',
                    'justify-between',
                    'gap-3',
                    'border-b',
                    'border-border',
                    'px-4',
                    'py-3',
                ].join(' ')
            );

            const titleBlock = this.el(
                'div',
                'space-y-1'
            );

            const title = this.el(
                'strong',
                'text-sm text-foreground'
            );

            title.textContent =
                'Notification operations';

            const description = this.el(
                'p',
                'text-xs text-muted-foreground'
            );

            description.textContent =
                'Delivery, retry and scheduler state for lead notifications.';

            titleBlock.append(
                title,
                description
            );

            const badges = this.el(
                'div',
                'flex flex-wrap items-center gap-2'
            );

            if (
                this.operationsMeta?.core_available
                === true
            ) {
                badges.append(
                    this.badge(
                        'Scheduler available'
                    )
                );
            }

            if (
                this.operationsMeta?.job_registered
                === true
            ) {
                badges.append(
                    this.badge(
                        'Job registered'
                    )
                );
            }

            if (
                this.operationsMeta?.job_enabled
                === true
            ) {
                badges.append(
                    this.badge(
                        'Job enabled'
                    )
                );
            }

            header.append(
                titleBlock,
                badges
            );

            panel.append(header);

            const scroll = this.el(
                'div',
                'overflow-x-auto'
            );

            const table = this.el(
                'table',
                'w-full text-sm'
            );

            const thead =
                document.createElement('thead');

            thead.className =
                'bg-muted/50 text-muted-foreground';

            const headerRow =
                document.createElement('tr');

            [
                'Updated',
                'State',
                'Attempts',
                'Next eligible',
                'Result',
            ].forEach((label) => {
                const th =
                    document.createElement('th');

                th.scope = 'col';

                th.className = [
                    'px-4',
                    'py-3',
                    'text-left',
                    'text-xs',
                    'font-medium',
                    'whitespace-nowrap',
                ].join(' ');

                th.textContent = label;

                headerRow.append(th);
            });

            thead.append(headerRow);
            table.append(thead);

            const tbody =
                document.createElement('tbody');

            this.operations.forEach(
                (operation) => {
                    const tr =
                        document.createElement('tr');

                    tr.className =
                        'border-t border-border';

                    const values = [
                        this.formatDate(
                            operation.updated_at
                        ),
                        this.humanize(
                            operation.state
                        ),
                        this.text(
                            operation.attempt_count
                        ),
                        this.formatDate(
                            operation.next_eligible_at
                        ),
                        this.humanize(
                            operation.last_result_code
                        ),
                    ];

                    values.forEach((value) => {
                        const td =
                            document.createElement(
                                'td'
                            );

                        td.className =
                            'px-4 py-3 whitespace-nowrap';

                        td.textContent =
                            value;

                        tr.append(td);
                    });

                    tbody.append(tr);
                }
            );

            table.append(tbody);
            scroll.append(table);
            panel.append(scroll);

            return panel;
        }
    }

    customElements.define(
        TAG,
        GoosializeLeadsWorkspaceField
    );
})();
