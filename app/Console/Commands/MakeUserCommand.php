<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MakeUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '新しいユーザーを作成するコマンド';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $username = $this->ask('ユーザー名を入力してください');
        $password = $this->secret('パスワードを入力してください');

        if (!$this->confirm('この情報でユーザーを作成してよろしいですか？')) {
            $this->info('ユーザー作成をキャンセルしました。');
            return 0;
        }

        try {
            User::create([
                'name' => $username,
                'password' => Hash::make($password),
            ]);

            $this->info('ユーザーが正常に作成されました！');
        } catch (\Exception $e) {
            $this->error('ユーザー作成中にエラーが発生しました: ' . $e->getMessage());
        }

        return 0;
    }
}
