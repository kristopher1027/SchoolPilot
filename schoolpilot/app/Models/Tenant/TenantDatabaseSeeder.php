<?php

namespace App\Models\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            'NURSERY' => 'Nursery',
            'PRIMARY' => 'Primary',
            'SECONDARY' => 'Secondary',
        ];

        $sectionIds = [];
        foreach ($sections as $code => $name) {
            $section = DB::table('sections')->where('code', $code)->first();

            $sectionIds[$code] = $section
                ? $section->id
                : DB::table('sections')->insertGetId([
                    'name' => $name,
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        foreach (['PRIMARY', 'SECONDARY'] as $code) {
            $sectionId = $sectionIds[$code];
            $components = [
                ['name' => 'CA1', 'type' => 'ca_test', 'max_score' => 10, 'weight' => 10, 'display_order' => 1],
                ['name' => 'CA2', 'type' => 'ca_assignment', 'max_score' => 10, 'weight' => 10, 'display_order' => 2],
                ['name' => 'CA3', 'type' => 'ca_project', 'max_score' => 10, 'weight' => 10, 'display_order' => 3],
                ['name' => 'Exam', 'type' => 'exam', 'max_score' => 70, 'weight' => 70, 'display_order' => 4],
            ];

            foreach ($components as $component) {
                $exists = DB::table('assessment_components')
                    ->where('section_id', $sectionId)
                    ->where('name', $component['name'])
                    ->where('type', $component['type'])
                    ->exists();

                if (! $exists) {
                    DB::table('assessment_components')->insert($component + [
                        'section_id' => $sectionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $nurseryAreas = ['Cognitive', 'Social-Emotional', 'Motor Skills', 'Language & Literacy', 'Creativity & Play'];
        foreach ($nurseryAreas as $index => $area) {
            $exists = DB::table('assessment_components')
                ->where('section_id', $sectionIds['NURSERY'])
                ->where('name', $area)
                ->where('type', 'observation')
                ->exists();

            if (! $exists) {
                DB::table('assessment_components')->insert([
                    'section_id' => $sectionIds['NURSERY'],
                    'name' => $area,
                    'type' => 'observation',
                    'max_score' => 5,
                    'weight' => 0,
                    'display_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->createGradingScale($sectionIds['SECONDARY'], 'WAEC Scale', [
            ['min' => 75, 'max' => 100, 'grade' => 'A1', 'remark' => 'Excellent', 'points' => 9],
            ['min' => 70, 'max' => 74.99, 'grade' => 'B2', 'remark' => 'Very Good', 'points' => 8],
            ['min' => 65, 'max' => 69.99, 'grade' => 'B3', 'remark' => 'Good', 'points' => 7],
            ['min' => 60, 'max' => 64.99, 'grade' => 'C4', 'remark' => 'Credit', 'points' => 6],
            ['min' => 55, 'max' => 59.99, 'grade' => 'C5', 'remark' => 'Credit', 'points' => 5],
            ['min' => 50, 'max' => 54.99, 'grade' => 'C6', 'remark' => 'Credit', 'points' => 4],
            ['min' => 45, 'max' => 49.99, 'grade' => 'D7', 'remark' => 'Pass', 'points' => 3],
            ['min' => 40, 'max' => 44.99, 'grade' => 'E8', 'remark' => 'Pass', 'points' => 2],
            ['min' => 0, 'max' => 39.99, 'grade' => 'F9', 'remark' => 'Fail', 'points' => 1],
        ]);

        $this->createGradingScale($sectionIds['PRIMARY'], 'Primary Scale', [
            ['min' => 70, 'max' => 100, 'grade' => 'A', 'remark' => 'Excellent'],
            ['min' => 60, 'max' => 69.99, 'grade' => 'B', 'remark' => 'Very Good'],
            ['min' => 50, 'max' => 59.99, 'grade' => 'C', 'remark' => 'Good'],
            ['min' => 40, 'max' => 49.99, 'grade' => 'D', 'remark' => 'Fair'],
            ['min' => 0, 'max' => 39.99, 'grade' => 'F', 'remark' => 'Needs Improvement'],
        ]);

        $skills = ['Communication', 'Critical Thinking', 'Collaboration', 'Creativity', 'Digital Literacy', 'Problem Solving'];
        foreach ($sectionIds as $sectionId) {
            foreach ($skills as $index => $skill) {
                $exists = DB::table('skills_rubrics')
                    ->where('section_id', $sectionId)
                    ->where('skill_name', $skill)
                    ->exists();

                if (! $exists) {
                    DB::table('skills_rubrics')->insert([
                        'section_id' => $sectionId,
                        'skill_name' => $skill,
                        'description' => "Observable {$skill} behaviours",
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'display_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        DB::table('settings')->insertOrIgnore([
            ['key' => 'school_name', 'value' => 'New School', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ranking_nursery_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ranking_primary_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ranking_secondary_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'result_pdf_qr_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createGradingScale(int $sectionId, string $name, array $bands): void
    {
        $scale = DB::table('grading_scales')
            ->where('section_id', $sectionId)
            ->where('name', $name)
            ->first();

        $scaleId = $scale
            ? $scale->id
            : DB::table('grading_scales')->insertGetId([
                'section_id' => $sectionId,
                'name' => $name,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($bands as $band) {
            $exists = DB::table('grading_bands')
                ->where('grading_scale_id', $scaleId)
                ->where('grade', $band['grade'])
                ->exists();

            if (! $exists) {
                DB::table('grading_bands')->insert([
                    'grading_scale_id' => $scaleId,
                    'min_score' => $band['min'],
                    'max_score' => $band['max'],
                    'grade' => $band['grade'],
                    'remark' => $band['remark'],
                    'points' => $band['points'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
