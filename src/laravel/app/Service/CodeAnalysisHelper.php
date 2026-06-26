<?php

namespace App\Service;

class CodeAnalysisHelper
{
    /** @var list<string> */
    private const BOILERPLATE_PATTERNS = [
        '/^\s*package\s+\w+/m',
        '/^\s*import\s+/m',
        '/^\s*using\s+/m',
        '/^\s*#include\s+/m',
        '/^\s*from\s+\S+\s+import/m',
        '/^\s*export\s+(default\s+)?(function|class|const|interface|type)\s/m',
        '/^\s*public\s+class\s+\w+/m',
        '/^\s*func\s+main\s*\(\s*\)/m',
        '/^\s*\/\/\s*Ваше решение/m',
        '/^\s*#\s*Ваше решение/m',
    ];

    public function hasMeaningfulCode(?string $code, ?string $starterCode = null): bool
    {
        $useful = $this->countUsefulLines($code);
        if ($useful >= 3) {
            return true;
        }

        if ($useful < 2) {
            return false;
        }

        $normalized  = $this->normalize($code);
        $starterNorm = $this->normalize($starterCode ?? '');

        return $starterNorm === '' || levenshtein($normalized, $starterNorm) > 12;
    }

    public function countUsefulLines(?string $code): int
    {
        if ($code === null || trim($code) === '') {
            return 0;
        }

        $stripped = $code;
        foreach (self::BOILERPLATE_PATTERNS as $pattern) {
            $stripped = preg_replace($pattern, '', $stripped) ?? $stripped;
        }

        $lines = preg_split('/\R/', $stripped) ?: [];
        $count = 0;

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '#')) {
                continue;
            }
            if (preg_match('/^[{}();]+$/', $t)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function normalize(?string $code): string
    {
        if ($code === null) {
            return '';
        }

        return preg_replace('/\s+/', ' ', trim($code)) ?? '';
    }
}
