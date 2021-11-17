<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\Event;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractUserEvent extends Event
{
    public function __construct(
        private UserInterface $user,
    ) {}

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
