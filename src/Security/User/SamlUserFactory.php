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

    /**
     * Creates a user instance based on the provided identifier and attributes.
     *
     * @param string                  $identifier the unique identifier for the user
     * @param array<array-key, mixed> $attributes the attributes associated with the user
     *
     * @return UserInterface the created user instance
     *
     * @throws \ReflectionException
     */
    #[\Override]
    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $user = new $this->userClass($identifier);
        $reflection = new \ReflectionClass($this->userClass);

        /** @psalm-suppress MixedAssignment, MixedArgumentTypeCoercion */
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

    /**
     * Retrieves the value of a specific attribute from the provided attributes array.
     *
     * @param array<string, mixed> $attributes the attributes array
     * @param string               $attribute  the attribute to retrieve
     *
     * @return mixed the value of the attribute
     *
     * @throws \RuntimeException if the attribute is not found in the attributes array
     */
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
