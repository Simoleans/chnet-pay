<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_wispro', function (Blueprint $table) {
            $table->id();
            $table->string('wispro_id')->unique();
            $table->unsignedBigInteger('public_id')->nullable();
            $table->string('client_id')->nullable();
            $table->string('client_name')->nullable();
            $table->unsignedBigInteger('client_public_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('credit_amount', 12, 2)->default(0);
            $table->timestamp('payment_date')->nullable();
            $table->string('comment')->nullable();
            $table->string('transaction_kind')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('state')->nullable();
            $table->boolean('download')->default(false);
            $table->string('name_user')->nullable();
            $table->string('name_collector')->nullable();
            $table->timestamp('wispro_created_at')->nullable();
            $table->timestamp('wispro_updated_at')->nullable();
            $table->timestamps();

            $table->index('payment_date');
            $table->index('client_id');
        });

        Schema::create('invoices_wispro', function (Blueprint $table) {
            $table->id();
            $table->string('wispro_id')->unique();
            $table->string('contract_id')->nullable();
            $table->string('invoicing_firm_id')->nullable();
            $table->string('kind_invoice')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->date('first_due_date')->nullable();
            $table->date('second_due_date')->nullable();
            $table->string('state')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('invoicing_firm_company_name')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_national_identification_number')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->timestamp('wispro_created_at')->nullable();
            $table->timestamp('wispro_updated_at')->nullable();
            $table->timestamps();

            $table->index('invoice_number');
            $table->index('period_from');
        });

        Schema::create('invoice_items_wispro', function (Blueprint $table) {
            $table->id();
            $table->string('wispro_id')->unique();
            $table->foreignId('invoice_wispro_id')->constrained('invoices_wispro')->cascadeOnDelete();
            $table->string('invoice_wispro_uuid');
            $table->string('description')->nullable();
            $table->string('product_code')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('product_code');
        });

        Schema::create('payment_invoice_wispro', function (Blueprint $table) {
            $table->id();
            $table->string('wispro_transaction_id')->unique();
            $table->foreignId('payment_wispro_id')->constrained('payments_wispro')->cascadeOnDelete();
            $table->foreignId('invoice_wispro_id')->nullable()->constrained('invoices_wispro')->nullOnDelete();
            $table->string('invoice_id');
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_invoice_wispro');
        Schema::dropIfExists('invoice_items_wispro');
        Schema::dropIfExists('invoices_wispro');
        Schema::dropIfExists('payments_wispro');
    }
};
