<?php

namespace Digit7s\FilamentAuditToolkit\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable implements FilamentUser
{
    protected $table = 'filament_audit_test_users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
