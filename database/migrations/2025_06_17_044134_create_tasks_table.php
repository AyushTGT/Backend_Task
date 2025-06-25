<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('unassigned'); // e.g., pending, in_progress, completed, overdue
            $table->date('due_date')->nullable();
            $table->string('priority', 20)->nullable(); // e.g., low, medium, high
            $table->string('project_name', 100)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->unsignedBigInteger('assignee')->nullable();
            $table->timestamps();

            // Foreign key constraints (if you have a users table)
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assignee')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
}