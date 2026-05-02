<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'organization_name' => ['required', 'string', 'max:255'],
            'country_code'             => ['required', 'string', 'size:2', 'in:SA,AE,QA,OM,BH,KW,IN'],
            'registration_source'      => ['nullable', 'string', 'in:web,mobile_ios,mobile_android,api,invitation'],
            'utm_source'               => ['nullable', 'string', 'max:100'],
            'utm_medium'               => ['nullable', 'string', 'max:100'],
            'utm_campaign'             => ['nullable', 'string', 'max:150'],
            'utm_term'                 => ['nullable', 'string', 'max:150'],
            'utm_content'              => ['nullable', 'string', 'max:150'],
            'referral_code'            => ['nullable', 'string', 'max:50'],
            'registration_device_type' => ['nullable', 'string', 'in:web,mobile'],
            'invited_by_user_id'       => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'country_code.in' => 'Currently we only support Saudi Arabia (SA), UAE (AE), Qatar (QA), Oman (OM), Bahrain (BH), Kuwait (KW), and India (IN).',
        ];
    }
}
