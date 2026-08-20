<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    private const JPG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==';

    private function makeUploadedFile(string $name, string $contents, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, base64_decode($contents));

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function pdfContents(): string
    {
        return base64_encode("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    }

    private function completedTask(int $userId): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => 'Done task',
            'status' => 'completed',
            'priority_tier' => 1,
            'progress_mode' => 'derived',
            'progress' => 100,
            'version' => 1,
        ]);
    }

    public function test_attachment_endpoints_require_authentication(): void
    {
        $user = User::factory()->create();
        $task = $this->completedTask($user->id);

        $this->getJson('/api/v1/attachments/rules')->assertStatus(401);
        $this->getJson("/api/v1/tasks/{$task->id}/attachments")->assertStatus(401);
        $this->postJson("/api/v1/tasks/{$task->id}/attachments")->assertStatus(401);
        $this->getJson("/api/v1/tasks/{$task->id}/attachments/1")->assertStatus(401);
        $this->deleteJson("/api/v1/tasks/{$task->id}/attachments/1")->assertStatus(401);
    }

    public function test_upload_is_restricted_to_completed_tasks(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $task = Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Pending',
            'status' => 'scheduled',
            'priority_tier' => 1,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);

        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('evidence.png', self::PNG, 'image/png'),
        ])->assertStatus(422);
    }

    public function test_upload_list_download_delete_flow(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $task = $this->completedTask($user->id);

        $response = $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('evidence.png', self::PNG, 'image/png'),
        ])->assertStatus(201);

        $attachmentId = $response->json('attachment.id');
        $this->assertNotNull($attachmentId);
        $this->assertSame(64, strlen($response->json('attachment.sha256')));
        $this->assertSame('evidence.png', $response->json('attachment.filename'));
        $this->assertSame('image/png', $response->json('attachment.mime_type'));

        $this->withToken($token)->get("/api/v1/tasks/{$task->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'attachments')
            ->assertJsonPath('attachments.0.id', $attachmentId);

        $this->withToken($token)->get("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")
            ->assertOk();

        $this->withToken($token)->delete("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->withToken($token)->get("/api/v1/tasks/{$task->id}/attachments")
            ->assertOk()
            ->assertJsonCount(0, 'attachments');
    }

    public function test_upload_rejects_fourth_attachment(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $task = $this->completedTask($user->id);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
                'file' => $this->makeUploadedFile("evidence{$i}.png", self::PNG, 'image/png'),
            ])->assertStatus(201);
        }

        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('fourth.png', self::PNG, 'image/png'),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'A task can have at most 3 attachments.');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $task = $this->completedTask($user->id);

        // ~6 MB fake file — size check runs before content-type detection.
        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('big.pdf', 6000),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'Attachment exceeds the 5 MB size limit.');
    }

    public function test_upload_rejects_wrong_extension_and_spoofed_content(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $task = $this->completedTask($user->id);

        // Wrong extension.
        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('note.txt', base64_encode('hello world'), 'text/plain'),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'Only JPG, PNG, and PDF files are allowed.');

        // Spoofed: named .png but content is plain text — detected content type wins.
        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('fake.png', base64_encode('not really an image'), 'image/png'),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'Unsupported file content type.');
    }

    public function test_upload_accepts_jpg_and_pdf(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $task = $this->completedTask($user->id);

        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('photo.jpg', self::JPG, 'image/jpeg'),
        ])->assertStatus(201)->assertJsonPath('attachment.mime_type', 'image/jpeg');

        $this->withToken($token)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('doc.pdf', $this->pdfContents(), 'application/pdf'),
        ])->assertStatus(201)->assertJsonPath('attachment.mime_type', 'application/pdf');
    }

    public function test_attachments_are_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerToken = $owner->createToken('owner')->plainTextToken;
        $otherToken = $other->createToken('owner')->plainTextToken;

        $task = $this->completedTask($owner->id);
        $attachmentId = $this->withToken($ownerToken)->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => $this->makeUploadedFile('evidence.png', self::PNG, 'image/png'),
        ])->json('attachment.id');

        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->get("/api/v1/tasks/{$task->id}/attachments")
            ->assertStatus(404);
        $this->withToken($otherToken)->get("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")
            ->assertStatus(404);
        $this->withToken($otherToken)->delete("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")
            ->assertStatus(404);
    }

    public function test_rules_endpoint_exposes_limits(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/attachments/rules')
            ->assertOk()
            ->assertJsonPath('max_per_task', 3)
            ->assertJsonPath('max_bytes', 5 * 1024 * 1024)
            ->assertJsonPath('allowed_extensions', ['jpg', 'jpeg', 'png', 'pdf']);
    }
}
