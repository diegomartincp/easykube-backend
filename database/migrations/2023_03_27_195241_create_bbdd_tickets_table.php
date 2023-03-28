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
        Schema::create('bbdd_tickets', function (Blueprint $table) {
            $table->id();
            $table->integer('action'); //0 Crear //1 Replicas //2 Borrar
            $table->integer('replicas')->default(0);
            $table->string('description');
            $table->integer('user_id')->references('id')->on('users');
            $table->integer('bbdd_project_id')->references('id')->on('bbdd_projects');
            $table->boolean('accepted')->default(0);
            $table->boolean('declined')->default(0);
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
        Schema::dropIfExists('bbdd_tickets');
    }
};
