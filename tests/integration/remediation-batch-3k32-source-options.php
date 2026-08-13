<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/classes/Admin/LeadsIndexController.php';

use Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$reflection = new ReflectionClass(LeadsIndexController::class);
$controller = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('sourceOptions');
$method->setAccessible(true);

$options = $method->invoke($controller, [
    ['source' => 'quote'],
    ['source' => 'contact'],
    ['source' => 'public_api'],
    ['source' => 'newsletter'],
    ['source' => 'download'],
    ['source' => 'contact'],
    ['source' => 'website'],
    ['source' => 'grav_forms'],
    ['source' => ''],
    ['source' => null],
]);

check(array_key_first($options) === '', 'All sources must be first.');
check($options[''] === 'All sources', 'All sources label mismatch.');
check($options === [
    '' => 'All sources',
    'contact' => 'Contact',
    'download' => 'Download',
    'grav_forms' => 'Grav Forms',
    'newsletter' => 'Newsletter',
    'public_api' => 'Public Api',
    'quote' => 'Quote',
    'website' => 'Website',
], 'Source vocabulary is incomplete or non-deterministic.');
check(count(array_keys($options, 'Contact', true)) === 1, 'Source values were not deduplicated.');
check(!array_key_exists(' ', $options), 'Empty source option leaked.');

echo "PASS_REMEDIATION_BATCH_3K32_SOURCE_OPTIONS\n";
