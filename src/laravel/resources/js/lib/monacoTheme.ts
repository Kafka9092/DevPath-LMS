import type { Monaco } from '@monaco-editor/react';

export const DEVPATH_MONACO_DARK = 'devpath-black';

let themesRegistered = false;

export function defineDevpathMonacoThemes(monaco: Monaco): void {
    if (themesRegistered) {
        return;
    }

    monaco.editor.defineTheme(DEVPATH_MONACO_DARK, {
        base: 'vs-dark',
        inherit: true,
        rules: [],
        colors: {
            'editor.background': '#000000',
            'editor.lineHighlightBackground': '#0a0a0a',
            'editorGutter.background': '#000000',
            'editorWidget.background': '#0a0a0a',
            'editorSuggestWidget.background': '#0a0a0a',
            'peekViewEditor.background': '#000000',
            'peekViewResult.background': '#0a0a0a',
            'minimap.background': '#000000',
        },
    });

    themesRegistered = true;
}

export function monacoThemeForAppTheme(resolved: 'light' | 'dark'): string {
    return resolved === 'dark' ? DEVPATH_MONACO_DARK : 'vs-light';
}
