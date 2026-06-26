<?php

namespace App\Service;

/**
 * Формат ответа Code Review API для внешних клиентов (VS Code и др.).
 */
class CodeReviewApiPresenter
{
  /**
   * @param  array<string, mixed>  $result
   * @param  array{language?: string|null, filename?: string|null, file_path?: string|null}  $context
   * @return array<string, mixed>
   */
  public function present(array $result, array $context = [], string $analysisMode = 'sync'): array
  {
    $payload = array_merge($result, [
      'analysis_mode' => $analysisMode,
      'context'       => $this->buildContext($context, $result),
      'diagnostics'   => $this->buildDiagnostics($result, $context),
    ]);

    if (isset($payload['ai_evaluation']) && is_array($payload['ai_evaluation'])) {
      $payload['ai_evaluation']['explained_issues'] = $this->enrichExplainedIssues(
        $payload['ai_evaluation']['explained_issues'] ?? [],
        $context,
      );
    }

    return $payload;
  }

  /**
   * @param  array<string, mixed>  $record
   * @return array<string, mixed>
   */
  public function presentHistoryItem(array $record, bool $includeCode = false): array
  {
    $ai = is_array($record['ai_evaluation'] ?? null) ? $record['ai_evaluation'] : [];

    $item = [
      'uuid'              => $record['uuid'] ?? null,
      'detected_language' => $record['detected_language'] ?? null,
      'editor_language'   => $record['editor_language'] ?? null,
      'overall_score'     => $record['overall_score'] ?? null,
      'summary'           => $ai['summary'] ?? null,
      'grade_label'       => $ai['grade_label'] ?? null,
      'issues_count'      => count($record['issues'] ?? []),
      'created_at'        => $record['created_at'] ?? null,
    ];

    if ($includeCode) {
      $item['code'] = $record['code'] ?? null;
    }

    return $item;
  }

  /**
   * @param  array{language?: string|null, filename?: string|null, file_path?: string|null}  $context
   * @param  array<string, mixed>  $result
   * @return array{language: ?string, filename: ?string, file_path: ?string, editor_language: ?string}
   */
  private function buildContext(array $context, array $result): array
  {
    return [
      'language'         => $context['language'] ?? ($result['detected_language'] ?? null),
      'filename'         => $context['filename'] ?? null,
      'file_path'        => $context['file_path'] ?? null,
      'editor_language'  => $result['editor_language'] ?? null,
    ];
  }

  /**
   * @param  array<string, mixed>  $result
   * @param  array{file_path?: string|null, filename?: string|null}  $context
   * @return list<array<string, mixed>>
   */
  private function buildDiagnostics(array $result, array $context): array
  {
    if (($result['is_code'] ?? true) === false) {
      return [];
    }

    $explained = $result['ai_evaluation']['explained_issues'] ?? $result['issues'] ?? [];
    $diagnostics = [];

    foreach ($explained as $issue) {
      if (! is_array($issue)) {
        continue;
      }

      $line = isset($issue['line']) ? (int) $issue['line'] : null;
      if ($line === null || $line < 1) {
        continue;
      }

      $severity = $this->mapSeverity((string) ($issue['severity'] ?? 'MAJOR'));
      $message = trim((string) ($issue['explanation'] ?? $issue['sonar_message'] ?? $issue['message'] ?? ''));

      if ($message === '') {
        continue;
      }

      $diagnostics[] = [
        'line'                => $line,
        'range'               => [
          'start_line'   => $line,
          'end_line'     => $line,
          'start_column' => 1,
          'end_column'   => 1,
        ],
        'severity'            => $severity,
        'vscode_severity'     => $this->mapVsCodeSeverity($severity),
        'source'              => 'devpath',
        'code'                => $issue['rule'] ?? $issue['type'] ?? null,
        'message'             => $message,
        'sonar_message'       => $issue['sonar_message'] ?? $issue['message'] ?? null,
        'how_to_fix'          => $issue['how_to_fix'] ?? null,
        'file_path'           => $context['file_path'] ?? null,
        'filename'            => $context['filename'] ?? null,
      ];
    }

    return $diagnostics;
  }

  /**
   * @param  list<array<string, mixed>>  $issues
   * @param  array{file_path?: string|null, filename?: string|null}  $context
   * @return list<array<string, mixed>>
   */
  private function enrichExplainedIssues(array $issues, array $context): array
  {
    return array_map(function (array $issue) use ($context): array {
      $line = isset($issue['line']) ? (int) $issue['line'] : null;
      $severity = $this->mapSeverity((string) ($issue['severity'] ?? 'MAJOR'));

      $issue['severity'] = $severity;
      $issue['vscode_severity'] = $this->mapVsCodeSeverity($severity);

      if ($line !== null && $line > 0) {
        $issue['range'] = [
          'start_line'   => $line,
          'end_line'     => $line,
          'start_column' => 1,
          'end_column'   => 1,
        ];
      }

      if (! empty($context['file_path'])) {
        $issue['file_path'] = $context['file_path'];
      }

      if (! empty($context['filename'])) {
        $issue['filename'] = $context['filename'];
      }

      return $issue;
    }, $issues);
  }

  private function mapSeverity(string $sonarSeverity): string
  {
    return match (strtoupper($sonarSeverity)) {
      'BLOCKER', 'CRITICAL' => 'error',
      'MAJOR'             => 'warning',
      'MINOR', 'INFO'     => 'information',
      default             => 'warning',
    };
  }

  private function mapVsCodeSeverity(string $severity): int
  {
    return match ($severity) {
      'error'       => 0,
      'warning'     => 1,
      'information' => 2,
      default       => 1,
    };
  }
}
