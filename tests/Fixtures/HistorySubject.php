<?php

namespace Digit7s\FilamentAuditToolkit\Tests\Fixtures;

use Digit7s\AuditToolkit\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** @property string|null $status */
class HistorySubject extends Model
{
    use Auditable;

    protected $table = 'filament_test_subjects';

    protected $guarded = [];

    protected function auditInclude(): array
    {
        return ['name', 'status'];
    }
}
