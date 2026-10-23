<?php

namespace Tests\Unit\Support\Export;

use App\Support\Export\Zip;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** The small ZIP writer behind .xlsx exports. */
class ZipTest extends TestCase
{
    #[RequiresPhpExtension('zip')]
    public function test_writes_an_archive_that_a_standard_reader_opens(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, (new Zip)->add('a.txt', str_repeat('hello ', 100))->add('dir/b.xml', '<x/>')->output());

        $zip = new ZipArchive;
        $opened = $zip->open($path);
        $first = $zip->getFromName('a.txt');
        $second = $zip->getFromName('dir/b.xml');
        $zip->close();
        unlink($path);

        $this->assertTrue($opened);
        $this->assertSame(str_repeat('hello ', 100), $first);
        $this->assertSame('<x/>', $second);
    }

    public function test_the_same_files_always_give_the_same_bytes(): void
    {
        $build = fn (): string => (new Zip)->add('a.txt', 'same')->output();

        $this->assertSame($build(), $build());
    }
}
