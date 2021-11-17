<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\EventListener\User;

use Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent;

class UserModifiedListener extends AbstractUserListener
{
    public function __invoke(UserModifiedEvent $event): void
    {
        $this->handleEvent($event);
    }
}
