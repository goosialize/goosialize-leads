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
const field = new Workspace();
const toolbar = field.buildPresentationToolbar();
const [sortGroup, viewGroup] = toolbar.children;
const sort = sortGroup.children[1];
const view = viewGroup.children[1];

const wrapperClass = 'inline-flex items-center gap-1.5 text-[0.75rem] font-medium text-muted-foreground';
const selectClass = 'h-8 rounded-md border border-border bg-transparent ps-2 pe-7 py-0 text-[0.75rem] shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

assert.equal(toolbar.className, 'flex items-center justify-end gap-4');
assert.equal(toolbar.children.length, 2);
assert.equal(sortGroup.className, wrapperClass);
assert.equal(viewGroup.className, wrapperClass);
assert.equal(sort.className, selectClass);
assert.equal(view.className, selectClass);
assert.equal(sort.attributes['data-leads-presentation-control'], 'sort');
assert.equal(view.attributes['data-leads-presentation-control'], 'view');
assert.equal(sort.value, 'name_asc');
assert.equal(view.value, '100');

assert.match(blueprint, /filters_panel:\n\s+type: fieldset/);
assert.match(blueprint, /collapsed: true/);
assert.match(source, /classList\.add\('lg:grid-cols-4'\)/);
assert.match(source, /if \(!showAll && totalPages > 1\)/);
assert.match(source, /`\$\{loaded\} loaded of \$\{total\}`/);
assert.match(source, /this\.pagesIcon\(\s*'trash-2'/);
assert.match(source, /buildEditor\(\)/);

console.log('PASS_REMEDIATION_BATCH_3K30_SORT_VIEW_SPACING');
