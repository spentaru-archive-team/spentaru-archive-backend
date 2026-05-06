<?php

namespace App\Http\Requests;

use App\Models\ArchivePhysicalLocation;
use App\Models\Rack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
                    $rack = Rack::find($value);
                    if ($rack && $rack->used_capacity >= $rack->capacity) {
                        $fail('Rak tidak cukup kapasitas. Silakan pilih rak lain.');
                    }
                },
            ],
            'slot_number' => 'sometimes|required|integer|min:1',
            'notes_physical_location' => 'sometimes|nullable|string',
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
        $archiveId = (int) $this->route('id');

        return [
            function (Validator $validator) use ($archiveId) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $currentLocation = ArchivePhysicalLocation::where('archive_id', $archiveId)->first();

                if (! $currentLocation) {
                    return;
                }

                $targetRackId = (int) ($this->rack_id ?? $currentLocation->rack_id);
                $targetCabinetId = (int) ($this->cabinet_id ?? $currentLocation->cabinet_id);
                $targetSlotNumber = (int) ($this->slot_number ?? $currentLocation->slot_number);
                $row_rack = Rack::find($targetRackId);

                if ($row_rack && $targetSlotNumber > $row_rack->capacity) {
                    $validator->errors()->add(
                        'slot_number',
                        'nilai slot tidak boleh lebih besar dari kapasitas rak'
                    );
                }

                if ($row_rack && (int) $row_rack->cabinet_id !== $targetCabinetId) {
                    $validator->errors()->add(
                        'rack_id',
                        'Rak tidak berada di lemari yang dipilih'
                    );
                }

                if (
                    $row_rack
                    && ArchivePhysicalLocation::where('rack_id', $targetRackId)
                        ->where('slot_number', $targetSlotNumber)
                        ->where('archive_id', '!=', $archiveId)
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
