<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Models\S3File;

class S3FileTest extends TestCase
{
    use RefreshDatabase;

    protected $token = 'my-secret-token';

    public function test_upload_requires_token(): void
    {
        $response = $this->postJson('/api/upload', []);
        $response->assertStatus(401);
    }

    public function test_upload_file_to_s3(): void
    {
        Storage::fake('s3');

        $uuid = (string) Str::uuid();
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/upload', [
            'file' => $file,
            'user_uuid' => $uuid
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'url', 'file_id']);

        $s3Path = "uploads/{$uuid}/" . $file->hashName();
        Storage::disk('s3')->assertExists($s3Path);

        $this->assertDatabaseHas('s3_files', [
            'user_uuid' => $uuid,
            'filename' => 'test.jpg',
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => 'jpg',
            's3_path' => $s3Path
        ]);
    }

    public function test_list_files_by_uuid(): void
    {
        $uuid = (string) Str::uuid();
        S3File::create([
            'user_uuid' => $uuid,
            'filename' => 'file1.jpg',
            's3_path' => 'path1',
            's3_url' => 'http://s3.com/file1.jpg'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/list?user_uuid={$uuid}");

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'files')
                 ->assertJsonFragment(['filename' => 'file1.jpg']);
    }

    public function test_delete_file(): void
    {
        Storage::fake('s3');
        $uuid = (string) Str::uuid();
        $path = "uploads/{$uuid}/test.jpg";
        Storage::disk('s3')->put($path, 'content');

        $s3File = S3File::create([
            'user_uuid' => $uuid,
            'filename' => 'test.jpg',
            's3_path' => $path,
            's3_url' => 'http://s3.com/test.jpg'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/delete', [
            'url' => 'http://s3.com/test.jpg'
        ]);

        $response->assertStatus(200);
        Storage::disk('s3')->assertMissing($path);
        $this->assertDatabaseMissing('s3_files', ['id' => $s3File->id]);
    }
}
