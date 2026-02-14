<?php

namespace App\Api\Dto;

use App\Api\InstanceTagService;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'TagCreate',
    description: 'Tag creation data',
    type: 'object',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 32, description: 'Tag name (slug is auto-generated from name)'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the tag is public', default: false),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata as key-value pairs', additionalProperties: true),
    ]
)]
#[OA\Schema(
    schema: 'TagUpdate',
    description: 'Tag update data',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 32, description: 'New tag name'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the tag is public'),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata as key-value pairs (merged with existing)', additionalProperties: true),
    ]
)]
class UserTagApiDto
{
    #[Groups(['tag:create', 'tag:update'])]
    #[Assert\NotBlank(groups: ['tag:create'])]
    #[Assert\Length(max: 32)]
    public string $name {
        set {
            $this->name = $value;
            $this->slug = InstanceTagService::slugger($value);
        }
    }

    #[Groups(['tag:show:public', 'tag:show:private'])]
    #[Assert\NotBlank(groups: ['tag:create'])]
    #[Assert\Length(max: 32)]
    public private(set) string $slug;

    #[Groups(['tag:create', 'tag:update'])]
    public ?bool $isPublic = null;

    /** @var array<string, string> */
    #[Groups(['tag:create', 'tag:update'])]
    public array $meta = [];
}
