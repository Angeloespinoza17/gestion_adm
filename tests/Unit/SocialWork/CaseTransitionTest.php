<?php
namespace Tests\Unit\SocialWork;
use App\Services\SocialWork\CaseService;
use PHPUnit\Framework\TestCase;
class CaseTransitionTest extends TestCase {
    public function test_closed_cases_only_allow_reopening(): void { $this->assertSame(['reabierto'], CaseService::TRANSITIONS['cerrado']); }
    public function test_annulled_cases_are_terminal(): void { $this->assertSame([], CaseService::TRANSITIONS['anulado']); }
    public function test_open_case_cannot_jump_directly_to_closed(): void { $this->assertNotContains('cerrado', CaseService::TRANSITIONS['abierto']); $this->assertContains('pendiente_cierre', CaseService::TRANSITIONS['abierto']); }
}
