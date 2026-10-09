# Contributing

Keep this plugin focused on read-only Filament 5 presentation and authorization for `digit7s/laravel-audit-toolkit`. Do not add a second event store, duplicate core privacy logic, or broaden host application behavior.

Before opening a change, run `composer validate --no-check-publish`, `composer lint`, `composer analyse`, and `composer test`. Add focused Livewire or HTTP regression coverage for authorization changes. Do not use production data or modify the reference application.

The CI workflow checks out the public `Digit7s/laravel-audit-toolkit` repository and adds a temporary Composer path repository in the runner only. The published `composer.json` intentionally has no local path repository; consumers resolve `digit7s/laravel-audit-toolkit:^0.1` through Packagist.
