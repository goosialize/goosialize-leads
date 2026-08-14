<?php

declare(strict_types=1);

use Grav\Common\Grav;
use Grav\Common\Yaml;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

chdir('/app/www/public');
define('GRAV_CLI', true);
define('GRAV_REQUEST_TIME', microtime(true));
$autoload = require 'vendor/autoload.php';
$grav = Grav::instance(['loader' => $autoload]);
$grav->initializeCli();
$plugin = \Grav\Common\Plugins::getPlugin('goosialize-leads');
$grav['plugins']->init();
if (!$plugin instanceof \Grav\Plugin\GoosializeLeadsPlugin) {
    throw new RuntimeException('plugin unavailable');
}
$plugin->onPluginsInitialized();

$path = '/app/www/public/user/config/plugins/goosialize-leads.yaml';
$environmentPath = '/app/www/public/user/env/staging/config/plugins/goosialize-leads.yaml';
$bytes = file_get_contents($path);
$persisted = is_string($bytes) ? Yaml::parse($bytes) : null;
$environmentBytes = file_get_contents($environmentPath);
$environment = is_string($environmentBytes) ? Yaml::parse($environmentBytes) : null;
$marker = 'LEGACY_TEST_SECRET_DO_NOT_EXPOSE';
if (($persisted['idempotency']['active_key_version'] ?? null) !== 1) {
    throw new RuntimeException('active key version changed');
}
if (($persisted['idempotency']['keys'][1]['secret'] ?? null) !== $marker) {
    throw new RuntimeException('secret was not persisted canonically');
}
if (($environment['idempotency']['active_key_version'] ?? null) !== 1
    || ($environment['idempotency']['keys'][1]['secret'] ?? null) !== $marker) {
    throw new RuntimeException('environment secret was not persisted canonically');
}
$baseHash = hash_file('sha256', $path);
$environmentHash = hash_file('sha256', $environmentPath);
$plugin->onPluginsInitialized();
if (hash_file('sha256', $path) !== $baseHash || hash_file('sha256', $environmentPath) !== $environmentHash) {
    throw new RuntimeException('migration was not idempotent');
}

$controller = new class($grav, $grav['config']) extends \Grav\Plugin\Api\Controllers\ConfigController {
    protected function requirePermission(ServerRequestInterface $request, string $permission): void
    {
    }
};
$request = (new ServerRequest('GET', '/api/v1/config/plugins/goosialize-leads'))
    ->withAttribute('route_params', ['scope' => 'plugins/goosialize-leads']);
$response = $controller->show($request);
$body = (string) $response->getBody();
if ($response->getStatusCode() !== 200) {
    throw new RuntimeException('config API response failed');
}
if (str_contains($body, $marker)) {
    throw new RuntimeException('secret leaked through config API');
}
if (!str_contains($body, '********')) {
    throw new RuntimeException('redaction sentinel missing');
}

$blocked = new ReflectionProperty($plugin, 'legacySecretMigrationBlocked');
$blocked->setValue($plugin, true);
$guardRequest = $request->withAttribute(
    'route',
    \Grav\Framework\Route\RouteFactory::createFromString('/api/v1/config/plugins/goosialize-leads')
);
$guardEvent = new \Grav\Common\Processors\Events\RequestHandlerEvent(['request' => $guardRequest]);
try {
    $plugin->onRequestHandlerInit($guardEvent);
    throw new RuntimeException('migration persistence failure did not block config API');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'goosialize_leads_configuration_unavailable') {
        throw $exception;
    }
}

echo "PASS_SECRET_MIGRATION_API\n";
