<?php

namespace App\Support;

final class Revision
{
    public static function current(): ?string
    {
        $env = env('APP_REVISION');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        $headPath = base_path('.git/HEAD');
        if (!is_readable($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));
        if ($head === '') {
            return null;
        }

        if (str_starts_with($head, 'ref:')) {
            $ref = trim(substr($head, 4));
            $refPath = base_path('.git/'.$ref);
            if ($ref !== '' && is_readable($refPath)) {
                return trim((string) file_get_contents($refPath));
            }

            $packedPath = base_path('.git/packed-refs');
            if ($ref !== '' && is_readable($packedPath)) {
                $lines = file($packedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    if ($line[0] === '#') continue;
                    if (str_ends_with($line, ' '.$ref)) {
                        return strtok($line, ' ');
                    }
                }
            }
        }

        if (preg_match('/^[0-9a-f]{7,40}$/i', $head)) {
            return $head;
        }

        return null;
    }
}
