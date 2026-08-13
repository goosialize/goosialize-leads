'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const blueprint = fs.readFileSync(path.join(root, 'admin/blueprints/goosialize-leads-index.yaml'), 'utf8');
const source = fs.readFileSync(path.join(root, 'admin-next/fields/leads-workspace.js'), 'utf8');

const selectRow = blueprint.match(/status_source_state:\n([\s\S]*?)\n        created_dates:/)?.[1] || '';
const selectFields = [...selectRow.matchAll(/filters\.(status|source|state|form_resource):\n\s+type: select/g)].map((match) => match[1]);
assert.deepEqual(selectFields, ['status', 'source', 'state', 'form_resource']);
assert.doesNotMatch(blueprint, /form_resource_row:/);

const dateRow = blueprint.match(/created_dates:\n([\s\S]*?)\n    leads_workspace:/)?.[1] || '';
assert.match(dateRow, /filters\.date_from:\n\s+type: datetime/);
assert.match(dateRow, /filters\.date_to:\n\s+type: datetime/);
assert.doesNotMatch(dateRow, /filters\.(status|source|state|form_resource)/);

assert.match(source, /row\.contains\(controls\.formResource\)/);
assert.match(source, /classList\.remove\('lg:grid-cols-2'\)/);
assert.match(source, /classList\.add\('lg:grid-cols-4'\)/);
assert.doesNotMatch(source, /classList\.add\('lg:grid-cols-3'\)/);
assert.doesNotMatch(source, /cloneNode|buildFilterControls|renderFilterControls/);

console.log('PASS_REMEDIATION_BATCH_3K35_FOUR_COLUMN_FILTER_ROW');
