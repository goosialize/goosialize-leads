<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Processors\Events\RequestHandlerEvent;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Framework\Acl\PermissionsReader;
use Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController;
use Grav\Plugin\GoosializeLeads\Admin\LeadIndexQuery;
use Grav\Plugin\GoosializeLeads\Admin\LeadMutationController;
use Grav\Plugin\GoosializeLeads\Admin\LeadEditController;
use Grav\Plugin\GoosializeLeads\Admin\LeadsCsvExportController;
use Grav\Plugin\GoosializeLeads\Admin\NotificationOperationsController;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureRuntimeFactory;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Integration\GoosializeLeadsCaptureCapabilityV1;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter;
use Grav\Plugin\GoosializeLeads\Http\ApiResponseMapper;
use Grav\Plugin\GoosializeLeads\Http\EndpointRateLimiter;
use Grav\Plugin\GoosializeLeads\Http\OriginPolicy;
use Grav\Plugin\GoosializeLeads\Http\PublicApiRawBodyMiddleware;
use Grav\Plugin\GoosializeLeads\Http\PublicLeadApiController;
use Grav\Plugin\GoosializeLeads\Http\RawJsonParser;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOutbox;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\YamlFile;

final class GoosializeLeadsPlugin extends Plugin
{
    /** @var \WeakMap<Scheduler,bool>|null */
    private ?\WeakMap $notificationSchedulers = null;
    private bool $legacySecretMigrationBlocked = false;
    public function autoload(): void
    {
        require_once __DIR__ . '/autoload.php';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            PermissionsRegisterEvent::class => ['onRegisterPermissions', 1000],
            'onApiRegisterRoutes' => ['onApiRegisterRoutes', 0],
            'onApiSidebarItems' => ['onApiSidebarItems', 0],
            'onApiPluginPageInfo' => ['onApiPluginPageInfo', 0],
            'onApiBlueprintResolved' => ['onApiBlueprintResolved', 0],
            'onApiCollectPublicRoutes' => ['onApiCollectPublicRoutes', 0],
            'onRequestHandlerInit' => ['onRequestHandlerInit', 98000],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onFormProcessed' => ['onFormProcessed', 0],
            'onSchedulerInitialized' => ['onSchedulerInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        try {
            $this->migrateLegacyIdempotencySecrets();
        } catch (\Throwable) {
            $this->legacySecretMigrationBlocked = true;
            if (isset($this->grav['log'])) {
                $this->grav['log']->warning('idempotency_secret_migration_unavailable');
            }
        }

        $serviceKey = 'goosialize-leads.public-capture.v1';

        if (isset($this->grav[$serviceKey])) {
            if (isset($this->grav['log'])) {
                $this->grav['log']->warning(
                    'public_capture_capability_service_collision'
                );
            }

            return;
        }

        $service = null;

        try {
            $config = $this->config();
            $idempotency = $config['idempotency'] ?? null;
            $outbox = $config['notifications']['outbox'] ?? null;

            if (!is_array($idempotency)) {
                throw new \InvalidArgumentException(
                    'Invalid idempotency configuration.'
                );
            }

            if ($outbox !== null && !is_array($outbox)) {
                throw new \InvalidArgumentException(
                    'Invalid outbox configuration.'
                );
            }

            $root = $this->grav['locator']->findResource(
                'user-data://',
                true
            );

            if (!is_string($root) || $root === '') {
                throw new \InvalidArgumentException(
                    'Invalid storage root.'
                );
            }

            $logger = isset($this->grav['log'])
                ? fn (string $code): mixed => $this->grav['log']->warning($code)
                : null;

            $service = LeadCaptureRuntimeFactory::create(
                $root,
                $idempotency,
                $outbox,
                $logger
            );
        } catch (\Throwable) {
            if (isset($this->grav['log'])) {
                $this->grav['log']->warning(
                    'public_capture_capability_unavailable'
                );
            }
        }

        $this->grav[$serviceKey] =
            new GoosializeLeadsCaptureCapabilityV1($service);
    }

    public function onApiBlueprintResolved(Event $event): void
    {
        if (($event['plugin'] ?? null) !== 'goosialize-leads') {
            return;
        }

        $fields = $event['fields'] ?? null;

        if (!is_array($fields)) {
            return;
        }

        $options = $this->discoverGravFormOptions();

        $this->injectSelectOptions(
            $fields,
            'forms.forms',
            $options
        );

        $this->injectSelectOptions(
            $fields,
            '.form',
            $options
        );

        $event['fields'] = $fields;
    }

    /**
     * @return array<string,string>
     */
    private function discoverGravFormOptions(): array
    {
        $options = [];

        try {
            $pages = $this->grav['pages'] ?? null;

            if (!is_object($pages) || !method_exists($pages, 'all')) {
                return [];
            }

            foreach ($pages->all() as $page) {
                if (
                    !is_object($page)
                    || !method_exists($page, 'header')
                ) {
                    continue;
                }

                $header = $page->header();

                if (!is_object($header)) {
                    continue;
                }

                $raw = null;

                if (isset($header->forms)) {
                    $raw = $header->forms;
                } elseif (isset($header->form)) {
                    $raw = $header->form;
                }

                if (is_object($raw)) {
                    $raw = (array) $raw;
                }

                if (!is_array($raw)) {
                    continue;
                }

                if (
                    isset($raw['name'])
                    && is_string($raw['name'])
                ) {
                    $name = $raw['name'];

                    if ($this->validDiscoveredFormName($name)) {
                        $options[$name] = $name;
                    }

                    continue;
                }

                foreach ($raw as $name => $definition) {
                    if (
                        !is_string($name)
                        || !$this->validDiscoveredFormName($name)
                    ) {
                        continue;
                    }

                    $label = $name;

                    if (is_object($definition)) {
                        $definition = (array) $definition;
                    }

                    if (
                        is_array($definition)
                        && is_string($definition['name'] ?? null)
                        && $definition['name'] !== ''
                    ) {
                        $label = $definition['name'];
                    }

                    $options[$name] = $label;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private function validDiscoveredFormName(string $name): bool
    {
        return preg_match(
            '/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D',
            $name
        ) === 1;
    }

    /**
     * @param array<mixed> $node
     * @param array<string,string> $options
     */
    private function injectSelectOptions(
        array &$node,
        string $targetName,
        array $options
    ): void {
        foreach ($node as $key => &$value) {
            if (
                $key === $targetName
                && is_array($value)
                && ($value['type'] ?? null) === 'select'
            ) {
                $value['options'] = $options;
            }

            if (is_array($value)) {
                $this->injectSelectOptions(
                    $value,
                    $targetName,
                    $options
                );
            }
        }

        unset($value);
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
            $routes->get('/goosialize-leads/filter-form-data', [LeadsIndexController::class, 'filterFormData']);
            $routes->get('/goosialize-leads/edit/{id}/form-data', [LeadEditController::class, 'formData']);

            if (method_exists($routes, 'patch')) {
                $routes->patch('/goosialize-leads/edit/{id}', [LeadEditController::class, 'save']);
            }

            $routes->post('/goosialize-leads/apply', [LeadMutationController::class, 'apply']);
            if ($this->admin2CsvExportConfigurationValid()) {
                $routes->get('/goosialize-leads/export', [LeadsCsvExportController::class, 'export']);
            }
        }
        if (method_exists($routes, 'get')) {
            $routes->get('/goosialize-leads/notification-operations', [NotificationOperationsController::class, 'index']);
        }
    }

    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        $event->permissions->addActions(PermissionsReader::fromYaml("plugin://{$this->name}/permissions.yaml"));
    }

    public function onApiSidebarItems(Event $event): void
    {
        if (
            !$this->admin2IndexConfigurationValid()
            || !$this->eventUserAllowed($event['user'] ?? null)
        ) {
            return;
        }

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
        if (
            ($event['plugin'] ?? null) !== 'goosialize-leads'
            || !$this->admin2IndexConfigurationValid()
            || !$this->eventUserAllowed($event['user'] ?? null)
        ) {
            return;
        }

        $actions = [
            [
                'id' => 'refresh',
                'label' => 'Refresh',
                'icon' => 'fa-refresh',
            ],
            [
                'id' => 'reset_filters',
                'label' => 'Reset filters',
                'icon' => 'fa-rotate-left',
            ],
        ];

        if (
            $this->eventUserCanExport($event['user'] ?? null)
            && $this->admin2CsvExportConfigurationValid()
        ) {
            $actions[] = [
                'id' => 'export',
                'label' => 'Export CSV',
                'icon' => 'fa-download',
                'download' => true,
                'endpoint' => '/goosialize-leads/export',
            ];
        }

        $event['definition'] = [
            'id' => 'goosialize-leads',
            'plugin' => 'goosialize-leads',
            'title' => 'Leads',
            'icon' => 'fa-address-book',
            'page_type' => 'blueprint',
                'blueprint' => 'goosialize-leads-index',
                'data_endpoint' => '/goosialize-leads/filter-form-data',
                'save_endpoint' => '/goosialize-leads/filter-form-data',
            'actions' => $actions,
        ];
    }

    public function onSchedulerInitialized(Event $event): void
    {
        try {
            $scheduler=$event['scheduler']??null;
            if(!$scheduler instanceof Scheduler){$this->schedulerLog('notification_scheduler_unavailable');return;}
            $this->notificationSchedulers??=new \WeakMap();
            if(isset($this->notificationSchedulers[$scheduler]))return;
            $settings=$this->schedulingSettings();
            if($settings===null){$this->schedulerLog('notification_scheduler_configuration_invalid');return;}
            if($settings['enabled']!==true)return;
            $frequency=$settings['frequency_minutes'];$cron=$frequency===1?'* * * * *':($frequency===60?'0 * * * *':"*/$frequency * * * *");
            $scheduler->addCommand(PHP_BINARY,[GRAV_ROOT.'/bin/plugin','goosialize-leads','deliver-notifications','--limit='.$settings['batch_limit']],'goosialize-leads-notification-delivery')
                ->at($cron)->inForeground()->timeout(300);
            $this->notificationSchedulers[$scheduler]=true;
        } catch (\Throwable) {$this->schedulerLog('notification_scheduler_unavailable');}
    }

    public function onApiCollectPublicRoutes(Event $event): void
    {
        if (!$this->publicApiConfigurationValid()) return;
        $exact = $event['exact'] ?? null;
        $apiBase = $event['api_base'] ?? null;
        if (!is_array($exact) || !is_string($apiBase) || $apiBase === '') return;
        $apiBase = '/' . trim($apiBase, '/');
        if ($apiBase === '/' || str_contains($apiBase, '//')) return;
        $route = 'POST ' . $apiBase . '/goosialize-leads/capture';
        if (!in_array($route, $exact, true)) $exact[] = $route;
        $event['exact'] = $exact;
    }

    public function onRequestHandlerInit(RequestHandlerEvent $event): void
    {
        if ($this->legacySecretMigrationBlocked) {
            $configRoute = $this->pluginConfigApiRoutePath();
            if ($configRoute !== null && $event->getRoute()->getRoute() === $configRoute) {
                throw new \RuntimeException('goosialize_leads_configuration_unavailable');
            }
        }

        if (!$this->publicApiConfigurationValid()) return;
        $routePath = $this->publicApiRoutePath();
        if ($routePath === null || $event->getRoute()->getRoute() !== $routePath) return;
        try {
            $config = $this->config()['public_api'];
            $root = $this->grav['locator']->findResource('user-data://', true);
            if (!is_string($root) || $root === '') return;
            $middleware = new PublicApiRawBodyMiddleware(
                new RawJsonParser(),
                new OriginPolicy(),
                new EndpointRateLimiter($root, static fn (): int => time()),
                new ApiResponseMapper(),
                $config,
                $routePath
            );
            $event->addMiddleware('goosialize_leads_public_api', $middleware);
        } catch (\Throwable) {
            if (isset($this->grav['log'])) $this->grav['log']->warning('public_api_configuration_unavailable');
        }
    }

    private function publicApiRoutePath(): ?string
    {
        $apiBase = $this->apiBasePath();
        return $apiBase === null ? null : $apiBase . '/goosialize-leads/capture';
    }

    private function pluginConfigApiRoutePath(): ?string
    {
        $apiBase = $this->apiBasePath();
        return $apiBase === null ? null : $apiBase . '/config/plugins/goosialize-leads';
    }

    private function apiBasePath(): ?string
    {
        $route = $this->grav['config']->get('plugins.api.route', '/api');
        $prefix = $this->grav['config']->get('plugins.api.version_prefix', 'v1');
        if (!is_string($route) || !is_string($prefix)) return null;
        $route = trim($route, '/');
        $prefix = trim($prefix, '/');
        if ($route === '' || $prefix === '' || str_contains($route, '//') || str_contains($prefix, '/')) return null;

        return '/' . $route . '/' . $prefix;
    }

    private function migrateLegacyIdempotencySecrets(): void
    {
        $userRoot = $this->grav['locator']->findResource('user://', true);
        if (!is_string($userRoot) || $userRoot === '') {
            throw new \RuntimeException('User configuration root unavailable.');
        }

        $paths = [$userRoot . '/config/plugins/goosialize-leads.yaml'];
        foreach (glob($userRoot . '/env/*/config/plugins/goosialize-leads.yaml') ?: [] as $path) {
            $paths[] = $path;
        }

        $persisted = false;
        foreach (array_values(array_unique($paths)) as $path) {
            if (!is_file($path)) continue;
            $file = YamlFile::instance($path);
            $configuration = $file->content();
            if (!is_array($configuration)) {
                throw new \RuntimeException('Plugin configuration is invalid.');
            }
            $normalized = $this->normalizeLegacyIdempotencySecrets($configuration);
            if ($normalized === $configuration) continue;
            $file->content($normalized);
            $file->save();
            $persisted = true;
        }

        $configuration = $this->config();
        $normalized = $this->normalizeLegacyIdempotencySecrets($configuration);
        if ($normalized !== $configuration) {
            $this->grav['config']->set('plugins.goosialize-leads', $normalized);
        }

        if ($persisted && isset($this->grav['cache'])) {
            $this->grav['cache']->clearCache('standard');
        }
    }

    /**
     * @param array<string,mixed> $configuration
     * @return array<string,mixed>
     */
    private function normalizeLegacyIdempotencySecrets(array $configuration): array
    {
        $idempotency = $configuration['idempotency'] ?? null;
        if (!is_array($idempotency) || !is_array($idempotency['keys'] ?? null)) {
            return $configuration;
        }

        $keys = $idempotency['keys'];
        foreach ($keys as $version => $value) {
            if (is_string($value)) {
                $keys[$version] = ['secret' => $value];
            }
        }
        $configuration['idempotency']['keys'] = $keys;

        return $configuration;
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
            $root = $this->grav['locator']->findResource(
                'user-data://',
                true
            );

            if (!is_string($root) || $root === '') {
                throw new \InvalidArgumentException(
                    'Invalid storage root.'
                );
            }

            $outboxConfig = $config['notifications']['outbox'] ?? null;

            if ($outboxConfig !== null && !is_array($outboxConfig)) {
                throw new \InvalidArgumentException(
                    'Invalid outbox configuration.'
                );
            }

            $logger = isset($this->grav['log'])
                ? fn (string $code): mixed => $this->grav['log']->warning($code)
                : null;

            $service = LeadCaptureRuntimeFactory::create(
                $root,
                $idempotency,
                $outboxConfig,
                $logger
            );

            $entropy = static fn (int $length): string =>
                random_bytes($length);

            $clock = static fn (): \DateTimeInterface =>
                new \DateTimeImmutable(
                    'now',
                    new \DateTimeZone('UTC')
                );

            $adapter = new FormsLeadCaptureAdapter(
                $service,
                $forms,
                $entropy,
                $clock
            );
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

    private function admin2CsvExportConfigurationValid(): bool
    {
        $config = $this->config()['admin2_csv_export'] ?? null;
        return is_array($config)
            && ($config['enabled'] ?? null) === true
            && ($config['max_response_bytes'] ?? null) === 131072;
    }

    private function eventUserCanExport(mixed $user): bool
    {
        if (!is_object($user)) return false;
        try {
            if (method_exists($user, 'get') && (bool) $user->get('access.api.super')) return true;
            $api = method_exists($user, 'get') && (bool) $user->get('access.api.access');
            $read = method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.read');
            $export = method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.export');
            return $api && (($read && $export) || (method_exists($user, 'authorize')
                && (bool) $user->authorize('api.goosialize_leads.read')
                && (bool) $user->authorize('api.goosialize_leads.export')));
        } catch (\Throwable) {
            return false;
        }
    }

    private function eventUserAllowed(mixed $user): bool
    {
        if (!is_object($user)) return false;
        try {
            if (method_exists($user, 'get') && (bool) $user->get('access.api.super')) return true;
            if (method_exists($user, 'get') && !(bool) $user->get('access.api.access')) return false;
            if (method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.read')) return true;
            return method_exists($user, 'authorize') && (bool) $user->authorize('api.goosialize_leads.read');
        } catch (\Throwable) {
            return false;
        }
    }

    private function eventUserCanOperate(mixed $user):bool
    {
        if(!is_object($user))return false;
        try{if(method_exists($user,'get')&&(bool)$user->get('access.api.super'))return true;
            if(method_exists($user,'get')&&!(bool)$user->get('access.api.access'))return false;
            if(method_exists($user,'get')&&(bool)$user->get('access.api.goosialize_leads.operations'))return true;
            return method_exists($user,'authorize')&&(bool)$user->authorize('api.goosialize_leads.operations');
        }catch(\Throwable){return false;}
    }
    private function schedulingSettings():?array
    {
        $notifications=$this->config()['notifications']??null;if(!is_array($notifications))return null;
        $s=$notifications['scheduling']??[];$defaults=['enabled'=>false,'frequency_minutes'=>5,'batch_limit'=>10,'timeout_seconds'=>300];
        if(!is_array($s))return null;foreach($s as$k=>$_)if(!array_key_exists($k,$defaults))return null;$s=array_replace($defaults,$s);
        if(!is_bool($s['enabled'])||!is_int($s['frequency_minutes'])||!in_array($s['frequency_minutes'],[1,2,5,10,15,20,30,60],true)
            ||!is_int($s['batch_limit'])||$s['batch_limit']<1||$s['batch_limit']>50||$s['timeout_seconds']!==300)return null;
        if($s['enabled']===false)return$s;
        $d=$notifications['delivery']??null;$r=$notifications['delivery_retry']??null;
        if(!$this->schedulerDeliveryValid($d)||!$this->schedulerRetryValid($r))return null;
        return$s;
    }
    private function schedulerDeliveryValid(mixed $value):bool
    {
        if(!is_array($value)||($value['enabled']??null)!==true||($value['default_limit']??null)!==10)return false;
        $recipients=$value['recipients']??null;$sender=$value['sender_address']??null;$name=$value['sender_name']??null;
        if(!is_array($recipients)||!array_is_list($recipients)||count($recipients)<1||count($recipients)>5
            ||!is_string($sender)||!$this->schedulerAddressValid($sender)
            ||($name!==null&&(!is_string($name)||strlen($name)<1||strlen($name)>80||preg_match('//u',$name)!==1
                ||preg_match('/[\x00-\x1f\x7f-\x9f]/u',$name)===1||!class_exists(\Normalizer::class)
                ||!\Normalizer::isNormalized($name,\Normalizer::FORM_C))))return false;
        $seen=[];foreach($recipients as$recipient){if(!is_string($recipient)||!$this->schedulerAddressValid($recipient)||isset($seen[$recipient]))return false;$seen[$recipient]=true;}
        return true;
    }
    private function schedulerAddressValid(string $value):bool
    {
        if(strlen($value)>254||preg_match('/\A([a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+)@(.+)\z/D',$value,$parts)!==1
            ||strlen($parts[1])>64||str_starts_with($parts[1],'.')||str_ends_with($parts[1],'.')||str_contains($parts[1],'..'))return false;
        $labels=explode('.',$parts[2]);if(count($labels)<2)return false;
        foreach($labels as$label)if(strlen($label)<1||strlen($label)>63||preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/D',$label)!==1)return false;
        return true;
    }
    private function schedulerRetryValid(mixed $value):bool
    {
        if(!is_array($value))return false;$defaults=['enabled'=>false,'maximum_attempts'=>5,'delays_seconds'=>[300,1800,7200,28800],'processing_limit'=>10,'state_max_bytes'=>1024];
        foreach($value as$key=>$_)if(!array_key_exists($key,$defaults))return false;$value=array_replace($defaults,$value);
        return$value['enabled']===true&&$value['maximum_attempts']===5&&$value['delays_seconds']===[300,1800,7200,28800]
            &&is_int($value['processing_limit'])&&$value['processing_limit']>=1&&$value['processing_limit']<=50&&$value['state_max_bytes']===1024;
    }
    private function schedulerLog(string $code):void{if(isset($this->grav['log']))$this->grav['log']->warning($code);}
}
