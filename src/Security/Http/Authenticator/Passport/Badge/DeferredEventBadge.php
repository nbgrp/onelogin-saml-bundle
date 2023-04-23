<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A tricky badge for deferred user creation/modification event dispatching by a firewall-specific event dispatcher.
 */
class DeferredEventBadge implements BadgeInterface
{
    private ?Event $event = null;
    private bool $resolved = false;

    public function setEvent(?Event $event): void
    {
        $this->event = $event;
    }

    public function getEvent(): ?Event
    {
        try {
            return $this->event;
        } finally {
            $this->resolved = true;
        }
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
