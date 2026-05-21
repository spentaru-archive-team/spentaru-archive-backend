<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'archive_id',
        'file_name',
        'file_size',
        'file_type',
        'vector_id',
        'extraction_status',
    ];

    protected $appends = ['file_url'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_name) {
            return null;
        }

        return '/storage/uploads/'.$this->file_name;
    }

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }
}
