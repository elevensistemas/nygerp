<?php

namespace App\Http\Controllers;

use App\Models\Transportista;
use App\Models\TransportistaPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportistaPaymentMethodController extends Controller
{
    public function index(Transportista $transportista): JsonResponse
    {
        $methods = $transportista->paymentMethods()
            ->with('bank:id,name')
            ->orderByDesc('is_default')
            ->orderBy('description')
            ->orderBy('id')
            ->get()
            ->map(function (TransportistaPaymentMethod $method) {
                return [
                    'id' => $method->id,
                    'bank_id' => $method->bank_id,
                    'bank_name' => optional($method->bank)->name,
                    'cbu' => $method->cbu,
                    'account_number' => $method->account_number,
                    'description' => $method->description,
                    'is_default' => $method->is_default,
                    'tags' => $method->tags ?? [],
                ];
            });

        return response()->json(['data' => $methods]);
    }

    public function store(Request $request, Transportista $transportista): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data['is_default']) {
            $transportista->paymentMethods()->update(['is_default' => false]);
        }

        $method = $transportista->paymentMethods()->create($data);
        $this->syncLegacyDefault($transportista, $method);

        return response()->json([
            'ok' => true,
            'message' => 'Medio de pago agregado.',
            'data' => $method->load('bank:id,name'),
        ], 201);
    }

    public function update(Request $request, TransportistaPaymentMethod $paymentMethod): JsonResponse
    {
        $transportista = $paymentMethod->transportista;
        $data = $this->validatedData($request);

        if ($data['is_default']) {
            $transportista->paymentMethods()
                ->where('id', '!=', $paymentMethod->id)
                ->update(['is_default' => false]);
        }

        $paymentMethod->update($data);
        $this->syncLegacyDefault($transportista, $paymentMethod->fresh());

        return response()->json([
            'ok' => true,
            'message' => 'Medio de pago actualizado.',
            'data' => $paymentMethod->fresh()->load('bank:id,name'),
        ]);
    }

    public function destroy(TransportistaPaymentMethod $paymentMethod): JsonResponse
    {
        $transportista = $paymentMethod->transportista;
        $wasDefault = $paymentMethod->is_default;

        $paymentMethod->delete();

        if ($wasDefault) {
            $newDefault = $transportista->paymentMethods()->orderBy('id')->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
            $this->syncLegacyDefault($transportista, $newDefault);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Medio de pago eliminado.',
        ]);
    }

    public function setDefault(TransportistaPaymentMethod $paymentMethod): JsonResponse
    {
        $transportista = $paymentMethod->transportista;
        $transportista->paymentMethods()
            ->where('id', '!=', $paymentMethod->id)
            ->update(['is_default' => false]);

        $paymentMethod->update(['is_default' => true]);
        $this->syncLegacyDefault($transportista, $paymentMethod->fresh());

        return response()->json([
            'ok' => true,
            'message' => 'Medio de pago marcado como por defecto.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'bank_id' => ['nullable', 'exists:banks,id'],
            'cbu' => ['required', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'tags' => ['nullable'],
        ]);

        $data['is_default'] = $request->boolean('is_default', false);

        if (isset($data['tags'])) {
            if (is_string($data['tags'])) {
                // If the frontend sends it as a comma-separated string
                $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
            } elseif (!is_array($data['tags'])) {
                $data['tags'] = [];
            }
        } else {
            $data['tags'] = [];
        }

        return $data;
    }

    private function syncLegacyDefault(Transportista $transportista, ?TransportistaPaymentMethod $method): void
    {
        $transportista->bank_id = $method ? $method->bank_id : null;
        $transportista->cbu = $method ? $method->cbu : null;
        $transportista->account_number = $method ? $method->account_number : null;
        $transportista->save();
    }
}
