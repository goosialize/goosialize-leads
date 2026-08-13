'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(
    path.join(root, 'admin-next/fields/leads-workspace.js'),
    'utf8'
);
const blueprint = fs.readFileSync(
    path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'),
    'utf8'
);

assert.match(blueprint, /filters_panel:\n\s+type: fieldset/);
assert.match(blueprint, /collapsible: true/);
assert.match(blueprint, /collapsed: true/);
assert.match(
    blueprint,
    /description: Filter leads by status, source, state or creation date\./
);
assert.match(
    blueprint,
    /filters\.search:\n\s+type: text[\s\S]*status_source_state:\n\s+type: columns/
);

const selectRow = blueprint.match(
    /status_source_state:\n([\s\S]*?)\n        created_dates:/
)?.[1] || '';

for (const name of ['status', 'source', 'state']) {
    assert.match(
        selectRow,
        new RegExp(`filters\\.${name}:\\n\\s+type: select`)
    );
}

const dateRow = blueprint.match(
    /created_dates:\n([\s\S]*?)\n    leads_workspace:/
)?.[1] || '';

assert.match(dateRow, /filters\.date_from:\n\s+type: datetime/);
assert.match(dateRow, /filters\.date_to:\n\s+type: datetime/);
assert.doesNotMatch(selectRow, /filters\.date_(from|to)/);

assert.match(source, /applyNativeSelectRowLayout\(\)/);
assert.match(source, /classList\.remove\('lg:grid-cols-2'\)/);
assert.match(source, /classList\.add\('lg:grid-cols-4'\)/);
assert.match(source, /observeNativeFilterLayout\(\)/);
assert.match(source, /new MutationObserver\(\(\) => \{/);
assert.match(source, /attributeFilter: \['class'\]/);
assert.match(source, /this\._nativeFilterLayoutObserver\?\.disconnect\(\)/);

// The observer must repeat the same narrow Batch 3K5 adjustment after a
// late Admin2 hydration/replacement; it must not move or recreate controls.
assert.doesNotMatch(
    source,
    /cloneNode|appendChild\([^)]*(status|source|state)|buildFilterControls|renderFilterControls/
);

assert.match(source, /this\.sortMode = 'name_asc'/);
assert.match(source, /this\.viewMode = '100'/);
assert.match(source, /if \(!showAll && totalPages > 1\)/);
assert.match(source, /`\$\{loaded\} loaded of \$\{total\}`/);
assert.match(source, /`\$\{total\} total`/);

class FakeClassList {
    constructor(values = []) { this.values = new Set(values); }
    contains(value) { return this.values.has(value); }
    remove(value) { this.values.delete(value); }
    add(value) { this.values.add(value); }
}

class FakeElement {
    constructor() {
        this.parentElement = null;
        this.children = [];
        this.classList = new FakeClassList();
    }
    append(...children) {
        for (const child of children) {
            child.parentElement = this;
            this.children.push(child);
        }
    }
    contains(target) {
        return this === target
            || this.children.some((child) => child.contains(target));
    }
    replaceChildren() {}
}

const row = new FakeElement();
row.classList = new FakeClassList([
    'grid',
    'gap-4',
    'lg:grid-cols-2',
]);
const controls = ['status', 'source', 'state', 'form_resource'].map((key) => {
    const column = new FakeElement();
    const control = new FakeElement();
    control.name = `data[filters][${key}]`;
    control.value = '';
    column.append(control);
    row.append(column);
    return control;
});

let observerCallback = null;
let observedOptions = null;
class FakeMutationObserver {
    constructor(callback) { observerCallback = callback; }
    observe(_target, options) { observedOptions = options; }
    disconnect() {}
}

const definitions = new Map();
global.HTMLElement = FakeElement;
global.MutationObserver = FakeMutationObserver;
global.customElements = {
    get: (tag) => definitions.get(tag),
    define: (tag, definition) => definitions.set(tag, definition),
};
global.document = {
    body: new FakeElement(),
    querySelectorAll() { return controls; },
    addEventListener() {},
    removeEventListener() {},
};
global.window = {
    __GRAV_FIELD_TAG: 'grav-goosialize-leads--leads-workspace',
    addEventListener() {},
    removeEventListener() {},
};

vm.runInThisContext(source, {filename: 'leads-workspace.js'});
const Workspace = definitions.get(window.__GRAV_FIELD_TAG);
const field = new Workspace();
field.observeNativeFilterLayout();

assert.equal(typeof observerCallback, 'function');
assert.equal(observedOptions.subtree, true);
assert.equal(observedOptions.childList, true);
assert.deepEqual(observedOptions.attributeFilter, ['class']);

// Initial Admin2 render is corrected.
assert.equal(field.applyNativeSelectRowLayout(), true);
assert.equal(row.classList.contains('lg:grid-cols-4'), true);

// Simulate late Admin2 hydration replacing/resetting the wrapper classes.
row.classList.remove('lg:grid-cols-4');
row.classList.add('lg:grid-cols-2');
observerCallback();
assert.equal(row.classList.contains('lg:grid-cols-2'), false);
assert.equal(row.classList.contains('lg:grid-cols-4'), true);

console.log('PASS_REMEDIATION_BATCH_3K27_NATIVE_FILTER_LAYOUT');
