<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sources', function(Blueprint $table){
            $table->increments('id');
            $table->index(['donor_class_name','parseit'],'filtor');
            $table->index(['source'],'source');
            $table->string('donor_class_name');
            $table->string('name');
            $table->string('image')->nullable();
            $table->text('desc')->nullable();
            $table->string('hash', 32);
            $table->string('source');
            $table->boolean('parseit')->default(1);
            $table->boolean('available')->default(true);
            $table->integer('version')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('sources');
    }
}
