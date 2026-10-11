<?php

namespace App\Http\Requests\Church;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'roles.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $churchId = $this->route('church')->id;

        return [
            'name' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => [$this->isMethod('post') ? 'required' : 'nullable', 'email', 'max:190'],
            'access' => ['required', Rule::in(['admin', 'role'])],
            'church_role_id' => ['required_if:access,role', 'nullable', Rule::exists('church_roles', 'id')->where('church_id', $churchId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $churchId)],
            'title' => ['nullable', 'string', 'max:80'],
        ];
    }
}
