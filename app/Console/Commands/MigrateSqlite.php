<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSqlite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:sqlite';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'MySQLからSQLiteへ移行します';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $sqlitePath = database_path('database.sqlite');
        if (!file_exists($sqlitePath)) {
            touch($sqlitePath);
        }
        config(['database.connections.sqlite.database' => $sqlitePath]);

        if (!config('database.connections.mysql')) {
            $this->error("MySQL接続情報が見つかりません。");
            return 1;
        }

        $this->info("MySQL → SQLite マイグレーションを開始します。");

        $mysql = DB::connection('mysql');
        $sqlite = DB::connection('sqlite');

        $dbName = $mysql->getDatabaseName();

        $tablesResults = $mysql->select("SHOW TABLES");
        if (empty($tablesResults)) {
            $this->error("MySQLデータベースにテーブルが見つかりません。");
            return 1;
        }
        $tableKey = "Tables_in_{$dbName}";

        foreach ($tablesResults as $tableObj) {
            $tableName = $tableObj->$tableKey;
            $this->info("テーブル [{$tableName}] を移行中...");

            $createResult = $mysql->select("SHOW CREATE TABLE {$tableName}");
            if (empty($createResult)) {
                $this->error("テーブル [{$tableName}] のCREATE文を取得できませんでした。");
                continue;
            }
            $createSql = $createResult[0]->{'Create Table'};

            $sqliteCreateSql = $this->convertMysqlToSqlite($createSql);

            $sqlite->beginTransaction();
            try {
                $sqlite->statement("DROP TABLE IF EXISTS {$tableName}");
                $sqlite->statement($sqliteCreateSql);

                $rows = $mysql->table($tableName)->get();
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        $rowArray = (array)$row;
                        $sqlite->table($tableName)->insert($rowArray);
                    }
                }

                $sqlite->commit();
            } catch (\Exception $e) {
                $sqlite->rollBack();
                $this->error("テーブル [{$tableName}] の移行中にエラーが発生しました: " . $e->getMessage());
            }
        }

        $this->info("全テーブルのマイグレーションが完了しました。");
    }

    /**
     *
     * @param string $mysqlSql
     * @return string
     */
    protected function convertMysqlToSqlite($mysqlSql)
    {
        $sql = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $mysqlSql);
        $sql = str_replace('`', '', $sql);
        $sql = preg_replace_callback(
            '/(\w+)\s+int\s*\(\d+\)\s+NOT NULL\s*AUTOINCREMENT/i',
            function ($matches) {
                return $matches[1] . ' INTEGER PRIMARY KEY AUTOINCREMENT';
            },
            $sql
        );
        if (stripos($sql, 'INTEGER PRIMARY KEY') !== false) {
            $sql = preg_replace('/,\s*PRIMARY KEY\s*\([^)]*\)/i', '', $sql);
        }
        $sql = preg_replace('/\)\s*ENGINE=.*$/i', ')', $sql);
        $sql = str_replace('unsigned', '', $sql);
        $sql = preg_replace('/ COLLATE\s+\w+/i', '', $sql);
        $sql = preg_replace('/ CHARACTER SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/ COMMENT \'.*?\'/', '', $sql);
        $sql = preg_replace('/DEFAULT current_timestamp\(\)/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);
        $sql = preg_replace('/\bvarchar\(\d+\)/i', 'TEXT', $sql);
        $sql = preg_replace('/\bdecimal\(\d+,\d+\)/i', 'NUMERIC', $sql);
        $sql = preg_replace('/DEFAULT NULL/i', '', $sql);
        $sql = preg_replace('/,\s*KEY\s+\w+\s*\([^)]+\)/i', '', $sql);
        return $sql;
    }
}
