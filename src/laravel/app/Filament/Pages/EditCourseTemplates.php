<?php

namespace App\Filament\Pages;

use App\Filament\Support\CourseTemplateFormBuilder;
use App\Models\CourseTemplate;
use App\Models\Level;
use App\Service\CourseTemplateStructureService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class EditCourseTemplates extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Редактировать шаблоны курсов';

    protected static ?string $title = 'Редактирование шаблонов курсов';

    protected static ?string $slug = 'edit-course-templates';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Курсы';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($this->loadTemplateData());
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isMethodist();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();

            foreach (array_keys(CourseTemplateFormBuilder::LEVEL_LABELS) as $levelKey) {
                if (! isset($data[$levelKey])) {
                    continue;
                }

                $level = Level::query()->where('name', $levelKey)->first();

                if (! $level) {
                    continue;
                }

                $structureService = app(CourseTemplateStructureService::class);
                $structure = $structureService->normalize($data[$levelKey]);

                CourseTemplate::query()->updateOrCreate(
                    ['level_id' => $level->id],
                    [
                        'structure' => $structure,
                        'structure_hash' => $structureService->hash($structure),
                    ],
                );
            }

            $this->commitDatabaseTransaction();
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        Notification::make()
            ->success()
            ->title('Шаблоны курсов сохранены')
            ->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Уровни')
                    ->tabs(
                        collect(array_keys(CourseTemplateFormBuilder::LEVEL_LABELS))
                            ->map(fn (string $levelKey): \Filament\Schemas\Components\Tabs\Tab => CourseTemplateFormBuilder::levelTab($levelKey))
                            ->all(),
                    )
                    ->persistTabInQueryString('level'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::Start;
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? parent::getTitle();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function loadTemplateData(): array
    {
        $data = [];

        foreach (array_keys(CourseTemplateFormBuilder::LEVEL_LABELS) as $levelKey) {
            $level = Level::query()
                ->with('courseTemplate')
                ->where('name', $levelKey)
                ->first();

            $data[$levelKey] = $level?->courseTemplate?->structure ?? [
                'title' => '',
                'description' => '',
                'modules' => [],
            ];
        }

        return $data;
    }
}
