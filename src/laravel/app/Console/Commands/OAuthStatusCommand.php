<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OAuthStatusCommand extends Command
{
    protected $signature = 'oauth:status';

    protected $description = 'Показать OAuth redirect URI и привязки пользователей';

    public function handle(): int
    {
        $appUrl = config('app.url');

        $this->info('APP_URL: ' . $appUrl);
        $this->newLine();

        foreach (['github', 'google'] as $provider) {
            $clientId = config("services.{$provider}.client_id");
            $redirect = config("services.{$provider}.redirect");
            $secretSet = config("services.{$provider}.client_secret") ? 'yes' : 'no';

            $this->line("<fg=cyan>{$provider}</>");
            $this->line('  client_id: ' . ($clientId ?: '(empty)'));
            $this->line('  client_secret set: ' . $secretSet);
            $this->line('  redirect_uri: ' . ($redirect ?: '(empty)'));
            $this->newLine();
        }

        $this->comment('Скопируйте redirect_uri в настройки OAuth-приложения (GitHub / Google).');
        $this->comment('Открывайте сайт только по APP_URL — иначе сессия OAuth может потеряться.');
        $this->newLine();

        if (! $this->tableExists('users')) {
            return self::SUCCESS;
        }

        $rows = DB::table('users')
            ->select('id', 'email', 'github_id', 'google_id')
            ->orderBy('id')
            ->get()
            ->map(fn ($u) => [
                $u->id,
                $u->email,
                $u->github_id ?: '—',
                $u->google_id ?: '—',
            ])
            ->all();

        if ($rows !== []) {
            $this->table(['id', 'email', 'github_id', 'google_id'], $rows);
        }

        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
