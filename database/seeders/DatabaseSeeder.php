<?php

namespace Database\Seeders;

use App\Models\AssessmentComponent;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\SectionSplitter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // حساب الإدارة الأول: غيّر كلمة المرور بعد أول دخول
        User::updateOrCreate(['username' => env('ADMIN_USERNAME', 'admin')], [
            'name' => 'إدارة المدرسة',
            'role' => 'admin',
            'is_active' => true,
            'password' => env('ADMIN_PASSWORD', 'ChangeMe@2026'),
        ]);

        $subjects = ['التربية الإسلامية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم والحياة',
            'التنشئة الوطنية والحياتية', 'الدراسات الاجتماعية', 'التكنولوجيا', 'الفيزياء', 'الكيمياء', 'الأحياء',
            'التاريخ', 'الجغرافيا'];
        foreach ($subjects as $i => $name) {
            Subject::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }

        if (AssessmentComponent::count() === 0) {
            foreach ([['الاختبار الشهري الأول', 20], ['الاختبار الشهري الثاني', 20], ['المشاركة والأنشطة', 5], ['الواجبات', 5]] as $i => [$name, $max]) {
                AssessmentComponent::create(['name' => $name, 'max_score' => $max, 'sort_order' => $i + 1]);
            }
        }

        // الصفوف والشعب والطلاب من كشف المدرسة (database/data/students.json)
        $data = json_decode(file_get_contents(database_path('data/students.json')), true);
        DB::transaction(function () use ($data) {
            $grades = [];
            foreach ($data['grades'] as $i => $gradeName) {
                $grades[$gradeName] = GradeLevel::firstOrCreate(['name' => $gradeName], ['sort_order' => $i + 1]);
            }
            // مختلط حتى الخامس، ومن السادس فما فوق إناث صباحي وذكور مسائي
            app(SectionSplitter::class)->apply();
            if (Student::count() > 0) {
                return; // لا نكرر الطلاب عند إعادة تشغيل البذرة
            }
            foreach ($data['students'] as $s) {
                $grade = $grades[$s['grade']]->refresh();
                Student::create([
                    'section_id' => SectionSplitter::sectionFor($grade, $s['gender'])->id,
                    'name' => $s['name'],
                    'gender' => $s['gender'],
                    'national_id' => $s['national_id'],
                    'birth_date' => $s['birth_date'],
                    'phone' => $s['phone'] ?? null,
                    'phone2' => $s['phone2'] ?? null,
                ]);
            }
        });
    }
}
