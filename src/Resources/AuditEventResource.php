<?php

namespace Digit7s\FilamentAuditToolkit\Resources;

use Digit7s\AuditToolkit\Contracts\AuditQuery;
use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages\ListAuditEvents;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages\ViewAuditEvent;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Support\DiffFormatter;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'event';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-audit-toolkit.navigation_group') ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-audit-toolkit.navigation_sort') ?? parent::getNavigationSort();
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof AuditEvent
            ? self::eventLabel((string) $record->event)
            : parent::getRecordTitle($record);
    }

    public static function canViewAny(): bool
    {
        return app(AuditAuthorization::class)->canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof AuditEvent
            && app(AuditAuthorization::class)->canView($record);
    }

    /**
     * @return Builder<AuditEvent>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = app(AuditQuery::class)->newQuery();

        if (! static::canViewAny()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')
                    ->label('Event')
                    ->formatStateUsing(fn (string $state): string => self::eventLabel($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('actor_reference')
                    ->label('Actor')
                    ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'actor')),
                TextColumn::make('original_actor_reference')
                    ->label('Original actor')
                    ->state(fn (AuditEvent $record): string => self::originalActorLabel($record)),
                TextColumn::make('subject_reference')
                    ->label('Subject')
                    ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'subject')),
                TextColumn::make('source')
                    ->placeholder('—'),
                TextColumn::make('occurred_at')
                    ->dateTime('Y-m-d H:i:s T')
                    ->description(fn (AuditEvent $record): string => $record->occurred_at?->diffForHumans() ?? '—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options(fn (): array => app(AuditQuery::class)
                        ->newQuery()
                        ->orderBy('event')
                        ->distinct()
                        ->pluck('event', 'event')
                        ->all()),
                SelectFilter::make('category')
                    ->options(fn (): array => app(AuditQuery::class)
                        ->newQuery()
                        ->whereNotNull('category')
                        ->orderBy('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->all()),
                Filter::make('occurred_at')
                    ->form([
                        DateTimePicker::make('from')->label('From'),
                        DateTimePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('occurred_at', '>=', $from))
                            ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->where('occurred_at', '<=', $until));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')
                ->schema([
                    TextEntry::make('event')->label('Event')->formatStateUsing(fn (string $state): string => self::eventLabel($state)),
                    TextEntry::make('category')->badge()->placeholder('—'),
                    TextEntry::make('occurred_at')->dateTime('Y-m-d H:i:s T')
                        ->helperText(fn (AuditEvent $record): string => $record->occurred_at?->diffForHumans() ?? '—'),
                    TextEntry::make('source')->placeholder('—'),
                    TextEntry::make('guard')->placeholder('—'),
                    TextEntry::make('actor_reference')
                        ->label('Actor')
                        ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'actor')),
                    TextEntry::make('original_actor_reference')
                        ->label('Original actor')
                        ->state(fn (AuditEvent $record): string => self::originalActorLabel($record)),
                    TextEntry::make('correlation_id')->label('Correlation')->placeholder('—'),
                    TextEntry::make('batch_id')->label('Batch')->placeholder('—'),
                    TextEntry::make('subject_reference')
                        ->label('Subject')
                        ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'subject')),
                ])
                ->columns(2),
            Section::make('Changes')
                ->schema([
                    TextEntry::make('old_values')
                        ->label('Before')
                        ->state(fn (AuditEvent $record): string => self::displayValues($record->old_values ?? [], $record)),
                    TextEntry::make('new_values')
                        ->label('After')
                        ->state(fn (AuditEvent $record): string => self::displayValues($record->new_values ?? [], $record)),
                    TextEntry::make('diff')
                        ->label('Before / after diff')
                        ->state(fn (AuditEvent $record): string => self::displayDiff($record))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Safe metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->state(fn (AuditEvent $record): string => self::displayValues($record->metadata ?? [], $record))
                        ->columnSpanFull(),
                    TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEvents::route('/'),
            'view' => ViewAuditEvent::route('/{record}'),
        ];
    }

    private static function referenceLabel(AuditEvent $record, string $relation): string
    {
        $type = $record->{$relation.'_type'};
        $id = $record->{$relation.'_id'};

        if ($type === null || $id === null) {
            return 'Anonymous / system';
        }

        return class_basename($type).' #'.$id;
    }

    private static function originalActorLabel(AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewOriginalActor($record)) {
            return '—';
        }

        return self::referenceLabel($record, 'original_actor');
    }

    private static function eventLabel(string $event): string
    {
        return str($event)->replace(['.', '_', '-'], ' ')->headline();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function displayValues(array $values, AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewRawValues($record)) {
            return '[REDACTED]';
        }

        $encoded = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '[UNSERIALIZABLE]' : $encoded;
    }

    private static function displayDiff(AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewRawValues($record)) {
            return '[REDACTED]';
        }

        return DiffFormatter::format($record->old_values ?? [], $record->new_values ?? []);
    }
}
