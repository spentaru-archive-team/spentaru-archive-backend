<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class ArchiveCategory extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'description',
        'has_subcategory',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'has_subcategory' => 'boolean',
        ];
    }

    public function setHasSubcategoryAttribute($value): void
    {
        $this->attributes['has_subcategory'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    public function toSearchableArray()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class, 'category_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class, 'category_id');
    }
}
