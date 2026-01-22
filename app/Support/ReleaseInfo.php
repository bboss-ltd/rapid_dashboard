<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class ReleaseInfo
{
    public static function releasedAt(): ?Carbon
    {
        $env = env('APP_RELEASED_AT');
        if (is_string($env) && $env !== '') {
            return Carbon::parse($env);
        }

        $manifestPath = public_path('build/manifest.json');
        if (is_readable($manifestPath)) {
            $mtime = filemtime($manifestPath);
            if ($mtime !== false) {
                return Carbon::createFromTimestamp($mtime);
            }
        }

        $indexPath = public_path('index.php');
        if (is_readable($indexPath)) {
            $mtime = filemtime($indexPath);
            if ($mtime !== false) {
                return Carbon::createFromTimestamp($mtime);
            }
        }

        $gitLog = base_path('.git/logs/HEAD');
        if (is_readable($gitLog)) {
            $lines = file($gitLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $last = $lines ? $lines[count($lines) - 1] : null;
            if ($last && preg_match('/\\s(\\d{9,})\\s[+-]\\d{4}\\t/', $last, $matches)) {
                return Carbon::createFromTimestamp((int) $matches[1]);
            }
        }

        return null;
    }
}
