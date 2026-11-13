<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\AttendanceController;
use ReflectionMethod;
use Tests\TestCase;

class ParentAttendanceNotificationRulesTest extends TestCase
{
    public function test_new_absence_and_late_statuses_trigger_parent_alerts(): void
    {
        $this->assertSame('attendance_alert', $this->invoke(null, 'absent'));
        $this->assertSame('attendance_alert', $this->invoke(null, 'late'));
    }

    public function test_changing_an_alerting_status_triggers_a_correction_notice(): void
    {
        $this->assertSame('attendance_updated', $this->invoke('absent', 'present'));
        $this->assertSame('attendance_updated', $this->invoke('late', 'absent'));
    }

    public function test_unchanged_status_does_not_send_duplicate_alerts(): void
    {
        $this->assertNull($this->invoke('absent', 'absent'));
        $this->assertNull($this->invoke('present', 'present'));
    }

    public function test_new_present_and_excused_statuses_do_not_trigger_alerts(): void
    {
        $this->assertNull($this->invoke(null, 'present'));
        $this->assertNull($this->invoke(null, 'excused'));
    }

    private function invoke(?string $previousStatus, string $status): ?string
    {
        $controller = new AttendanceController();
        $method = new ReflectionMethod($controller, 'attendanceNotificationType');

        return $method->invoke($controller, $previousStatus, $status);
    }
}
