<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Jobs\VerifyCompanyJob;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Nom commercial
                TextColumn::make('name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),

                // Raison sociale
                TextColumn::make('legal_name')
                    ->label('Legal name')
                    ->searchable()
                    ->toggleable(),

                // Pays
                TextColumn::make('country')
                    ->label('Country')
                    ->sortable(),

                // SIREN
                TextColumn::make('siren')
                    ->label('SIREN')
                    ->toggleable(),

                // SIRET
                TextColumn::make('siret')
                    ->label('SIRET')
                    ->toggleable(),

                // TVA
                TextColumn::make('vat_number')
                    ->label('VAT')
                    ->toggleable(),

                // Statut avec badge coloré
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved'       => 'success',
                        'pending_review' => 'warning',
                        'rejected'       => 'danger',
                        default          => 'gray',
                    })
                    ->sortable(),

                // Date de création
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                // Date d'approbation
                TextColumn::make('approved_at')
                    ->label('Approved at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])

            ->filters([
                // Filtre par statut
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending_review' => 'Pending review',
                        'approved'       => 'Approved',
                        'rejected'       => 'Rejected',
                    ]),

                // Filtre par pays
                SelectFilter::make('country')
                    ->label('Country')
                    ->options([
                        'FR' => 'France',
                        'BE' => 'Belgique',
                        'DE' => 'Allemagne',
                        'ES' => 'Espagne',
                        'IT' => 'Italie',
                    ]),
            ])

            ->actions([
                // Bouton Approuver — visible si pas encore approuvé
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Company $record): bool => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this company?')
                    ->modalDescription('The client will gain access to professional pricing.')
                    ->action(function (Company $record): void {
                        $record->update([
                            'status'      => Company::STATUS_APPROVED,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);
                    }),

                // Bouton Rejeter — visible si pas encore rejeté
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Company $record): bool => $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->modalHeading('Reject this company?')
                    ->modalDescription('The client will lose access to professional pricing.')
                    ->action(function (Company $record): void {
                        $record->update([
                            'status' => Company::STATUS_REJECTED,
                        ]);
                    }),

                // Bouton Re-vérifier via INSEE/VIES
                Action::make('reverify')
                    ->label('Re-verify')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Re-run automatic verification?')
                    ->modalDescription('A new verification via INSEE or VIES will be triggered.')
                    ->action(function (Company $record): void {
                        $record->update(['status' => Company::STATUS_PENDING_REVIEW]);
                        VerifyCompanyJob::dispatch($record->id);
                    }),
            ])

            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            // Tri par défaut : pending_review en premier
            ->defaultSort('status', 'asc')
            ->poll('10s'); // Rafraîchit automatiquement toutes les 10s
    }
}
