'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'admin-next/fields/leads-workspace.js'), 'utf8');
const blueprint = fs.readFileSync(path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'), 'utf8');

const buildTable = source.match(
    /buildTable\(\) \{([\s\S]*?)\n        buildDatasetFooter\(/
)?.[1] || '';
const toolbar = source.match(
    /buildPresentationToolbar\(\) \{([\s\S]*?)\n        buildTable\(/
)?.[1] || '';

assert.match(buildTable, /const wrapper = this\.el\(\s*'div',\s*'space-y-3'/);
assert.doesNotMatch(buildTable, /const wrapper = this\.el\(\s*'div',\s*'space-y-1'/);
assert.match(
    buildTable,
    /wrapper\.append\(\s*this\.buildPresentationToolbar\(\)\s*\)[\s\S]*wrapper\.append\(card\)/
);

const wrapperClass = 'inline-flex items-center gap-1.5 text-[0.75rem] font-medium text-muted-foreground';
const selectClass = 'h-8 rounded-md border border-border bg-transparent ps-2 pe-7 py-0 text-[0.75rem] shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

assert.match(toolbar, /flex items-center justify-end gap-4/);
assert.equal((toolbar.match(new RegExp(wrapperClass.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length, 1);
for (const token of selectClass.split(' ')) {
    assert.match(toolbar, new RegExp(`'${token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}'`));
}

assert.match(buildTable, /card\.append\(\s*scroll,\s*\.\.\.datasetFooter\s*\)/);
assert.match(source, /`\$\{loaded\} loaded of \$\{total\}`/);
assert.match(source, /if \(!showAll && totalPages > 1\)/);
assert.match(blueprint, /filters_panel:\n\s+type: fieldset/);
assert.match(blueprint, /collapsed: true/);
assert.match(source, /classList\.add\('lg:grid-cols-4'\)/);

console.log('PASS_REMEDIATION_BATCH_3K31_TOOLBAR_TABLE_SPACING');
