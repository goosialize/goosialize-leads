'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'admin-next/fields/leads-workspace.js'), 'utf8');
const blueprint = fs.readFileSync(path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'), 'utf8');

let mutationCount = 0;
class FakeElement {
    constructor(tag = 'element') {
        this.tag = tag; this.children = []; this.value = ''; this.textContent = '';
        this.name = ''; this.dataset = {}; this.listeners = {}; this.parentElement = null;
        this.classList = {contains() { return false; }};
    }
    get options() { return this.tag === 'select' ? this.children : undefined; }
    replaceChildren(...children) { this.children = children; mutationCount += 1; }
    append(...children) { this.children.push(...children); }
    addEventListener(name, listener) { this.listeners[name] = listener; }
    removeEventListener() {}
    dispatchEvent(event) { this.listeners[event.type]?.(event); }
}
const option = (value, label) => Object.assign(new FakeElement('option'), {value, textContent: label});
const sourceSelect = Object.assign(new FakeElement('select'), {name: 'data[filters][source]'});
let formSelect = Object.assign(new FakeElement('select'), {name: 'data[filters][form_resource]'});
sourceSelect.replaceChildren(option('', 'All sources'));
formSelect.replaceChildren(option('', 'All forms / resources'));
let observerCallback = null;
class FakeMutationObserver { constructor(callback) { observerCallback = callback; } observe() {} disconnect() {} }
const definitions = new Map();
global.HTMLElement = FakeElement;
global.MutationObserver = FakeMutationObserver;
global.customElements = {get: (tag) => definitions.get(tag), define: (tag, value) => definitions.set(tag, value)};
global.document = {
    body: new FakeElement(),
    createElement: (tag) => new FakeElement(tag),
    createElementNS: (_namespace, tag) => new FakeElement(tag),
    addEventListener() {}, removeEventListener() {},
    querySelectorAll(selector) { return selector.includes('select') ? [sourceSelect, formSelect] : []; },
};
global.Event = class { constructor(type) { this.type = type; } };
global.window = {__GRAV_FIELD_TAG: 'grav-goosialize-leads--leads-workspace', addEventListener() {}, removeEventListener() {}};

vm.runInThisContext(source, {filename: 'leads-workspace.js'});
const Workspace = definitions.get(window.__GRAV_FIELD_TAG);
const field = new Workspace();
field.meta = {
    source_options: {'': 'All sources', public_api: 'Public Api', website: 'Website'},
    form_resource_options: {
        '': 'All forms / resources', contact: 'Contact', newsletter: 'Newsletter',
        public_api: 'Public Api', request_quote: 'Request Quote', website_guide: 'Website Guide',
    },
};

assert.equal(field.syncNativeFormResourceOptions(), true);
assert.deepEqual(formSelect.options.map((entry) => entry.value), ['', 'contact', 'newsletter', 'public_api', 'request_quote', 'website_guide']);
formSelect.value = 'request_quote'; field.filters.formResource = 'request_quote';
assert.equal(field.syncNativeFormResourceOptions(), true);
assert.equal(formSelect.value, 'request_quote');
const stableMutations = mutationCount;
field.syncNativeFormResourceOptions();
assert.equal(mutationCount, stableMutations);

field.observeNativeFilterLayout();
const replacement = Object.assign(new FakeElement('select'), {name: 'data[filters][form_resource]'});
replacement.replaceChildren(option('', 'All forms / resources'));
formSelect = replacement;
observerCallback();
assert.deepEqual(formSelect.options.map((entry) => entry.value), ['', 'contact', 'newsletter', 'public_api', 'request_quote', 'website_guide']);
assert.equal(formSelect.value, 'request_quote');
assert.equal(document.querySelectorAll('select').filter((entry) => entry.name === 'data[filters][form_resource]').length, 1);
assert.equal(new Set(formSelect.options.map((entry) => entry.value)).size, formSelect.options.length);

field.rows = [
    {source: 'website', status: 'new', state: 'active', form_or_resource: 'website_guide'},
    {source: 'public_api', status: 'new', state: 'active', form_or_resource: 'public_api'},
    {source: 'website', status: 'closed', state: 'inactive', form_or_resource: 'request_quote'},
];
field.filters = {search: '', source: 'website', formResource: 'website_guide', status: 'new', state: 'active', dateFrom: '', dateTo: ''};
field.sortMode = 'oldest'; field.viewMode = '30'; field.currentPage = 3;
assert.deepEqual(field.filteredRows(), [field.rows[0]]);
field.onFilterEvent({target: Object.assign(formSelect, {value: 'request_quote'})});
assert.equal(field.currentPage, 1);
assert.equal(field.sortMode, 'oldest');
assert.equal(field.viewMode, '30');
field.resetNativeFilters();
assert.equal(field.filters.formResource, '');
assert.equal(formSelect.value, '');

assert.match(blueprint, /filters\.form_resource:\n\s+type: select[\s\S]*label: Form \/ Resource[\s\S]*'': All forms \/ resources/);
assert.doesNotMatch(source, /buildFilterControls|renderFilterControls|cloneNode/);
console.log('PASS_REMEDIATION_BATCH_3K34_FORM_RESOURCE_FILTER');
