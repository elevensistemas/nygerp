<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class starters extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(){
        DB::table('accounts')->insert([
        ['code'=>'1.1.01','name'=>'Caja','type'=>'asset'],
        ['code'=>'1.1.02','name'=>'Bancos','type'=>'asset'],
        ['code'=>'1.1.03','name'=>'Clientes','type'=>'asset'],
        ['code'=>'2.1.01','name'=>'Proveedores','type'=>'liability'],
        ['code'=>'4.1.01','name'=>'Ingresos','type'=>'income'],
        ['code'=>'5.1.01','name'=>'Servicios Tercerizados','type'=>'expense'],
        ]);
        DB::table('cost_centers')->insert([
        ['code'=>'AMBA','name'=>'AMBA Depósito'],
        ['code'=>'CBA','name'=>'Córdoba Depósito'],
        ]);
        DB::table('payment_terms')->insert([
        ['name'=>'30-60-90','days'=>json_encode([30,60,90])],
        ['name'=>'Quincenal','days'=>json_encode([15,30])],
        ['name'=>'Mensual','days'=>json_encode([30])],
        ]);
    }
}
