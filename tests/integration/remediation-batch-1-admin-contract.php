<?php

declare(strict_types=1);

require '/app/www/public/vendor/autoload.php';
require '/app/www/public/user/plugins/api/vendor/autoload.php';
require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Common\Grav;
use Grav\Plugin\Api\Exceptions\ConflictException;
use Grav\Plugin\GoosializeLeads\Admin\LeadEditController;
use Grav\Plugin\GoosializeLeads\Admin\LeadMutationController;
use Nyholm\Psr7\ServerRequest;

function batch1AdminCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$grav = Grav::instance();
$mutation = new LeadMutationController($grav);
$request = new ServerRequest('POST', '/api/v1/goosialize-leads/apply');

batch1AdminCheck(
    $mutation->apply($request)->getStatusCode() === 401,
    'mutation authentication boundary failed'
);

$denied = new class {
    public function get(string $key): bool
    {
        return false;
    }

    public function authorize(string $key): bool
    {
        return false;
    }
};

batch1AdminCheck(
    $mutation->apply($request->withAttribute('api_user', $denied))->getStatusCode() === 403,
    'mutation token boundary failed'
);

$jsonRequest = $request
    ->withAttribute('api_user', $denied)
    ->withHeader('X-API-Token', 'test-token')
    ->withHeader('Content-Type', 'application/json')
    ->withBody(Nyholm\Psr7\Stream::create(json_encode([
        'lead_id' => str_repeat('a', 32),
        'expected_revision' => 0,
        'status' => 'new',
        'action' => 'active',
    ], JSON_THROW_ON_ERROR)));

batch1AdminCheck(
    $mutation->apply($jsonRequest)->getStatusCode() === 403,
    'mutation write ACL boundary failed'
);

$writeOnly = new class {
    public function get(string $key): bool
    {
        return in_array($key, [
            'access.api.access',
            'access.api.goosialize_leads.write',
        ], true);
    }

    public function authorize(string $key): bool
    {
        return $key === 'api.goosialize_leads.write';
    }
};

$deleteRequest = $jsonRequest
    ->withAttribute('api_user', $writeOnly)
    ->withBody(Nyholm\Psr7\Stream::create(json_encode([
        'lead_id' => str_repeat('a', 32),
        'expected_revision' => 0,
        'status' => 'new',
        'action' => 'delete',
    ], JSON_THROW_ON_ERROR)));

batch1AdminCheck(
    $mutation->apply($deleteRequest)->getStatusCode() === 403,
    'delete ACL boundary failed'
);

$reflection = new ReflectionClass(LeadEditController::class);
$edit = $reflection->newInstanceWithoutConstructor();
$assertEditable = $reflection->getMethod('assertMetadataEditable');

try {
    $assertEditable->invoke($edit, ['state' => 'deleted']);
    throw new RuntimeException('deleted Lead was editable');
} catch (ReflectionException $error) {
    throw $error;
} catch (Throwable $error) {
    batch1AdminCheck(
        $error instanceof ConflictException,
        'deleted Lead edit restriction changed'
    );
}

$assertEditable->invoke($edit, ['state' => 'active']);

echo "PASS_REMEDIATION_BATCH_1_ADMIN_ACL\n";
echo "PASS_REMEDIATION_BATCH_1_DELETED_EDIT\n";
