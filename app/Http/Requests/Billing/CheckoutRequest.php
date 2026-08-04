<?php

namespace App\Http\Requests\Billing;

use App\Support\Billing\BillingPlans;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $plans = app(BillingPlans::class);

        return [
            'plan' => ['required', 'string', Rule::in([...$plans->keys(), ...$plans->priceIds()])],
            'success_url' => ['required', 'url', 'max:2048'],
            'cancel_url' => ['required', 'url', 'max:2048'],
            'allow_promotion_codes' => ['sometimes', 'boolean'],
        ];
    }
}
