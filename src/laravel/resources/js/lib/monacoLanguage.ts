export function monacoLanguage(direction: string): string {
    const map: Record<string, string> = {
        php: "php",
        python: "python",
        javascript: "javascript",
        typescript: "typescript",
        java: "java",
        "c++": "cpp",
        "c#": "csharp",
        go: "go",
        ruby: "ruby",
    };

    return map[direction.toLowerCase()] ?? "plaintext";
}
