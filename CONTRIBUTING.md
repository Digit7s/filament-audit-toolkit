# Contributing

Keep this plugin focused on read-only Filament 5 presentation and authorization for `digit7s/laravel-audit-toolkit`. Do not add a second event store, duplicate core privacy logic, or broaden host application behavior.

## Prerequisites

- PHP `^8.5` and Composer.
- A disposable Laravel application or the package's Testbench environment.
- A local checkout of `digit7s/laravel-audit-toolkit` when testing unreleased core changes.
- SQLite for local tests; Docker is useful for broader integration checks.

## Clone and install

```bash
git clone https://github.com/Digit7s/filament-audit-toolkit.git
cd filament-audit-toolkit
composer install
```

The normal package install resolves `digit7s/laravel-audit-toolkit:^0.1` from Packagist. The published `composer.json` intentionally contains no local path repository.

## Sibling package development

Path repositories are configured in the consuming application's `composer.json`, and each path is relative to that file. This example is illustrative rather than universal:

```text
workspace/
├── laravel-audit-toolkit/
├── filament-audit-toolkit/
└── playground/
    └── composer.json
```

For the example layout, add temporary overrides to the playground:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-audit-toolkit",
            "options": {
                "symlink": true,
                "versions": {
                    "digit7s/laravel-audit-toolkit": "0.1.0"
                }
            }
        },
        {
            "type": "path",
            "url": "../filament-audit-toolkit",
            "options": {
                "symlink": true,
                "versions": {
                    "digit7s/filament-audit-toolkit": "0.1.0"
                }
            }
        }
    ]
}
```

Adjust the paths for your own checkout. Run `composer update digit7s/laravel-audit-toolkit digit7s/filament-audit-toolkit` in the consuming application after changing either sibling. Remove the temporary entries and update again to return to Packagist dependencies.

The package quality workflow checks out the public core repository and injects a temporary path repository only inside the CI runner. This keeps local development and CI overrides out of the published package manifest.

## Checks and tests

Run the focused checks before opening a pull request:

```bash
composer validate --no-check-publish
composer check-platform-reqs
composer lint
composer analyse
composer test
```

Add focused Livewire or HTTP regression coverage for authorization, diff-style, JSON-viewer, explorer, detail, or history changes. Use the playground for package discovery, panel registration, asset loading, and responsive UI checks. Do not use production data or modify the reference application.

## Pull requests and security

Keep pull requests focused and explain any public API or documentation changes. Report suspected vulnerabilities privately as described in [SECURITY.md](SECURITY.md); do not open a public issue with credentials, production audit data, or personal data.

Release tags, Packagist publication, and Filament Plugin Directory submissions are maintainer-controlled activities. Do not retag an existing release, force-push, or publish from a contributor checkout.
