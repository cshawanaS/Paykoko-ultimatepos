<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('koko_module_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('business_id')->unsigned();
            $table->string('merchant_id')->nullable();
            $table->text('api_key')->nullable();
            $table->text('public_key')->nullable();
            $table->text('private_key')->nullable();
            $table->string('account_id')->nullable();
            $table->string('pos_account_id')->nullable();
            $table->string('mode')->default('sandbox');
            $table->string('payment_method')->nullable();
            
            $table->decimal('fee_percentage', 5, 2)->default(0);
            $table->decimal('max_fee_amount', 10, 2)->default(0);
            $table->boolean('enable_fee')->default(false);
            
            $table->timestamps();

            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('koko_module_settings');
    }
};
