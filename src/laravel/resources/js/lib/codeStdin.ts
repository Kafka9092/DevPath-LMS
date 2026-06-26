export function codeNeedsStdin(code: string): boolean {
    const patterns = [
        /\bscanf\s*\(/,
        /\bcin\s*>>/,
        /\bstd::cin\b/,
        /\bgetline\s*\(/,
        /\bgets\s*\(/,
        /\bfgets\s*\(/,
        /\binput\s*\(/,
        /\breadline\s*\(/,
        /\bScanner\b/,
        /\breadLine\s*\(/,
        /\bConsole\.Read/,
        /\bfmt\.Scan/,
        /\bbufio\.NewReader/,
        /\bgets\b/,
        /\bSTDIN\b/,
    ];

    return patterns.some((pattern) => pattern.test(code));
}
