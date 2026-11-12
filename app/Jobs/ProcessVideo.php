<?php

namespace App\Jobs;

use App\Models\PostImage;
use App\Notifications\ClubMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Komprimerer en video fra et opslag: højst 1280 px på den længste led, højst 30 billeder/sek., H.264 + AAC i MP4
 * (afspilles i alle browsere og på telefoner), "faststart" så den kan starte før hele filen er hentet.
 * Metadata (fx GPS-position) fjernes. Der laves også et stillbillede (plakat). Originalen slettes bagefter.
 * Køres af køen "video" (systemd: klub-video-worker).
 */
class ProcessVideo implements ShouldQueue
{
    use Queueable;

    public const MAX_SECONDS = 300; // højst 5 minutter
    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(public int $videoId) { $this->onQueue('video'); }

    public function handle(): void
    {
        $v = PostImage::with('post.user')->find($this->videoId);
        if (! $v || $v->status !== 'processing') return;
        $src = Storage::path($v->path);
        $base = 'feed/'.now()->format('Y/m').'/'.Str::uuid();

        try {
            $probe = $this->run(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height:format=duration', '-of', 'json', $src], 60);
            $info = json_decode($probe, true);
            $duration = (float) ($info['format']['duration'] ?? 0);
            if (empty($info['streams'][0]['width'])) throw new \DomainException('Filen indeholder ingen video.');
            if ($duration > self::MAX_SECONDS) throw new \DomainException('Videoen er længere end '.(self::MAX_SECONDS / 60).' minutter.');

            Storage::makeDirectory(dirname($base));
            $out = Storage::path($base.'.mp4');
            $this->run(['ffmpeg', '-y', '-v', 'error', '-i', $src, '-map', '0:v:0', '-map', '0:a:0?',
                '-vf', "scale='if(gt(iw,ih),min(1280,iw),-2)':'if(gt(iw,ih),-2,min(1280,ih))'", '-fpsmax', '30',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-pix_fmt', 'yuv420p', '-profile:v', 'high',
                '-c:a', 'aac', '-b:a', '96k', '-ac', '2', '-movflags', '+faststart', '-map_metadata', '-1', '-threads', '2', $out], 840);
            $poster = Storage::path($base.'.jpg');
            $this->run(['ffmpeg', '-y', '-v', 'error', '-ss', (string) min(1, $duration / 2), '-i', $out, '-frames:v', '1', '-q:v', '4', $poster], 60);
            [$w, $h] = getimagesize($poster) ?: [null, null];

            $v->update(['status' => 'ready', 'path' => $base.'.mp4', 'poster_path' => $base.'.jpg', 'width' => $w, 'height' => $h,
                'duration' => (int) round($duration), 'bytes' => filesize($out)]);
            Storage::delete('feed-tmp/'.basename($src));
        } catch (\Throwable $e) {
            Log::warning('Video kunne ikke behandles', ['video' => $v->id, 'error' => $e->getMessage()]);
            Storage::delete([$base.'.mp4', $base.'.jpg', 'feed-tmp/'.basename($src)]);
            $v->update(['status' => 'failed']);
            $v->post?->user?->notify(new ClubMessage('Din video kunne ikke vises',
                'Videoen i dit opslag kunne ikke behandles'.($e instanceof \DomainException ? ': '.$e->getMessage() : '.').' Prøv evt. en kortere video.', '/opslag'));
        }
    }

    private function run(array $cmd, int $timeout): string
    {
        $p = new Process($cmd);
        $p->setTimeout($timeout);
        $p->run();
        if (! $p->isSuccessful()) throw new \Exception(trim($p->getErrorOutput()) ?: 'ffmpeg fejlede');

        return $p->getOutput();
    }
}
