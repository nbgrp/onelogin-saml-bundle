<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A tricky badge for deferred user creation/modification event dispatching by a firewall-specific event dispatcher.
 */
final class DeferredEventBadge implements BadgeInterface
{
    public ?Event $event = null {
        get {
            try {
                return $this->event;
            } finally {
                $this->resolved = true;
            }
        }
        set(?Event $event) {
            $this->event = $event;
        }
    }

    private bool $resolved = false;

    #[\Override]
    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
