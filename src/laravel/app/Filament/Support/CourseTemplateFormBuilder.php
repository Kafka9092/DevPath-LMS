<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class CourseTemplateFormBuilder
{
    /**
     * @var array<string, string>
     */
    public const LEVEL_LABELS = [
        'beginner' => 'Beginner',
        'junior' => 'Junior',
        'middle' => 'Middle',
        'senior' => 'Senior',
    ];

    public static function levelTab(string $levelKey): Tab
    {
        $label = self::LEVEL_LABELS[$levelKey] ?? ucfirst($levelKey);

        return Tab::make($label)
            ->schema([
                Section::make('Курс')
                    ->schema([
                        TextInput::make("{$levelKey}.title")
                            ->label('Название курса')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Textarea::make("{$levelKey}.description")
                            ->label('Описание курса')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Структура курса')
                    ->schema([
                        Repeater::make("{$levelKey}.modules")
                            ->label('Модули')
                            ->schema([
                                TextInput::make('module_number')
                                    ->hidden(),
                                TextInput::make('title')
                                    ->label('Модуль')
                                    ->required()
                                    ->maxLength(500)
                                    ->default('Новый модуль'),
                                Textarea::make('description')
                                    ->label('Описание модуля')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Repeater::make('themes')
                                    ->label('Темы')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Тема')
                                            ->required()
                                            ->maxLength(500)
                                            ->default('Новая тема'),
                                        Repeater::make('subtopics')
                                            ->label('Подтемы')
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Подтема')
                                                    ->required()
                                                    ->maxLength(500)
                                                    ->default('Новая подтема'),
                                            ])
                                            ->defaultItems(1)
                                            ->addActionLabel('Добавить подтему')
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Новая подтема'),
                                    ])
                                    ->defaultItems(1)
                                    ->addActionLabel('Добавить тему')
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Новая тема'),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Добавить модуль')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Новый модуль'),
                    ]),
            ]);
    }
}
