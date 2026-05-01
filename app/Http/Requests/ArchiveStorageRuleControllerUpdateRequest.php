<?php

namespace App\Http\Requests;

use App\Models\ArchiveCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveStorageRuleControllerUpdateRequest extends FormRequest
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
        $ruleId = $this->route('id');
        // sesuaikan dengan nama parameter route kamu

        $categoryId = $this->input('category_id');

        $category = $categoryId
            ? ArchiveCategory::find($categoryId)
            : null;

        return [
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:archive_categories,id',
            ],

            'subcategory_id' => [
                'sometimes',
                'nullable',
                'integer',

                function ($attribute, $value, $fail) use ($category) {
                    if (! $category) {
                        return;
                    }

                    if ($category->has_subcategory && empty($value)) {
                        $fail('Subkategori wajib diisi');
                    }

                    if (! $category->has_subcategory && ! is_null($value)) {
                        $fail('Subkategori harus kosong untuk kategori ini, karena kategori tidak mempunyai subkategori');
                    }
                },

                Rule::exists('subcategories', 'id')
                    ->where('category_id', $categoryId),
            ],

            'cabinet_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:cabinets,id',
            ],

            'priority' => [
                'sometimes',
                'required',
                'integer',
                Rule::unique('archive_storage_rules', 'priority')
                    ->ignore($ruleId),
            ],
        ];
    }
}
