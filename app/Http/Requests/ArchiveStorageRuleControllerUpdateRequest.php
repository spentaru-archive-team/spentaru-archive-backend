<?php

namespace App\Http\Requests;

use App\Models\ArchiveCategory;
use App\Models\ArchiveStorageRule;
use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $rule = ArchiveStorageRule::find($ruleId);

        $categoryId = $this->has('category_id')
            ? $this->input('category_id')
            : $rule?->category_id;

        $subcategoryId = $this->has('subcategory_id')
            ? $this->input('subcategory_id')
            : $rule?->subcategory_id;

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
                    ->where(fn($query) => $query->where('category_id', $categoryId)),
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
                    ->where(function ($query) use ($categoryId, $subcategoryId) {
                        $query->where('category_id', $categoryId);
                        $query->where('subcategory_unique_key', $subcategoryId ?? 0);
                    })
                    ->ignore($ruleId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            if (! $this->hasAny(['category_id', 'subcategory_id', 'priority'])) {
                return;
            }

            $ruleId = $this->route('id');
            $rule = ArchiveStorageRule::find($ruleId);

            if (! $rule) {
                return;
            }

            $categoryId = $this->has('category_id')
                ? $this->input('category_id')
                : $rule->category_id;

            $subcategoryId = $this->has('subcategory_id')
                ? $this->input('subcategory_id')
                : $rule->subcategory_id;
            $subcategoryUniqueKey = $subcategoryId ?? 0;

            $priority = $this->has('priority')
                ? $this->input('priority')
                : $rule->priority;

            $category = ArchiveCategory::find($categoryId);

            if ($category && $category->has_subcategory && empty($subcategoryId)) {
                $validator->errors()->add('subcategory_id', 'Subkategori wajib diisi');

                return;
            }

            if ($category && ! $category->has_subcategory && ! is_null($subcategoryId)) {
                $validator->errors()->add('subcategory_id', 'Subkategori harus kosong untuk kategori ini, karena kategori tidak mempunyai subkategori');

                return;
            }

            if (
                ! is_null($subcategoryId)
                && ! Subcategory::whereKey($subcategoryId)->where('category_id', $categoryId)->exists()
            ) {
                $validator->errors()->add('subcategory_id', 'Subkategori tidak valid untuk kategori ini');

                return;
            }

            $duplicateExists = ArchiveStorageRule::query()
                ->where('category_id', $categoryId)
                ->where('subcategory_unique_key', $subcategoryUniqueKey)
                ->where('priority', $priority)
                ->whereKeyNot($ruleId)
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('priority', 'Priority sudah digunakan untuk kategori dan subkategori ini');
            }
        });
    }
}
