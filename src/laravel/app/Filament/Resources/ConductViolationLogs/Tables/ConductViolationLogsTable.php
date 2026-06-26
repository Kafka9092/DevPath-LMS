<?php

namespace App\Filament\Resources\ConductViolationLogs\Tables;

use App\Models\ConductViolationLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConductViolationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn (ConductViolationLog $record): string => $record->sourceLabel())
                    ->badge()
                    ->color(fn (ConductViolationLog $record): string => match ($record->source) {
                        ConductViolationLog::SOURCE_MENTOR_CHAT  => 'primary',
                        ConductViolationLog::SOURCE_HR_INTERVIEW => 'orange',
                        default                                  => 'gray',
                    }),
                TextColumn::make('category')
                    ->label('Тип')
                    ->formatStateUsing(fn (ConductViolationLog $record): string => $record->categoryLabel())
                    ->badge()
                    ->color(fn (ConductViolationLog $record): string => match ($record->category) {
                        ConductViolationLog::CATEGORY_JAILBREAK => 'danger',
                        ConductViolationLog::CATEGORY_ABUSE     => 'warning',
                        default                                 => 'gray',
                    }),
                TextColumn::make('action')
                    ->label('Реакция')
                    ->formatStateUsing(fn (ConductViolationLog $record): string => $record->actionLabel())
                    ->badge()
                    ->color(fn (ConductViolationLog $record): string => match ($record->action) {
                        ConductViolationLog::ACTION_WARN      => 'info',
                        ConductViolationLog::ACTION_TERMINATE => 'success',
                        ConductViolationLog::ACTION_BLOCK     => 'gray',
                        default                               => 'gray',
                    }),
                TextColumn::make('warning_number')
                    ->label('№')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('message_excerpt')
                    ->label('Сообщение')
                    ->limit(60)
                    ->tooltip(fn (ConductViolationLog $record): ?string => $record->message_excerpt)
                    ->wrap(),
                TextColumn::make('classifier_reason')
                    ->label('Причина (ИИ)')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('classifier_confidence')
                    ->label('Уверенность')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('context')
                    ->label('Контекст')
                    ->formatStateUsing(function (ConductViolationLog $record): string {
                        $ctx = $record->context ?? [];

                        if ($record->source === ConductViolationLog::SOURCE_MENTOR_CHAT) {
                            return ($ctx['subtopic_title'] ?? 'урок')
                                . ' (курс #' . ($ctx['course_id'] ?? '?') . ')';
                        }

                        return ($ctx['direction'] ?? '?')
                            . ' / ' . ($ctx['level'] ?? '?')
                            . ' (#'. ($ctx['interview_id'] ?? '?') . ')';
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options([
                        ConductViolationLog::SOURCE_MENTOR_CHAT  => 'Чат ментора',
                        ConductViolationLog::SOURCE_HR_INTERVIEW => 'Собеседования AI HR',
                    ]),
                SelectFilter::make('category')
                    ->label('Тип')
                    ->options([
                        ConductViolationLog::CATEGORY_JAILBREAK => 'Обход правил',
                        ConductViolationLog::CATEGORY_ABUSE     => 'Токсичность',
                    ]),
                SelectFilter::make('action')
                    ->label('Реакция')
                    ->options([
                        ConductViolationLog::ACTION_WARN      => 'Предупреждение',
                        ConductViolationLog::ACTION_BLOCK     => 'Блок чата',
                        ConductViolationLog::ACTION_TERMINATE => 'Завершение',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Записей пока нет')
            ->emptyStateDescription('Попытки обхода правил и другие нарушения общения появятся здесь автоматически.');
    }
}
