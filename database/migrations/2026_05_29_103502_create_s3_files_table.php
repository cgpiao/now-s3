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
        Schema::create('s3_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid')->index(); // 用于列表查询的 ID
            $table->string('filename');
            $table->string('s3_path');
            $table->string('s3_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s3_files');
    }
};
