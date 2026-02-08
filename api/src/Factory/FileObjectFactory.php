<?php

namespace App\Factory;

use App\Entity\FileObject;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<FileObject>
 */
final class FileObjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return FileObject::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'owner' => UserFactory::new(),
            'size' => 42,
        ];
    }
}
