<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyKeyConflict;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->latest('id')
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $request->user()->id, Response::HTTP_NOT_FOUND);

        return new OrderResource($order->load('items'));
    }

    public function store(StoreOrderRequest $request): JsonResponse|OrderResource
    {
        try {
            $result = $this->orders->create(
                $request->user(),
                $request->validated('items'),
                $request->validated('idempotency_key'),
            );
        } catch (IdempotencyKeyConflict $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $response = new OrderResource($result->order);

        if ($result->replayed) {
            return $response;
        }

        return $response
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
