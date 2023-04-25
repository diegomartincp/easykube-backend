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
        Schema::create('bbdd_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->integer('memory');
            $table->string('dbname');
            $table->string('dbuser');
            $table->string('dbpwd');
            $table->string('ip')->default("Pending");
            $table->integer('port');
            $table->integer('cluster_id')->references('id')->on('cluster');
            $table->integer('workgroup_id')->references('id')->on('workgroups');
            $table->boolean('aproved')->default(0);
            $table->boolean('deleted')->default(0);
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
        Schema::dropIfExists('bbdd_projects');
    }
};
