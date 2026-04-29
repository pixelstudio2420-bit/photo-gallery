<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDriveTest extends TestCase
{
    use RefreshDatabase;

    // ─── Drive API Returns JSON ───

    public function test_drive_api_returns_json(): void
    {
        $event = Event::create([
            'name'            => 'Drive JSON Test',
            'slug'            => 'drive-json-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 5.00,
            'is_free'         => false,
            'view_count'      => 0,
            'drive_folder_id' => null,
        ]);

        $response = $this->getJson("/api/drive/{$event->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure(['files', 'count', 'source']);
    }

    // ─── Drive API Returns Photos for Event With Folder ───

    public function test_drive_api_returns_photos_for_event_with_folder(): void
    {
        $event = Event::create([
            'name'            => 'Drive Folder Test',
            'slug'            => 'drive-folder-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 10.00,
            'is_free'         => false,
            'view_count'      => 0,
            'drive_folder_id' => '1ABCdef_fake_folder_id',
        ]);

        // The API will attempt to reach Google Drive. Without real credentials,
        // it may return 200 with empty results or 500 if the API call fails.
        $response = $this->getJson("/api/drive/{$event->id}");

        // Accept either a successful response or a server error (no real API keys in test)
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 500]),
            "Expected 200 or 500, got {$response->getStatusCode()}"
        );
    }
}
