<?php

namespace Digit7s\FilamentAuditToolkit\Resources;

use Digit7s\AuditToolkit\Contracts\AuditQuery;
use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\Diff\DiffBuilder;
use Digit7s\FilamentAuditToolkit\Diff\DiffResult;
use Digit7s\FilamentAuditToolkit\Diff\DiffViewerEntry;
use Digit7s\FilamentAuditToolkit\Json\JsonViewerBuilder;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages\ListAuditEvents;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages\ViewAuditEvent;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Support\DiffConfiguration;
use Digit7s\FilamentAuditToolkit\Support\RawJsonEntry;
use Digit7s\FilamentAuditToolkit\Support\StructuredJsonEntry;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
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
                    ->url(fn (AuditEvent $record): ?string => static::canView($record)
                        ? static::getUrl('view', ['record' => $record])
                        : null)
                    ->tooltip(fn (AuditEvent $record): string => (string) $record->event)
                    ->width('18rem')
                    ->extraCellAttributes(['class' => 'fat-audit-event-cell'])
                    ->wrap()
                    ->lineClamp(2)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->visibleFrom('md')
                    ->placeholder('—'),
                TextColumn::make('actor_reference')
                    ->label('Actor')
                    ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'actor'))
                    ->width('11rem')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('original_actor_reference')
                    ->label('Original actor')
                    ->state(fn (AuditEvent $record): ?string => self::originalActorTableLabel($record))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('subject_reference')
                    ->label('Subject')
                    ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'subject'))
                    ->width('12rem')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('source')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('correlation_id')
                    ->label('Correlation ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('batch_id')
                    ->label('Batch ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('request_id')
                    ->label('Request ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->since('UTC')
                    ->tooltip(fn (AuditEvent $record): string => $record->occurred_at === null
                        ? 'No timestamp available'
                        : $record->occurred_at->setTimezone('UTC')->format('Y-m-d H:i:s T'))
                    ->placeholder('—')
                    ->width('10rem')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options(fn (): array => app(AuditQuery::class)
                        ->newQuery()
                        ->reorder()
                        ->select('event')
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event')
                        ->mapWithKeys(fn (string $event): array => [$event => self::eventLabel($event)])
                        ->all()),
                SelectFilter::make('category')
                    ->options(fn (): array => app(AuditQuery::class)
                        ->newQuery()
                        ->reorder()
                        ->whereNotNull('category')
                        ->select('category')
                        ->distinct()
                        ->orderBy('category')
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
            ->recordActions([
                ViewAction::make()
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->iconButton()
                    ->tooltip('View audit event')
                    ->visible(fn (AuditEvent $record): bool => static::canView($record)),
            ], RecordActionsPosition::AfterColumns)
            ->stackedOnMobile()
            ->searchPlaceholder('Search event names...')
            ->emptyStateHeading('No audit events found')
            ->emptyStateDescription('Try clearing filters or broadening your search.')
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $eventSummary = Section::make('Event summary')
            ->schema([
                TextEntry::make('event')
                    ->label('Event')
                    ->formatStateUsing(fn (string $state): string => self::eventLabel($state))
                    ->weight(FontWeight::SemiBold)
                    ->wrap(),
                TextEntry::make('category')->badge()->placeholder('—'),
                TextEntry::make('occurred_at')
                    ->dateTime('Y-m-d H:i:s T', 'UTC')
                    ->dateTimeTooltip('Y-m-d H:i:s T', 'UTC')
                    ->helperText(fn (AuditEvent $record): string => $record->occurred_at?->diffForHumans() ?? '—'),
                TextEntry::make('actor_reference')
                    ->label('Actor')
                    ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'actor')),
                TextEntry::make('subject_reference')
                    ->label('Subject')
                    ->state(fn (AuditEvent $record): string => self::referenceLabel($record, 'subject'))
                    ->wrap(),
            ])
            ->columns(['default' => 1, 'md' => 2]);

        $changes = Section::make('Changes')
            ->description('Field-level changes with explicit missing and null values.')
            ->schema([
                DiffViewerEntry::make('structured_diff')
                    ->label('Field changes')
                    ->viewData(function (AuditEvent $record, $livewire): array {
                        $panelId = Filament::getCurrentOrDefaultPanel()->getId();
                        $style = $livewire instanceof ViewAuditEvent
                            ? $livewire->getDiffStyle()
                            : app(DiffConfiguration::class)->defaultStyle($panelId);

                        return [
                            'diff' => self::structuredDiff($record),
                            'authorized' => app(AuditAuthorization::class)->canViewRawValues($record),
                            'style' => $style,
                        ];
                    })
                    ->columnSpanFull(),
            ]);

        $executionContext = Section::make('Execution context')
            ->schema([
                TextEntry::make('guard')->placeholder('—'),
                TextEntry::make('source')->placeholder('—'),
                TextEntry::make('original_actor_reference')
                    ->label('Original actor')
                    ->state(fn (AuditEvent $record): string => self::originalActorLabel($record)),
                TextEntry::make('correlation_id')->label('Correlation')->placeholder('—'),
                TextEntry::make('batch_id')->label('Batch')->placeholder('—'),
                TextEntry::make('request_id')->label('Request')->placeholder('—'),
            ])
            ->columns(['default' => 1, 'md' => 2]);

        $developerDetails = Section::make('Developer details')
            ->description('Bounded before and after JSON for authorized debugging.')
            ->schema([
                RawJsonEntry::make('raw_values')
                    ->label('Raw before and after')
                    ->viewData(fn (AuditEvent $record): array => self::jsonViewerData([
                        'before' => $record->old_values ?? [],
                        'after' => $record->new_values ?? [],
                    ], $record, 'json', rawValues: true))
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed();

        $safeMetadata = Section::make('Safe metadata')
            ->description('Sanitized application metadata. Expand nested values or inspect bounded JSON.')
            ->schema([
                StructuredJsonEntry::make('metadata')
                    ->label('Metadata')
                    ->viewData(fn (AuditEvent $record): array => self::jsonViewerData($record->metadata ?? [], $record, 'tree'))
                    ->columnSpanFull(),
                TextEntry::make('description')->placeholder('—')->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed();

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 12])
                ->extraAttributes(['class' => 'fat-audit-detail-layout'])
                ->columnSpanFull()
                ->schema([
                    Group::make([
                        $eventSummary,
                        $executionContext,
                        $safeMetadata,
                    ])
                        ->extraAttributes(['class' => 'fat-audit-detail-column fat-audit-detail-column--left'])
                        ->columnSpan(['default' => 'full', 'lg' => 5]),
                    Group::make([
                        $changes,
                        $developerDetails,
                    ])
                        ->extraAttributes(['class' => 'fat-audit-detail-column fat-audit-detail-column--right'])
                        ->columnSpan(['default' => 'full', 'lg' => 7]),
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
            if ($relation === 'subject') {
                return 'No subject';
            }

            if ($relation === 'original_actor') {
                return 'Not impersonated';
            }

            return 'Anonymous';
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

    private static function originalActorTableLabel(AuditEvent $record): ?string
    {
        if (! app(AuditAuthorization::class)->canViewOriginalActor($record)) {
            return null;
        }

        if ($record->original_actor_type === null || $record->original_actor_id === null) {
            return null;
        }

        return self::referenceLabel($record, 'original_actor');
    }

    public static function eventLabel(string $event): string
    {
        return str($event)->replace(['.', '_', '-'], ' ')->headline();
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonViewerData(
        mixed $values,
        AuditEvent $record,
        string $defaultMode,
        bool $rawValues = false,
    ): array {
        $authorization = app(AuditAuthorization::class);
        $authorized = $rawValues
            ? $authorization->canViewRawValues($record)
            : $authorization->canView($record);

        if (! $authorized) {
            return [
                'authorized' => false,
                'copyable' => false,
                'defaultMode' => $defaultMode,
                'root' => [
                    'key' => null,
                    'kind' => 'truncated',
                    'type_label' => 'Redacted',
                    'display' => '[REDACTED]',
                    'children' => [],
                    'empty' => false,
                    'truncated' => true,
                ],
                'json' => '[REDACTED]',
                'tokens' => [['type' => 'string', 'text' => '[REDACTED]']],
                'truncated' => false,
            ];
        }

        $configuration = config('filament-audit-toolkit.json_viewer', []);
        $result = (new JsonViewerBuilder(
            maxDepth: (int) ($configuration['max_depth'] ?? 5),
            maxEntries: (int) ($configuration['max_entries'] ?? 200),
            maxValueLength: (int) ($configuration['max_value_length'] ?? 2_000),
        ))->build($values);

        return [
            'authorized' => true,
            'copyable' => (bool) ($configuration['allow_copy'] ?? true),
            'defaultMode' => $defaultMode,
            'root' => $result->root,
            'json' => $result->json,
            'tokens' => $result->tokens,
            'truncated' => $result->truncated,
        ];
    }

    private static function structuredDiff(AuditEvent $record): DiffResult
    {
        if (! app(AuditAuthorization::class)->canViewRawValues($record)) {
            return new DiffResult([]);
        }

        return (new DiffBuilder(
            maxDepth: (int) config('filament-audit-toolkit.diff.max_depth', 5),
            maxEntries: (int) config('filament-audit-toolkit.diff.max_entries', 100),
            maxValueLength: (int) config('filament-audit-toolkit.diff.max_value_length', 2_000),
            maxArrayElements: (int) config('filament-audit-toolkit.diff.max_array_elements', 100),
        ))->build($record->old_values ?? [], $record->new_values ?? []);
    }
}
