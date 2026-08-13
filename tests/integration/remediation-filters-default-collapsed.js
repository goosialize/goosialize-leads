'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const blueprint = fs.readFileSync(
    path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'),
    'utf8'
);
const workspace = fs.readFileSync(
    path.join(root, 'admin-next/fields/leads-workspace.js'),
    'utf8'
);

const panel = blueprint.match(
    /^    filters_panel:\n([\s\S]*?)(?=^    leads_workspace:)/m
)?.[1] ?? '';

assert.match(panel, /^      type: fieldset$/m);
assert.match(panel, /^      collapsible: true$/m);
assert.match(panel, /^      collapsed: true$/m);

for (const [name, type] of [
    ['filters.search', 'text'],
    ['filters.status', 'select'],
    ['filters.source', 'select'],
    ['filters.state', 'select'],
    ['filters.date_from', 'datetime'],
    ['filters.date_to', 'datetime'],
]) {
    assert.match(
        panel,
        new RegExp(`${name.replace('.', '\\.')}:\\n\\s+type: ${type}`)
    );
}

assert.doesNotMatch(workspace, /filters_panel|collaps(?:e|ed|ible)|toggle.*filter/i);
assert.match(workspace, /normalizeNativeFieldName\(name\)/);
assert.match(workspace, /'filters\.search': 'search'/);
assert.match(workspace, /'filters\.status': 'status'/);
assert.match(workspace, /'filters\.source': 'source'/);
assert.match(workspace, /'filters\.state': 'state'/);
assert.match(workspace, /nativeDateTimeValue\(control\)/);

// Collapse is owned by Admin2 and only hides the fieldset body. The filter
// value bridge reads the same native controls when expanded again, without
// mutating values during collapse/expand.
assert.doesNotMatch(workspace, /hidden\s*=|style\.display|replaceChildren\([^)]*filter/i);

console.log('PASS_FILTERS_DEFAULT_COLLAPSED');
