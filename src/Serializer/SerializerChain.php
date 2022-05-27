<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Spec\Serializer;

use BackedEnum;
use OpenApi\Attributes\OpenApiAttributeInterface;

class SerializerChain implements SerializerInterface
{
    /**
     * @param SerializerInterface[] $serializers
     */
    public function __construct(protected array $serializers)
    {
    }

    public function serialize(OpenApiAttributeInterface|int|bool|array|string|null|BackedEnum $value, ?SerializerResolver $serializerResolver): mixed
    {
        foreach ($this->serializers as $serializer) {
            $value = $serializer->serialize($value, $serializerResolver);
        }

        return $value;
    }
}
