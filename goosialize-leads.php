<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Processors\Events\RequestHandlerEvent;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Framework\Acl\PermissionsReader;
use Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter;
use Grav\Plugin\GoosializeLeads\Http\ApiResponseMapper;
use Grav\Plugin\GoosializeLeads\Http\EndpointRateLimiter;
use Grav\Plugin\GoosializeLeads\Http\OriginPolicy;
use Grav\Plugin\GoosializeLeads\Http\PublicApiRawBodyMiddleware;
use Grav\Plugin\GoosializeLeads\Http\PublicLeadApiController;
use Grav\Plugin\GoosializeLeads\Http\RawJsonParser;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
use RocketTheme\Toolbox\Event\Event;

final class GoosializeLeadsPlugin extends Plugin
{
    public function autoload(): void
    {
        require_once __DIR__ . '/autoload.php';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsRegisterEvent::class => ['onRegisterPermissions', 1000],
            'onApiRegisterRoutes' => ['onApiRegisterRoutes', 0],
            'onApiSidebarItems' => ['onApiSidebarItems', 0],
            'onApiPluginPageInfo' => ['onApiPluginPageInfo', 0],
            'onApiCollectPublicRoutes' => ['onApiCollectPublicRoutes', 0],
            'onRequestHandlerInit' => ['onRequestHandlerInit', 98000],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onFormProcessed' => ['onFormProcessed', 0],
        ];
    }

    public function onApiRegisterRoutes(Event $event): void
    {
        $routes = $event['routes'] ?? null;
        if (!is_object($routes)) return;
        if ($this->publicApiConfigurationValid() && method_exists($routes, 'post')) {
            $routes->post('/goosialize-leads/capture', [PublicLeadApiController::class, 'capture']);
        }
        if ($this->admin2IndexConfigurationValid() && method_exists($routes, 'get')) {
            $routes->get('/goosialize-leads', [LeadsIndexController::class, 'index']);
        }
    }

    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        $event->permissions->addActions(PermissionsReader::fromYaml("plugin://{$this->name}/permissions.yaml"));
    }

    public function onApiSidebarItems(Event $event): void
    {
        if (!$this->admin2IndexConfigurationValid() || !$this->eventUserAllowed($event['user'] ?? null)) return;
        $items = $event['items'] ?? [];
        if (!is_array($items)) $items = [];
        $items[] = [
            'id' => 'goosialize-leads',
            'plugin' => 'goosialize-leads',
            'label' => 'Leads',
            'icon' => 'fa-address-book',
            'route' => '/plugin/goosialize-leads',
            'priority' => 20,
            'badge' => null,
            'authorize' => 'api.goosialize_leads.read',
        ];
        $event['items'] = $items;
    }

    public function onApiPluginPageInfo(Event $event): void
    {
        if (($event['plugin'] ?? null) !== 'goosialize-leads'
            || !$this->admin2IndexConfigurationValid()
            || !$this->eventUserAllowed($event['user'] ?? null)
        ) return;
        $event['definition'] = [
            'id' => 'goosialize-leads',
            'plugin' => 'goosialize-leads',
            'title' => 'Leads — latest 100',
            'icon' => 'fa-address-book',
            'page_type' => 'blueprint',
            'blueprint' => 'goosialize-leads-index',
            'actions' => [],
        ];
    }

    public function onApiCollectPublicRoutes(Event $event): void
    {
        if (!$this->publicApiConfigurationValid()) return;
        $exact = $event['exact'] ?? null;
        if (!is_array($exact)) return;
        $route = 'POST /api/v1/goosialize-leads/capture';
        if (!in_array($route, $exact, true)) $exact[] = $route;
        $event['exact'] = $exact;
    }

    public function onRequestHandlerInit(RequestHandlerEvent $event): void
    {
        if (!$this->publicApiConfigurationValid()) return;
        if ($event->getRoute()->getRoute() !== '/api/v1/goosialize-leads/capture') return;
        try {
            $config = $this->config()['public_api'];
            $root = $this->grav['locator']->findResource('user-data://', true);
            if (!is_string($root) || $root === '') return;
            $middleware = new PublicApiRawBodyMiddleware(
                new RawJsonParser(),
                new OriginPolicy(),
                new EndpointRateLimiter($root, static fn (): int => time()),
                new ApiResponseMapper(),
                $config
            );
            $event->addMiddleware('goosialize_leads_public_api', $middleware);
        } catch (\Throwable) {
            if (isset($this->grav['log'])) $this->grav['log']->warning('public_api_configuration_unavailable');
        }
    }

    /**
     * Add the plugin-owned inert template directory to Twig lookup paths.
     */
    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onFormProcessed(Event $event): void
    {
        $form = $event['form'] ?? null;
        if (
            ($event['action'] ?? null) !== 'goosialize_leads_capture'
            || !$form instanceof \Grav\Plugin\Form\Form
        ) {
            return;
        }

        try {
            $config = $this->config();
            $forms = $config['forms'] ?? null;
            $idempotency = $config['idempotency'] ?? null;
            if (!is_array($forms) || !is_array($idempotency)) {
                throw new \InvalidArgumentException('Invalid plugin configuration.');
            }
            $versions = [];
            foreach (($idempotency['keys'] ?? []) as $version => $key) {
                if (is_int($version) || (is_string($version) && ctype_digit($version))) {
                    $versions[(int) $version] = $key;
                } else {
                    throw new \InvalidArgumentException('Invalid plugin configuration.');
                }
            }
            $activeVersion = $idempotency['active_key_version'] ?? null;
            if (is_string($activeVersion) && ctype_digit($activeVersion)) {
                $activeVersion = (int) $activeVersion;
            }
            $root = $this->grav['locator']->findResource('user-data://', true);
            if (!is_string($root) || $root === '') {
                throw new \InvalidArgumentException('Invalid storage root.');
            }

            $entropy = static fn (int $length): string => random_bytes($length);
            $clock = static fn (): \DateTimeInterface => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $keyRing = new IdempotencyKeyRing($activeVersion, $versions);
            $repository = new FilesystemLeadRepository($root, $entropy, $keyRing);
            $normalizer = new LeadNormalizer();
            $validator = new LeadInputValidator($normalizer);
            $coordinator = new LeadPersistenceCoordinator($validator, $repository, $keyRing);
            $service = new LeadCaptureService($coordinator, $keyRing);
            $adapter = new FormsLeadCaptureAdapter($service, $forms, $entropy, $clock);
            $adapter->process($event);
        } catch (\Throwable) {
            $form->setMessage('PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE');
            $event->stopPropagation();
            if (isset($this->grav['log'])) {
                $this->grav['log']->warning('forms_configuration_invalid');
            }
        }
    }

    private function publicApiConfigurationValid(): bool
    {
        $config = $this->config()['public_api'] ?? null;
        if (!is_array($config) || ($config['enabled'] ?? null) !== true) return false;
        if (($config['body_max_bytes'] ?? null) !== 16384
            || ($config['json_max_depth'] ?? null) !== 4
            || ($config['rate_limit_count'] ?? null) !== 10
            || ($config['rate_limit_window_seconds'] ?? null) !== 60
            || !is_array($config['allowed_origins'] ?? null)
            || !array_is_list($config['allowed_origins'])
            || !is_string($config['consent_version'] ?? null)
            || preg_match('/\A[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?\z/D', $config['consent_version']) !== 1
        ) return false;
        $locale = $config['locale'] ?? null;
        if ($locale !== null && (!is_string($locale)
            || preg_match('/\A[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/D', $locale) !== 1)) return false;
        $origins = $config['allowed_origins'];
        if (count($origins) !== count(array_unique($origins, SORT_STRING))) return false;
        foreach ($origins as $origin) {
            if (!is_string($origin) || str_contains($origin, '*') || !$this->configuredOriginValid($origin)) return false;
        }
        $idempotency = $this->config()['idempotency'] ?? null;
        if (!is_array($idempotency)) return false;
        $active = $idempotency['active_key_version'] ?? null;
        if (is_string($active) && ctype_digit($active)) $active = (int) $active;
        $keys = $idempotency['keys'] ?? null;
        if (!is_int($active) || $active < 1 || !is_array($keys)
            || !array_key_exists($active, $keys) && !array_key_exists((string) $active, $keys)) return false;
        return true;
    }

    private function configuredOriginValid(string $origin): bool
    {
        if (trim($origin) !== $origin || preg_match('/[^\x20-\x7e]/', $origin) === 1
            || str_contains($origin, '*') || str_ends_with($origin, '.')) return false;
        $parts = parse_url($origin);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'], $parts['pass'], $parts['path'], $parts['query'], $parts['fragment'])) return false;
        if (!in_array($parts['scheme'], ['http', 'https'], true)
            || strtolower((string) $parts['host']) !== (string) $parts['host']) return false;
        $host = (string) $parts['host'];
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) $host = substr($host, 1, -1);
        if (!filter_var($host, FILTER_VALIDATE_IP)
            && (preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D', $host) !== 1
                || str_contains($host, '..'))) return false;
        $port = $parts['port'] ?? null;
        if (($parts['scheme'] === 'http' && $port === 80) || ($parts['scheme'] === 'https' && $port === 443)) return false;
        return true;
    }

    private function admin2IndexConfigurationValid(): bool
    {
        $config = $this->config()['admin2_index'] ?? null;
        return is_array($config)
            && ($config['enabled'] ?? null) === true
            && ($config['timezone'] ?? null) === 'UTC';
    }

    private function eventUserAllowed(mixed $user): bool
    {
        if (!is_object($user)) return false;
        try {
            if (method_exists($user, 'get') && (bool) $user->get('access.api.super')) return true;
            if (method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.read')) return true;
            return method_exists($user, 'authorize') && (bool) $user->authorize('api.goosialize_leads.read');
        } catch (\Throwable) {
            return false;
        }
    }
}
