<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropAllocationsUniqueConstraint extends Migration
{
    public function up()
    {
        // Intentionally left empty: previous attempt to drop the unique index
        // caused MySQL errors on this environment. We handle duplicates at
        // application level (merge on insert) to avoid violating the unique
        // constraint. Leaving migration as a no-op to avoid breaking deploys.
        return;
    }

    public function down()
    {
        // no-op
        return;
    }
}
