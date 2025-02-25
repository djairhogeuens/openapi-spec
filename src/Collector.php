<?php

declare(strict_types=1);

namespace Radebatz\OpenApi\Spec;

use OpenApi\Attributes\Media\ArraySchema;
use OpenApi\Attributes\Media\Schema;
use OpenApi\Attributes\OpenApiAttributeInterface;
use OpenApi\Attributes\Parameter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Radebatz\OpenApi\Spec\Attributes\ParameterType;
use Radebatz\OpenApi\Spec\Attributes\ReturnType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Reflector;

/**
 * Collect OpenAPI attributes from the given `Reflector`.
 */
class Collector
{
    use Helper;

    public function __construct(protected LoggerInterface $logger = new NullLogger())
    {
    }

    public function collect(Reflector $reflector)
    {
        $attributes = [];

        foreach ($reflector->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            $attributes[] = $instance;
        }

        if ($reflector instanceof ReflectionMethod) {
            $attributes = $this->collectMethod($reflector, $attributes);
        }
        if ($reflector instanceof ReflectionProperty) {
            $attributes = $this->collectProperty($reflector, $attributes);
        }

        return array_filter($attributes, fn ($attribute) => $attribute instanceof OpenApiAttributeInterface);
    }

    protected function collectMethod(ReflectionMethod $reflectionMethod, array $attributes)
    {
        // also look at parameter attributes
        foreach ($reflectionMethod->getParameters() as $rp) {
            foreach ($rp->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof Parameter) {
                    $instance->name = $rp->getName();
                    if (($type = $rp->getType()) && $type instanceof ReflectionNamedType) {
                        $attributes[] = new ParameterType($rp->getName(), $type);
                    }
                }
                $attributes[] = $instance;
            }
        }

        if (($returnType = $reflectionMethod->getReturnType()) && $returnType instanceof ReflectionNamedType) {
            $attributes[] = new ReturnType($returnType);
        }

        return $attributes;
    }

    protected function collectProperty(ReflectionProperty $reflectionProperty, array $attributes): array
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof Schema) {
                // type details?
                $attribute->name = !empty($attribute->name) ? $attribute->name : $reflectionProperty->getName();
            } elseif ($attribute instanceof ArraySchema) {
                if (($reflectionType = $reflectionProperty->getType()) != null && (!$reflectionType instanceof ReflectionNamedType || $reflectionType->getName() != 'array')) {
                    $this->logger->warning(sprintf('Property %s on %s has ArraySchema attribute but is not of type array', $reflectionProperty->getName(), $reflectionProperty->getDeclaringClass()->getName()));
                } else {
                    // TODO check docblock
                    $attribute->name = $reflectionProperty->getName();
                }
            }
        }

        if (!$this->first($attributes, Schema::class)) {
            $attributes[] = new Schema(name: $reflectionProperty->getName());
        }

        return $attributes;
    }
}
