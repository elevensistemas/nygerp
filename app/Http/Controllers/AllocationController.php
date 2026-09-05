<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\ScheduledInstallment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'target_document_id' => 'required|exists:documents,id',
            'installment_id' => 'required|exists:scheduled_installments,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        // Try to find an existing allocation for the same OP -> target pair.
        // Since the DB unique constraint was removed, we merge duplicates here to avoid multiple rows
        // representing the same logical allocation.
        $existing = Allocation::where('document_id', $validated['document_id'])
            ->where('target_document_id', $validated['target_document_id'])
            ->first();

        $amount = round((float) $validated['amount'], 2);

        if ($existing) {
            // increment existing amount
            $existing->increment('amount', $amount);
            // if installment_id is provided and existing has none, set it
            if (empty($existing->installment_id) && !empty($validated['installment_id'])) {
                $existing->installment_id = $validated['installment_id'];
                $existing->save();
            }
            $alloc = $existing;
        } else {
            $alloc = Allocation::create([
                'document_id' => $validated['document_id'],
                'target_document_id' => $validated['target_document_id'],
                'installment_id' => $validated['installment_id'],
                'amount' => $amount,
            ]);
        }

        // Update the installment remaining amount
        $installment = ScheduledInstallment::lockForUpdate()->find($validated['installment_id']);
        if ($installment) {
            $remaining = round((float) $installment->amount - (float) $validated['amount'], 2);
            if ($remaining <= 0.01) {
                $installment->update([
                    'amount' => max($remaining, 0),
                    'paid' => true,
                    'paid_at' => Carbon::now(),
                ]);
            } else {
                $installment->update([
                    'amount' => $remaining,
                    'paid' => false,
                ]);
            }
        }

        return redirect()->route('documents.show', $validated['document_id'])->with('ok', 'Imputación creada correctamente.');
    }
}
