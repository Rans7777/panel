<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropExistingConstraints('product_options');
        $this->dropExistingConstraints('orders');

        Schema::table('product_options', function (Blueprint $table) {
            $table->dropColumn('product_id');
            $table->unsignedBigInteger('product_id')->after('id');
            $table->index('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('product_id');
            $table->unsignedBigInteger('product_id')->after('id');
            $table->index('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
        });
    }

    private function dropExistingConstraints(string $tableName): void
    {
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$tableName]);

        Schema::table($tableName, function (Blueprint $table) use ($constraints, $tableName) {
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
            }
        });

        $indexes = DB::select("
            SHOW INDEX FROM {$tableName}
            WHERE Key_name != 'PRIMARY'
        ");

        Schema::table($tableName, function (Blueprint $table) use ($indexes) {
            foreach ($indexes as $index) {
                $table->dropIndex($index->Key_name);
            }
        });
    }
};
