<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\EventListener\User;

use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Event\AbstractUserEvent;

abstract class AbstractUserListener
{
    public function __construct(
        protected ?EntityManagerInterface $entityManager,
        protected bool $needPersist,
    ) {}

    public function __invoke(AbstractUserEvent $event): void
    {
        if ($this->needPersist && $this->entityManager instanceof EntityManagerInterface) {
            $this->entityManager->persist($event->getUser());
            $this->entityManager->flush();
        }
    }
}
