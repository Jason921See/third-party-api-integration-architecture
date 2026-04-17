<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\IntegrationProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\IntegrationHelper;

class ConnectIntegrationRequest extends FormRequest
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
        $provider = IntegrationProvider::where('slug', $this->provider)->first();

        return [
            'provider' => [
                'required',
                'string',
                'in:facebook,google,tiktok',
            ],

            'credentials' => ['required', 'array'],

            'credentials.access_token' => ['required', 'string'],

            'credentials.ad_account_id' => [
                'required',
                'string',
                Rule::unique('integrations', 'external_user_id')
                    ->where(fn($q) => $q->where('ip_id', $provider?->id)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('credentials.ad_account_id')) {
            $this->merge([
                'credentials' => array_merge(
                    $this->input('credentials', []),
                    [
                        'ad_account_id' => IntegrationHelper::normalizeAdAccountId(
                            $this->input('credentials.ad_account_id')
                        ),
                    ]
                )
            ]);
        }
    }
}
