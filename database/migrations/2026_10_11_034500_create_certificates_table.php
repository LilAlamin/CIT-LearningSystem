<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('title');
            $table->string('status')->default('issued'); // issued, revoked
            $table->string('grade')->nullable(); // e.g. "Lulus dengan Pujian (A)", "Lulus"
            $table->text('notes')->nullable(); // pesan atau catatan apresiasi
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_url')->nullable(); // link file pdf atau drive
            $table->timestamps();

            $table->unique(['user_id', 'learning_path_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
