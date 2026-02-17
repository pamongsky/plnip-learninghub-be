<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Super-admin can update any user, admins can update learners only
        $user = $this->user();
        $targetUser = $this->route('user');

        if (!$user) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            // Admin can't modify super-admin or other admins
            return $targetUser && !$targetUser->hasRole(['super-admin', 'admin']);
        }

        // Users can only update themselves
        return $user->id === $targetUser?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
            'email' => ['sometimes', 'email:rfc,dns', 'unique:users,email,' . $userId, 'max:255'],
            'employee_id' => ['sometimes', 'nullable', 'string', 'unique:users,employee_id,' . $userId, 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+62|62|0)[0-9]{9,12}$/'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'string', 'in:super-admin,admin,instructor,learner'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', Password::min(8)->letters()->numbers()->symbols()],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Nama minimal 3 karakter',
            'name.max' => 'Nama maksimal 255 karakter',
            'name.regex' => 'Nama hanya boleh mengandung huruf, spasi, dan titik',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'employee_id.unique' => 'NIP sudah terdaftar',
            'employee_id.regex' => 'NIP hanya boleh mengandung huruf kapital, angka, dan tanda hubung',
            'phone.regex' => 'Format nomor telepon tidak valid (contoh: 08123456789 atau +628123456789)',
            'role.in' => 'Role tidak valid',
            'password.min' => 'Password minimal 8 karakter',
            'reason.max' => 'Alasan maksimal 500 karakter',
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
            'is_active' => 'Status Aktif',
            'reason' => 'Alasan',
        ];
    }
}
