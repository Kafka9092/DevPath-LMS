<?php

namespace App\Service;

/** Приводит JSON шаблона к одному виду и считает hash — чтобы не плодить дубликаты курсов. */
class CourseTemplateStructureService
{
    /**
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    /** Нормализуем порядок модулей/тем/подтопиков из админки. */
    public function normalize(array $structure): array
    {
        $normalized = [
            'title' => trim((string) ($structure['title'] ?? '')),
            'description' => trim((string) ($structure['description'] ?? '')),
            'modules' => [],
        ];

        foreach ($structure['modules'] ?? [] as $moduleIndex => $module) {
            if (! is_array($module)) {
                continue;
            }

            $themes = [];

            foreach ($module['themes'] ?? [] as $themeIndex => $theme) {
                if (! is_array($theme)) {
                    continue;
                }

                $subtopics = [];

                foreach ($theme['subtopics'] ?? [] as $subtopicIndex => $subtopic) {
                    if (! is_array($subtopic)) {
                        continue;
                    }

                    $normalizedSubtopic = [
                        'title' => trim((string) ($subtopic['title'] ?? '')),
                        'order' => (int) ($subtopic['order'] ?? ($subtopicIndex + 1)),
                    ];

                    if (array_key_exists('task', $subtopic)) {
                        $normalizedSubtopic['task'] = $subtopic['task'];
                    }

                    if (array_key_exists('theory', $subtopic)) {
                        $normalizedSubtopic['theory'] = $subtopic['theory'];
                    }

                    $subtopics[] = $normalizedSubtopic;
                }

                $themes[] = [
                    'title' => trim((string) ($theme['title'] ?? '')),
                    'order' => (int) ($theme['order'] ?? ($themeIndex + 1)),
                    'subtopics' => $subtopics,
                ];
            }

            $normalized['modules'][] = [
                'module_number' => $moduleIndex + 1,
                'title' => trim((string) ($module['title'] ?? '')),
                'description' => trim((string) ($module['description'] ?? '')),
                'themes' => $themes,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    public function hash(array $structure): string
    {
        $normalized = $this->normalize($structure);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function legacyCourseHash(int $courseId): string
    {
        return 'legacy:' . $courseId;
    }
}
