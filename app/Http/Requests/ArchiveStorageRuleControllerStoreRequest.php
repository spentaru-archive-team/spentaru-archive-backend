<?php

namespace App\Http\Requests;

use App\Models\ArchiveCategory;
use App\Models\ArchiveStorageRule;
use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $categoryId = $this->input('category_id');
        $subcategoryId = $this->input('subcategory_id');
        $category = ArchiveCategory::find($categoryId);

        return [
            'category_id' => 'required|exists:archive_categories,id|integer',
            'subcategory_id' => ['nullable', 'integer',
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
                    ->where(fn ($query) => $query->where('category_id', $categoryId)),
            ],
            'cabinet_id' => 'required|integer|exists:cabinets,id',
            'priority' => [
                'required',
                'integer',
                Rule::unique('archive_storage_rules', 'priority')
                    ->where(function ($query) use ($categoryId, $subcategoryId) {
                        $query->where('category_id', $categoryId);

                        if ($subcategoryId === null) {
                            $query->whereNull('subcategory_id');
                        } else {
                            $query->where('subcategory_id', $subcategoryId);
                        }
                    }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $categoryId = $this->input('category_id');
            $subcategoryId = $this->input('subcategory_id');
            $priority = $this->input('priority');
            $category = ArchiveCategory::find($categoryId);

            if (! $category) {
                return;
            }

            if ($category->has_subcategory && $subcategoryId === null) {
                $validator->errors()->add('subcategory_id', 'Subkategori wajib diisi');

                return;
            }

            if (! $category->has_subcategory && $subcategoryId !== null) {
                $validator->errors()->add('subcategory_id', 'Subkategori harus kosong untuk kategori ini, karena kategori tidak mempunyai subkategori');

                return;
            }

            if ($subcategoryId !== null && ! Subcategory::whereKey($subcategoryId)->where('category_id', $categoryId)->exists()) {
                $validator->errors()->add('subcategory_id', 'Subkategori tidak sesuai dengan kategori');

                return;
            }

            $priorityExists = ArchiveStorageRule::query()
                ->where('category_id', $categoryId)
                ->when(
                    $subcategoryId === null,
                    fn ($query) => $query->whereNull('subcategory_id'),
                    fn ($query) => $query->where('subcategory_id', $subcategoryId),
                )
                ->where('priority', $priority)
                ->exists();

            if ($priorityExists) {
                $validator->errors()->add('priority', 'Priority sudah digunakan untuk kategori dan subkategori ini.');
            }
        });
    }
}
