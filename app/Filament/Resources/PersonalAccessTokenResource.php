<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalAccessTokenResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'API Tokens (MCP)';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('User')
                ->options(
                    User::orderBy('name')->get()
                        ->mapWithKeys(fn ($u) => [
                            $u->id => "{$u->name} — joined {$u->created_at->format('d/m/Y')}",
                        ])
                )
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('name')
                ->label('Token name')
                ->placeholder('e.g. claude-desktop-tung')
                ->helperText('Dùng để phân biệt token của từng người / thiết bị')
                ->required(),

            Forms\Components\CheckboxList::make('abilities')
                ->label('Abilities')
                ->options([
                    'mcp:read'    => 'mcp:read — Đọc context (GET)',
                    'mcp:write'   => 'mcp:write — Tạo/sửa draft (PUT)',
                    'mcp:publish' => 'mcp:publish — Publish/activate (PATCH)',
                ])
                ->columns(1)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Token name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tokenable.name')
                    ->label('User'),

                Tables\Columns\TextColumn::make('abilities')
                    ->label('Abilities')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(fn ($record) => implode(',', $record->abilities ?? [])),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime(timezone: 'Asia/Ho_Chi_Minh')
                    ->placeholder('Never')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(timezone: 'Asia/Ho_Chi_Minh')
                    ->sortable(),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Revoke selected'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPersonalAccessTokens::route('/'),
            'create' => Pages\CreatePersonalAccessToken::route('/create'),
        ];
    }
}
