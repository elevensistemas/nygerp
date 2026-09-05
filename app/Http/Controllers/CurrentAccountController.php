<?php
// app/Http/Controllers/CurrentAccountController.php

namespace App\Http\Controllers;

use App\Models\{Document, Party, Supplier};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class CurrentAccountController extends Controller
{
    /** Listado de clientes */
    public function indexCustomers()
    {
        $parties = Party::where('role', 'customer')
            ->orderBy('name')
            ->paginate(30);

        return view('cc.index', [
            'title'   => 'Cuenta Corriente - Clientes',
            'role'    => 'customer',
            'parties' => $parties,
        ]);
    }

    /** Listado de proveedores */
    public function indexSuppliers()
    {
        $parties = Supplier::orderBy('name')->paginate(30);

        return view('cc.index', [
            'title'   => 'Cuenta Corriente - Proveedores',
            'role'    => 'supplier',
            'parties' => $parties,
        ]);
    }

    /**
     * Cuenta corriente para un tercero (cliente o proveedor).
     */
    public function show(string $role, int $partyId)
    {
        if ($role === 'supplier') {
            $party = Supplier::findOrFail($partyId);
        } else {
            $party = Party::where('role', $role)->findOrFail($partyId);
        }

        $docs = Document::query()
            ->with(['allocations.target'])
            ->where('affects_current_account', true)
            ->where(function ($q) use ($role, $party) {
                if ($role === 'supplier') {
                    $q->where('scope', 'purchase')
                        ->where(function ($q2) use ($party) {
                            $q2->where('supplier_id', $party->id);

                            if (Schema::hasColumn('documents', 'party_id')) {
                                $q2->orWhere('party_id', $party->id);
                            }
                        });
                } else {
                    $q->where('scope', 'sale')
                        ->where(function ($q2) use ($party) {
                            if (Schema::hasColumn('documents', 'customer_id')) {
                                $q2->where('customer_id', $party->id);
                            } elseif (Schema::hasColumn('documents', 'party_id')) {
                                $q2->where('party_id', $party->id);
                            } else {
                                $q2->whereRaw('0 = 1');
                            }
                        });
                }
            })
            ->orderBy('issue_date')
            ->get();

        $movs = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($docs as $doc) {
            $sign = $this->signFor($role, $doc->doctype);

            $movs[] = [
                'date'  => Carbon::parse($doc->issue_date)->format('Y-m-d'),
                'doc'   => strtoupper($doc->doctype) . ' ' . $doc->number,
                'debe'  => $sign > 0 ? (float) $doc->total : 0.0,
                'haber' => $sign < 0 ? (float) $doc->total : 0.0,
                'id'    => $doc->id,
                'doctype' => $doc->doctype,
                'sign'    => $sign,
            ];

            $totalDebe  += $sign > 0 ? (float) $doc->total : 0.0;
            $totalHaber += $sign < 0 ? (float) $doc->total : 0.0;

            foreach ($doc->allocations as $allocation) {
                $movs[] = [
                    'date'  => Carbon::parse($doc->issue_date)->format('Y-m-d'),
                    'doc'   => 'IMPUTA a ' . ($allocation->target->number ?? ''),
                    'debe'  => 0.0,
                    'haber' => 0.0,
                    'id'    => $doc->id,
                ];
            }
        }

        $running = 0.0;
        foreach ($movs as &$movement) {
            $running += ($movement['debe'] - $movement['haber']);
            $movement['saldo'] = round($running, 2);
        }
        unset($movement);

        return view('cc.show', [
            'party' => $party,
            'role'  => $role,
            'movs'  => $movs,
            'totals' => [
                'debe'   => round($totalDebe, 2),
                'haber'  => round($totalHaber, 2),
                'saldo'  => round($totalDebe - $totalHaber, 2),
            ],
        ]);
    }

    /**
     * Signo por rol y tipo de documento.
     * Convencion:
     *  - DEBE (+): aumenta deuda del tercero
     *  - HABER (-): reduce deuda del tercero
     */
    private function signFor(string $role, string $doctype): int
    {
        $doctype = strtolower($doctype);

        if ($role === 'supplier') {
            if (in_array($doctype, ['invoice', 'debit_note'], true)) {
                return 1;
            }

            if (in_array($doctype, ['credit_note', 'payment_order'], true)) {
                return -1;
            }

            return 0;
        }

        if (in_array($doctype, ['invoice', 'debit_note'], true)) {
            return 1;
        }

        if (in_array($doctype, ['credit_note', 'receipt'], true)) {
            return -1;
        }

        return 0;
    }
}
