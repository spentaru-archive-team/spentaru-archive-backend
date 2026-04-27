<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Archive extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'year',
        'notes',
        'category_id',
        'subcategory_id',
        'uploader',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArchiveCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class, 'subcategory_id');
    }

    public function files(): HasOne
    {
        return $this->hasOne(ArchiveFile::class);
    }

    public function physicalLocation(): HasOne
    {
        return $this->hasOne(ArchivePhysicalLocation::class);
    }

    public function ocrText(): HasOne
    {
        return $this->hasOne(OcrText::class);
    }

}
