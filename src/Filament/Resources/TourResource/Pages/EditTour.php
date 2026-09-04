<?php

namespace Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages;

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TourResource::mutateFormData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            TourResource::previewAction(),
            TourResource::resetSeenStateAction(),
            DeleteAction::make(),
        ];
    }
}
