<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class MakeUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:user {username?} {password?} {role?} {skip_confirmation?}';

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
        $username = $this->argument('username') ?: text('<fg=green>ユーザー名を入力してください</>', default: 'admin', required: true);

        if (User::where('name', $username)->exists()) {
            $this->error("ユーザー名 \"{$username}\" は既に存在します。");

            return 0;
        }

        $password = $this->argument('password') ?: password('<fg=green>パスワードを入力してください</>', required: true);
        $role = $this->argument('role') ?: text('<fg=green>付与するロールを入力してください</>', required: true);

        $skipConfirmation = (bool)$this->argument('skip_confirmation') === true;
        if (!$skipConfirmation && !confirm('この情報でユーザーを作成してよろしいですか？ [ユーザー名: '.$username.', ロール: '.$role.']', default: false)) {
            $this->info('ユーザー作成をキャンセルしました。');

            return 0;
        }

        try {
            DB::transaction(function () use ($username, $password, $role) {
                $user = User::create([
                    'name' => $username,
                    'password' => Hash::make($password),
                ]);
                $user->assignRole($role);
                $this->info('ユーザーが正常に作成されました！');
            });
        } catch (Exception $e) {
            $this->error('ユーザー作成中にエラーが発生しました: '.$e->getMessage());
        }

        return 0;
    }
}
