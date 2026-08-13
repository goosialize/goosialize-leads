'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const workspacePath = path.join(root, 'admin-next/fields/leads-workspace.js');
const source = fs.readFileSync(workspacePath, 'utf8');
const definitions = new Map();

assert.doesNotMatch(source, /onbeforeunload|beforeunload/);
assert.doesNotMatch(source, /location\.reload|editor-selection|#edit\//);
assert.doesNotMatch(source, /buildFilters\(|data-leads-filter/);
assert.match(source, /document\.addEventListener\([\s\S]*'input'/);
assert.match(source, /document\.addEventListener\([\s\S]*'change'/);

class FakeHTMLElement {
    constructor() {
        this.isConnected = true;
    }
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
    setTimeout(callback) { callback(); return 1; },
    clearTimeout() {},
};

vm.runInThisContext(source, {filename: workspacePath});
const Workspace = definitions.get(window.__GRAV_FIELD_TAG);
assert.equal(typeof Workspace, 'function');

const field = new Workspace();
field.render = () => {};
field.onFilterEvent({
    target: {
        name: 'data[filters][status]',
        value: 'qualified',
    },
});
assert.equal(field.filters.status, 'qualified');
assert.equal(field.currentPage, 1);

field.filters.dateFrom = '2026-08-01T09:30';
field.filters.dateTo = '2026-08-12T17:45';
field.resetNativeFilters();
assert.equal(field.filters.dateFrom, '');
assert.equal(field.filters.dateTo, '');
assert.equal(field.filters.state, '');

const from = field.parseFilterDate('2026-08-12T09:30', false);
const to = field.parseFilterDate('2026-08-12T17:45', true);
assert.equal(from.getHours(), 9);
assert.equal(from.getMinutes(), 30);
assert.equal(to.getHours(), 17);
assert.equal(to.getMinutes(), 45);

(async () => {
    const leadId = '0123456789abcdef0123456789abcdef';
    field.capabilities.write = true;
    field.apiGet = async () => ({
        lead_name: 'Older Lead',
        status: 'new',
        active: 1,
        revision: 7,
    });
    await field.editLead({id: leadId, state: 'active'});
    assert.equal(field.editor.id, leadId);
    assert.equal(field.editorIsDirty(), false);

    let confirmations = 0;
    window.__GRAV_DIALOGS = {
        async confirm() {
            confirmations += 1;
            return false;
        },
    };
    await field.closeEditor();
    assert.equal(confirmations, 0);
    assert.equal(field.editor, null);

    await field.editLead({id: leadId, state: 'active'});
    field.editor.status = 'qualified';
    await field.closeEditor();
    assert.equal(confirmations, 1);
    assert.notEqual(field.editor, null);

    console.log('PASS_REMEDIATION_BATCH_3A_ADMIN2_DIRTY_STATE');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
