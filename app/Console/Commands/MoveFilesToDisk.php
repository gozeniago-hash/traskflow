<?php

namespace App\Console\Commands;

use App\Actions\Files\MoveFilesToDisk as MoveFilesToDiskAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('files:move-to {disk : The disk to move every uploaded file onto, e.g. s3}')]
#[Description('Move uploaded files onto another disk (e.g. from this server to a bucket). Safe to run again.')]
class MoveFilesToDisk extends Command
{
    public function handle(MoveFilesToDiskAction $moveFilesToDisk): int
    {
        $disk = (string) $this->argument('disk');
        if (! is_array(config('filesystems.disks.'.$disk))) {
            $this->error('There is no "'.$disk.'" disk in config/filesystems.php.');

            return self::FAILURE;
        }

        $result = $moveFilesToDisk->handle($disk);

        $this->info('Moved '.$result['moved'].' '.str('file')->plural($result['moved']).' to '.$disk.'.');
        if ($result['missing'] !== []) {
            $this->warn(count($result['missing']).' could not be moved because the file was not found. Their rows were left unchanged:');
            foreach ($result['missing'] as $file) {
                $this->line('  '.$file);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
