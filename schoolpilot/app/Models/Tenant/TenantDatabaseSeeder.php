<?php

namespace App\Models\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $nurseryId = DB::table('sections')->insertGetId(['name' => 'Nursery', 'code' => 'NURSERY', 'created_at' => now(), 'updated_at' => now()]);
        $primaryId = DB::table('sections')->insertGetId(['name' => 'Primary', 'code' => 'PRIMARY', 'created_at' => now(), 'updated_at' => now()]);
        $secondaryId = DB::table('sections')->insertGetId(['name' => 'Secondary', 'code' => 'SECONDARY', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([$primaryId, $secondaryId] as $sectionId) {
            DB::table('assessment_components')->insert([
                ['section_id' => $sectionId, 'name' => 'CA1', 'type' => 'ca_test', 'max_score' => 10, 'weight' => 10, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['section_id' => $sectionId, 'name' => 'CA2', 'type' => 'ca_assignment', 'max_score' => 10, 'weight' => 10, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['section_id' => $sectionId, 'name' => 'CA3', 'type' => 'ca_project', 'max_score' => 10, 'weight' => 10, 'display_order' => 3, 'created_at' => now(), 'updated_at' => now()],
                ['section_id' => $sectionId, 'name' => 'Exam', 'type' => 'exam', 'max_score' => 70, 'weight' => 70, 'display_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $nurseryAreas = ['Cognitive', 'Social-Emotional', 'Motor Skills', 'Language & Literacy', 'Creativity & Play'];
        foreach ($nurseryAreas as $index => $area) {
            DB::table('assessment_components')->insert([
                'section_id' => $nurseryId, 'name' => $area, 'type' => 'observation', 
                'max_score' => 5, 'weight' => 0, 'display_order' => $index + 1, 
                'created_at' => now(), 'updated_at' => now()
            ]);
        }

        $this->createGradingScale($secondaryId, 'WAEC Scale', [
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

        $this->createGradingScale($primaryId, 'Primary Scale', [
            ['min' => 70, 'max' => 100, 'grade' => 'A', 'remark' => 'Excellent'],
            ['min' => 60, 'max' => 69.99, 'grade' => 'B', 'remark' => 'Very Good'],
            ['min' => 50, 'max' => 59.99, 'grade' => 'C', 'remark' => 'Good'],
            ['min' => 40, 'max' => 49.99, 'grade' => 'D', 'remark' => 'Fair'],
            ['min' => 0, 'max' => 39.99, 'grade' => 'F', 'remark' => 'Needs Improvement'],
        ]);

        $skills = ['Communication', 'Critical Thinking', 'Collaboration', 'Creativity', 'Digital Literacy', 'Problem Solving'];
        foreach ([$nurseryId, $primaryId, $secondaryId] as $sectionId) {
            foreach ($skills as $index => $skill) {
                DB::table('skills_rubrics')->insert([
                    'section_id' => $sectionId, 'skill_name' => $skill, 'description' => "Observable {$skill} behaviours",
                    'scale_min' => 1, 'scale_max' => 5, 'display_order' => $index + 1,
                    'created_at' => now(), 'updated_at' => now()
                ]);
            }
        }

        DB::table('settings')->insert([
            ['key' => 'school_name', 'value' => 'New School', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ranking_nursery_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ranking_primary_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ranking_secondary_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'result_pdf_qr_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createGradingScale($sectionId, $name, $bands)
    {
        $scaleId = DB::table('grading_scales')->insertGetId([
            'section_id' => $sectionId, 'name' => $name, 'is_default' => true,
            'created_at' => now(), 'updated_at' => now()
        ]);

        foreach ($bands as $band) {
            DB::table('grading_bands')->insert([
                'grading_scale_id' => $scaleId,
                'min_score' => $band['min'],
                'max_score' => $band['max'],
                'grade' => $band['grade'],
                'remark' => $band['remark'],
                'points' => $band['points'] ?? null,
                'created_at' => now(), 'updated_at' => now()
            ]);
        }
    }
}