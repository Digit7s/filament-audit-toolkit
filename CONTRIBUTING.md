# Contributing

Keep this plugin focused on read-only Filament 5 presentation and authorization for `digit7s/laravel-audit-toolkit`. Do not add a second event store, duplicate core privacy logic, or broaden host application behavior.

Before opening a change, run `composer validate --no-check-publish`, `composer lint`, `composer analyse`, and `composer test`. Add focused Livewire or HTTP regression coverage for authorization changes. Do not use production data or modify the reference application.

The private CI workflow expects a repository-scoped `DIGIT7S_PACKAGES_READ_TOKEN` secret with read access to `Digit7s/laravel-audit-toolkit`. The token is used only by GitHub Actions to check out the core dependency into a temporary path repository; it is never committed or embedded in Composer metadata.
