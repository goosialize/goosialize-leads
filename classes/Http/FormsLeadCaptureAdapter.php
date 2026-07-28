<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

use Grav\Plugin\Form\Form;
use Grav\Plugin\GoosializeLeads\Application\CaptureResult;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use RocketTheme\Toolbox\Event\Event;

final class FormsLeadCaptureAdapter
{
    /** @var array{enabled:bool,forms:list<string>,source:string,locale:?string,consent_version:string,success_redirect:string} */
    private readonly array $configuration;
    /** @var \Closure(int):string */
    private readonly \Closure $entropy;
    /** @var \Closure():\DateTimeInterface */
    private readonly \Closure $clock;

    /**
     * @param array{enabled:bool,forms:list<string>,source:string,locale:?string,consent_version:string,success_redirect:string} $configuration
     * @param callable(int):string $entropy
     * @param callable():\DateTimeInterface $clock
     */
    public function __construct(
        private readonly LeadCaptureService $service,
        array $configuration,
        callable $entropy,
        callable $clock
    ) {
        $this->configuration = self::validateConfiguration($configuration);
        $this->entropy = \Closure::fromCallable($entropy);
        $this->clock = \Closure::fromCallable($clock);
    }

    public function process(Event $event): void
    {
        $form = $event['form'] ?? null;
        $action = $event['action'] ?? null;
        $params = $event['params'] ?? null;
        if (
            !$form instanceof Form
            || $action !== 'goosialize_leads_capture'
            || ($params !== true && $params !== [])
            || !$this->configuration['enabled']
        ) {
            return;
        }

        $formName = $form->getFormName();
        if (!in_array($formName, $this->configuration['forms'], true)) {
            return;
        }
        if (isset($form->xhr_submit) && $form->xhr_submit) {
            $this->fail($form, $event, 'PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE');
            return;
        }

        $values = $form->value();
        if (!is_array($values)) {
            $values = [];
        }
        $submitted = $values;
        foreach (['email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign'] as $optional) {
            if (!array_key_exists($optional, $submitted)) {
                $submitted[$optional] = null;
            }
        }
        $trusted = [
            'source' => $this->configuration['source'],
            'form_name' => $formName,
            'locale' => $this->configuration['locale'],
            'consent_version' => $this->configuration['consent_version'],
        ];

        $result = $this->service->capture(
            $submitted,
            $trusted,
            $formName,
            $form->getUniqueId(),
            $this->entropy,
            $this->clock
        );
        $this->applyResult($form, $event, $result);
    }

    private function applyResult(Form $form, Event $event, CaptureResult $result): void
    {
        if ($result->isSuccess()) {
            $form->status = 'success';
            $form->message = 'PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_SUCCESS';
            $event['redirect'] = $this->configuration['success_redirect'];
            $event['redirect_code'] = 303;
            $event->stopPropagation();
            return;
        }
        $message = $result->code() === 'validation_failed'
            ? 'PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_INVALID'
            : 'PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE';
        $this->fail($form, $event, $message);
    }

    private function fail(Form $form, Event $event, string $message): void
    {
        $form->setMessage($message);
        $event->stopPropagation();
    }

    /**
     * @param array<string,mixed> $configuration
     * @return array{enabled:bool,forms:list<string>,source:string,locale:?string,consent_version:string,success_redirect:string}
     */
    private static function validateConfiguration(array $configuration): array
    {
        if (
            array_keys($configuration) !== ['enabled', 'forms', 'source', 'locale', 'consent_version', 'success_redirect']
            || !is_bool($configuration['enabled'])
            || !is_array($configuration['forms'])
            || !array_is_list($configuration['forms'])
            || !is_string($configuration['source'])
            || ($configuration['locale'] !== null && !is_string($configuration['locale']))
            || !is_string($configuration['consent_version'])
            || !is_string($configuration['success_redirect'])
        ) {
            throw new \InvalidArgumentException('Invalid Forms capture configuration.');
        }
        foreach ($configuration['forms'] as $form) {
            if (!is_string($form) || !self::validSlug($form)) {
                throw new \InvalidArgumentException('Invalid Forms capture configuration.');
            }
        }
        if (
            count($configuration['forms']) !== count(array_unique($configuration['forms']))
            || !self::validSlug($configuration['source'])
            || !self::validSlug($configuration['consent_version'])
            || ($configuration['locale'] !== null
                && preg_match('/\A[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*\z/D', $configuration['locale']) !== 1)
            || !self::validRedirect($configuration['success_redirect'])
        ) {
            throw new \InvalidArgumentException('Invalid Forms capture configuration.');
        }
        return $configuration;
    }

    private static function validSlug(string $value): bool
    {
        return preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $value) === 1;
    }

    private static function validRedirect(string $value): bool
    {
        if (
            $value === ''
            || $value[0] !== '/'
            || str_starts_with($value, '//')
            || str_contains($value, '//')
            || strpbrk($value, "?#\\") !== false
            || preg_match('/[\x00-\x1f\x7f]/', $value) === 1
        ) {
            return false;
        }
        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
