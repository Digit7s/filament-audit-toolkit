<?php

use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\Diff\DiffBuilder;
use Digit7s\FilamentAuditToolkit\Diff\DiffStyle;
use Digit7s\FilamentAuditToolkit\FilamentAuditToolkitPlugin;
use Digit7s\FilamentAuditToolkit\Json\JsonViewerBuilder;
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
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Blade;

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

it('registers a package-owned stylesheet for consuming Filament panels', function (): void {
    $styles = FilamentAsset::getStyles(['digit7s/filament-audit-toolkit']);
    $style = collect($styles)->first(fn (Css $asset): bool => $asset->getId() === 'filament-audit-toolkit');

    expect($style)->not->toBeNull()
        ->and($style->getPackage())->toBe('digit7s/filament-audit-toolkit')
        ->and(str_ends_with((string) $style->getPath(), '/resources/css/filament-audit-toolkit.css'))->toBeTrue()
        ->and($style->getPath())->toBeFile();
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

it('builds a deterministic bounded structured diff without mutating inputs', function (): void {
    $before = ['profile' => ['name' => 'Before'], 'items' => ['one', 'two']];
    $after = ['profile' => ['name' => 'After'], 'items' => ['one', 'three'], 'added' => null];
    $beforeSnapshot = $before;
    $afterSnapshot = $after;

    $result = (new DiffBuilder(maxDepth: 5, maxEntries: 10, maxValueLength: 100))->build($before, $after);

    expect($result->toArray())->toHaveCount(3)
        ->and(collect($result->entries())->pluck('path')->all())->toBe([
            'profile.name',
            'items.1',
            'added',
        ])
        ->and($result->entries()[2]->beforeDisplay)->toBe('[missing]')
        ->and($result->entries()[2]->afterDisplay)->toBe('null')
        ->and($before)->toBe($beforeSnapshot)
        ->and($after)->toBe($afterSnapshot);
});

it('marks depth, entry, and display limits explicitly', function (): void {
    $result = (new DiffBuilder(maxDepth: 0, maxEntries: 1, maxValueLength: 8, maxArrayElements: 1))->build(
        ['nested' => ['secret' => 'before'], 'second' => 'before'],
        ['nested' => ['secret' => 'after'], 'second' => 'after'],
    );

    expect($result->isTruncated())->toBeTrue()
        ->and(collect($result->entries())->last()->type)->toBe('truncated')
        ->and(collect($result->entries())->first()->truncated)->toBeTrue();
});

it('builds a type-aware bounded JSON viewer without mutating input values', function (): void {
    $values = [
        'profile' => [
            'name' => 'Example',
            'enabled' => true,
            'attempts' => 3,
            'ratio' => 0.5,
            'deleted_at' => null,
        ],
        'items' => ['first', 'second'],
    ];
    $snapshot = $values;

    $result = (new JsonViewerBuilder(maxDepth: 5, maxEntries: 20, maxValueLength: 200))->build($values);
    $profile = collect($result->root['children'])->firstWhere('key', 'profile');
    $profileChildren = collect($profile['children']);

    expect($profile['kind'])->toBe('object')
        ->and($profileChildren->firstWhere('key', 'enabled')['kind'])->toBe('boolean')
        ->and($profileChildren->firstWhere('key', 'attempts')['kind'])->toBe('integer')
        ->and($profileChildren->firstWhere('key', 'ratio')['kind'])->toBe('number')
        ->and($profileChildren->firstWhere('key', 'deleted_at')['kind'])->toBe('null')
        ->and($result->json)->toContain('"enabled": true')
        ->and($result->json)->toContain('"deleted_at": null')
        ->and($values)->toBe($snapshot);
});

it('marks JSON viewer depth, entry, and display limits explicitly', function (): void {
    $nested = (new JsonViewerBuilder(maxDepth: 0, maxEntries: 1, maxValueLength: 8))->build([
        'nested' => ['secret' => 'value'],
    ]);
    $long = (new JsonViewerBuilder(maxDepth: 5, maxEntries: 10, maxValueLength: 8))->build([
        'value' => 'a very long value',
    ]);
    $many = (new JsonViewerBuilder(maxDepth: 5, maxEntries: 1, maxValueLength: 200))->build([
        'first' => 'value',
        'second' => 'omitted value',
    ]);

    expect($nested->truncated)->toBeTrue()
        ->and($nested->json)->toContain('omitted')
        ->and($long->truncated)->toBeTrue()
        ->and($long->json)->toContain('truncated')
        ->and($many->truncated)->toBeTrue()
        ->and($many->json)->toContain('omitted');
});

it('renders the structured JSON viewer and omits copy for unauthorized values', function (): void {
    $result = (new JsonViewerBuilder)->build(['safe' => 'value', 'enabled' => true]);

    $render = static fn (array $data): string => Blade::render(
        '@include(\'filament-audit-toolkit::components.structured-json\', $payload)',
        ['payload' => $data],
    );

    $authorized = $render([
        'authorized' => true,
        'copyable' => true,
        'defaultMode' => 'tree',
        'root' => $result->root,
        'json' => $result->json,
        'tokens' => $result->tokens,
        'truncated' => $result->truncated,
    ]);
    $unauthorized = $render([
        'authorized' => false,
        'copyable' => false,
        'defaultMode' => 'tree',
        'root' => $result->root,
        'json' => $result->json,
        'tokens' => $result->tokens,
        'truncated' => false,
    ]);

    expect($authorized)->toContain('Tree View')
        ->and($authorized)->toContain('JSON View')
        ->and($authorized)->toContain('Copy JSON')
        ->and($authorized)->toContain('fat-json-token--boolean')
        ->and($unauthorized)->toContain('[REDACTED]')
        ->and($unauthorized)->not->toContain('Copy JSON')
        ->and($unauthorized)->not->toContain('safe')
        ->and($unauthorized)->not->toContain('value');
});

it('normalizes supported styles and safely falls back from unsupported input', function (): void {
    expect(DiffStyle::normalize('fields', ['unified', 'fields'], 'unified'))->toBe('fields')
        ->and(DiffStyle::normalize('arbitrary', ['unified', 'fields'], 'unified'))->toBe('unified')
        ->and(DiffStyle::normalizeList(['split', 'split', 'arbitrary']))->toBe(['split']);
});

it('flattens nested changes without losing null and missing semantics', function (): void {
    $changes = DiffFormatter::changes(
        [
            'profile' => [
                'display_name' => 'Before',
                'preferences' => ['theme' => 'light', 'density' => null],
            ],
            'removed' => 'value',
        ],
        [
            'profile' => [
                'display_name' => 'After',
                'preferences' => ['theme' => 'dark', 'layout' => ['density' => 'compact']],
            ],
            'added' => true,
        ],
    );

    expect($changes)->toContain([
        'path' => 'profile.display_name',
        'type' => 'modified',
        'before' => '"Before"',
        'after' => '"After"',
    ])
        ->and($changes)->toContain([
            'path' => 'profile.preferences.theme',
            'type' => 'modified',
            'before' => '"light"',
            'after' => '"dark"',
        ])
        ->and($changes)->toContain([
            'path' => 'profile.preferences.density',
            'type' => 'removed',
            'before' => 'null',
            'after' => '[missing]',
        ])
        ->and(collect($changes)->firstWhere('path', 'profile.preferences.layout'))->toMatchArray([
            'type' => 'added',
            'before' => '[missing]',
        ])
        ->and(collect($changes)->firstWhere('path', 'profile.preferences.layout')['after'])->toContain('"density": "compact"');
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
        ->assertSee('Modified status')
        ->assertSee('Diff: Split')
        ->set('diffStyle', 'fields')
        ->assertSee('Fields: Modified status');
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
        ->assertSee('Livewire Example')
        ->assertSee('Not impersonated')
        ->assertSee('published');
});

it('loads empty event and category filter options', function (): void {
    $component = livewire(ListAuditEvents::class);
    $page = $component->instance();

    if (! $page instanceof ListAuditEvents) {
        throw new LogicException('The Livewire test instance is not the audit events page.');
    }

    $filters = $page->getTable()->getFilters();
    $eventFilter = $filters['event'];
    $categoryFilter = $filters['category'];

    if (! $eventFilter instanceof SelectFilter || ! $categoryFilter instanceof SelectFilter) {
        throw new LogicException('The audit event filters are not select filters.');
    }

    expect($eventFilter->getOptions())->toBe([])
        ->and($categoryFilter->getOptions())->toBe([]);
});

it('loads distinct alphabetic filter options without changing explorer ordering', function (): void {
    audit()->recordEvent('zeta.event', category: 'zeta', occurredAt: now()->subMinute());
    audit()->recordEvent('alpha.event', category: 'alpha', occurredAt: now());
    audit()->recordEvent('alpha.event', category: 'alpha', occurredAt: now()->subMinutes(2));
    audit()->recordEvent('no.category', occurredAt: now()->subMinutes(3));

    $component = livewire(ListAuditEvents::class);
    $page = $component->instance();

    if (! $page instanceof ListAuditEvents) {
        throw new LogicException('The Livewire test instance is not the audit events page.');
    }

    $filters = $page->getTable()->getFilters();
    $eventFilter = $filters['event'];
    $categoryFilter = $filters['category'];

    if (! $eventFilter instanceof SelectFilter || ! $categoryFilter instanceof SelectFilter) {
        throw new LogicException('The audit event filters are not select filters.');
    }

    $orders = AuditEventResource::getEloquentQuery()->getQuery()->orders;

    expect($eventFilter->getOptions())->toBe([
        'alpha.event' => 'Alpha Event',
        'no.category' => 'No Category',
        'zeta.event' => 'Zeta Event',
    ])
        ->and($categoryFilter->getOptions())->toBe([
            'alpha' => 'alpha',
            'zeta' => 'zeta',
        ])
        ->and($orders)->toContain([
            'column' => 'occurred_at',
            'direction' => 'desc',
        ]);
});

it('keeps unauthorized explorer queries empty while filter queries retain their read scopes', function (): void {
    audit()->recordEvent('protected.event');

    app(AuditAuthorization::class)->configure();

    expect(AuditEventResource::getEloquentQuery()->count())->toBe(0)
        ->and(AuditEventResource::getEloquentQuery()->toSql())->toContain('1 = 0');
});

it('renders Audit Detail sections in independent responsive column stacks', function (): void {
    $event = audit()->recordEvent(
        event: 'layout.independent.stacks',
        oldValues: ['status' => 'draft'],
        newValues: ['status' => 'published'],
        allowedValueKeys: ['status'],
    );

    $html = livewire(ViewAuditEvent::class, ['record' => $event->getKey()])->html();

    expect($html)->toContain('fat-audit-detail-layout')
        ->and($html)->toContain('fat-audit-detail-column--left')
        ->and($html)->toContain('fat-audit-detail-column--right')
        ->and($html)->toContain('id="infolist.event-summary::section-heading"')
        ->and($html)->toContain('id="infolist.changes::section-heading"')
        ->and($html)->toContain('id="infolist.execution-context::section-heading"')
        ->and($html)->toContain('id="infolist.safe-metadata::section-heading"')
        ->and($html)->toContain('id="infolist.developer-details::section-heading"')
        ->and($html)->toContain('fat-diff-viewer');
});

it('keeps explorer navigation authorized and optional columns hidden by default', function (): void {
    $event = audit()->recordEvent(
        event: 'explorer.long_event_name_for_navigation',
        category: 'business',
        correlationId: '11111111-1111-4111-8111-111111111111',
        batchId: '22222222-2222-4222-8222-222222222222',
        requestId: '33333333-3333-4333-8333-333333333333',
    );

    $component = livewire(ListAuditEvents::class)
        ->assertCanSeeTableRecords([$event])
        ->assertTableColumnVisible('event')
        ->assertTableColumnExists('original_actor_reference')
        ->assertTableColumnExists('correlation_id')
        ->assertSee('Search event names...')
        ->assertSee('/test/audit-events/'.$event->getKey(), escape: false);

    $tableColumns = collect($component->get('tableColumns'))->keyBy('name');

    foreach (['original_actor_reference', 'source', 'correlation_id', 'batch_id', 'request_id'] as $column) {
        expect(data_get($tableColumns->get($column), 'isToggled'))->toBeFalse();
    }

    app(AuditAuthorization::class)->configure(
        viewAny: fn (): bool => true,
        view: fn (): bool => false,
        viewRawValues: fn (): bool => false,
    );

    livewire(ListAuditEvents::class)
        ->assertCanSeeTableRecords([$event])
        ->assertDontSee('/test/audit-events/'.$event->getKey(), escape: false);
});

it('supports panel-scoped runtime style switching with server-side validation', function (): void {
    $event = audit()->recordEvent(
        event: 'runtime.style',
        oldValues: ['status' => 'draft'],
        newValues: ['status' => 'published'],
        allowedValueKeys: ['status'],
    );

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('Diff: Split')
        ->assertSee('fat-diff-viewer--split', escape: false)
        ->call('setDiffStyle', 'fields')
        ->assertSee('Diff: Fields')
        ->assertSee('fat-diff-fields-grid', escape: false)
        ->assertSee('fat-diff-badge--modified', escape: false)
        ->call('setDiffStyle', 'unified')
        ->assertSee('fat-diff-list--unified', escape: false)
        ->call('setDiffStyle', 'not-a-style')
        ->assertSee('Diff: Split');
});

it('renders nested field paths in the structured detail diff', function (): void {
    $event = audit()->recordEvent(
        event: 'nested.profile.updated',
        oldValues: ['profile' => ['display_name' => 'Before']],
        newValues: ['profile' => ['display_name' => 'After']],
        allowedValueKeys: ['profile.display_name'],
    );

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('profile.display_name')
        ->assertSee('Modified')
        ->assertSee('"Before"')
        ->assertSee('"After"');
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
        metadata: ['channel' => 'metadata-safe-sentinel'],
        allowedValueKeys: ['status'],
    );

    livewire(ViewAuditEvent::class, ['record' => $event->getKey()])
        ->assertSee('[REDACTED]')
        ->assertDontSee('secret-sentinel')
        ->assertSee('metadata-safe-sentinel')
        ->assertSee('Copy JSON');
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
