<?php
// app/helpers.php (registrar en composer.json autoload.files)
use Illuminate\Support\Facades\DB; function account_id(string $code): int { return (int) DB::table('accounts')->where('code',$code)->value('id'); }