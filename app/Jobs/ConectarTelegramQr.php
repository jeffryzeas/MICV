<?php

namespace App\Jobs;

use App\Services\TelegramCvQr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ConectarTelegramQr implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 350;

    public function __construct(public string $id)
    {
        $this->onConnection('telegram-cv-login')->onQueue('telegram-cv-login');
    }

    public function handle(TelegramCvQr $qr): void
    {
        $qr->ejecutar($this->id);
    }

    public function failed(?\Throwable $e): void
    {
        app(TelegramCvQr::class)->fallar($this->id);
    }
}
