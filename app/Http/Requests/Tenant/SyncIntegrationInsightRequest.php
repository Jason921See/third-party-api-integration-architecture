<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncIntegrationInsightRequest extends FormRequest
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
        return [
            'provider'  => 'required|string|in:facebook,google,tiktok',
            'level'     => 'required|string|in:campaign,adset,ad',
            'fields'    => 'nullable|array',
            'fields.*'  => 'string|in:impressions,clicks,reach,spend,cpc,cpm,ctr,cpp,frequency,actions,action_values',
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ];
    }
}
