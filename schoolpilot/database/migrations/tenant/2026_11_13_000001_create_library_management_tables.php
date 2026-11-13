<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 32)->nullable()->unique();
            $table->string('publisher')->nullable();
            $table->string('edition')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['title', 'author']);
        });

        Schema::create('library_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->string('accession_no')->unique();
            $table->string('condition', 24)->default('good');
            $table->string('status', 24)->default('available');
            $table->timestamps();
            $table->index(['book_id', 'status']);
        });

        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained('library_copies')->restrictOnDelete();
            $table->string('member_type', 16);
            $table->unsignedBigInteger('member_id');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('issued_at');
            $table->date('due_at');
            $table->date('returned_at')->nullable();
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->decimal('fine_paid', 10, 2)->default(0);
            $table->timestamps();
            $table->index(['member_type', 'member_id']);
            $table->index(['due_at', 'returned_at']);
        });

        Schema::create('library_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->string('member_type', 16);
            $table->unsignedBigInteger('member_id');
            $table->string('status', 20)->default('pending');
            $table->timestamp('reserved_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status']);
            $table->index(['member_type', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reservations');
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_copies');
        Schema::dropIfExists('library_books');
        Schema::dropIfExists('library_categories');
    }
};