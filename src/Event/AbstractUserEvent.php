<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Event;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractUserEvent extends Event
{
    public function __construct(
        private readonly UserInterface $user,
    ) {}

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
