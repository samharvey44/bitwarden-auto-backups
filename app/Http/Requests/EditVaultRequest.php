<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EditVaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only the vault's owner may change its settings.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('vault'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
