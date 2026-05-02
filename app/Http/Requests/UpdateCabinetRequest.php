<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCabinetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cabinetId = $this->route('id');

        return [
            'cabinet_number' => 'sometimes|required|integer|min:1|unique:cabinets,cabinet_number,'.$cabinetId,
            'name' => 'sometimes|required|string|max:255|unique:cabinets,name,'.$cabinetId,

            'racks' => ['sometimes', 'required', 'array', 'min:1'],
            'racks.*.id' => ['nullable', 'integer'],
            'racks.*.rack_number' => ['required_with:racks', 'integer', 'min:1', 'distinct'],
            'racks.*.capacity' => ['required_with:racks', 'integer', 'min:0'],
            'racks.*.used_capacity' => [
                'required_with:racks',
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
