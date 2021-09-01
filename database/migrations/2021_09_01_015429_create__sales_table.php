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
        Schema::create('_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quantity');
            $table->timestamp('s_date');
            $table->foreign('shop_id')
                ->references('id')
                ->on('shops')
                ->onDelete('cascade');
            $table->foriegn('user_id')
                ->references('id')
                ->on('user')
                ->onDelete('cascade');
            $table->foriegn('prod_id')
                ->references('id')
                ->on('product')
                ->onDelete('cascade');
            $table->foriegn('reciept_id')
                ->references('id')
                ->on('reciept')
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
        Schema::dropIfExists('_sales');
    }
}
