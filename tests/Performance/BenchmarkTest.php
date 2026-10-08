<?php

use Digit7s\AuditToolkit\Models\AuditEvent;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Tests\Fixtures\TestUser;
use Digit7s\FilamentAuditToolkit\Tests\TestCase;
use Filament\Facades\Filament;

uses(TestCase::class);

it('runs the optional disposable SQLite explorer benchmark', function (): void {
    $events = max(1, (int) (getenv('AUDIT_BENCHMARK_EVENTS') ?: 1000));
    Filament::setCurrentPanel('test');
    $this->actingAs(TestUser::query()->create(['name' => 'Benchmark User']));
    app(AuditAuthorization::class)->configure(viewAny: fn (): bool => true, view: fn (): bool => true);

    for ($index = 0; $index < $events; $index++) {
        audit()->recordEvent('benchmark.explorer', metadata: ['channel' => 'benchmark']);
    }

    $started = hrtime(true);
    $page = AuditEventResource::getEloquentQuery()
        ->where('event', 'benchmark.explorer')
        ->paginate(50);
    $seconds = (hrtime(true) - $started) / 1_000_000_000;
    $result = [
        'events' => $events,
        'page_query_seconds' => round($seconds, 6),
        'rows_per_second' => round($events / max($seconds, 0.000001), 2),
        'page_count' => $page->count(),
        'peak_memory_bytes' => memory_get_peak_usage(true),
        'stored_count' => AuditEvent::query()->where('event', 'benchmark.explorer')->count(),
    ];

    fwrite(STDOUT, 'BENCHMARK '.json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);

    expect($page->total())->toBe($events);
});
