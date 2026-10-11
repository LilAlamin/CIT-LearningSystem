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
        Schema::create('path_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('meeting_number'); // e.g. 1, 2, 3...
            $table->string('title');
            $table->text('description')->nullable(); // detail acara
            $table->dateTime('meeting_date'); // tanggal & jam pertemuan
            $table->string('end_time')->nullable(); // e.g. "21:30 WIB"
            $table->string('location')->nullable(); // e.g. "Zoom Online / Lab Komputer 3"
            $table->string('meeting_url')->nullable(); // link zoom/meet
            $table->text('notes')->nullable(); // notes persiapan peserta
            $table->longText('recap_summary')->nullable(); // rangkuman materi pas acara
            $table->string('recap_slides_url')->nullable(); // link slide materi tayang
            $table->string('recap_recording_url')->nullable(); // link rekaman video pas acara
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->unique(['learning_path_id', 'meeting_number']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('path_meeting_id')->nullable()->after('learning_path_id')->constrained('path_meetings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['path_meeting_id']);
            $table->dropColumn('path_meeting_id');
        });

        Schema::dropIfExists('path_meetings');
    }
};
