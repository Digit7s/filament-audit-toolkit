<?php

use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\FilamentAuditToolkitPlugin;
use Digit7s\FilamentAuditToolkit\RelationManagers\AuditHistoryRelationManager;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages\ListAuditEvents;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource\Pages\ViewAuditEvent;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Support\DiffFormatter;
use Digit7s\FilamentAuditToolkit\Tests\Fixtures\HistorySubject;
use Digit7s\FilamentAuditToolkit\Tests\Fixtures\TestUser;
use Digit7s\FilamentAuditToolkit\Tests\SecondaryPanelProvider;
use Digit7s\FilamentAuditToolkit\Tests\TestPanelProvider;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('test');
    $this->actingAs(TestUser::query()->create(['name' => 'Audit Tester']));
    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        view: fn (): bool => true,
        viewRawValues: fn (): bool => true,
    );
});

it('exposes a stable Filament plugin identity', function (): void {
    expect(FilamentAuditToolkitPlugin::make()->getId())->toBe('filament-audit-toolkit');
});

it('denies explorer access by default', function (): void {
    app(AuditAuthorization::class)->configure();

    expect(AuditEventResource::canViewAny())->toBeFalse();
});

it('requires explicit host authorization for list and detail access', function (): void {
    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        view: fn (AuditEvent $event): bool => $event->event === 'allowed.event',
        viewRawValues: fn (): bool => false,
    );

    $allowed = new AuditEvent(['event' => 'allowed.event']);
    $denied = new AuditEvent(['event' => 'denied.event']);

    expect(AuditEventResource::canViewAny())->toBeTrue()
        ->and(AuditEventResource::canView($allowed))->toBeTrue()
        ->and(AuditEventResource::canView($denied))->toBeFalse();
});

it('formats a safe before and after diff', function (): void {
    $diff = DiffFormatter::format(
        ['status' => 'draft', 'unchanged' => 'same'],
        ['status' => 'published', 'new' => true, 'unchanged' => 'same'],
    );

    expect($diff)->toContain('- status: "draft"')
        ->and($diff)->toContain('+ status: "published"')
        ->and($diff)->toContain('+ new: true')
        ->and($diff)->not->toContain('unchanged');
});

it('distinguishes missing values from explicit null values', function (): void {
    $diff = DiffFormatter::format(
        ['removed_null' => null],
        ['added_null' => null, 'changed' => null],
    );

    expect($diff)->toContain('- removed_null: null')
        ->and($diff)->toContain('+ removed_null: [missing]')
        ->and($diff)->toContain('- changed: [missing]')
        ->and($diff)->toContain('+ changed: null')
        ->and($diff)->toContain('+ added_null: null')
        ->and($diff)->toContain('- added_null: [missing]');
});

it('isolates authorization callbacks and guards per Filament panel', function (): void {
    Filament::setCurrentPanel('test');
    expect(app(AuditAuthorization::class)->canViewAny())->toBeTrue();

    Filament::setCurrentPanel('secondary');
    expect(app(AuditAuthorization::class)->canViewAny())->toBeFalse();

    $secondaryAdmin = TestUser::query()->create(['name' => 'Secondary Admin']);
    $this->actingAs($secondaryAdmin, 'admin');
    Filament::setCurrentPanel('secondary');
    expect(app(AuditAuthorization::class)->canViewAny())->toBeTrue();

    Filament::setCurrentPanel('test');
    expect(app(AuditAuthorization::class)->canViewAny())->toBeTrue();
});

it('denies forbidden detail and history queries server-side on a conflicting panel', function (): void {
    $event = audit()->recordEvent('secondary.denied');
    $subject = HistorySubject::create(['name' => 'Protected', 'status' => 'draft']);
    $other = HistorySubject::create(['name' => 'Other protected', 'status' => 'draft']);
    $wrongAdmin = TestUser::query()->create(['name' => 'Wrong Admin']);
    $this->actingAs($wrongAdmin, 'admin');
    Filament::setCurrentPanel('secondary');

    expect(AuditEventResource::canViewAny())->toBeFalse()
        ->and(AuditEventResource::canView($event))->toBeFalse()
        ->and(AuditEventResource::getEloquentQuery()->whereKey($event->getKey())->count())->toBe(0)
        ->and(AuditHistoryRelationManager::canViewForRecord($subject, SecondaryPanelProvider::class))->toBeFalse()
        ->and(AuditHistoryRelationManager::canViewForRecord($other, SecondaryPanelProvider::class))->toBeFalse();

    livewire(AuditHistoryRelationManager::class, [
        'ownerRecord' => $other,
        'pageClass' => SecondaryPanelProvider::class,
    ])->assertForbidden();
});

it('provides subject-scoped, authorization-protected history access', function (): void {
    $subject = HistorySubject::create(['name' => 'History', 'status' => 'draft']);
    $subject->update(['status' => 'published']);

    expect(AuditHistoryRelationManager::getRelationshipName())->toBe('auditHistory')
        ->and($subject->auditHistory()->count())->toBe(2);

    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        viewSubjectHistory: fn (HistorySubject $record): bool => $record->getKey() === $subject->getKey(),
    );

    expect(AuditHistoryRelationManager::canViewForRecord($subject, TestPanelProvider::class))->toBeTrue();

    $other = HistorySubject::create(['name' => 'Other', 'status' => 'draft']);

    expect(AuditHistoryRelationManager::canViewForRecord($other, TestPanelProvider::class))->toBeFalse();
});

it('renders the reusable history relation through Livewire', function (): void {
    $subject = HistorySubject::create(['name' => 'Renderable', 'status' => 'draft']);
    $subject->update(['status' => 'published']);

    livewire(AuditHistoryRelationManager::class, [
        'ownerRecord' => $subject,
        'pageClass' => TestPanelProvider::class,
    ])
        ->assertSee('model.created')
        ->assertSee('model.updated')
        ->assertSee('published');
});

it('renders the explorer and detail pages through Livewire', function (): void {
    $event = audit()->recordEvent(
        event: 'livewire.example',
        oldValues: ['status' => 'draft'],
        newValues: ['status' => 'published'],
        allowedValueKeys: ['status'],
    );

    livewire(ListAuditEvents::class)
        ->assertCanSeeTableRecords([$event]);

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('livewire.example')
        ->assertSee('published');
});

it('hides stored values when raw-value authorization is denied', function (): void {
    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        view: fn (): bool => true,
        viewRawValues: fn (): bool => false,
    );

    $event = audit()->recordEvent(
        event: 'redacted.example',
        oldValues: ['status' => 'draft'],
        newValues: ['status' => 'secret-sentinel'],
        allowedValueKeys: ['status'],
    );

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('[REDACTED]')
        ->assertDontSee('secret-sentinel');
});

it('shows original actor attribution only when the active panel authorizes it', function (): void {
    $administrator = TestUser::query()->create(['name' => 'Original Administrator']);
    $effective = TestUser::query()->create(['name' => 'Effective Operator']);

    audit_context()->withImpersonation($administrator, function () use ($effective): void {
        audit()->recordEvent('impersonation.action', actor: $effective, source: 'model');
    }, $effective);

    $event = AuditEvent::query()->where('event', 'impersonation.action')->firstOrFail();

    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        view: fn (): bool => true,
        viewRawValues: fn (): bool => true,
        viewOriginalActor: fn (): bool => false,
    );

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('Original actor')
        ->assertSee('—')
        ->assertDontSee('Original Administrator');

    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        view: fn (): bool => true,
        viewRawValues: fn (): bool => true,
        viewOriginalActor: fn (): bool => true,
    );

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('TestUser #'.$administrator->getKey());
});

it('enforces panel authorization through HTTP list and detail entry points', function (): void {
    $event = audit()->recordEvent('http.panel.security', oldValues: ['state' => 'old'], newValues: ['state' => 'new'], allowedValueKeys: ['state']);

    $this->actingAs(TestUser::query()->create(['name' => 'HTTP Test User']), 'web')
        ->get('/test/audit-events')
        ->assertOk();

    $this->actingAs(TestUser::query()->create(['name' => 'Not Secondary Admin']), 'admin')
        ->get('/secondary/audit-events')
        ->assertForbidden();

    $this->actingAs(TestUser::query()->create(['name' => 'Not Secondary Admin']), 'admin')
        ->get('/secondary/audit-events/'.$event->getKey())
        ->assertNotFound();

    $this->actingAs(TestUser::query()->create(['name' => 'Secondary Admin']), 'admin')
        ->get('/secondary/audit-events/'.$event->getKey())
        ->assertForbidden();
});
