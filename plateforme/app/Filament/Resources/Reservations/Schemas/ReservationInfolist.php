<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use App\Support\Montant;
use App\Support\Telephone;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $fcfa = fn ($state) => Montant::fcfa($state);

        return $schema
            ->columns(3)
            ->components([
                Section::make('Réservation')
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('reference')->label('Référence')->weight('bold')->color('primary')->copyable()->copyMessage('Référence copiée'),
                        TextEntry::make('statut')->label('Statut')->badge(),
                        TextEntry::make('logement.titre')->label(fn (Reservation $record) => ucfirst($record->logement?->mot() ?? 'logement')),
                        TextEntry::make('formule')->label('Formule'),
                        TextEntry::make('debut')->label('Début')->dateTime('l j F Y à H:i'),
                        TextEntry::make('fin')->label('Fin')->dateTime('l j F Y à H:i'),
                        TextEntry::make('creneau.nom')->label('Créneau')->placeholder('—'),
                        TextEntry::make('voyageurs')->label('Voyageurs')->hidden(fn (Reservation $record) => (bool) $record->logement?->estSalle()),
                        TextEntry::make('debut_initial')->label('Date prévue au départ')->dateTime('d/m/Y H:i')->placeholder('Jamais déplacée'),
                        TextEntry::make('deplaceePar.name')->label('Déplacée par')->placeholder('—'),
                    ]),
                Section::make('Client')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('client.name')->label('Nom'),
                        TextEntry::make('whatsapp')->label('WhatsApp')
                            ->state(fn ($record) => $record->whatsapp ?: $record->client?->whatsapp)
                            ->formatStateUsing(fn (?string $state) => Telephone::lisibleInternational($state))
                            ->url(fn ($record) => Telephone::lienWhatsapp($record->whatsapp ?: $record->client?->whatsapp), shouldOpenInNewTab: true)
                            ->icon('heroicon-o-chat-bubble-left-right')->placeholder('—'),
                        TextEntry::make('client.telephone')->label('Téléphone')->formatStateUsing(fn (?string $state) => $state ? Telephone::lisible($state) : null)->placeholder('—'),
                        TextEntry::make('client.email')->label('Email')->placeholder('—'),
                    ]),
                Section::make('Montants')
                    ->columns(3)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('montant_location')->label('Location')->formatStateUsing($fcfa),
                        TextEntry::make('montant_services')->label('Services')->formatStateUsing($fcfa),
                        TextEntry::make('montant_commission')->label('Commission VIVI')
                            ->formatStateUsing(fn ($state, $record) => Montant::fcfa($state).' ('.rtrim(rtrim((string) $record->taux_commission, '0'), '.').' %)'),
                        TextEntry::make('montant_marketing')->label('Part marketing')
                            ->visible(fn ($record) => $record->montant_marketing > 0)
                            ->formatStateUsing($fcfa)
                            ->helperText(fn ($record) => $record->agent_id ? 'Versée à l’agent '.$record->loadMissing('agent')->agent?->name : 'Sans agent : revient à VIVI'),
                        TextEntry::make('montant_gestion')->label('Gestion VIVI')
                            ->visible(fn ($record) => $record->montant_gestion > 0)
                            ->formatStateUsing($fcfa)
                            ->helperText(fn ($record) => 'Retirée de la part du propriétaire : '.$record->gestion_type?->libelleValeur((float) $record->gestion_valeur)),
                        TextEntry::make('montant_total')->label('Total')->formatStateUsing($fcfa)->weight('bold'),
                        TextEntry::make('montant_bonus')->label('Payé avec le bonus')->formatStateUsing($fcfa)
                            ->visible(fn ($record) => $record->montant_bonus > 0)
                            ->helperText(fn ($record) => $record->bonus_rendu > 0 ? Montant::fcfa($record->bonus_rendu).' rendus au client à l’annulation' : 'À la charge de VIVI'),
                        TextEntry::make('services_beneficiaire')->label('Services versés à')
                            ->formatStateUsing(fn ($state, $record) => match ($state) {
                                'vivi' => 'VIVI',
                                'mixte' => 'Partagés : '.Montant::fcfa($record->montant_services_proprietaire).' au propriétaire',
                                default => 'Propriétaire',
                            }),
                        TextEntry::make('montant_rembourse')->label('Remboursé')->formatStateUsing($fcfa)->placeholder('—'),
                    ]),
                Section::make('Accès')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('code_secours')->label('Code de secours')->weight('bold')->size(TextSize::Large)->copyable(),
                        TextEntry::make('scanne_le')->label('Séjour démarré le')->dateTime('d/m/Y H:i')->placeholder('Pas encore scanné'),
                        TextEntry::make('scannePar.name')->label('Scanné par')->placeholder('—'),
                    ]),
                Section::make('Services à la carte')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('services')
                            ->hiddenLabel()
                            ->columns(4)
                            ->placeholder('Aucun service')
                            ->schema([
                                TextEntry::make('nom')->label('Service'),
                                TextEntry::make('quantite')->label('Quantité'),
                                TextEntry::make('prix_unitaire')->label('Prix unitaire')->formatStateUsing($fcfa),
                                TextEntry::make('montant')->label('Montant')->formatStateUsing($fcfa),
                            ]),
                    ]),
                Section::make('Paiements')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('paiements')
                            ->hiddenLabel()
                            ->columns(5)
                            ->placeholder('Aucun paiement')
                            ->schema([
                                TextEntry::make('moyen')->label('Moyen')->placeholder('—'),
                                TextEntry::make('montant')->label('Montant')->formatStateUsing($fcfa),
                                TextEntry::make('statut')->label('Statut')->badge(),
                                TextEntry::make('reference_passerelle')->label('Réf. FedaPay')->weight('bold')->color('primary')->copyable()->placeholder('—'),
                                TextEntry::make('paye_le')->label('Payé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                            ]),
                    ]),
                Section::make('Annulation')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->annulee_le !== null)
                    ->schema([
                        TextEntry::make('annulee_le')->label('Annulée le')->dateTime('d/m/Y H:i'),
                        TextEntry::make('motif_annulation')->label('Motif')->placeholder('—'),
                        TextEntry::make('montant_rembourse')->label('Remboursement (hors commission)')->formatStateUsing($fcfa),
                        TextEntry::make('rembourse_le')->label('Remboursement versé')
                            ->formatStateUsing(fn ($state, $record) => $state->format('d/m/Y H:i').' · '.$record->mode_remboursement
                                .($record->reference_remboursement ? ' · '.$record->reference_remboursement : ''))
                            ->placeholder(fn ($record) => $record->montant_rembourse > 0 ? 'À verser (Finances › Remboursements clients)' : '—'),
                    ]),
            ]);
    }
}
