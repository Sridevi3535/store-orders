<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('customer_email')) {
            $this->merge([
                'customer_email' => Str::lower(trim((string) $this->input('customer_email'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            // Puthu customer-na name kandippa venum; already irundha optional
            'customer_name' => [
                Rule::requiredIf(fn () => ! Customer::where('email', $this->input('customer_email'))->exists()),
                'nullable', 'string', 'max:255',
            ],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'amount_given' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}