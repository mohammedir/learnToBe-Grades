<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\StudentImporter;
use App\Support\Phone;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * يملأ أرقام جوال الطلاب الموجودين دون تغيير أي بيانات أخرى.
 *   php artisan school:sync-phones                 ← من database/data/students.json المرفق مع المشروع
 *   php artisan school:sync-phones كشف.xlsx        ← من ملف Excel بنفس شكل كشف المدرسة
 *   --overwrite  لاستبدال الأرقام المحفوظة سابقاً
 */
class SyncStudentPhones extends Command
{
    protected $signature = 'school:sync-phones {file? : ملف Excel (اختياري)} {--overwrite : استبدال الأرقام الموجودة}';

    protected $description = 'إضافة أرقام جوال الطلاب من ملف Excel أو من البيانات المرفقة';

    public function handle(): int
    {
        $rows = $this->argument('file') ? $this->fromExcel($this->argument('file')) : $this->fromJson();
        if ($rows === null) {
            return self::FAILURE;
        }

        $students = Student::with('section.gradeLevel')->get();
        $byId = $students->filter(fn ($s) => $s->national_id)->keyBy('national_id');
        $byName = $students->groupBy(fn ($s) => StudentImporter::normalize($s->name));

        $updated = $kept = $notFound = $noPhone = 0;
        $missing = [];
        foreach ($rows as $r) {
            if (! $r['phones']) {
                $noPhone++;

                continue;
            }
            $s = ($r['national_id'] ? $byId->get($r['national_id']) : null);
            if (! $s) {
                $cands = $byName->get(StudentImporter::normalize($r['name']), collect());
                $s = $cands->count() === 1 ? $cands->first() : null;
            }
            if (! $s) {
                $notFound++;
                $missing[] = $r['name'];

                continue;
            }
            if ($s->phone && ! $this->option('overwrite')) {
                $kept++;

                continue;
            }
            $s->forceFill(['phone' => $r['phones'][0], 'phone2' => $r['phones'][1] ?? null])->save();
            $updated++;
        }

        $this->info("أُضيف جوال {$updated} طالب.");
        if ($kept) {
            $this->line("{$kept} طالب لديهم جوال محفوظ فلم يتغير (استخدم --overwrite للاستبدال).");
        }
        if ($noPhone) {
            $this->line("{$noPhone} طالب بدون رقم صالح في الملف.");
        }
        if ($notFound) {
            $this->warn("{$notFound} طالب في الملف غير موجود في النظام: ".implode('، ', array_slice($missing, 0, 10)).($notFound > 10 ? '…' : ''));
        }
        $this->line('إجمالي الطلاب الذين لهم جوال الآن: '.Student::whereNotNull('phone')->count().' من '.Student::count());

        return self::SUCCESS;
    }

    private function fromJson(): ?array
    {
        $path = database_path('data/students.json');
        if (! is_file($path)) {
            $this->error('لم أجد database/data/students.json');

            return null;
        }
        $data = json_decode(file_get_contents($path), true);

        return array_map(fn ($s) => [
            'name' => $s['name'],
            'national_id' => $s['national_id'] ?? null,
            'phones' => array_values(array_filter([$s['phone'] ?? null, $s['phone2'] ?? null])),
        ], $data['students'] ?? []);
    }

    private function fromExcel(string $file): ?array
    {
        $path = is_file($file) ? $file : base_path($file);
        if (! is_file($path)) {
            $this->error("لم أجد الملف: {$file}");

            return null;
        }
        $raw = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        $header = array_map(fn ($h) => trim((string) $h), $raw[0] ?? []);
        $find = function (array $names, int $fallback) use ($header) {
            foreach ($header as $i => $h) {
                if (in_array($h, $names, true)) {
                    return $i;
                }
            }

            return $fallback;
        };
        $cName = $find(['الاسم الرباعي', 'الاسم', 'اسم الطالب'], 1);
        $cId = $find(['رقم الهوية', 'هوية الطالب'], 4);
        $cPhone = $find(['رقم الجوال', 'الجوال', 'رقم جوال ولي الأمر', 'جوال ولي الأمر'], 3);

        $rows = [];
        foreach (array_slice($raw, 1) as $r) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($r[$cName] ?? '')));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'national_id' => preg_replace('/\D/', '', (string) ($r[$cId] ?? '')) ?: null,
                'phones' => Phone::parseMany($r[$cPhone] ?? null),
            ];
        }

        return $rows;
    }
}
