<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            $table->index('stock'); // low-stock query fast-a irukka
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};