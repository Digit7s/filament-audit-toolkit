# Filament Audit Toolkit

`digit7s/filament-audit-toolkit` is a read-only Filament 5 explorer and reusable record-history relation manager for events recorded by `digit7s/laravel-audit-toolkit`.

## Status

This package provides a paginated explorer, event detail page, safe before/after diff, reusable subject-scoped history, and explicit host authorization callbacks. It does not write audit events or implement retention, exports, audit-record restore actions, dashboards, or queues.

## Installation

The package is currently an unpublished release-candidate development line. For a controlled pilot, resolve both packages from GitHub explicitly:

```bash
composer config repositories.digit7s-laravel-audit-toolkit vcs https://github.com/Digit7s/laravel-audit-toolkit.git
composer config repositories.digit7s-filament-audit-toolkit vcs https://github.com/Digit7s/filament-audit-toolkit.git
composer require \
    'digit7s/laravel-audit-toolkit:dev-main as 0.1.0' \
    'digit7s/filament-audit-toolkit:dev-main'
```

After tagged and published releases, the normal command will be:

```bash
composer require digit7s/filament-audit-toolkit
```

The core package owns the audit table. Publish its configuration and migrations and migrate before opening the Filament pages:

```bash
php artisan vendor:publish --tag=audit-toolkit-config
php artisan vendor:publish --tag=audit-toolkit-migrations
php artisan migrate
```

Register the plugin explicitly in a Filament panel provider:

```php
use Digit7s\FilamentAuditToolkit\FilamentAuditToolkitPlugin;

->plugin(
    FilamentAuditToolkitPlugin::make()
        ->authorization(
            viewAny: fn (?object $user): bool => $user?->can('viewAnyAuditEvents') ?? false,
            view: fn ($event, ?object $user): bool => $user?->can('viewAuditEvent') ?? false,
            viewRawValues: fn ($event, ?object $user): bool => $user?->can('viewAuditValues') ?? false,
            viewSubjectHistory: fn ($record, ?object $user): bool => $user?->can('viewAuditHistory', $record) ?? false,
            viewOriginalActor: fn ($event, ?object $user): bool => $user?->can('viewAuditImpersonation', $event) ?? false,
        ),
)
```

Authorization is deny-by-default. Hiding the navigation item is not authorization; the resource and detail query paths enforce the callbacks as well. If `viewRawValues` is omitted, the detail page uses the record-view callback for safe values.

Authorization callbacks are stored per registered panel instance. The active panel's guard is used when resolving the callback user, so separate panels may use different guards and policies without callback leakage. Configure every panel explicitly; an unconfigured panel remains denied.

## UI

The plugin supplies an Audit Explorer resource with event/category/date filters, pagination, actor and subject references, source, occurrence time, and a read-only detail page. Before/after values are escaped by Filament’s native text entries and are never rendered as raw HTML.

## Record History

Models using `Digit7s\AuditToolkit\Concerns\Auditable` expose the existing audit events through a polymorphic `auditHistory()` relationship. Add the reusable relation manager to any host resource:

```php
use Digit7s\FilamentAuditToolkit\RelationManagers\AuditHistoryRelationManager;

public static function getRelations(): array
{
    return [AuditHistoryRelationManager::class];
}
```

The relation manager is read-only, paginated, subject-scoped, and displays event, actor, source, timestamp, and an escaped before/after diff. It checks `viewSubjectHistory` before mounting. If that callback is omitted, the explorer's `viewAny` authorization is used. Raw-value display still requires `viewRawValues`; the relation manager cannot bypass the core package's persisted privacy filtering. Route or subject-ID changes do not broaden the relationship query.

The relation manager assumes the host resource itself already authorizes viewing the subject. It does not add policy methods to host models, and hidden navigation is not an authorization boundary.

The core package performs storage-time allowlisting and redaction before Filament reads an event. `viewRawValues` controls presentation of the already-sanitized values; it cannot recover excluded data or bypass the core privacy policy. Values are rendered through native escaped components rather than arbitrary HTML.

The explorer and history timeline show human-readable event labels, lifecycle categories, actor/effective actor references, relative timestamps with an exact timezone-bearing timestamp, and optional correlation/batch identifiers. Deleted actors and subjects fall back to stable type/id labels. Original-actor attribution is controlled independently by `viewOriginalActor`; when omitted it follows `view`. It is hidden as a neutral placeholder for unauthorized viewers and is never inferred from request input. Pagination remains enabled for large histories, and the components use native Filament rendering with no additional frontend framework.

## Troubleshooting

- If the core package cannot satisfy `^0.1` from a GitHub branch, use the documented `dev-main as 0.1.0` alias until a `0.1.x` tag is published.
- If the Explorer is forbidden, configure `viewAny` and `view` for the active panel; hidden navigation is not authorization.
- If raw values are hidden, configure `viewRawValues` separately. It can only reveal values already safely persisted by the core package.
- If subject history is forbidden, configure `viewSubjectHistory` for the active panel and confirm the host resource authorizes the subject.
- If the audit table is missing, publish the core migrations and run `php artisan migrate`.

## Compatibility

The Phase 3 implementation is tested against Filament 5.9.x, Laravel 13, Livewire 4, PHP 8.5.5 in package tests, and SQLite. Multi-panel callback configuration is keyed by active panel ID and uses each panel's configured guard. MySQL/PostgreSQL live verification was unavailable in the local environment and is not claimed.

## Phase 4 verification

The package requires PHP `^8.5`, Laravel 13 through the core package, Filament 5.9+, and Livewire 4 through Filament. Phase 4 uses Larastan 3.13 with PHPStan 2.3 at analysis level 5. Run `composer validate --no-check-publish`, `composer check-platform-reqs`, `composer lint`, `composer analyse`, and `composer test` before a pilot.

The optional `composer benchmark` command measures a bounded paginated Explorer query against disposable SQLite data. It is not a MySQL/PostgreSQL performance claim. The plugin has no release or Filament Directory submission in this development line; see [SECURITY.md](SECURITY.md), [CONTRIBUTING.md](CONTRIBUTING.md), and [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md).

## License

MIT. See [LICENSE](LICENSE).
