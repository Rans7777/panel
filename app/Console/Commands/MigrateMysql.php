<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:mysql';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SQLiteからMySQLへ移行します';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sqlitePath = database_path('database.sqlite');

        if (!file_exists($sqlitePath)) {
            $this->error("SQLiteデータベースファイルが存在しません: {$sqlitePath}");
            return 1;
        }

        config(['database.connections.sqlite.database' => $sqlitePath]);

        $this->info("SQLite → MySQL マイグレーションを開始します。");

        $sqliteTables = DB::connection('sqlite')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");

        foreach ($sqliteTables as $tableObj) {
            $tableName = $tableObj->name;
            $this->info("テーブル [{$tableName}] を移行中...");

            $result = DB::connection('sqlite')->select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?;", [$tableName]);
            if (empty($result) || empty($result[0]->sql)) {
                $this->error("テーブル [{$tableName}] のCREATE文が見つかりませんでした。");
                continue;
            }

            $createTableSql = $result[0]->sql;

            $createTableSql = preg_replace(
                '/INTEGER PRIMARY KEY AUTOINCREMENT(?:\s+NOT\s+NULL)?/i',
                'INT AUTO_INCREMENT PRIMARY KEY',
                $createTableSql
            );
            $createTableSql = preg_replace('/\"([a-zA-Z0-9_]+)\"/', '`$1`', $createTableSql);
            $createTableSql = str_ireplace('WITHOUT ROWID', '', $createTableSql);
            $createTableSql = preg_replace_callback(
                '/\bvarchar\b(?!\s*\()/i',
                function () {
                    return 'varchar(255)';
                },
                $createTableSql
            );

            try {
                DB::connection('mysql')->statement("DROP TABLE IF EXISTS `{$tableName}`;");
                DB::connection('mysql')->statement($createTableSql);
            } catch (\Exception $e) {
                $this->error("MySQL側でテーブル [{$tableName}] 作成時にエラー: " . $e->getMessage());
                continue;
            }

            try {
                $rows = DB::connection('sqlite')->table($tableName)->get()->toArray();
                $data = [];
                foreach ($rows as $row) {
                    $data[] = (array) $row;
                }

                if (!empty($data)) {
                    foreach (array_chunk($data, 100) as $chunk) {
                        DB::connection('mysql')->table($tableName)->insert($chunk);
                    }
                }
            } catch (\Exception $e) {
                $this->error("テーブル [{$tableName}] のデータ移行時にエラー: " . $e->getMessage());
            }
        }

        $this->info("全テーブルのマイグレーションが完了しました。");
        return 0;
    }
}
