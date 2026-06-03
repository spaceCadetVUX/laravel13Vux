<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity Log';

    // Read-only resource — no form needed
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->columns([
                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? class_basename($state)
                        : '—'
                    )
                    ->color('gray'),

                TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('System')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Log Channel')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->filter()
                        ->toArray()
                    ),

                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null,
                                fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when($data['until'] ?? null,
                                fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading('Activity Detail')
                    ->infolist(fn (Schema $schema): Schema => $schema->schema([
                        Section::make('Info')->schema([
                            TextEntry::make('log_name')->label('Log')->badge()->color('primary'),
                            TextEntry::make('description')->label('Event')->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'created' => 'success',
                                    'updated' => 'warning',
                                    'deleted' => 'danger',
                                    default   => 'gray',
                                }),
                            TextEntry::make('subject_type')->label('Subject')
                                ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                            TextEntry::make('causer.name')->label('By')->placeholder('System'),
                            TextEntry::make('created_at')->label('When')->dateTime(),
                        ])->columns(3),

                        Section::make('Old values')
                            ->schema([
                                KeyValueEntry::make('attribute_changes.old')->label('')->placeholder('—'),
                            ])
                            ->visible(fn (Activity $record): bool => filled($record->attribute_changes['old'] ?? null)),

                        Section::make('New values')
                            ->schema([
                                KeyValueEntry::make('attribute_changes.attributes')->label('')->placeholder('—'),
                            ])
                            ->visible(fn (Activity $record): bool => filled($record->attribute_changes['attributes'] ?? null)),
                    ])),
            ])
            ->bulkActions([
                DeleteBulkAction::make()->label('Xóa đã chọn'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
