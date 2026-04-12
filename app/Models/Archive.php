<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Archive extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'archive_code',
        'notes',
        'category_id',
        'created_by',
        'has_hardcopy',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'has_hardcopy' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArchiveCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ArchiveFile::class);
    }

    public function physicalLocation(): HasOne
    {
        return $this->hasOne(PhysicalLocation::class);
    }
}
