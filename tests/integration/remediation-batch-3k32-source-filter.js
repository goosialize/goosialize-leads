'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'admin-next/fields/leads-workspace.js'), 'utf8');
const blueprint = fs.readFileSync(path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'), 'utf8');

class FakeElement {
    constructor(tag = 'element') {
        this.tag = tag;
        this.children = [];
        this.attributes = {};
        this.listeners = {};
        this.className = '';
        this.textContent = '';
        this.value = '';
        this.name = '';
        this.dataset = {};
        this.classList = {add() {}, remove() {}, contains() { return false; }};
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(name, value) { this.attributes[name] = value; }
    addEventListener(name, listener) { this.listeners[name] = listener; }
    removeEventListener() {}
    dispatchEvent() {}
}

const nativeSource = new FakeElement('select');
nativeSource.name = 'data[filters][source]';

const definitions = new Map();
global.HTMLElement = FakeElement;
global.customElements = {
    get: (tag) => definitions.get(tag),
    define: (tag, definition) => definitions.set(tag, definition),
};
global.document = {
    createElement: (tag) => new FakeElement(tag),
    createElementNS: (_namespace, tag) => new FakeElement(tag),
    addEventListener() {},
    removeEventListener() {},
    querySelectorAll(selector) {
        return ['select', 'input, select'].includes(selector)
            ? [nativeSource]
            : [];
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

assert.equal(field.syncNativeSourceOptions(), true);
assert.deepEqual(
    nativeSource.children.map((option) => [option.value, option.textContent]),
    [
        ['', 'All sources'],
        ['contact', 'Contact'],
        ['download', 'Download'],
        ['newsletter', 'Newsletter'],
        ['public_api', 'Public API'],
        ['quote', 'Quote'],
    ]
);

for (const value of ['contact', 'download', 'newsletter', 'public_api', 'quote']) {
    field.rows = [{source: value}, {source: 'another'}];
    field.filters.source = value;
    assert.deepEqual(field.filteredRows(), [{source: value}]);
}

nativeSource.value = 'quote';
field.filters.source = 'quote';
field.resetNativeFilters();
assert.equal(field.filters.source, '');
assert.equal(nativeSource.value, '');

assert.match(blueprint, /filters\.source:\n\s+type: select[\s\S]*options:\n\s+'': All sources/);
assert.doesNotMatch(blueprint, /website: Website|grav_forms: Grav Forms|public_api: Public API/);
assert.match(source, /this\.syncNativeSourceOptions\(\)/);

console.log('PASS_REMEDIATION_BATCH_3K32_SOURCE_FILTER');
