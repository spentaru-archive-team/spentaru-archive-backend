<?php

namespace App\Http\Requests;

use App\Models\ArchiveCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveStorageRuleControllerStoreRequest extends FormRequest
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
        $category = ArchiveCategory::find($this->category_id);

        return [
            'category_id' => 'required|exists:archive_categories,id|integer',
            'subcategory_id' => ['nullable', 'integer',
                function ($attribute, $value, $fail) use ($category) {
                    if ($category->has_subcategory && empty($value)) {
                        $fail('Subkategori wajib diisi');
                    }
                    if ($category && ! $category->has_subcategory && ! is_null($value)) {
                        $fail('Subkategori harus kosong untuk kategori ini, karena kategori tidak mempunyai subkategori');
                    }
                },
                Rule::exists('subcategories', 'id')->where('category_id', $this->category_id),
            ],
            'cabinet_id' => 'required|integer|exists:cabinets,id',
            'priority' => 'required|integer|unique:archive_storage_rules,priority',
        ];
    }
}
