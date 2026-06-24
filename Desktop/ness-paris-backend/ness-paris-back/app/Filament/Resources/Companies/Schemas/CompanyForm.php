<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('legal_name'),
                TextInput::make('vat_number'),
                TextInput::make('siren'),
                TextInput::make('siret'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('address_line1'),
                TextInput::make('address_line2'),
                TextInput::make('postal_code'),
                TextInput::make('city'),
                TextInput::make('shipping_address'),
                TextInput::make('shipping_city'),
                TextInput::make('shipping_zip'),
                TextInput::make('shipping_country'),
                TextInput::make('billing_address'),
                TextInput::make('billing_city'),
                TextInput::make('billing_zip'),
                TextInput::make('billing_country'),
                TextInput::make('country')
                    ->required()
                    ->default('FR'),
                TextInput::make('status')
                    ->required()
                    ->default('pending_review'),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('approved_at'),
                TextInput::make('approved_by')
                    ->numeric(),
            ]);
    }
}
