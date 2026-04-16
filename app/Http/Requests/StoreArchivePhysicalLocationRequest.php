<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArchivePhysicalLocationRequest extends FormRequest
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
        return [
            'cabinet_id' => 'required|integer|min:0|exists:cabinets,id',
            'rack_id' => 'required|integer|min:0|exists:racks,id',
            'slot_number' => 'required|integer|min:1',
            'notes_physical_location' => 'nullable|string',
        ];
    }
}
