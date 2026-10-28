<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create($request->validated());

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    // GET /api/orders?email=...  -> customer order history
    public function index(Request $request): AnonymousResourceCollection
    {
        $email = Str::lower($request->validate(['email' => ['required', 'email']])['email']);

        $customer = Customer::where('email', $email)->firstOrFail();

        return OrderResource::collection(
            $customer->orders()->with('customer', 'items.product')->latest('id')->paginate(15)
        );
    }
}