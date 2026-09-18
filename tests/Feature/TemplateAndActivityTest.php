<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlacementTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TemplateAndActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_are_validated_scoped_and_manageable(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $data = ['name' => 'Approval form', 'page_count' => 2, 'placements' => [['id' => (string) Str::uuid(), 'type' => 'date', 'text' => '2026-09-18', 'page_number' => 2, 'x_ratio' => 0.1, 'y_ratio' => 0.1, 'width_ratio' => 0.2, 'height_ratio' => 0.05]]];
        $this->actingAs($owner)->postJson('/templates', $data)->assertCreated();
        $template = PlacementTemplate::firstOrFail();
        $this->patch('/templates/'.$template->id, ['name' => 'Renamed'])->assertRedirect();
        $this->assertSame('Renamed', $template->fresh()->name);
        $data['placements'][0]['page_number'] = 3;
        $this->postJson('/templates', $data)->assertUnprocessable();
        $this->actingAs($other)->get('/templates')->assertInertia(fn (Assert $page) => $page->component('Templates/Index')->has('templates.data', 0));
        $this->patch('/templates/'.$template->id, ['name' => 'Stolen'])->assertForbidden();
        $this->delete('/templates/'.$template->id)->assertForbidden();
        $this->actingAs($owner)->delete('/templates/'.$template->id)->assertRedirect();
        $this->assertDatabaseCount('placement_templates', 0);
    }

    public function test_activity_only_exposes_the_current_users_events(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        AuditLog::create(['user_id' => $owner->id, 'action' => 'document_uploaded', 'document_name' => 'Private.pdf', 'ip_address' => '127.0.0.1']);
        $this->actingAs($other)->get('/activity')->assertInertia(fn (Assert $page) => $page->component('Activity/Index')->has('logs.data', 0));
        $this->actingAs($owner)->get('/activity')->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.document_name', 'Private.pdf')->missing('logs.data.0.ip_address'));
    }
}
