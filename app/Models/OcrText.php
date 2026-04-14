<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrText extends Model
{
    use HasFactory;

    protected $fillable = [
        'archive_id',
        'extracted_text',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class, 'archive_id');
    }
}
