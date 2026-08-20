<?php

namespace Tests\Feature\Api;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Identity\ValueObjects\ProfileSettings;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class IcalImportApiTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $lines): string
    {
        return "BEGIN:VEVENT\n".$lines."\nEND:VEVENT\n";
    }

    private function icsUpload(string $filename, string $vevents): UploadedFile
    {
        $body = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Kinevo//Test//EN\n"
            .$vevents
            ."END:VCALENDAR\n";

        $path = tempnam(sys_get_temp_dir(), 'ics');
        file_put_contents($path, $body);

        return new UploadedFile($path, $filename, 'text/calendar', null, true);
    }

    private function createHardLandscape(int $userId, string $title = 'Existing', string $start = '2026-08-19T09:00:00', string $end = '2026-08-19T10:00:00'): HardLandscapeEvent
    {
        return app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create($userId, $title, HardLandscapeType::oneTime(), $start, $end),
        );
    }

    public function test_import_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/imports/ics')->assertStatus(401);
        $this->getJson('/api/v1/imports/ics/1')->assertStatus(401);
        $this->postJson('/api/v1/imports/ics/1/confirm')->assertStatus(401);
        $this->postJson('/api/v1/imports/ics/1/discard')->assertStatus(401);
    }

    public function test_upload_stages_parsed_events_for_preview(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $ics = $this->event(
            "SUMMARY:Team Standup\nLOCATION:Room 1\nDTSTART;TZID=UTC:20260819T090000\nDTEND;TZID=UTC:20260819T093000\nUID:1"
        ).$this->event(
            "SUMMARY:Weekly Class\nDTSTART;TZID=UTC:20260819T130000\nDTEND;TZID=UTC:20260819T153000\nRRULE:FREQ=WEEKLY;BYDAY=WE;COUNT=12\nUID:2"
        );

        $response = $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('calendar.ics', $ics),
        ])->assertStatus(201);

        $importId = $response->json('import.id');
        $this->assertNotNull($importId);
        $response->assertJsonPath('import.status', 'pending')
            ->assertJsonPath('import.filename', 'calendar.ics')
            ->assertJsonCount(2, 'import.rows')
            ->assertJsonPath('import.rows.0.summary', 'Team Standup')
            ->assertJsonPath('import.rows.0.type', 'one_time')
            ->assertJsonPath('import.rows.1.summary', 'Weekly Class')
            ->assertJsonPath('import.rows.1.type', 'recurring')
            ->assertJsonPath('import.rows.1.recurrence', 'FREQ=WEEKLY;BYDAY=WE;COUNT=12')
            ->assertJsonPath('import.confidence', 1);

        // Preview is visible before persistence.
        $this->withToken($token)->get("/api/v1/imports/ics/{$importId}")
            ->assertOk()
            ->assertJsonCount(2, 'import.rows');
    }

    public function test_upload_uses_profile_timezone_for_floating_events(): void
    {
        $user = User::factory()->create();
        app(ProfileRepository::class)->create(
            $user->id,
            new ProfileSettings('Owner', 'en', 'Asia/Jakarta', 'monday'),
        );
        $token = $user->createToken('owner')->plainTextToken;

        $ics = $this->event(
            "SUMMARY:Floating\nDTSTART:20260819T090000\nDTEND:20260819T100000\nUID:3"
        );

        $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('floating.ics', $ics),
        ])->assertStatus(201)
            ->assertJsonPath('import.rows.0.start_at', '2026-08-19T09:00:00+07:00')
            ->assertJsonPath('import.rows.0.tzid', 'Asia/Jakarta');
    }

    public function test_upload_rejects_non_ics(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $path = tempnam(sys_get_temp_dir(), 'ics');
        file_put_contents($path, 'not a calendar');

        $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => new UploadedFile($path, 'notes.txt', 'text/plain', null, true),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'Only .ics calendar files are accepted.');
    }

    public function test_upload_rejects_unparseable_calendar(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('empty.ics', ''),
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'No events could be parsed from this calendar.');
    }

    public function test_upload_surfaces_per_event_errors_and_warnings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $ics = $this->event(
            "SUMMARY:Broken\nDTSTART;TZID=UTC:garbage\nDTEND;TZID=UTC:20260819T100000\nUID:4"
        ).$this->event(
            "SUMMARY:Holiday\nDTSTART;VALUE=DATE:20260819\nDTEND;VALUE=DATE:20260820\nUID:5"
        ).$this->event(
            "SUMMARY:Good\nDTSTART;TZID=UTC:20260819T110000\nDTEND;TZID=UTC:20260819T120000\nUID:6"
        );

        $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('mixed.ics', $ics),
        ])->assertStatus(201)
            ->assertJsonCount(1, 'import.rows')
            ->assertJsonCount(1, 'import.errors')
            ->assertJsonPath('import.errors.0.summary', 'Broken')
            ->assertJsonPath('import.errors.0.error', 'Malformed date-time value.')
            ->assertJsonCount(1, 'import.warnings')
            ->assertJsonPath('import.warnings.0.summary', 'Holiday')
            ->assertJsonPath('import.warnings.0.warning', 'All-day events are not imported.')
            ->assertJsonPath('import.confidence', 0.3333);
    }

    public function test_confirm_persists_recurring_and_one_time_hard_landscape(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $ics = $this->event(
            "SUMMARY:One Off\nDTSTART;TZID=UTC:20260819T090000\nDTEND;TZID=UTC:20260819T093000\nUID:7"
        ).$this->event(
            "SUMMARY:Weekly\nDTSTART;TZID=UTC:20260819T130000\nDTEND;TZID=UTC:20260819T153000\nRRULE:FREQ=WEEKLY\nUID:8"
        );

        $importId = $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('confirm.ics', $ics),
        ])->json('import.id');

        $this->withToken($token)->post("/api/v1/imports/ics/{$importId}/confirm")
            ->assertOk()
            ->assertJsonPath('import.status', 'confirmed');

        $landscape = app(HardLandscapeRepository::class)->listForUser($user->id);
        $this->assertCount(2, $landscape);
        $this->assertSame('One Off', $landscape[0]->title);
        $this->assertSame('one_time', $landscape[0]->type->value);
        $this->assertSame('Weekly', $landscape[1]->title);
        $this->assertSame('recurring', $landscape[1]->type->value);
        $this->assertSame('FREQ=WEEKLY', $landscape[1]->recurrence);

        // Already resolved — cannot confirm again.
        $this->withToken($token)->post("/api/v1/imports/ics/{$importId}/confirm")
            ->assertStatus(422);
    }

    public function test_confirm_skips_events_that_conflict_with_existing_hard_landscape(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->createHardLandscape($user->id, 'Existing', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $ics = $this->event(
            "SUMMARY:Conflicting\nDTSTART;TZID=UTC:20260819T093000\nDTEND;TZID=UTC:20260819T103000\nUID:9"
        ).$this->event(
            "SUMMARY:Clear\nDTSTART;TZID=UTC:20260819T130000\nDTEND;TZID=UTC:20260819T140000\nUID:10"
        );

        $importId = $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('conflict.ics', $ics),
        ])->json('import.id');

        // Conflict flagged in the preview (FR-30 / TASK-142 conflict preview).
        $this->withToken($token)->get("/api/v1/imports/ics/{$importId}")
            ->assertOk()
            ->assertJsonPath('import.rows.0.conflict', true)
            ->assertJsonPath('import.rows.0.conflict_with', 'Existing')
            ->assertJsonPath('import.rows.1.conflict', false);

        $this->withToken($token)->post("/api/v1/imports/ics/{$importId}/confirm")
            ->assertOk()
            ->assertJsonPath('import.rows.0.conflict', true);

        // Only the non-conflicting event is persisted; nothing is overwritten.
        $landscape = app(HardLandscapeRepository::class)->listForUser($user->id);
        $this->assertCount(2, $landscape);
        $this->assertSame(['Existing', 'Clear'], array_map(static fn ($e) => $e->title, $landscape));
    }

    public function test_confirm_keeps_first_of_intra_import_overlaps(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $ics = $this->event(
            "SUMMARY:First\nDTSTART;TZID=UTC:20260819T090000\nDTEND;TZID=UTC:20260819T100000\nUID:11"
        ).$this->event(
            "SUMMARY:Second Overlap\nDTSTART;TZID=UTC:20260819T093000\nDTEND;TZID=UTC:20260819T103000\nUID:12"
        );

        $importId = $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('intra.ics', $ics),
        ])->json('import.id');

        $this->withToken($token)->post("/api/v1/imports/ics/{$importId}/confirm")
            ->assertOk()
            ->assertJsonPath('import.rows.0.conflict', false)
            ->assertJsonPath('import.rows.1.conflict', true);

        $landscape = app(HardLandscapeRepository::class)->listForUser($user->id);
        $this->assertCount(1, $landscape);
        $this->assertSame('First', $landscape[0]->title);
    }

    public function test_discard_resolves_without_persisting(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $ics = $this->event(
            "SUMMARY:Discard Me\nDTSTART;TZID=UTC:20260819T090000\nDTEND;TZID=UTC:20260819T100000\nUID:13"
        );

        $importId = $this->withToken($token)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('discard.ics', $ics),
        ])->json('import.id');

        $this->withToken($token)->post("/api/v1/imports/ics/{$importId}/discard")
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

        $ics = $this->event(
            "SUMMARY:Private\nDTSTART;TZID=UTC:20260819T090000\nDTEND;TZID=UTC:20260819T100000\nUID:14"
        );

        $importId = $this->withToken($ownerToken)->post('/api/v1/imports/ics', [
            'file' => $this->icsUpload('private.ics', $ics),
        ])->json('import.id');

        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->get("/api/v1/imports/ics/{$importId}")->assertStatus(404);
        $this->withToken($otherToken)->post("/api/v1/imports/ics/{$importId}/confirm")->assertStatus(422);
    }
}
