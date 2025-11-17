<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReceiptRequest;
use App\Services\ShipmentReceiptService;
use Illuminate\Support\Facades\Auth;

class ReceiptController extends Controller
{
    public function store($shipmentId, CreateReceiptRequest $request)
    {
        $receipt = app(ShipmentReceiptService::class)
            ->processReceipt($shipmentId, $request->validated(), Auth::id());

        return redirect()
            ->route('admin.shipments.show', $shipmentId)
            ->with('success', 'Receipt saved successfully (ID: ' . $receipt->id . ').');
    }
}
