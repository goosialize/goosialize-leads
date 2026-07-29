<?php

declare(strict_types=1);

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\Email\Email;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemPendingNotificationRepository;
use Grav\Plugin\GoosializeLeads\Notification\GravEmailNotificationTransport;
use Grav\Plugin\GoosializeLeads\Notification\LeadDeliveryRecordReader;
use Grav\Plugin\GoosializeLeads\Notification\NotificationDeliveryWorker;
use Grav\Plugin\GoosializeLeads\Notification\NotificationMessageFactory;
use Symfony\Component\Console\Input\InputOption;

final class DeliverNotificationsCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('deliver-notifications')
            ->setDescription('Deliver pending Goosialize Lead notifications once')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum pending events to process', '10');
    }

    protected function serve(): int
    {
        $this->initializePlugins();
        $value = $this->getInput()->getOption('limit');
        if (!is_string($value) || preg_match('/\A(?:[1-9]|[1-4][0-9]|50)\z/D', $value) !== 1) {
            $this->getIO()->writeln('ERROR code=invalid_limit count=1');
            $this->getIO()->writeln('RESULT discovered=0 delivered=0 contended=0 failed=0 uncertain=0');
            return 2;
        }
        $grav = Grav::instance();
        $config = $grav['config'];
        if ($config->get('plugins.goosialize-leads.notifications.delivery.enabled') !== true) {
            $this->getIO()->writeln('ERROR code=delivery_disabled count=1');
            $this->getIO()->writeln('RESULT discovered=0 delivered=0 contended=0 failed=0 uncertain=0');
            return 2;
        }
        $recipients = $config->get('plugins.goosialize-leads.notifications.delivery.recipients', []);
        $senderAddress = $config->get('plugins.goosialize-leads.notifications.delivery.sender_address');
        $senderName = $config->get('plugins.goosialize-leads.notifications.delivery.sender_name');
        $defaultLimit = $config->get('plugins.goosialize-leads.notifications.delivery.default_limit', 10);
        if (!$this->validRouting($recipients, $senderAddress, $senderName, $defaultLimit)) {
            $this->getIO()->writeln('ERROR code=routing_invalid count=1');
            $this->getIO()->writeln('RESULT discovered=0 delivered=0 contended=0 failed=0 uncertain=0');
            return 2;
        }
        $plugin = Plugins::get('email');
        if (
            !is_object($plugin)
            || !($plugin->enabled ?? false)
            || !class_exists(Email::class)
            || !isset($grav['Email'])
            || !$grav['Email'] instanceof Email
            || !Email::enabled()
        ) {
            $this->getIO()->writeln('ERROR code=email_unavailable count=1');
            $this->getIO()->writeln('RESULT discovered=0 delivered=0 contended=0 failed=0 uncertain=0');
            return 2;
        }
        $root = $grav['locator']->findResource('user-data://', true, true);
        if (!is_string($root) || $root === '') {
            $this->getIO()->writeln('ERROR code=email_unavailable count=1');
            $this->getIO()->writeln('RESULT discovered=0 delivered=0 contended=0 failed=0 uncertain=0');
            return 2;
        }
        try {
            $worker = new NotificationDeliveryWorker(
                new FilesystemPendingNotificationRepository($root),
                new LeadDeliveryRecordReader($root),
                new NotificationMessageFactory(),
                new GravEmailNotificationTransport($grav['Email'], $recipients, $senderAddress, $senderName)
            );
            $result = $worker->deliver((int) $value);
        } catch (\Throwable) {
            $this->getIO()->writeln('ERROR code=email_unavailable count=1');
            $this->getIO()->writeln('RESULT discovered=0 delivered=0 contended=0 failed=0 uncertain=0');
            return 2;
        }
        foreach ($result->codes() as $code => $count) {
            $this->getIO()->writeln('ERROR code=' . $code . ' count=' . $count);
        }
        $this->getIO()->writeln(sprintf(
            'RESULT discovered=%d delivered=%d contended=%d failed=%d uncertain=%d',
            $result->discovered(),
            $result->delivered(),
            $result->contended(),
            $result->failed(),
            $result->uncertain()
        ));
        return $result->isComplete() ? 0 : 3;
    }

    private function validRouting(mixed $recipients, mixed $senderAddress, mixed $senderName, mixed $limit): bool
    {
        if (!is_array($recipients) || !array_is_list($recipients) || count($recipients) < 1 || count($recipients) > 5
            || !is_string($senderAddress) || !$this->validAddress($senderAddress) || $limit !== 10
            || ($senderName !== null && (!is_string($senderName) || strlen($senderName) < 1 || strlen($senderName) > 80
                || preg_match('//u', $senderName) !== 1 || preg_match('/[\x00-\x1f\x7f-\x9f]/u', $senderName) === 1
                || !class_exists(\Normalizer::class) || !\Normalizer::isNormalized($senderName, \Normalizer::FORM_C)))
        ) return false;
        $unique = [];
        foreach ($recipients as $recipient) {
            if (!is_string($recipient) || !$this->validAddress($recipient) || isset($unique[$recipient])) return false;
            $unique[$recipient] = true;
        }
        return true;
    }

    private function validAddress(string $address): bool
    {
        if (strlen($address) > 254 || preg_match('/\A([a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+)@(.+)\z/D', $address, $parts) !== 1
            || strlen($parts[1]) > 64 || str_starts_with($parts[1], '.') || str_ends_with($parts[1], '.')
            || str_contains($parts[1], '..')
        ) return false;
        $labels = explode('.', $parts[2]);
        if (count($labels) < 2) return false;
        foreach ($labels as $label) {
            if (strlen($label) < 1 || strlen($label) > 63
                || preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/D', $label) !== 1
            ) return false;
        }
        return true;
    }
}
