<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sql = <<<SQL
SELECT
    'Liquidación' as tipo_discrepancia,
    DATE(r.driver_contact_request_date) as fecha_reclamo,
    t.name as chofer,
    CONCAT('Recibo #', r.id) as referencia,
    r.driver_contact_request_comment as comentario,
    DATE(r.fecha_emision) as fecha_documento
FROM recibos_chofer r
JOIN transportistas t ON t.id = r.transportista_id
WHERE r.driver_contact_request_date IS NOT NULL
  AND DATE(r.driver_contact_request_date) >= :desde1
  AND DATE(r.driver_contact_request_date) <= :hasta1
  AND (:tipo_reclamo1 = 'todos' OR :tipo_reclamo2 = 'liquidacion')

UNION ALL

SELECT
    'Día Faltante' as tipo_discrepancia,
    DATE(i.driver_status_date) as fecha_reclamo,
    t.name as chofer,
    CONCAT('Ítem: ', COALESCE(i.concepto, 'S/F'), ' (Recibo #', r.id, ')') as referencia,
    i.driver_comment as comentario,
    DATE(r.fecha_emision) as fecha_documento
FROM recibo_chofer_items i
JOIN recibos_chofer r ON r.id = i.recibo_chofer_id
JOIN transportistas t ON t.id = r.transportista_id
WHERE i.driver_status = 'disputado'
  AND DATE(i.driver_status_date) >= :desde2
  AND DATE(i.driver_status_date) <= :hasta2
  AND (:tipo_reclamo3 = 'todos' OR :tipo_reclamo4 = 'dias')

ORDER BY fecha_reclamo DESC
SQL;

        DB::table('driver_payment_reports')->updateOrInsert(
            ['nombre' => 'discrepancias_choferes'],
            [
                'alias' => 'Discrepancias de Choferes',
                'sql_query' => trim($sql),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('driver_payment_reports')->where('nombre', 'discrepancias_choferes')->delete();
    }
};
