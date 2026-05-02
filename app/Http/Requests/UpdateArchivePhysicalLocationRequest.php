<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArchivePhysicalLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $archiveId = $this->route('id');
        $currentLocation = \App\Models\ArchivePhysicalLocation::where('archive_id', $archiveId)->first();
        $currentRackId = $currentLocation?->rack_id;

        return [
            'cabinet_id' => 'sometimes|required|integer|min:0|exists:cabinets,id',
            'rack_id' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'exists:racks,id',
                function ($attribute, $value, $fail) use ($currentRackId) {
                    if ($value === $currentRackId) {
                        return;
                    }
                    $rack = \App\Models\Rack::find($value);
                    if ($rack && $rack->used_capacity >= $rack->capacity) {
                        $fail('Rak tidak cukup kapasitas. Silakan pilih rak lain.');
                    }
                },
            ],
            'slot_number' => 'sometimes|required|integer|min:1',
            'notes_physical_location' => 'sometimes|nullable|string',
        ];
    }
}
