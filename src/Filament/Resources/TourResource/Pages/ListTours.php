<?php

namespace Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages;

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
