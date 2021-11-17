<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\EventListener\User;

use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Event\AbstractUserEvent;

abstract class AbstractUserListener
{
    public function __construct(
        protected ?EntityManagerInterface $entityManager,
        protected bool $needPersist,
    ) {}

    protected function handleEvent(AbstractUserEvent $event): void
    {
        if ($this->needPersist && $this->entityManager instanceof EntityManagerInterface) {
            $this->entityManager->persist($event->getUser());
            $this->entityManager->flush();
        }
    }
}
