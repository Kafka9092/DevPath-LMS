const BOILERPLATE = [
    /^\s*package\s+\w+/m,
    /^\s*import\s+/m,
    /^\s*using\s+/m,
    /^\s*#include\s+/m,
    /^\s*from\s+\S+\s+import/m,
    /^\s*export\s+(default\s+)?(function|class|const|interface|type)\s/m,
    /^\s*public\s+class\s+\w+/m,
    /^\s*func\s+main\s*\(\s*\)/m,
    /^\s*\/\/\s*Ваше решение/m,
    /^\s*#\s*Ваше решение/m,
];

function stripBoilerplate(code: string): string {
    let out = code;
    for (const re of BOILERPLATE) {
        out = out.replace(re, "");
    }
    return out;
}

export function countUsefulCodeLines(code: string): number {
    const stripped = stripBoilerplate(code);
    return stripped
        .split("\n")
        .map(l => l.trim())
        .filter(l => l && !l.startsWith("//") && !l.startsWith("#") && !/^[{}();]+$/.test(l))
        .length;
}

export function hasMeaningfulCode(code: string, starterCode?: string): boolean {
    const useful = countUsefulCodeLines(code);
    if (useful >= 3) return true;
    if (useful < 2) return false;

    const norm = code.replace(/\s+/g, " ").trim();
    const starterNorm = (starterCode ?? "").replace(/\s+/g, " ").trim();
    if (!starterNorm) return true;

    return Math.abs(norm.length - starterNorm.length) > 12;
}

export function normalizeForCompare(code: string): string {
    return code.replace(/\s+/g, " ").trim();
}
