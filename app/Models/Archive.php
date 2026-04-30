<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

class Archive extends Model
{
    use HasFactory, Searchable, Sortable, Filterable {
        Sortable::getTableColumns insteadof Filterable;
        Sortable::realName insteadof Filterable;
    }

    protected $fillable = [
        'event_id',
        'title',
        'year',
        'notes',
        'category_id',
        'subcategory_id',
        'uploader',
        'retention_due_date',
        'retention_status',
        'retention_decided_at',
        'retention_decided_by',
        'retention_note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'retention_due_date' => 'date',
            'retention_decided_at' => 'datetime',
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'notes' => $this->notes
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

    public function retentionDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retention_decided_by');
    }
}
