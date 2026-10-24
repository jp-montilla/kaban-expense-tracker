<?php

namespace App\Models\Concerns;

use App\Support\Ownership;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use ReflectionClass;

trait Trashable
{
    use SoftDeletes {
        bootSoftDeletes as bootFrameworkSoftDeletes;
        initializeSoftDeletes as initializeFrameworkSoftDeletes;
        performDeleteOnModel as performFrameworkSoftDeleteOnModel;
    }

    public static function bootSoftDeletes(): void
    {
        if (static::trashes()) {
            static::bootFrameworkSoftDeletes();
        }
    }

    public function initializeSoftDeletes(): void
    {
        if (static::trashes()) {
            $this->initializeFrameworkSoftDeletes();
        }
    }

    protected function performDeleteOnModel(): void
    {
        if (! static::trashes()) {
            parent::performDeleteOnModel();

            return;
        }

        $this->performFrameworkSoftDeleteOnModel();
    }

    public static function trashes(): bool
    {
        return Ownership::hasTrash(static::trashTable());
    }

    protected static function trashTable(): string
    {
        $table = (new ReflectionClass(static::class))->getDefaultProperties()['table'] ?? null;

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return Str::snake(Str::pluralStudly(class_basename(static::class)));
    }
}
