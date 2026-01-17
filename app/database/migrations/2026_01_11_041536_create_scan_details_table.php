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
        Schema::create('scan_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('scan_id');
            $table->string('service')->comment('AWS service (iam, s3, ec2, rds, etc.)');
            $table->string('resource_type')->nullable()->comment('Type of resource (user, bucket, instance, etc.)');
            $table->string('resource_id')->nullable()->comment('Resource identifier (username, bucket name, instance ID)');
            $table->string('api_method')->comment('AWS SDK method called (listUsers, listMFADevices, etc.)');
            $table->json('raw_data')->comment('Raw JSON response from AWS API');
            $table->string('parent_resource_id')->nullable()->comment('For nested calls (e.g., username for listMFADevices)');
            $table->string('region')->nullable()->comment('AWS region if applicable');
            $table->timestamps();

            $table->foreign('scan_id')->references('id')->on('scans')->onDelete('cascade');
            
            $table->index('scan_id');
            $table->index('service');
            $table->index('api_method');
            $table->index(['scan_id', 'service', 'resource_id'], 'scan_service_resource_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_details');
    }
};
