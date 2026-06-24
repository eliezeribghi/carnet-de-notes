<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('legal_name')
                    ->placeholder('-'),
                TextEntry::make('vat_number')
                    ->placeholder('-'),
                TextEntry::make('siren')
                    ->placeholder('-'),
                TextEntry::make('siret')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('address_line1')
                    ->placeholder('-'),
                TextEntry::make('address_line2')
                    ->placeholder('-'),
                TextEntry::make('postal_code')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                TextEntry::make('shipping_address')
                    ->placeholder('-'),
                TextEntry::make('shipping_city')
                    ->placeholder('-'),
                TextEntry::make('shipping_zip')
                    ->placeholder('-'),
                TextEntry::make('shipping_country')
                    ->placeholder('-'),
                TextEntry::make('billing_address')
                    ->placeholder('-'),
                TextEntry::make('billing_city')
                    ->placeholder('-'),
                TextEntry::make('billing_zip')
                    ->placeholder('-'),
                TextEntry::make('billing_country')
                    ->placeholder('-'),
                TextEntry::make('country'),
                TextEntry::make('status'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
