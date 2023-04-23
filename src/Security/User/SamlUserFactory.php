<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

final class SamlUserFactory implements SamlUserFactoryInterface
{
    /**
     * @param class-string<UserInterface> $userClass
     * @param array<string, mixed>        $mapping
     */
    public function __construct(
        private readonly string $userClass,
        private readonly array $mapping,
    ) {}

    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $user = new $this->userClass($identifier);
        $reflection = new \ReflectionClass($this->userClass);

        /** @psalm-suppress MixedAssignment */
        foreach ($this->mapping as $field => $attribute) {
            $property = $reflection->getProperty($field);
            $property->setValue(
                $user,
                \is_string($attribute) && str_starts_with($attribute, '$')
                    ? $this->getAttributeValue($attributes, substr($attribute, 1))
                    : $attribute,
            );
        }

        return $user;
    }

    private function getAttributeValue(array $attributes, string $attribute): mixed
    {
        $isArrayValue = str_ends_with($attribute, '[]');
        $attribute = $isArrayValue ? substr($attribute, 0, -2) : $attribute;

        if (!\array_key_exists($attribute, $attributes)) {
            throw new \RuntimeException('Attribute "'.$attribute.'" not found in SAML data.');
        }

        $attributeValue = (array) $attributes[$attribute];
        if (!$isArrayValue) {
            /** @psalm-suppress MixedAssignment */
            $attributeValue = reset($attributeValue);
        }

        return $attributeValue;
    }
}
