<?php

namespace Tests\Unit;

use App\Service\CodeReviewApiPresenter;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CodeReviewApiPresenterTest extends TestCase
{
    private CodeReviewApiPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenter = new CodeReviewApiPresenter();
    }

    #[TestDox('Presenter добавляет diagnostics для VS Code')]
    public function test_present_builds_diagnostics(): void
    {
        $result = $this->presenter->present([
            'uuid'              => '550e8400-e29b-41d4-a716-446655440000',
            'is_code'           => true,
            'detected_language' => 'php',
            'editor_language'   => 'php',
            'overall_score'     => 70,
            'issues'            => [],
            'ai_evaluation'     => [
                'summary'          => 'Есть замечания.',
                'explained_issues' => [
                    [
                        'line'          => 3,
                        'severity'      => 'MAJOR',
                        'sonar_message' => 'Unused variable',
                        'explanation'   => 'Переменная не используется.',
                        'how_to_fix'    => 'Удалите переменную.',
                    ],
                ],
            ],
        ], [
            'filename'  => 'test.php',
            'file_path' => '/src/test.php',
        ]);

        $this->assertSame('sync', $result['analysis_mode']);
        $this->assertCount(1, $result['diagnostics']);
        $this->assertSame(3, $result['diagnostics'][0]['line']);
        $this->assertSame('warning', $result['diagnostics'][0]['severity']);
        $this->assertSame(1, $result['diagnostics'][0]['vscode_severity']);
        $this->assertSame('/src/test.php', $result['diagnostics'][0]['file_path']);
    }
}
