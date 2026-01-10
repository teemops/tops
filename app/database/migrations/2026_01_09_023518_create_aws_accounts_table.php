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
        Schema::create('aws_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name')->nullable()->comment('User-defined name');
            $table->string('aws_account_id', 12)->nullable()->comment('12-digit AWS account ID (null until CloudFormation completes)');
            $table->text('iam_role_arn')->nullable()->comment('Encrypted IAM Role ARN (null until CloudFormation completes)');
            $table->uuid('external_id')->comment('For AssumeRole security, generated per account');
            $table->uuid('unique_id')->comment('Derived from organization.orgId, same for all accounts in org');
            $table->enum('status', ['pending', 'completed', 'error'])->default('pending');
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            
            $table->index('organization_id');
            $table->index('aws_account_id');
            $table->index('status');
            $table->index('unique_id');
            $table->index(['unique_id', 'external_id']); // Composite index for SQS message matching
            $table->unique(['organization_id', 'aws_account_id'], 'org_aws_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aws_accounts');
    }
};
