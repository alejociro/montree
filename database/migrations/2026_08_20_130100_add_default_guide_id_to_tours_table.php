<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guía por defecto del tour: la propuesta que hereda cada salida nueva
     * (D7). Nullable en el esquema — es obligatorio al publicar, y eso lo
     * comprueba la aplicación, no una constraint que dejaría en `draft` a los
     * tours existentes.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->foreignId('default_guide_id')->nullable()->after('category_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_guide_id');
        });
    }
};
