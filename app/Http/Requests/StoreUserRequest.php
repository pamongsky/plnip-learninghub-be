<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super-admin can create users manually
        return $this->user() && $this->user()->hasRole('super-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
            'email' => ['required', 'email:rfc,dns', 'unique:users,email', 'max:255'],
            'employee_id' => ['nullable', 'string', 'unique:users,employee_id', 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)[0-9]{9,12}$/'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'role' => ['required', 'string', 'in:super-admin,admin,instructor,learner'],
            'password' => ['nullable', Password::min(8)->letters()->numbers()->symbols()],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi',
            'name.min' => 'Nama minimal 3 karakter',
            'name.max' => 'Nama maksimal 255 karakter',
            'name.regex' => 'Nama hanya boleh mengandung huruf, spasi, dan titik',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'employee_id.unique' => 'NIP sudah terdaftar',
            'employee_id.regex' => 'NIP hanya boleh mengandung huruf kapital, angka, dan tanda hubung',
            'phone.regex' => 'Format nomor telepon tidak valid (contoh: 08123456789 atau +628123456789)',
            'role.required' => 'Role wajib dipilih',
            'role.in' => 'Role tidak valid',
            'password.min' => 'Password minimal 8 karakter',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'NIP',
            'phone' => 'Nomor Telepon',
            'department' => 'Departemen',
            'position' => 'Jabatan',
        ];
    }
}
