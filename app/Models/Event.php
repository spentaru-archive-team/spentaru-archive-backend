<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Event extends Model
{
    use HasFactory, Sortable, Searchable, Filterable {
        Sortable::getTableColumns insteadof Filterable;
        Sortable::realName insteadof Filterable;
    }

    protected $fillable = [
        'title',
        'user_id',
        'description',
        'date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

        public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class);
    }
}
