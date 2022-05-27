<?php

declare(strict_types=1);

namespace Radebatz\OpenApi\Spec;

trait Helper
{
    protected function fqdn2name(string $fqdn)
    {
        $token = explode('\\', $fqdn);

        return array_pop($token);
    }

    protected function fqdn2ref(string $fqdn)
    {
        $name = $this->fqdn2name($fqdn);

        return "#components/schemas/$name";
    }

    protected function all(array $attributes, string $class): array
    {
        return array_filter($attributes, fn ($attribute) => $attribute instanceof $class);
    }

    protected function first(array $attributes, string $class)
    {
        return ($match = $this->all($attributes, $class)) ? array_pop($match) : null;
    }
}
