'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(
    path.join(root, 'admin-next/fields/leads-workspace.js'),
    'utf8'
);
const plugin = fs.readFileSync(
    path.join(root, 'goosialize-leads.php'),
    'utf8'
);
const manifest = fs.readFileSync(
    path.join(root, 'packaging/package-files.txt'),
    'utf8'
);

assert.doesNotMatch(source, /datetime-local|showPicker|custom popover/);
assert.doesNotMatch(source, /buildFilters|addDateField|data-leads-filter/);
assert.match(source, /nativeDateTimeValue\(control\)/);

assert.match(source, /async editLead\(lead\)/);
assert.match(source, /\/form-data'/);
assert.match(source, /async saveEditor\(\)/);
assert.match(source, /this\.apiPatch\(/);
assert.match(source, /revision: this\.editor\.revision/);
assert.match(source, /editableSnapshot\(\)/);
assert.match(source, /Discard unsaved changes\?/);
assert.match(source, /confirmLabel: 'Leave'/);
assert.match(source, /cancelLabel: 'Stay'/);
assert.doesNotMatch(source, /location\.reload|location\.assign/);
assert.doesNotMatch(source, /onbeforeunload|beforeunload/);
assert.doesNotMatch(source, /editor-selection|#edit\//);

assert.doesNotMatch(plugin, /editor-selection|LeadEditorContextStore/);
assert.doesNotMatch(manifest, /leads-back-bridge|goosialize-leads-edit|LeadEditorContextStore/);
assert.equal(
    fs.existsSync(path.join(root, 'admin-next/fields/leads-back-bridge.js')),
    false
);
assert.equal(
    fs.existsSync(path.join(root, 'admin/blueprints/goosialize-leads-edit.yaml')),
    false
);
assert.equal(
    fs.existsSync(path.join(root, 'classes/Admin/LeadEditorContextStore.php')),
    false
);

console.log('PASS_REMEDIATION_BATCH_3J_INLINE_EDITOR');
