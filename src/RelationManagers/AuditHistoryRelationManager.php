<?php

namespace Digit7s\FilamentAuditToolkit\RelationManagers;

use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Support\DiffFormatter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class AuditHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'auditHistory';

    protected static ?string $title = 'Audit history';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return app(AuditAuthorization::class)->canViewSubjectHistory($ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->sortable(),
                TextColumn::make('actor_reference')
                    ->label('Actor')
                    ->state(fn (AuditEvent $record): string => $this->referenceLabel($record, 'actor')),
                TextColumn::make('original_actor_reference')
                    ->label('Original actor')
                    ->state(fn (AuditEvent $record): string => $this->originalActorLabel($record)),
                TextColumn::make('source')->placeholder('—'),
                TextColumn::make('occurred_at')
                    ->dateTime('Y-m-d H:i:s T')
                    ->description(fn (AuditEvent $record): string => $record->occurred_at?->diffForHumans() ?? '—')
                    ->sortable(),
                TextColumn::make('changes')
                    ->label('Before / after')
                    ->state(fn (AuditEvent $record): string => $this->displayChanges($record))
                    ->wrap()
                    ->limit(240),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function referenceLabel(AuditEvent $record, string $relation): string
    {
        $type = $record->{$relation.'_type'};
        $id = $record->{$relation.'_id'};

        return $type === null || $id === null
            ? 'Anonymous / system'
            : class_basename($type).' #'.$id;
    }

    private function displayChanges(AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewRawValues($record)) {
            return '[REDACTED]';
        }

        return DiffFormatter::format($record->old_values ?? [], $record->new_values ?? []);
    }

    private function originalActorLabel(AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewOriginalActor($record)) {
            return '—';
        }

        return $this->referenceLabel($record, 'original_actor');
    }
}
