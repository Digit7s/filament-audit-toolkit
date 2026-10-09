<?php

namespace Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages;

use Digit7s\FilamentAuditToolkit\Diff\DiffStyle;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource;
use Digit7s\FilamentAuditToolkit\Support\DiffConfiguration;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditEvent extends ViewRecord
{
    protected static string $resource = AuditEventResource::class;

    public string $diffStyle = DiffStyle::Unified->value;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->diffStyle = app(DiffConfiguration::class)->defaultStyle($this->panelId());
    }

    public function getDiffStyle(): string
    {
        return app(DiffConfiguration::class)->resolve($this->panelId(), $this->diffStyle);
    }

    public function setDiffStyle(string $style): void
    {
        $this->diffStyle = app(DiffConfiguration::class)->resolve($this->panelId(), $style);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $configuration = app(DiffConfiguration::class);

        if (! $configuration->allowsSwitching($this->panelId())) {
            return [];
        }

        return [
            Action::make('diffStyle')
                ->label(fn (): string => 'Diff: '.DiffStyle::label($this->getDiffStyle()))
                ->icon('heroicon-m-adjustments-horizontal')
                ->schema([
                    Select::make('style')
                        ->label('Presentation style')
                        ->options($configuration->styleOptions($this->panelId()))
                        ->default(fn (): string => $this->getDiffStyle())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->setDiffStyle((string) ($data['style'] ?? ''));
                }),
        ];
    }

    public function getTitle(): string
    {
        return $this->getRecordTitle();
    }

    private function panelId(): string
    {
        return Filament::getCurrentOrDefaultPanel()->getId();
    }
}
