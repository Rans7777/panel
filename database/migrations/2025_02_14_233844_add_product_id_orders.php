<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (config('database.default') === 'sqlite') {
            Schema::dropIfExists('orders_temp');

            Schema::create('orders_temp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->integer('quantity')->default(0);
                $table->string('image')->nullable();
                $table->integer('total_price')->default(0);
                $table->text('options')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO orders_temp (id, product_id, quantity, image, total_price, options, created_at, updated_at) SELECT id, 1, quantity, image, total_price, options, created_at, updated_at FROM orders');

            Schema::drop('orders');
            Schema::rename('orders_temp', 'orders');
        } else {
            Schema::dropIfExists('orders_temp');

            Schema::create('orders_temp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->integer('quantity')->default(0);
                $table->string('image')->nullable();
                $table->integer('total_price')->default(0);
                $table->text('options')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO orders_temp (id, product_id, quantity, image, total_price, options, created_at, updated_at) SELECT id, 1, quantity, image, total_price, options, created_at, updated_at FROM orders');

            Schema::drop('orders');
            Schema::rename('orders_temp', 'orders');
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            Schema::dropIfExists('orders_temp');

            Schema::create('orders_temp', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('quantity')->default(0);
                $table->string('image')->nullable();
                $table->integer('total_price')->default(0);
                $table->text('options')->nullable();
                $table->timestamps();
            });

            DB::statement("INSERT INTO orders_temp (id, name, quantity, image, total_price, options, created_at, updated_at) SELECT id, '' as name, quantity, image, total_price, options, created_at, updated_at FROM orders");

            Schema::drop('orders');
            Schema::rename('orders_temp', 'orders');
            Schema::enableForeignKeyConstraints();
        } else {
            Schema::disableForeignKeyConstraints();

            Schema::dropIfExists('orders_temp');

            Schema::create('orders_temp', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('quantity')->default(0);
                $table->string('image')->nullable();
                $table->integer('total_price')->default(0);
                $table->text('options')->nullable();
                $table->timestamps();
            });

            DB::statement("INSERT INTO orders_temp (id, name, quantity, image, total_price, options, created_at, updated_at) SELECT id, '' as name, quantity, image, total_price, options, created_at, updated_at FROM orders");

            Schema::drop('orders');
            Schema::rename('orders_temp', 'orders');

            Schema::enableForeignKeyConstraints();
        }
    }
};
