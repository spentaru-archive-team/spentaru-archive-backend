<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabinet extends Model
{
    use HasFactory;

    protected $fillable = [
        'cabinet_number',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    public function storageRules(): HasMany
    {
        return $this->hasMany(ArchiveStorageRule::class);
    }
}
