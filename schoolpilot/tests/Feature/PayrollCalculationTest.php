<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\PayrollController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    public function test_percentage_component_overrides_cannot_exceed_one_hundred_percent(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'isValidComponentValue');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 'percentage', 100.0));
        $this->assertFalse($method->invoke($controller, 'percentage', 100.01));
        $this->assertTrue($method->invoke($controller, 'fixed', 250000.0));
    }

    public function test_fixed_salary_component_returns_the_configured_amount(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'componentAmount');
        $method->setAccessible(true);

        $this->assertSame(12500.0, $method->invoke($controller, 250000.0, 'fixed', 12500.0));
    }

    public function test_percentage_component_is_calculated_from_base_salary_and_rounded(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'componentAmount');
        $method->setAccessible(true);

        $this->assertSame(25000.0, $method->invoke($controller, 250000.0, 'percentage', 10.0));
        $this->assertSame(333.34, $method->invoke($controller, 10000.0, 'percentage', 3.3334));
    }

    public function test_payroll_creator_cannot_approve_their_own_period(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'canApprovePayroll');
        $method->setAccessible(true);
        $payroll = (object) ['created_by' => 17];

        $this->assertFalse($method->invoke($controller, $payroll, 17));
        $this->assertTrue($method->invoke($controller, $payroll, 23));
    }

    public function test_legacy_payroll_without_creator_can_be_approved_by_an_authorized_manager(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'canApprovePayroll');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, (object) ['created_by' => null], 23));
    }

    public function test_payroll_approver_cannot_mark_the_same_period_as_paid(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'canMarkPayrollPaid');
        $method->setAccessible(true);
        $payroll = (object) ['approved_by' => 17];

        $this->assertFalse($method->invoke($controller, $payroll, 17));
        $this->assertTrue($method->invoke($controller, $payroll, 23));
    }

    public function test_legacy_payroll_without_approver_can_be_marked_paid_by_an_authorized_manager(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'canMarkPayrollPaid');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, (object) ['approved_by' => null], 23));
    }

    public function test_payslip_uses_tenant_school_identity_and_logo(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'payslip');
        $method->setAccessible(true);

        $this->assertSame('payslip', $method->getName());
        $source = file_get_contents(app_path('Http/Controllers/Tenant/PayrollController.php'));
        $this->assertStringContainsString("DB::table('settings')->pluck('value', 'key')", $source);
        $this->assertStringContainsString("Storage::disk('public')->url", $source);
        $view = file_get_contents(resource_path('views/tenant/payroll/payslip.blade.php'));
        $this->assertStringContainsString("$"."schoolSettings->get('school_name'", $view);
        $this->assertStringContainsString("$"."schoolLogoUrl", $view);
    }

    public function test_payroll_register_csv_values_are_safe_from_formula_injection(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'safeCsvValue');
        $method->setAccessible(true);

        $this->assertSame("'=HYPERLINK(\"https://example.com\")", $method->invoke($controller, '=HYPERLINK("https://example.com")'));
        $this->assertSame("'+SUM(1,2)", $method->invoke($controller, '+SUM(1,2)'));
        $this->assertSame("'@command", $method->invoke($controller, '@command'));
        $this->assertSame('Christopher Okoh', $method->invoke($controller, 'Christopher Okoh'));
        $this->assertSame(12500.50, $method->invoke($controller, 12500.50));
    }

    public function test_monthly_salary_changes_must_start_on_first_day_of_month(): void
    {
        $controller = new PayrollController();
        $method = new ReflectionMethod($controller, 'isFirstDayOfMonth');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, '2026-12-01'));
        $this->assertFalse($method->invoke($controller, '2026-12-02'));
        $this->assertFalse($method->invoke($controller, '2026-11-30'));
    }

    public function test_salary_changes_cannot_rewrite_generated_payroll_periods(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');

        Schema::create('payroll_periods', function ($table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
        });
        Schema::create('payroll_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payroll_period_id');
            $table->unsignedBigInteger('staff_id');
        });

        try {
            $periodId = DB::table('payroll_periods')->insertGetId([
                'year' => 2026,
                'month' => 11,
            ]);
            DB::table('payroll_items')->insert([
                'payroll_period_id' => $periodId,
                'staff_id' => 7,
            ]);

            $controller = new PayrollController();
            $method = new ReflectionMethod($controller, 'hasGeneratedPayrollOnOrAfter');
            $method->setAccessible(true);

            $this->assertTrue($method->invoke($controller, 7, '2026-11-01'));
            $this->assertTrue($method->invoke($controller, 7, '2026-11-30'));
            $this->assertFalse($method->invoke($controller, 7, '2026-12-01'));
            $this->assertFalse($method->invoke($controller, 8, '2026-11-01'));
        } finally {
            Schema::dropIfExists('payroll_items');
            Schema::dropIfExists('payroll_periods');
        }
    }

    public function test_payroll_approval_requires_item_count_and_all_totals_to_match(): void
    {
        $controller = new PayrollController();
        $method = new \ReflectionMethod($controller, 'payrollTotalsMatch');
        $method->setAccessible(true);

        $payroll = (object) [
            'staff_count' => 2,
            'gross_total' => '500000.00',
            'deductions_total' => '25000.00',
            'net_total' => '475000.00',
        ];
        $matchingTotals = (object) [
            'item_count' => 2,
            'gross_total' => '500000.00',
            'deductions_total' => '25000.00',
            'net_total' => '475000.00',
        ];

        $this->assertTrue($method->invoke($controller, $payroll, $matchingTotals));

        $wrongCount = clone $matchingTotals;
        $wrongCount->item_count = 1;
        $this->assertFalse($method->invoke($controller, $payroll, $wrongCount));

        $wrongGross = clone $matchingTotals;
        $wrongGross->gross_total = '499999.99';
        $this->assertFalse($method->invoke($controller, $payroll, $wrongGross));

        $wrongDeductions = clone $matchingTotals;
        $wrongDeductions->deductions_total = '24999.99';
        $this->assertFalse($method->invoke($controller, $payroll, $wrongDeductions));

        $wrongNet = clone $matchingTotals;
        $wrongNet->net_total = '474999.99';
        $this->assertFalse($method->invoke($controller, $payroll, $wrongNet));
    }

}
