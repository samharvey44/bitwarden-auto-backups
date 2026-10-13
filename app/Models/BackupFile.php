<?php

namespace App\Models;

use App\Enums\BackupFormat;
use Database\Factories\BackupFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['format', 'disk', 'path', 'size_bytes', 'password'])]
#[Hidden(['password'])]
class BackupFile extends Model
{
    /** @use HasFactory<BackupFileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => BackupFormat::class,
            'size_bytes' => 'integer',
            'password' => 'encrypted',
        ];
    }

    /**
     * Get the backup the file belongs to.
     *
     * @return BelongsTo<Backup, $this>
     */
    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }
}
