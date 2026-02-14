<?php

namespace App\Api\Helper;

use Symfony\Component\HttpFoundation\Request;

final readonly class RequestHelper
{
    /**
     * @param array<int, string> $types
     */
    public static function accepts(Request $request, array $types): bool
    {
        $accepts = $request->getAcceptableContentTypes();

        if (0 === \count($accepts)) {
            return false;
        }

        return \count(array_intersect($accepts, $types)) > 0;
    }
}
