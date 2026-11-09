<?php

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SchoolPilotDemoSetupCommand extends Command
{
    protected $signature = 'schoolpilot:demo-setup';

    protected $description = 'Create the temporary Greenfield demo school on first startup';

    public function handle(): int
    {
        if (app()->environment('production') && ! filter_var(env('SCHOOLPILOT_DEMO_MODE', false), FILTER_VALIDATE_BOOL)) {
            $this->components->error('Demo setup is disabled unless SCHOOLPILOT_DEMO_MODE=true.');
            return self::FAILURE;
        }

        $slug = (string) env('SCHOOLPILOT_DEMO_SCHOOL_SLUG', 'greenfield');
        $name = (string) env('SCHOOLPILOT_DEMO_SCHOOL_NAME', 'Greenfield Demo School');
        $email = (string) env('SCHOOLPILOT_DEMO_ADMIN_EMAIL', 'admin@schoolpilot-demo.test');

        if (Tenant::query()->where('data->slug', $slug)->exists()) {
            $this->components->info('Demo school already exists; leaving its data unchanged.');
            return self::SUCCESS;
        }

        $exitCode = Artisan::call('school:create', [
            'name' => $name,
            'slug' => $slug,
            'email' => $email,
            '--demo' => true,
        ]);

        $this->output->write(Artisan::output());

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
