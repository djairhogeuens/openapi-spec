<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Spec\Serializer;

use BackedEnum;
use OpenApi\Attributes\OpenApiAttributeInterface;

interface SerializerInterface
{
    public function serialize(OpenApiAttributeInterface|array|int|string|bool|null|BackedEnum $value, ?SerializerResolver $serializerResolver): mixed;
}
