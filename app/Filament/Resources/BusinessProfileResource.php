<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessProfileResource\Pages;
use App\Models\BusinessProfile;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BusinessProfileResource extends Resource
{
    protected static ?string $model = BusinessProfile::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static \UnitEnum|string|null $navigationGroup = 'Setting';

    protected static ?string $navigationLabel = 'Business Profile';

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Tabs')
                ->tabs([

                    // ── Identity ──────────────────────────────────────────────
                    Tab::make('Identity')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Business Name')
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('legal_name')
                                ->label('Legal Name')
                                ->placeholder('Company Ltd.'),

                            Forms\Components\TextInput::make('tagline')
                                ->label('Tagline')
                                ->placeholder('Short slogan'),

                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(4)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('logo_path')
                                ->label('Logo Path / URL')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('currency')
                                ->label('Currency')
                                ->default('VND'),

                            Forms\Components\TextInput::make('founded_year')
                                ->label('Founded Year')
                                ->numeric()
                                ->minValue(1800)
                                ->maxValue(now()->year),

                            Forms\Components\TextInput::make('vat_number')
                                ->label('VAT / Tax Number'),
                        ])
                        ->columns(2),

                    // ── Contact ───────────────────────────────────────────────
                    Tab::make('Contact & Location')
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email(),

                            Forms\Components\TextInput::make('phone')
                                ->label('Phone')
                                ->tel(),

                            Forms\Components\Textarea::make('address_line')
                                ->label('Address')
                                ->rows(2)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('city')
                                ->label('City'),

                            Forms\Components\TextInput::make('state')
                                ->label('State / Province'),

                            Forms\Components\TextInput::make('country')
                                ->label('Country'),

                            Forms\Components\TextInput::make('postal_code')
                                ->label('Postal Code'),

                            Forms\Components\TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric()
                                ->placeholder('10.7769'),

                            Forms\Components\TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric()
                                ->placeholder('106.7009'),
                        ])
                        ->columns(2),

                    // ── Online ────────────────────────────────────────────────
                    Tab::make('Online Presence')
                        ->schema([
                            Forms\Components\KeyValue::make('social_links')
                                ->label('Social Links')
                                ->keyLabel('Platform')
                                ->valueLabel('URL')
                                ->keyPlaceholder('facebook')
                                ->valuePlaceholder('https://facebook.com/...')
                                ->reorderable()
                                ->columnSpanFull(),

                            Forms\Components\KeyValue::make('business_hours')
                                ->label('Business Hours')
                                ->keyLabel('Day')
                                ->valueLabel('Hours')
                                ->keyPlaceholder('Monday')
                                ->valuePlaceholder('09:00 – 18:00')
                                ->reorderable()
                                ->columnSpanFull(),

                            Forms\Components\KeyValue::make('extra')
                                ->label('Extra Info')
                                ->keyLabel('Key')
                                ->valueLabel('Value')
                                ->reorderable()
                                ->helperText('Any additional info (e.g. registration number, certifications).')
                                ->columnSpanFull(),
                        ]),

                ])
                ->columnSpanFull(),
        ]);
    }

    // ── Table (not used — singleton) ──────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\EditBusinessProfile::route('/'),
        ];
    }
}
