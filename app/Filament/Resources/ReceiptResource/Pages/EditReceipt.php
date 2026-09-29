<?php

namespace App\Filament\Resources\ReceiptResource\Pages;

use App\Filament\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Services\MaterialReceiptService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReceipt extends EditRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('post')
                ->label('Провести')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Провести документ поступления?')
                ->modalDescription('После проведения по каждой строке будет создана партия на складе, а сам документ станет недоступен для редактирования.')
                ->action(function () {
                    try {
                        app(MaterialReceiptService::class)->postReceipt($this->record);

                        Notification::make()
                            ->title('Документ проведён')
                            ->body('Партии материалов созданы и добавлены на склад.')
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('🚨 Провести не удалось')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
