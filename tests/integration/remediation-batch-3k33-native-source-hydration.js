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

class FakeElement {
    constructor(tag = 'element') {
        this.tag = tag;
        this.children = [];
        this.listeners = {};
        this.value = '';
        this.textContent = '';
        this.name = '';
        this.parentElement = null;
        this.classList = {contains() { return false; }};
    }
    get options() { return this.tag === 'select' ? this.children : undefined; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) {
        this.children = children;
        mutationCount += 1;
    }
    addEventListener(name, listener) { this.listeners[name] = listener; }
    removeEventListener() {}
    dispatchEvent(event) { this.listeners[event.type]?.(event); }
}

const option = (value, label) => {
    const node = new FakeElement('option');
    node.value = value;
    node.textContent = label;
    return node;
};

let mutationCount = 0;
let nativeSource = new FakeElement('select');
nativeSource.name = 'data[filters][source]';
nativeSource.replaceChildren(option('', 'All sources'));
let observerCallback = null;

class FakeMutationObserver {
    constructor(callback) { observerCallback = callback; }
    observe() {}
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
    createElement: (tag) => new FakeElement(tag),
    createElementNS: (_namespace, tag) => new FakeElement(tag),
    addEventListener() {},
    removeEventListener() {},
    querySelectorAll(selector) {
        return selector.includes('select') ? [nativeSource] : [];
    },
};
global.Event = class { constructor(type) { this.type = type; } };
global.window = {
    __GRAV_FIELD_TAG: 'grav-goosialize-leads--leads-workspace',
    addEventListener() {},
    removeEventListener() {},
};

vm.runInThisContext(source, {filename: 'leads-workspace.js'});
const Workspace = definitions.get(window.__GRAV_FIELD_TAG);
const field = new Workspace();
field.meta = {
    source_options: {
        '': 'All sources',
        contact: 'Contact',
        download: 'Download',
        newsletter: 'Newsletter',
        public_api: 'Public Api',
        quote: 'Quote',
    },
};

const expected = [
    ['', 'All sources'],
    ['contact', 'Contact'],
    ['download', 'Download'],
    ['newsletter', 'Newsletter'],
    ['public_api', 'Public API'],
    ['quote', 'Quote'],
];

assert.equal(field.syncNativeSourceOptions(), true);
assert.deepEqual(
    nativeSource.options.map((entry) => [entry.value, entry.textContent]),
    expected
);

nativeSource.value = 'quote';
field.filters.source = 'quote';
assert.equal(field.syncNativeSourceOptions(), true);
assert.equal(nativeSource.value, 'quote');
const stableMutationCount = mutationCount;
assert.equal(field.syncNativeSourceOptions(), true);
assert.equal(mutationCount, stableMutationCount);

field.observeNativeFilterLayout();
assert.equal(typeof observerCallback, 'function');

const hydratedReplacement = new FakeElement('select');
hydratedReplacement.name = 'data[filters][source]';
hydratedReplacement.replaceChildren(option('', 'All sources'));
nativeSource = hydratedReplacement;
observerCallback();

assert.deepEqual(
    nativeSource.options.map((entry) => [entry.value, entry.textContent]),
    expected
);
assert.equal(nativeSource.value, 'quote');
assert.equal(document.querySelectorAll('select').length, 1);
assert.equal(new Set(nativeSource.options.map((entry) => entry.value)).size, 6);

field.resetNativeFilters();
assert.equal(field.filters.source, '');
assert.equal(nativeSource.value, '');

for (const value of expected.slice(1).map(([canonical]) => canonical)) {
    field.rows = [{source: value}, {source: 'other'}];
    field.filters.source = value;
    assert.deepEqual(field.filteredRows(), [{source: value}]);
}

assert.match(
    source,
    /new MutationObserver\(\(\) => \{\s*this\.syncNativeSourceOptions\(\);\s*this\.syncNativeFormResourceOptions\(\);\s*this\.applyNativeSelectRowLayout\(\);/
);
assert.doesNotMatch(source, /cloneNode|buildFilterControls|renderFilterControls/);

console.log('PASS_REMEDIATION_BATCH_3K33_NATIVE_SOURCE_HYDRATION');
