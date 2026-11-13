<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\ReportController;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ReportAnalyticsAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::guard('school')->logout();
        parent::tearDown();
    }

    public function test_bursar_report_data_is_limited_to_finance(): void
    {
        Auth::guard('school')->setUser(new User(['role' => 'bursar']));
        $controller = new ReportController();
        $method = new \ReflectionMethod($controller, 'visibleReportData');
        $method->setAccessible(true);

        $visible = $method->invoke($controller, [
            'finance' => ['collected' => 2500],
            'enrollment' => ['total' => 20],
            'attendance' => ['present' => 18],
            'academics' => ['average_score' => 70],
            'staff' => ['total' => 4],
        ]);

        $this->assertSame(['finance'], array_keys($visible));
        $this->assertSame(2500, $visible['finance']['collected']);
    }

    public function test_dean_report_data_is_limited_to_academic_and_enrollment_metrics(): void
    {
        Auth::guard('school')->setUser(new User(['role' => 'dean']));
        $controller = new ReportController();
        $method = new \ReflectionMethod($controller, 'visibleReportData');
        $method->setAccessible(true);

        $visible = $method->invoke($controller, [
            'finance' => ['collected' => 2500],
            'enrollment' => ['total' => 20],
            'attendance' => ['present' => 18],
            'academics' => ['average_score' => 70],
            'staff' => ['total' => 4],
        ]);

        $this->assertSame(['enrollment', 'attendance', 'academics'], array_keys($visible));
        $this->assertArrayNotHasKey('finance', $visible);
        $this->assertArrayNotHasKey('staff', $visible);
    }

    public function test_csv_rows_include_scalar_metrics_and_class_breakdowns(): void
    {
        $controller = new ReportController();
        $method = new \ReflectionMethod($controller, 'exportRows');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, [
            'finance' => ['collected' => 2500],
            'enrollment' => [
                'by_class' => new Collection([
                    (object) ['class_name' => 'Primary 1', 'student_count' => 12],
                ]),
            ],
        ]);

        $this->assertContains(['finance', 'collected', 2500], $rows);
        $this->assertContains(['enrollment', 'by_class - class_name', 'Primary 1'], $rows);
        $this->assertContains(['enrollment', 'by_class - student_count', 12], $rows);
    }
}
