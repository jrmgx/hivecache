<?php

namespace App\Naming;

class HashAndSubdirectories
{
    private const string ALGORITHM = 'sha256';
    private const int LENGTH = 50;

    public function name(): string
    {
        $name = hash(self::ALGORITHM, random_bytes(128));
        $name = mb_substr($name, 0, self::LENGTH);

        return $this->splitToDirectories($name);
    }

    private function splitToDirectories(string $name): string
    {
        return mb_substr($name, 0, 3) . \DIRECTORY_SEPARATOR .
            mb_substr($name, 3, 3) . \DIRECTORY_SEPARATOR .
            $name;
    }
}
