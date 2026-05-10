<?php

namespace App\Http\Requests;

use App\Models\ArchivePhysicalLocation;
use App\Models\Rack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'rack_id' => [
                'required',
                'integer',
                'min:0',
                'exists:racks,id',
                function ($attribute, $value, $fail) {
                    $rack = Rack::find($value);
                    if ($rack && $rack->used_capacity >= $rack->capacity) {
                        $fail('Rak tidak cukup kapasitas. Silakan pilih rak lain.');
                    }
                },
            ],
            'slot_number' => 'required|integer|min:1',
            'notes_physical_location' => 'nullable|string',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $row_rack = Rack::find($this->rack_id);

                if ($row_rack && $this->slot_number > $row_rack->capacity) {
                    $validator->errors()->add(
                        'slot_number',
                        'nilai slot tidak boleh lebih besar dari kapasitas rak'
                    );
                }

                if ($row_rack && (int) $row_rack->cabinet_id !== (int) $this->cabinet_id) {
                    $validator->errors()->add(
                        'rack_id',
                        'Rak tidak berada di lemari yang dipilih'
                    );
                }

                if (
                    $row_rack
                    && ArchivePhysicalLocation::where('rack_id', $this->rack_id)
                        ->where('slot_number', $this->slot_number)
                        ->exists()
                ) {
                    $validator->errors()->add(
                        'slot_number',
                        'Slot pada rak tersebut sudah terpakai'
                    );
                }
            },
        ];
    }
}
