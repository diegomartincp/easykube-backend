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
        Schema::create('web_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('email');
            $table->boolean('prod');
            $table->string('token');
            $table->string('url');
            $table->string('ipname');
            $table->string('dns');
            $table->integer('cluster_id')->references('id')->on('cluster');
            $table->integer('replicas');
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
        Schema::dropIfExists('web_projects');
    }
};
