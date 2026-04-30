<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class ArchivePhysicalLocation extends Model
{
    use Filterable, HasFactory, Searchable, Sortable {
        Sortable::getTableColumns insteadof Filterable;
        Sortable::realName insteadof Filterable;
    }

    protected $fillable = [
        'archive_id',
        'cabinet_id',
        'rack_id',
        'slot_number',
        'label_code',
        'notes',
    ];

    public function toSearchableArray(): array
    {
        return [
            'notes' => $this->notes,
            'label_code' => $this->label_code
        ];
    }

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
