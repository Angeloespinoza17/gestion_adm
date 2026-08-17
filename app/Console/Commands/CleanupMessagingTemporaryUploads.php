<?php

namespace App\Console\Commands;

use App\Models\Messaging\TemporaryUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMessagingTemporaryUploads extends Command
{
    protected $signature = 'messaging:cleanup-temporary-uploads';

    protected $description = 'Elimina cargas temporales de mensajería vencidas';

    public function handle(): int
    {
        $count = 0;
        TemporaryUpload::query()->whereNull('consumed_at')->where('expires_at', '<', now())->chunkById(200, function ($uploads) use (&$count) {
            foreach ($uploads as $upload) {
                Storage::disk($upload->disk)->delete($upload->path);
                $upload->delete();
                $count++;
            }
        });
        $this->info("Cargas eliminadas: {$count}");

        return self::SUCCESS;
    }
}
