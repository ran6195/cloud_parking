<?php

namespace App\Engine\Filament\Resources\BlockedDates\Pages;

use App\Engine\Filament\Resources\BlockedDates\BlockedDateResource;
use App\Engine\Models\BlockedDate;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedDates extends ManageRecords
{
    protected static string $resource = BlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Blocca un giorno'),
            Action::make('blockPeriod')
                ->label('Blocca un periodo')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    DatePicker::make('from')->label('Dal')->required(),
                    DatePicker::make('to')->label('Al (incluso)')->required()->afterOrEqual('from'),
                    TextInput::make('reason')->label('Motivo')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $added = 0;
                    $day = CarbonImmutable::parse((string) $data['from']);
                    $to = CarbonImmutable::parse((string) $data['to']);

                    for (; $day->lte($to) && $added < 366; $day = $day->addDay()) {
                        $added += BlockedDate::query()->firstOrCreate(['date' => $day->format('Y-m-d')], ['reason' => $data['reason'] ?? null])->wasRecentlyCreated ? 1 : 0;
                    }

                    Notification::make()->success()->title("Giorni bloccati: {$added}.")->send();
                }),
        ];
    }
}
