<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivePhysicalLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'archive_id',
        'cabinet_id',
        'rack_id',
        'slot_number',
        'label_code',
        'notes',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
