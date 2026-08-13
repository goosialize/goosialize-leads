'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const workspacePath = path.join(
    root,
    'admin-next/fields/leads-workspace.js'
);
const source = fs.readFileSync(workspacePath, 'utf8');
const blueprint = fs.readFileSync(
    path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'),
    'utf8'
);
const definitions = new Map();

class FakeHTMLElement {
    constructor() {
        this.isConnected = true;
    }

    addEventListener() {}
    removeEventListener() {}
    replaceChildren() {}
    append() {}
}

global.HTMLElement = FakeHTMLElement;
global.customElements = {
    get: (tag) => definitions.get(tag),
    define: (tag, definition) => definitions.set(tag, definition),
};
global.document = {
    addEventListener() {},
    removeEventListener() {},
    querySelectorAll() { return []; },
};
global.window = {
    __GRAV_FIELD_TAG: 'grav-goosialize-leads--leads-workspace',
    addEventListener() {},
    removeEventListener() {},
};

vm.runInThisContext(source, {filename: workspacePath});

const Workspace = definitions.get(window.__GRAV_FIELD_TAG);
assert.equal(typeof Workspace, 'function');

async function capabilitiesFor(payload) {
    const field = new Workspace();
    field.render = () => {};
    field.loadOperations = async () => {};
    field.apiGet = async () => payload;
    await field.load();
    return field.capabilities;
}

(async () => {
    assert.deepEqual(
        await capabilitiesFor({data: [], meta: {}}),
        {write: false, delete: false, restore: false}
    );
    assert.deepEqual(
        await capabilitiesFor({
            data: [],
            meta: {capabilities: {write: 'true', delete: 1, restore: {}}},
        }),
        {write: false, delete: false, restore: false}
    );
    assert.deepEqual(
        await capabilitiesFor({
            data: [],
            meta: {capabilities: {write: true, delete: false, restore: true}},
        }),
        {write: true, delete: false, restore: true}
    );
    assert.deepEqual(
        await capabilitiesFor({
            data: [],
            meta: {capabilities: {write: true, delete: true, restore: true}},
        }),
        {write: true, delete: true, restore: true}
    );

    for (const [name, type] of [
        ['filters.search', 'text'],
        ['filters.status', 'select'],
        ['filters.source', 'select'],
        ['filters.state', 'select'],
        ['filters.date_from', 'datetime'],
        ['filters.date_to', 'datetime'],
    ]) {
        assert.match(
            blueprint,
            new RegExp(`${name.replace('.', '\\.')}:\\n\\s+type: ${type}`)
        );
    }

    assert.doesNotMatch(source, /buildFilters|addDateField|data-leads-filter/);
    assert.doesNotMatch(source, /Edit \| Status \| Delete/);
    assert.match(
        source,
        /this\.capabilities\.write === true\s*&& lead\.state !== 'deleted'/
    );
    assert.match(source, /this\.capabilities\.delete === true/);
    assert.match(source, /this\.capabilities\.restore === true/);

    console.log('PASS_REMEDIATION_BATCH_3B_CAPABILITY_UI');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
