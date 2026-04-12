<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'archive_id',
        'cabinet_no',
        'rack_no',
        'drawer_no',
        'box_no',
        'label_code',
        'notes',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }
}
