<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Inject PaymentService through the constructor.
     */
    public function __construct(protected PaymentService $paymentService)
    {
        //
    }

    public function index(Request $request)
    {
        $payments = $this->paymentService->getUserPayments($request->user());

        return $this->successResponse(
            PaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    public function balance(Request $request)
    {
        $balance = $this->paymentService->calculateBalance($request->user());

        return $this->successResponse([
            'balance' => $balance,
        ], 'Balance calculated successfully');
    }

    public function store(StorePaymentRequest $request)
    {
        $payment = $this->paymentService->createPayment($request->user(), $request->validated('amount'));

        return $this->successResponse(
            new PaymentResource($payment),
            'Payment initiated successfully',
            201
        );
    }
}
