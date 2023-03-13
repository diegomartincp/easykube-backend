<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //AUN SIN USO
        Schema::create('web_tickets', function (Blueprint $table) {
            $table->id();
            $table->integer('action'); //0 Crear //1 Replicas //2 Borrar
            $table->integer('replicas');
            $table->string('description');
            $table->integer('user_id')->references('id')->on('users');
            $table->integer('web_project_id')->references('id')->on('web_project');
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
        Schema::dropIfExists('web_tickets');
    }
};
