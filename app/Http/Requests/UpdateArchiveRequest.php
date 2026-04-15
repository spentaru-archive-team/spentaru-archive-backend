<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArchiveRequest extends FormRequest
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
            'title' => 'sometimes|required|string',
            'year' => 'sometimes|required|integer',
            'notes' => 'sometimes|nullable|string',
            'file' => 'sometimes|required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'event_id' => 'sometimes|nullable|integer|min:0|exists:events,id',
            'category_id' => 'sometimes|required|integer|min:0|exists:archive_categories,id',
            'subcategory_id' => 'sometimes|nullable|integer|min:0|exists:subcategories,id',

            'cabinet_id' => 'sometimes|required|integer|min:0|exists:cabinets,id',
            'rack_id' => 'sometimes|required|integer|min:0|exists:racks,id',
            'slot_number' => 'sometimes|required|integer|min:1',
            'notes_physical_location' => 'sometimes|nullable|string',
        ];

    }
}
