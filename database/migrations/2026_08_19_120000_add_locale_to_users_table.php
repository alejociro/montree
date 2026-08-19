<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // WHY: nullable a proposito. `null` significa "segui el idioma de mi navegador",
            // que no es lo mismo que 'es': una eleccion explicita del usuario tiene que
            // ganarle al Accept-Language, y sin el null no habria forma de distinguirlas.
            $table->string('locale', 5)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
