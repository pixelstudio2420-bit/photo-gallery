<?php

namespace Tests\Unit;

use App\Services\GoogleDriveService;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    // ─── Extract Folder ID from URL ───

    public function test_extract_folder_id_from_url(): void
    {
        $url = 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz';

        $folderId = GoogleDriveService::extractFolderId($url);

        $this->assertEquals('1AbCdEfGhIjKlMnOpQrStUvWxYz', $folderId);
    }

    // ─── Extract Folder ID from Direct ID ───

    public function test_extract_folder_id_from_direct_id(): void
    {
        $directId = '1AbCdEfGhIjKlMnOpQrStUvWxYz';

        $folderId = GoogleDriveService::extractFolderId($directId);

        $this->assertEquals('1AbCdEfGhIjKlMnOpQrStUvWxYz', $folderId);
    }

    // ─── Thumbnail URL Format ───

    public function test_thumbnail_url_format(): void
    {
        $service = new GoogleDriveService();
        $fileId  = 'abc123XYZ';
        $size    = 400;

        $url = $service->thumbnailUrl($fileId, $size);

        $this->assertEquals(
            "https://drive.google.com/thumbnail?id={$fileId}&sz=w{$size}",
            $url
        );
    }
}
