<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCabinetRequest extends FormRequest
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
            'cabinet_number' => 'required|integer|min:1|unique:cabinets,cabinet_number',
            'name' => 'required|string|max:255|unique:cabinets,name',

            'racks' => ['required', 'array', 'min:1'],
            'racks.*.id' => ['nullable', 'integer'],
            'racks.*.rack_number' => ['required', 'integer', 'min:1', 'max:10', 'distinct'],
            'racks.*.capacity' => ['required', 'integer', 'min:0'],
            'racks.*.used_capacity' => [
                'required',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) {
                    preg_match('/racks\.(\d+)\.used_capacity/', $attribute, $matches);

                    $index = $matches[1] ?? null;
                    $capacity = $this->input("racks.$index.capacity");

                    if ($index !== null && (int) $value > (int) $capacity) {
                        $fail('Used capacity tidak boleh lebih besar dari capacity.');
                    }
                },
            ],
        ];
    }
}
