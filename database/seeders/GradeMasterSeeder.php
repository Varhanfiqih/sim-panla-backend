<?php

namespace Database\Seeders;

use App\Models\GradeCategory;
use App\Models\GradePeriod;
use Illuminate\Database\Seeder;

class GradeMasterSeeder extends Seeder
{
    public function run(): void
    {
        GradePeriod::updateOrCreate(
            ['name' => 'Periode GENAP 2025/2026'],
            [
                'semester' => 'genap',
                'academic_year' => '2025/2026',
                'is_active' => true,
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
            ]
        );

        $categories = [
            [
                'name' => 'Ulangan Harian',
                'code' => 'ulangan_harian',
                'is_repeatable' => true,
                'max_item' => 5,
                'max_score' => 100,
                'weight' => 35,
                'sort_order' => 10,
                'is_active' => true,
                'description' => 'Penilaian ulangan harian.',
            ],
            [
                'name' => 'Ulangan Tengah Semester',
                'code' => 'uts',
                'is_repeatable' => false,
                'max_item' => 1,
                'max_score' => 100,
                'weight' => 20,
                'sort_order' => 30,
                'is_active' => true,
                'description' => 'Penilaian tengah semester.',
            ],
            [
                'name' => 'Ulangan Semester',
                'code' => 'uas',
                'is_repeatable' => false,
                'max_item' => 1,
                'max_score' => 100,
                'weight' => 20,
                'sort_order' => 40,
                'is_active' => true,
                'description' => 'Penilaian akhir semester.',
            ],
        ];

        foreach ($categories as $category) {
            GradeCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
