<?php

namespace Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages;

use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditEvents extends ListRecords
{
    protected static string $resource = AuditEventResource::class;
}
