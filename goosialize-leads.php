<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
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
}
