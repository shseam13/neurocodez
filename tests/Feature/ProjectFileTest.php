<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    private ?User $staff = null;

    /** Memoised: the upload helper needs staff too, and emails are unique. */
    private function staff(): User
    {
        return $this->staff ??= User::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_ADMIN);
    }

    /**
     * Drop the authenticated user set by a previous actingAs().
     *
     * Needed because the upload helper signs in as staff, and that would
     * otherwise carry into a test asserting guest behaviour.
     */
    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    private function clientUser(Client $client, string $email = 'client@example.com'): User
    {
        return User::create([
            'name' => 'Client', 'email' => $email, 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => $client->id, 'is_active' => true,
        ]);
    }

    private function project(?Client $client = null): Project
    {
        $client ??= Client::create(['name' => 'Rahim Traders']);

        return Project::create([
            'client_id' => $client->id, 'title' => 'Portal v2', 'agreed_amount' => 50000,
        ]);
    }

    private function upload(Project $project, bool $visible, string $name = 'design.pdf'): ProjectFile
    {
        $this->actingAs($this->staff())->post("/admin/projects/{$project->id}/files", [
            'files' => [UploadedFile::fake()->create($name, 100)],
            'client_visible' => $visible ? '1' : null,
        ]);

        return $project->files()->latest()->firstOrFail();
    }

    #[Test]
    public function a_file_is_stored_under_a_generated_name_not_the_uploaded_one(): void
    {
        $project = $this->project();
        $file = $this->upload($project, visible: true, name: 'My Design v2 (final).pdf');

        // The real name is kept for the download, but never used as the path —
        // uploaded names are untrusted input.
        $this->assertSame('My Design v2 (final).pdf', $file->original_name);
        $this->assertStringNotContainsString('My Design', $file->path);
        $this->assertStringStartsWith("projects/{$project->id}/", $file->path);
        Storage::disk('local')->assertExists($file->path);
    }

    #[Test]
    public function a_dangerous_file_type_is_refused(): void
    {
        $project = $this->project();

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/files", [
                'files' => [UploadedFile::fake()->create('shell.php', 10)],
            ])->assertSessionHasErrors('files');

        $this->assertSame(0, $project->files()->count());
    }

    #[Test]
    public function staff_can_download_any_file(): void
    {
        $project = $this->project();
        $internal = $this->upload($project, visible: false);

        $this->actingAs($this->staff())
            ->get("/files/{$internal->id}")
            ->assertOk();
    }

    #[Test]
    public function a_client_can_download_a_file_shared_with_them(): void
    {
        $client = Client::create(['name' => 'Rahim Traders']);
        $project = $this->project($client);
        $shared = $this->upload($project, visible: true);

        $this->actingAs($this->clientUser($client))
            ->get("/files/{$shared->id}")
            ->assertOk();
    }

    #[Test]
    public function a_client_cannot_download_an_internal_file_on_their_own_project(): void
    {
        // The case most likely to leak: it IS their project, but the file was
        // never meant for them.
        $client = Client::create(['name' => 'Rahim Traders']);
        $project = $this->project($client);
        $internal = $this->upload($project, visible: false);

        $this->actingAs($this->clientUser($client))
            ->get("/files/{$internal->id}")
            ->assertForbidden();
    }

    #[Test]
    public function a_client_cannot_download_another_clients_file(): void
    {
        $mine = Client::create(['name' => 'Rahim Traders']);
        $theirs = Client::create(['name' => 'Karim Enterprise']);

        $theirFile = $this->upload($this->project($theirs), visible: true);

        $this->actingAs($this->clientUser($mine))
            ->get("/files/{$theirFile->id}")
            ->assertForbidden();
    }

    private function partnerSetup(string $email = 'karim@example.com'): array
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => 10]);
        $client = Client::create(['name' => 'Rahim Traders', 'partner_id' => $partner->id]);
        $project = $this->project($client);
        $project->update(['partner_id' => $partner->id]);

        $user = User::create([
            'name' => 'Karim', 'email' => $email, 'password' => Hash::make('x'),
            'type' => AccountType::Partner, 'partner_id' => $partner->id, 'is_active' => true,
        ]);

        return [$partner, $project, $user];
    }

    #[Test]
    public function a_partner_can_download_shared_files_on_projects_they_brought(): void
    {
        // Partners often ARE the point of contact — sometimes the end client
        // never deals with us — so they get the same shared deliverables.
        [, $project, $partnerUser] = $this->partnerSetup();
        $shared = $this->upload($project, visible: true);

        $this->actingAs($partnerUser)->get("/files/{$shared->id}")->assertOk();
    }

    #[Test]
    public function a_partner_still_cannot_download_internal_files(): void
    {
        [, $project, $partnerUser] = $this->partnerSetup();
        $internal = $this->upload($project, visible: false);

        $this->actingAs($partnerUser)->get("/files/{$internal->id}")->assertForbidden();
    }

    #[Test]
    public function a_partner_cannot_download_files_from_a_project_they_did_not_bring(): void
    {
        // The rule that matters now that partners have file access at all.
        [, , $partnerUser] = $this->partnerSetup();

        $otherClient = Client::create(['name' => 'Unrelated Ltd']);
        $othersFile = $this->upload($this->project($otherClient), visible: true);

        $this->actingAs($partnerUser)->get("/files/{$othersFile->id}")->assertForbidden();
    }

    #[Test]
    public function a_guest_cannot_download_anything(): void
    {
        $file = $this->upload($this->project(), visible: true);
        $this->signOut();

        $this->get("/files/{$file->id}")->assertRedirect('/login');
    }

    #[Test]
    public function visibility_can_be_toggled(): void
    {
        $project = $this->project();
        $file = $this->upload($project, visible: false);

        $this->actingAs($this->staff())
            ->patch("/admin/projects/{$project->id}/files/{$file->id}/visibility")
            ->assertRedirect();

        $this->assertTrue($file->fresh()->client_visible);
    }

    #[Test]
    public function the_client_portal_lists_only_shared_files(): void
    {
        $client = Client::create(['name' => 'Rahim Traders']);
        $project = $this->project($client);

        $this->upload($project, visible: true, name: 'deliverable.pdf');
        $this->upload($project, visible: false, name: 'internal-notes.txt');

        $this->actingAs($this->clientUser($client))
            ->get('/portal')
            ->assertOk()
            ->assertSee('deliverable.pdf')
            ->assertDontSee('internal-notes.txt');
    }

    #[Test]
    public function removing_a_file_soft_deletes_it_and_keeps_the_object(): void
    {
        $project = $this->project();
        $file = $this->upload($project, visible: true);
        $path = $file->path;

        $this->actingAs($this->staff())
            ->delete("/admin/projects/{$project->id}/files/{$file->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('project_files', ['id' => $file->id]);
        // Recoverable: only a force delete removes the stored object.
        Storage::disk('local')->assertExists($path);
    }

    #[Test]
    public function a_missing_object_reads_as_404_rather_than_a_server_error(): void
    {
        $project = $this->project();
        $file = $this->upload($project, visible: true);

        Storage::disk('local')->delete($file->path);

        $this->actingAs($this->staff())->get("/files/{$file->id}")->assertNotFound();
    }
}
