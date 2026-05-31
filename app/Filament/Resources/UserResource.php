<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $form): Schema
    {
        $isEdit = $form->getOperation() === 'edit';

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Tên')
                ->required(),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\Select::make('role')
                ->label('Role')
                ->options(collect(UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => ucfirst($r->value)]))
                ->default(UserRole::Admin->value)
                ->native(false)
                ->required(),

            Forms\Components\TextInput::make('password')
                ->label($isEdit ? 'Mật khẩu mới (để trống = giữ nguyên)' : 'Mật khẩu')
                ->password()
                ->revealable()
                ->required(!$isEdit)
                ->minLength(8)
                ->dehydrated(fn ($state) => filled($state))
                ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        UserRole::Admin    => 'warning',
                        UserRole::Customer => 'gray',
                        default            => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof UserRole ? ucfirst($state->value) : $state),

                Tables\Columns\TextColumn::make('tokens_count')
                    ->label('Tokens')
                    ->getStateUsing(fn ($record) => $record->tokens()->count())
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime(timezone: 'Asia/Ho_Chi_Minh')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),

                DeleteAction::make()
                    ->label('Delete & Revoke tokens')
                    ->hidden(fn ($record) => $record->id === auth()->id())
                    ->before(function ($record) {
                        $record->tokens()->delete();
                    })
                    ->successNotificationTitle('User deleted — all tokens revoked'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->id === auth()->id()) {
                                    Notification::make()
                                        ->title('Không thể xóa tài khoản của chính mình')
                                        ->warning()
                                        ->send();
                                    return false;
                                }
                                $record->tokens()->delete();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
