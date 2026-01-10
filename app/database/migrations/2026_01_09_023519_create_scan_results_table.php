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
        Schema::create('scan_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('scan_id');
            $table->enum('severity', ['critical', 'high', 'medium', 'low']);
            $table->string('service')->comment('AWS service (S3, IAM, EC2, etc.)');
            $table->string('resource_type')->comment('Resource type');
            $table->string('resource_id')->comment('Resource identifier');
            $table->string('finding_type')->comment('Type of finding');
            $table->string('title');
            $table->text('description');
            $table->text('remediation')->nullable()->comment('Remediation steps');
            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('scan_id')->references('id')->on('scans')->onDelete('cascade');
            
            $table->index('scan_id');
            $table->index('severity');
            $table->index('status');
            $table->index('service');
            $table->index(['scan_id', 'severity'], 'scan_severity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_results');
    }
};
