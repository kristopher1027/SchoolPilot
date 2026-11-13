<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\StaffHrController;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffHrWorkflowTest extends TestCase
{
    public function test_leave_working_day_calculation_excludes_weekends(): void
    {
        $controller = new StaffHrController();
        $method = new \ReflectionMethod($controller, 'workingDays');
        $method->setAccessible(true);

        $days = $method->invoke(
            $controller,
            Carbon::parse('2026-11-16'),
            Carbon::parse('2026-11-22')
        );

        $this->assertSame(5, $days);
    }

    public function test_leave_working_day_calculation_handles_a_weekend_only_period(): void
    {
        $controller = new StaffHrController();
        $method = new \ReflectionMethod($controller, 'workingDays');
        $method->setAccessible(true);

        $days = $method->invoke(
            $controller,
            Carbon::parse('2026-11-14'),
            Carbon::parse('2026-11-15')
        );

        $this->assertSame(0, $days);
    }
}
