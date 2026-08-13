<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController;

$controller = (new ReflectionClass(LeadsIndexController::class))
    ->newInstanceWithoutConstructor();
$method = new ReflectionMethod($controller, 'formResourceOptions');
$method->setAccessible(true);
$options = $method->invoke($controller, [
    ['form_or_resource' => 'website_guide'],
    ['form_or_resource' => 'contact'],
    ['form_or_resource' => 'request_quote'],
    ['form_or_resource' => 'public_api'],
    ['form_or_resource' => 'contact'],
    ['form_or_resource' => ''],
    ['form_or_resource' => null],
]);

$expected = [
    '' => 'All forms / resources',
    'contact' => 'Contact',
    'public_api' => 'Public Api',
    'request_quote' => 'Request Quote',
    'website_guide' => 'Website Guide',
];

if ($options !== $expected) {
    throw new RuntimeException('Form / Resource options mismatch.');
}

echo "PASS_REMEDIATION_BATCH_3K34_FORM_RESOURCE_OPTIONS\n";
