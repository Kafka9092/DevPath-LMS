import Editor, { type Monaco } from '@monaco-editor/react';
import { useTheme } from '@/contexts/ThemeProvider';
import { defineDevpathMonacoThemes, monacoThemeForAppTheme } from '@/lib/monacoTheme';
import { ComponentProps } from 'react';

type Props = ComponentProps<typeof Editor>;

export function ThemedMonacoEditor({
    beforeMount,
    theme: _ignoredTheme,
    ...props
}: Props) {
    const { resolvedTheme } = useTheme();

    const handleBeforeMount = (monaco: Monaco) => {
        defineDevpathMonacoThemes(monaco);
        beforeMount?.(monaco);
    };

    return (
        <Editor
            {...props}
            theme={monacoThemeForAppTheme(resolvedTheme)}
            beforeMount={handleBeforeMount}
        />
    );
}
