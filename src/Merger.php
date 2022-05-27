<?php

declare(strict_types=1);

namespace Radebatz\OpenApi\Spec;

use OpenApi\Attributes\Hidden;
use OpenApi\Attributes\Media\Schema;
use OpenApi\Attributes\Method;
use OpenApi\Attributes\OpenAPIDefinition;
use OpenApi\Attributes\Operation;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Parameters\RequestBody;
use OpenApi\Attributes\Path;
use OpenApi\Attributes\Responses\ApiResponse;
use OpenApi\Attributes\Security\SecurityScheme;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Radebatz\OpenApi\Spec\Attributes\ReturnType;
use ReflectionClass;

class Merger
{
    use Helper;

    public function __construct(protected LoggerInterface $logger = new NullLogger())
    {
    }

    /**
     * Wrangle all grouped attributes into a spec.
     */
    public function merge(array $attributes): array
    {
        $data = [
            'openapi' => '3.0.2',
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [],
            ],
        ];

        foreach ($attributes as $fqdn => $fqdnDetails) {
            if ($this->first($fqdnDetails['object'][0], Hidden::class) || $this->first($fqdnDetails['object'][0], Schema::class)?->hidden) {
                continue;
            }
            $pathPrefix = $this->first($fqdnDetails['object'][0], Path::class)?->path ?? '';
            foreach ($fqdnDetails as $type => $typeAttributes) {
                if ('properties' == $type && $typeAttributes) {
                    $data['components']['schemas'] += $this->mergeSchema($fqdn, $typeAttributes);
                }
                foreach ($typeAttributes as $attributes) {
                    if (($path = $this->first($attributes, Path::class)) && !$this->first($attributes, Hidden::class) && !$this->first($attributes, Operation::class)?->hidden) {
                        $data['paths'][$pathPrefix . $path->path] = array_merge($data['paths'][$pathPrefix . $path->path] ?? [], $this->mergePath($path, $attributes));
                        continue;
                    }
                    foreach ($attributes as $attribute) {
                        if ($attribute instanceof OpenAPIDefinition) {
                            $data[] = $attribute;
                        }

                        if ($attribute instanceof SecurityScheme) {
                            $data['components']['securitySchemes'][] = $attribute;
                        }
                    }
                }
            }
        }

        return $data;
    }

    protected function mergePath(Path $path, array $attributes): array
    {
        $responses = $this->all($attributes, ApiResponse::class);
        if ($responses && ($returnType = $this->first($attributes, ReturnType::class))) {
            array_walk($responses, function (ApiResponse $response) use ($returnType) {
                foreach ($response->content as $content) {
                    $content->schema ??= new Schema();
                    $content->schema->ref = $returnType->ref();
                }
            });
        }

        $parameters = $this->all($attributes, Parameter::class);

        $details = [
            $this->first($attributes, Operation::class),
            'parameters' => $parameters,
            'requestBody' => $this->first($attributes, RequestBody::class),
            'responses' => $this->all($attributes, ApiResponse::class),
        ];

        $methods = [];
        foreach ($this->all($attributes, Method::class) as $method) {
            $methods[strtolower((new ReflectionClass($method))->getShortName())] = $details;
        }

        return $methods;
    }

    protected function mergeSchema(string $fqdn, array $attributes): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($attributes as $name => $propertyAttributes) {
            $schemaAttribute = $this->first($propertyAttributes, Schema::class);
            assert($schemaAttribute != null);
            if ($this->first($propertyAttributes, Hidden::class) || $schemaAttribute->hidden) {
                continue;
            }
            $schema['properties'][$schemaAttribute->name] = $schemaAttribute;
        }

        return $schema['properties'] ? [$this->fqdn2name($fqdn) => $schema] : [];
    }
}
