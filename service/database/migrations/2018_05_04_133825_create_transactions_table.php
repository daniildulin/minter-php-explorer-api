<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('block_id');
            $table->integer('type');
            $table->integer('nonce');
            $table->integer('gas_price');
            $table->string('from');
            $table->string('to');
            $table->string('coin');
            $table->string('hash');
            $table->string('payload');
            $table->string('service_data');
            $table->string('pub_key')->nullable();
            $table->decimal('fee', 30, 0);
            $table->decimal('value', 30, 18);
            $table->decimal('stake', 30, 18)->nullable();
            $table->decimal('commission', 30, 18)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('block_id')->references('id')->on('blocks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
}