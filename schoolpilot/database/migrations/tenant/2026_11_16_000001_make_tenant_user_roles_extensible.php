<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep tenant roles extensible so route-authorized roles can be assigned
     * without a database enum alteration for every new staff role.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->change();
        });
    }

    public function down(): void
    {
        // Intentionally keep this column as a string. Reverting to a fixed
        // enum could fail when a school already has librarian accounts.
    }
};
