<?php

namespace Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages;

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TourResource::mutateFormData($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
