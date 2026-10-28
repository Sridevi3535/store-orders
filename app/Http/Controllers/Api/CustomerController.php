<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    // UI-la email type pannina name auto-fill panna
    public function lookup(Request $request): JsonResponse
    {
        $email = Str::lower($request->validate(['email' => ['required', 'email']])['email']);

        $customer = Customer::where('email', $email)->first();
        abort_if(! $customer, 404, 'Customer not found.');

        return response()->json(['data' => $customer->only('id', 'name', 'email')]);
    }
}