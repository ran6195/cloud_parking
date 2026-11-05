<?php

namespace App\Engine\Filament\Resources\Reservations\Pages;

use App\Engine\Application\CancellationService;
use App\Engine\Application\RefundService;
use App\Engine\Application\ReservationNotifier;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Shared\EngineException;
use App\Engine\Filament\Resources\Reservations\ReservationResource;
use App\Engine\Models\Reservation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Dettaglio con le azioni del gestore: annullare, rimborsare, concludere, reinviare la conferma.
 */
class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('resend')
                ->label('Reinvia conferma')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->reservation()->status, [ReservationStatus::Confirmed, ReservationStatus::Paid], true))
                ->action(function (ReservationNotifier $notifier): void {
                    $sent = $notifier->confirmed($this->reservation());
                    $this->notify($sent, 'Conferma reinviata al cliente.', 'Invio non riuscito: controlla il log.');
                }),

            Action::make('complete')
                ->label('Segna come conclusa')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->reservation()->status->canTransitionTo(ReservationStatus::Completed))
                ->action(function (): void {
                    $this->reservation()->transitionTo(ReservationStatus::Completed);
                    $this->notify(true, 'Prenotazione conclusa.');
                }),

            Action::make('refundAutomatically')
                ->label('Rimborsa su PayPal')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Rimborsa '.number_format(((int) ($this->reservation()->refund['amount_cents'] ?? 0)) / 100, 2, ',', '.').' € sul conto PayPal del cliente.')
                ->visible(fn () => $this->refundPending())
                ->action(function (RefundService $refunds): void {
                    $refunds->refundAutomatically($this->reservation());
                    $refunded = RefundService::status($this->reservation()->refresh()) === RefundService::REFUNDED;
                    $this->notify($refunded, 'Rimborso eseguito su PayPal.', 'Rimborso non riuscito: '.($this->reservation()->refund['error'] ?? ''));
                }),

            Action::make('markRefunded')
                ->label('Rimborsata a mano')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->schema([Textarea::make('note')->label('Nota')->rows(2)->maxLength(500)])
                ->visible(fn () => $this->refundPending())
                ->action(function (array $data, RefundService $refunds): void {
                    $refunds->markRefunded($this->reservation(), $data['note'] ?? null);
                    $this->notify(true, 'Rimborso segnato come eseguito.');
                }),

            Action::make('cancel')
                ->label('Annulla prenotazione')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->reservation()->status->canTransitionTo(ReservationStatus::Cancelled))
                ->modalDescription(fn () => $this->reservation()->paid_at !== null
                    ? 'È stata pagata online: il rimborso verrà registrato (ed eseguito, se i rimborsi automatici sono attivi).'
                    : 'Il posto torna disponibile.')
                ->schema([
                    Textarea::make('reason')->label('Motivo')->rows(2)->maxLength(500),
                    Toggle::make('notify')->label('Avvisa il cliente via email')->default(true),
                ])
                ->action(function (array $data, CancellationService $cancellations): void {
                    try {
                        $cancellations->cancelByManager($this->reservation(), $data['reason'] ?? null, (bool) ($data['notify'] ?? true));
                    } catch (EngineException $e) {
                        $this->notify(false, '', $e->getMessage());

                        return;
                    }
                    $this->notify(true, 'Prenotazione annullata.');
                }),
        ];
    }

    private function reservation(): Reservation
    {
        /** @var Reservation */
        return $this->getRecord();
    }

    private function refundPending(): bool
    {
        return in_array(RefundService::status($this->reservation()), [RefundService::REQUESTED, RefundService::FAILED], true);
    }

    private function notify(bool $success, string $ok, string $failure = ''): void
    {
        $this->reservation()->refresh();
        $this->refreshFormData([]);

        Notification::make()
            ->title($success ? $ok : $failure)
            ->{$success ? 'success' : 'danger'}()
            ->send();
    }
}
