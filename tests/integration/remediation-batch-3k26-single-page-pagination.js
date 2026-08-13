'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../admin-next/fields/leads-workspace.js'),
    'utf8'
);

class FakeElement {
    constructor(tag = 'element') {
        this.tag = tag;
        this.children = [];
        this.attributes = {};
        this.listeners = {};
        this.className = '';
        this.textContent = '';
        this.value = '';
        this.disabled = false;
        this.isConnected = true;
        this.classList = {add() {}, remove() {}};
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(name, value) { this.attributes[name] = value; }
    addEventListener(name, listener) { this.listeners[name] = listener; }
    removeEventListener() {}
}

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
    querySelectorAll() { return []; },
};
global.window = {
    __GRAV_FIELD_TAG: 'grav-goosialize-leads--leads-workspace',
    addEventListener() {},
    removeEventListener() {},
};

vm.runInThisContext(source, {filename: 'leads-workspace.js'});
const Workspace = definitions.get(window.__GRAV_FIELD_TAG);

const rows = (count) => Array.from({length: count}, (_, index) => ({
    id: String(index + 1).padStart(32, '0'),
    created_at: `2026-08-${String((index % 28) + 1).padStart(2, '0')}T10:00:00Z`,
    name: `Lead ${index + 1}`,
    email: `lead${index + 1}@example.test`,
    source: 'website',
    form_or_resource: 'contact',
    status: 'new',
    state: index < 8 ? 'active' : 'inactive',
}));

const render = (count, view, page = 1) => {
    const field = new Workspace();
    field.capabilities = {write: false, delete: false, restore: false};
    field.rows = rows(count);
    field.sortMode = 'name_asc';
    field.viewMode = view;
    field.pageSize = view === 'all' ? Number.POSITIVE_INFINITY : Number(view);
    field.currentPage = page;
    return {field, root: field.buildTable()};
};

for (const view of ['100', '20']) {
    const {root} = render(20, view);
    assert.equal(root.children.length, 2, `View ${view}: toolbar + card only`);
    assert.equal(root.children[0].attributes['data-goosialize-leads-presentation-toolbar'], '');
    assert.equal(root.children[1].children[1].textContent, '20 loaded of 20');
    assert.equal(root.children.some((node) => node.tag === 'nav'), false);
}

const ten = render(20, '10');
assert.equal(ten.root.children.length, 3);
assert.equal(ten.root.children[2].tag, 'nav');
assert.deepEqual(
    ten.root.children[2].children[0].children.map((node) => node.textContent),
    ['Previous', '1', '2', 'Next']
);

const hundred = render(150, '100');
assert.equal(hundred.root.children[2].tag, 'nav');
assert.deepEqual(
    hundred.root.children[2].children[0].children.map((node) => node.textContent),
    ['Previous', '1', '2', 'Next']
);

const all = render(20, 'all');
assert.equal(all.root.children.length, 2);
assert.equal(all.root.children.some((node) => node.tag === 'nav'), false);

const clamped = render(20, '100', 3);
assert.equal(clamped.field.currentPage, 1);
assert.equal(clamped.root.children.some((node) => node.tag === 'nav'), false);

assert.match(source, /if \(!showAll && totalPages > 1\)/);
assert.match(source, /const datasetFooter\s*=\s*this\.buildDatasetFooter/);

console.log('PASS_REMEDIATION_BATCH_3K26_SINGLE_PAGE_PAGINATION');
