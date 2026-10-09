<?php

namespace Tests\Feature;

use App\Actions\BOQ\SubmitBOQ;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BoqApprovalMenuTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_approver_sees_only_boqs_waiting_for_approval(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);

        $waitingBoq = $this->makeDraftBoq($admin, $project, code: 'BOQ-WAITING');
        app(SubmitBOQ::class)->handle($admin, $waitingBoq);

        $this->makeDraftBoq($admin, $project, code: 'BOQ-DRAFT');

        $otherProject = $this->makeActiveProject($admin, ['code' => 'PRJ-002']);
        $this->makeApprovedBoq($admin, $otherProject, 'BOQ-APPROVED');

        $this->actingAs($this->makeApprover())
            ->get(route('boqs.approvals'))
            ->assertOk()
            ->assertSee('BOQ-WAITING')
            ->assertSee(route('boqs.review', $waitingBoq), false)
            ->assertDontSee('BOQ-DRAFT')
            ->assertDontSee('BOQ-APPROVED');
    }

    public function test_sidebar_shows_approval_menu_with_pending_count_for_approver(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $boq = $this->makeDraftBoq($admin, $project);
        app(SubmitBOQ::class)->handle($admin, $boq);

        $this->actingAs($this->makeApprover())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('boqs.approvals'), false)
            ->assertSee('1 BOQ menunggu approval');
    }

    public function test_users_without_review_permission_cannot_open_approval_menu(): void
    {
        $user = $this->makeBoqCostControl();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('boqs.approvals'), false);

        $this->actingAs($user)
            ->get(route('boqs.approvals'))
            ->assertForbidden();
    }
}
