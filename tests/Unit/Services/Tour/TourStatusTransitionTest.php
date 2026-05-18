<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tour;

use App\Enums\TourStatus;
use App\Services\Tour\TourStatusTransition;
use Tests\TestCase;

class TourStatusTransitionTest extends TestCase
{
    public function test_allows_draft_to_active_and_archived(): void
    {
        $transition = new TourStatusTransition;

        $this->assertTrue($transition->isValid(TourStatus::Draft, TourStatus::Active));
        $this->assertTrue($transition->isValid(TourStatus::Draft, TourStatus::Archived));
    }

    public function test_blocks_same_status_transition(): void
    {
        $transition = new TourStatusTransition;

        foreach (TourStatus::cases() as $status) {
            $this->assertFalse($transition->isValid($status, $status), "Transition from {$status->value} to itself should be invalid");
        }
    }

    public function test_blocks_archived_to_active(): void
    {
        $transition = new TourStatusTransition;

        $this->assertFalse($transition->isValid(TourStatus::Archived, TourStatus::Active));
        $this->assertTrue($transition->isValid(TourStatus::Archived, TourStatus::Draft));
    }

    public function test_active_can_only_go_to_paused_or_archived(): void
    {
        $transition = new TourStatusTransition;

        $this->assertTrue($transition->isValid(TourStatus::Active, TourStatus::Paused));
        $this->assertTrue($transition->isValid(TourStatus::Active, TourStatus::Archived));
        $this->assertFalse($transition->isValid(TourStatus::Active, TourStatus::Draft));
    }

    public function test_paused_can_only_go_to_active_or_archived(): void
    {
        $transition = new TourStatusTransition;

        $this->assertTrue($transition->isValid(TourStatus::Paused, TourStatus::Active));
        $this->assertTrue($transition->isValid(TourStatus::Paused, TourStatus::Archived));
        $this->assertFalse($transition->isValid(TourStatus::Paused, TourStatus::Draft));
    }

    public function test_archiving_requires_admin(): void
    {
        $transition = new TourStatusTransition;

        $this->assertTrue($transition->requiresAdmin(TourStatus::Archived));
        $this->assertFalse($transition->requiresAdmin(TourStatus::Active));
        $this->assertFalse($transition->requiresAdmin(TourStatus::Paused));
        $this->assertFalse($transition->requiresAdmin(TourStatus::Draft));
    }
}
