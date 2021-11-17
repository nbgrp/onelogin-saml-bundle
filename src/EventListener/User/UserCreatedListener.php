<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\EventListener\User;

use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;

class UserCreatedListener extends AbstractUserListener
{
    public function __invoke(UserCreatedEvent $event): void
    {
        $this->handleEvent($event);
    }
}
