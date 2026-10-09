<?php

namespace Digit7s\FilamentAuditToolkit\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Digit7s\AuditToolkit\AuditServiceProvider;
use Digit7s\FilamentAuditToolkit\FilamentAuditServiceProvider;
use Digit7s\FilamentAuditToolkit\Tests\Fixtures\TestUser;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app): array
    {
        return [
            AuditServiceProvider::class,
            FilamentAuditServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            FilamentServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LivewireServiceProvider::class,
            TestPanelProvider::class,
            SecondaryPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $driver = (string) (getenv('AUDIT_TEST_DB_DRIVER') ?: 'sqlite');

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseConnectionForTests($driver));
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseConnectionForTests(string $driver): array
    {
        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        $password = getenv('AUDIT_TEST_DB_PASSWORD');

        return [
            'driver' => $driver,
            'host' => getenv('AUDIT_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('AUDIT_TEST_DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306'),
            'database' => getenv('AUDIT_TEST_DB_DATABASE') ?: 'audit_toolkit_test',
            'username' => getenv('AUDIT_TEST_DB_USERNAME') ?: ($driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => $password === false ? ($driver === 'pgsql' ? 'postgres' : 'root') : $password,
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (['filament_audit_test_users', 'filament_test_subjects'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('audit_events')) {
            DB::table('audit_events')->delete();
        } else {
            $this->loadMigrationsFrom(__DIR__.'/../../../laravel-audit-toolkit/database/migrations');
        }

        Schema::create('filament_audit_test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('filament_test_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
    }
}
