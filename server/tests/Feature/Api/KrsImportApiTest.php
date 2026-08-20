<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KrsImportApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a minimal, valid (xref-correct), text-extractable PDF containing
     * the given lines.
     */
    private function makeKrsPdf(array $lines): string
    {
        $content = "BT /F1 12 Tf 50 770 Td 14 TL\n";
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $content .= "T*\n";
            }
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "({$escaped}) Tj\n";
        }
        $content .= "ET\n";

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        for ($i = 1; $i <= count($objects); $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n".$objects[$i - 1]."\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";

        return $pdf;
    }

    private function pdfUpload(string $filename, array $lines): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'krs');
        file_put_contents($path, $this->makeKrsPdf($lines));

        return new UploadedFile($path, $filename, 'application/pdf', null, true);
    }

    public function test_import_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/imports/krs-pdf')->assertStatus(401);
        $this->getJson('/api/v1/imports/1')->assertStatus(401);
        $this->postJson('/api/v1/imports/1/confirm')->assertStatus(401);
        $this->postJson('/api/v1/imports/1/discard')->assertStatus(401);
    }

    public function test_upload_stages_parsed_rows_for_preview(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->post('/api/v1/imports/krs-pdf', [
            'file' => $this->pdfUpload('krs.pdf', [
                'SENIN 07.30-09.00 Matematika Ruang A',
                'RABU 13.00-15.30 Pemrograman Lab B',
            ]),
        ])->assertStatus(201);

        $importId = $response->json('import.id');
        $this->assertNotNull($importId);
        $response->assertJsonPath('import.status', 'pending')
            ->assertJsonCount(2, 'import.rows')
            ->assertJsonPath('import.rows.0.day', 'senin')
            ->assertJsonPath('import.rows.0.start_time', '07:30')
            ->assertJsonPath('import.rows.0.end_time', '09:00')
            ->assertJsonPath('import.rows.0.course', 'Matematika Ruang A');

        // Preview is visible before persistence.
        $this->withToken($token)->get("/api/v1/imports/{$importId}")
            ->assertOk()
            ->assertJsonCount(2, 'import.rows');
    }

    public function test_upload_rejects_non_pdf(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $path = tempnam(sys_get_temp_dir(), 'krs');
        file_put_contents($path, 'not a pdf');

        $this->withToken($token)->post('/api/v1/imports/krs-pdf', [
            'file' => new UploadedFile($path, 'notes.txt', 'text/plain', null, true),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'Only PDF files are accepted.');
    }

    public function test_confirm_persists_hard_landscape_in_transaction(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $importId = $this->withToken($token)->post('/api/v1/imports/krs-pdf', [
            'file' => $this->pdfUpload('krs.pdf', [
                'SENIN 07.30-09.00 Matematika Ruang A',
                'RABU 13.00-15.30 Pemrograman Lab B',
            ]),
        ])->json('import.id');

        $this->withToken($token)->post("/api/v1/imports/{$importId}/confirm")
            ->assertOk()
            ->assertJsonPath('import.status', 'confirmed');

        $landscape = app(HardLandscapeRepository::class)->listForUser($user->id);
        $this->assertCount(2, $landscape);
        $this->assertSame('Matematika Ruang A', $landscape[0]->title);

        // Already resolved — cannot confirm again.
        $this->withToken($token)->post("/api/v1/imports/{$importId}/confirm")
            ->assertStatus(422);
    }

    public function test_discard_resolves_without_persisting(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $importId = $this->withToken($token)->post('/api/v1/imports/krs-pdf', [
            'file' => $this->pdfUpload('krs.pdf', ['SENIN 07.30-09.00 Matematika Ruang A']),
        ])->json('import.id');

        $this->withToken($token)->post("/api/v1/imports/{$importId}/discard")
            ->assertOk()
            ->assertJsonPath('import.status', 'discarded');

        $this->assertCount(0, app(HardLandscapeRepository::class)->listForUser($user->id));
    }

    public function test_imports_are_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerToken = $owner->createToken('owner')->plainTextToken;
        $otherToken = $other->createToken('owner')->plainTextToken;

        $importId = $this->withToken($ownerToken)->post('/api/v1/imports/krs-pdf', [
            'file' => $this->pdfUpload('krs.pdf', ['SENIN 07.30-09.00 Matematika Ruang A']),
        ])->json('import.id');

        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->get("/api/v1/imports/{$importId}")->assertStatus(404);
        $this->withToken($otherToken)->post("/api/v1/imports/{$importId}/confirm")->assertStatus(422);
    }
}
