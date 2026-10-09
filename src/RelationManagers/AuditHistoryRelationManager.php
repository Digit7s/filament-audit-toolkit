<?php

namespace Digit7s\FilamentAuditToolkit\RelationManagers;

use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\Diff\DiffStyle;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Support\DiffConfiguration;
use Digit7s\FilamentAuditToolkit\Support\DiffFormatter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class AuditHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'auditHistory';

    protected static ?string $title = 'Audit history';

    public string $diffStyle = DiffStyle::Unified->value;

    public function mount(): void
    {
        parent::mount();

        $this->diffStyle = app(DiffConfiguration::class)->defaultStyle($this->panelId());
    }

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
                    ->formatStateUsing(fn (string $state): string => AuditEventResource::eventLabel($state))
                    ->tooltip(fn (AuditEvent $record): string => (string) $record->event)
                    ->wrap()
                    ->lineClamp(2)
                    ->sortable(),
                TextColumn::make('actor_reference')
                    ->label('Actor')
                    ->state(fn (AuditEvent $record): string => $this->referenceLabel($record, 'actor'))
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('original_actor_reference')
                    ->label('Original actor')
                    ->state(fn (AuditEvent $record): string => $this->originalActorLabel($record))
                    ->visibleFrom('lg')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('source')
                    ->visibleFrom('lg')
                    ->placeholder('—'),
                TextColumn::make('occurred_at')
                    ->dateTime('Y-m-d H:i:s T', 'UTC')
                    ->dateTimeTooltip('Y-m-d H:i:s T', 'UTC')
                    ->description(fn (AuditEvent $record): string => $record->occurred_at?->diffForHumans() ?? '—')
                    ->sortable(),
                TextColumn::make('changes')
                    ->label('Before / after')
                    ->state(fn (AuditEvent $record): string => $this->displayChanges($record))
                    ->wrap()
                    ->lineClamp(2)
                    ->visibleFrom('md'),
            ])
            ->headerActions($this->getDiffStyleActions())
            ->stackedOnMobile()
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function referenceLabel(AuditEvent $record, string $relation): string
    {
        $type = $record->{$relation.'_type'};
        $id = $record->{$relation.'_id'};

        return $type === null || $id === null
            ? ($relation === 'subject' ? 'No subject' : 'Anonymous')
            : class_basename($type).' #'.$id;
    }

    private function displayChanges(AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewRawValues($record)) {
            return '[REDACTED]';
        }

        $summary = DiffFormatter::summary($record->old_values ?? [], $record->new_values ?? []);

        return match ($this->diffStyle()) {
            DiffStyle::Split->value => 'Before / after: '.$summary,
            DiffStyle::Fields->value => 'Fields: '.$summary,
            default => $summary,
        };
    }

    private function originalActorLabel(AuditEvent $record): string
    {
        if (! app(AuditAuthorization::class)->canViewOriginalActor($record)) {
            return '—';
        }

        $type = $record->original_actor_type;
        $id = $record->original_actor_id;

        return $type === null || $id === null
            ? 'Not impersonated'
            : $this->referenceLabel($record, 'original_actor');
    }

    /**
     * @return array<int, Action>
     */
    private function getDiffStyleActions(): array
    {
        $configuration = app(DiffConfiguration::class);

        if (! $configuration->allowsSwitching($this->panelId())) {
            return [];
        }

        return [
            Action::make('diffStyle')
                ->label(fn (): string => 'Diff: '.DiffStyle::label($this->diffStyle()))
                ->schema([
                    Select::make('style')
                        ->label('Presentation style')
                        ->options($configuration->styleOptions($this->panelId()))
                        ->default(fn (): string => $this->diffStyle())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->diffStyle = app(DiffConfiguration::class)->resolve($this->panelId(), (string) ($data['style'] ?? ''));
                }),
        ];
    }

    private function diffStyle(): string
    {
        return app(DiffConfiguration::class)->resolve($this->panelId(), $this->diffStyle);
    }

    private function panelId(): string
    {
        return Filament::getCurrentOrDefaultPanel()->getId();
    }
}
