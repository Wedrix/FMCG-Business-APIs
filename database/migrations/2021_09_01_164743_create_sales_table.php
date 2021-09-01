<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quantity');
            $table->timestamp('sales_date');
            
            $table->unsignedBigInteger('shops_id');
            $table->foreign('shops_id')
                ->references('id')
                ->on('shops')
                ->onDelete('cascade');
                
            $table->unsignedBigInteger('users_id');
            $table->foreign('users_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
                
            $table->unsignedBigInteger('prod_id');
            $table->foreign('prod_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

                
            $table->unsignedBigInteger('receipt_id');
            $table->foreign('receipt_id')
                ->references('id')
                ->on('receipt')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales');
    }
}