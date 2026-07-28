<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter;
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
            'onApiRegisterRoutes' => ['onApiRegisterRoutes', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onFormProcessed' => ['onFormProcessed', 0],
        ];
    }

    /**
     * Establish the Phase 2 route-provider contract without registering routes.
     */
    public function onApiRegisterRoutes(Event $event): void
    {
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
}
