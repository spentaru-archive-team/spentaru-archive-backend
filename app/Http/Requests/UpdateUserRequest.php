<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $rules = [
            'name' => 'string|max:200|required',
            'subject' => 'string|max:200|required',
            'position' => 'string|max:200|required',
            'username' => 'string|max:120|required|unique:users,username,'.$this->route('id'),
            'password' => ['string', 'min:8', 'nullable', Password::min(8)->letters()->numbers()->mixedCase()],
        ];

        if ($this->user()?->role === 'admin' && $this->route('id') !== (string) $this->user()->id) {
            $rules['role'] = 'sometimes|required|in:guru,admin';
        }

        return $rules;
    }
}
