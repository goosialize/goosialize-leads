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
        this.className = '';
        this.textContent = '';
        this.disabled = false;
        this.isConnected = true;
        this.classList = {add() {}, remove() {}};
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(name, value) { this.attributes[name] = value; }
    addEventListener() {}
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
    querySelector() { return null; },
};
global.window = {
    __GRAV_FIELD_TAG: 'grav-goosialize-leads--leads-workspace',
    addEventListener() {},
    removeEventListener() {},
};

vm.runInThisContext(source, {filename: 'leads-workspace.js'});
const Workspace = definitions.get(window.__GRAV_FIELD_TAG);

function render(rows, page = 1, view = '10') {
    const field = new Workspace();
    field.capabilities = {write: false, delete: false, restore: false};
    field.rows = rows;
    field.filteredRows = () => rows;
    field.sortedRows = (items) => items;
    field.viewMode = view;
    field.pageSize = view === 'all' ? rows.length : Number(view);
    field.currentPage = page;
    return {field, root: field.buildTable()};
}

const twenty = [
    ...Array.from({length: 8}, (_, i) => ({id: `a${i}`, state: 'active'})),
    ...Array.from({length: 10}, (_, i) => ({id: `i${i}`, state: 'inactive'})),
    ...Array.from({length: 2}, (_, i) => ({id: `d${i}`, state: 'deleted'})),
];

for (const page of [1, 2]) {
    const {root} = render(twenty, page);
    const [toolbar, card, pagination] = root.children;
    assert.equal(toolbar.attributes['data-goosialize-leads-presentation-toolbar'], '');
    assert.equal(card.children.length, 3);
    assert.equal(card.children[0].children[0].tag, 'table');
    assert.equal(card.children[1].textContent, '10 loaded of 20');
    assert.deepEqual(
        card.children[2].children.map((node) => node.textContent),
        ['20 total', '|', '8 active', '|', '10 inactive', '|', '2 deleted']
    );
    assert.equal(pagination.tag, 'nav');
    assert.match(pagination.className, /justify-end/);
    assert.deepEqual(
        pagination.children[0].children.map((node) => node.textContent),
        ['Previous', '1', '2', 'Next']
    );
}

const thirteen = Array.from(
    {length: 13},
    (_, i) => ({id: `lead${i}`, state: i < 5 ? 'active' : 'inactive'})
);
const {root: secondOfThirteen} = render(thirteen, 2);
assert.equal(secondOfThirteen.children[1].children[1].textContent, '3 loaded of 13');
assert.deepEqual(
    secondOfThirteen.children[1].children[2].children.map((node) => node.textContent),
    ['13 total', '|', '5 active', '|', '8 inactive', '|', '0 deleted']
);

const seven = twenty.slice(0, 7);
const {root: filteredSeven} = render(seven);
assert.equal(filteredSeven.children[1].children[1].textContent, '7 loaded of 7');

const {root: allTwenty} = render(twenty, 1, 'all');
assert.equal(allTwenty.children.length, 2);
assert.equal(allTwenty.children[1].children[1].textContent, '20 loaded of 20');

assert.doesNotMatch(source, /rangeInfo|1[–-]10 of 20/);
assert.match(source, /card\.append\([\s\S]*scroll,[\s\S]*\.\.\.datasetFooter[\s\S]*wrapper\.append\(card\)[\s\S]*this\.buildPagination/);

console.log('PASS_REMEDIATION_BATCH_3K19_BOTTOM_DOM');
