<?php

namespace App\Services;

use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * يستورد الطلاب من ملف Excel بنفس شكل كشف المدرسة، ويطابقهم مع الطلاب الموجودين
 * حتى لا يتكرر أحد ولا تضيع درجاته:
 *   1) برقم الهوية  2) بالاسم الكامل في نفس الصف  3) بالاسم الأول واسم العائلة في نفس الصف
 *      (لمن أُكمل اسمه أو صُحّح رقم هويته في الكشف الجديد)
 * الأعمدة: الاسم الرباعي | تاريخ الميلاد | رقم الهوية | الصف | (الشعبة اختياري) | الجنس
 */
class StudentImporter
{
    /** تقرير الاستيراد للعرض قبل التأكيد وبعده */
    public array $report = ['phones' => 0, 'created' => [], 'updated' => 0, 'corrected' => [], 'moved' => [], 'deactivated' => [], 'warnings' => [], 'newSections' => []];

    /**
     * @param  bool  $sync  إيقاف الطلاب غير الموجودين في الملف (تبقى درجاتهم محفوظة)
     * @param  bool  $dryRun  معاينة فقط بدون حفظ
     */
    public function import(string $path, bool $sync = false, bool $dryRun = false): array
    {
        $rows = $this->readRows($path);

        DB::beginTransaction();
        try {
            $this->apply($rows, $sync);
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->report;
    }

    public static function normalize(string $name): string
    {
        $s = preg_replace('/[أإآ]/u', 'ا', $name);
        $s = str_replace(['ة', 'ى', 'ـ'], ['ه', 'ي', ''], $s);
        $s = preg_replace('/[\x{064B}-\x{0652}]/u', '', $s);        // التشكيل
        $s = preg_replace('/(^|\s)(عبد|ابو)\s+/u', '$1$2', $s);      // عبد الله = عبدالله
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return $s;
    }

    private function readRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getSheet(0);
        $raw = $sheet->toArray(null, true, false, false); // قيم خام: التواريخ تبقى أرقام Excel
        $header = array_map(fn ($h) => trim((string) $h), $raw[0] ?? []);
        $col = fn (array $names, ?int $fallback) => $this->findColumn($header, $names) ?? $fallback;
        $cName = $col(['الاسم الرباعي', 'الاسم', 'اسم الطالب'], 1);
        $cBirth = $col(['تاريخ الميلاد'], 2);
        $cId = $col(['رقم الهوية', 'هوية الطالب'], 4);
        $cGrade = $col(['الصف'], 5);
        $cSection = $col(['الشعبة'], null);
        $cGender = $col(['الجنس'], 7);
        $cPhone = $col(['رقم الجوال', 'الجوال', 'رقم جوال ولي الأمر', 'جوال ولي الأمر'], 3);

        $rows = [];
        $grade = null;
        $genderBlock = null;
        foreach (array_slice($raw, 1) as $i => $r) {
            $line = $i + 2;
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($r[$cName] ?? '')));
            $first = trim((string) ($r[0] ?? ''));
            if ($name === '') {
                // سطور العناوين داخل الكشف: اسم الصف، الذكور، الإناث
                if (in_array($first, ['الذكور', 'الإناث'], true)) {
                    $genderBlock = $first === 'الذكور' ? 'male' : 'female';
                } elseif ($first !== '') {
                    $grade = $first;
                }

                continue;
            }

            $gradeName = trim((string) ($r[$cGrade] ?? '')) ?: $grade;
            if (! $gradeName) {
                $this->report['warnings'][] = "سطر {$line}: {$name} بدون صف، فلم يُستورد.";

                continue;
            }

            $g = trim((string) ($r[$cGender] ?? ''));
            $gender = str_starts_with($g, 'ذ') ? 'male' : (preg_match('/^[أا]نث/u', $g) ? 'female' : null);
            if (! $gender) {
                $gender = $genderBlock ?? 'male';
                $this->report['warnings'][] = "سطر {$line}: {$name} الجنس غير مسجّل، فاعتُبر ".($gender === 'male' ? 'ذكراً' : 'أنثى').' حسب قسمه في الكشف.';
            }

            $idRaw = trim((string) ($r[$cId] ?? ''));
            $nid = preg_replace('/\D/', '', strtr($idRaw, array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)))) ?: null;
            if ($idRaw !== '' && ! $nid) {
                $this->report['warnings'][] = "سطر {$line}: {$name} رقم الهوية غير صحيح («{$idRaw}»)، فاستُورد بدون هوية.";
            } elseif ($nid && (strlen($nid) < 8 || strlen($nid) > 9)) {
                $this->report['warnings'][] = "سطر {$line}: {$name} رقم الهوية {$nid} طوله غير معتاد، راجعه.";
            }

            $phoneRaw = $r[$cPhone] ?? null;
            $phones = \App\Support\Phone::parseMany($phoneRaw);
            if (! $phones && ! in_array($phoneRaw, [null, '', 0, '0'], true)) {
                $this->report['warnings'][] = "سطر {$line}: {$name} رقم الجوال «{$phoneRaw}» غير صحيح (ناقص أو زائد رقم)، فلم يُحفظ.";
            }

            $rows[] = [
                'phone' => $phones[0] ?? null, 'phone2' => $phones[1] ?? null,
                'line' => $line, 'name' => $name, 'grade' => $gradeName, 'gender' => $gender, 'national_id' => $nid,
                'birth_date' => $this->parseDate($r[$cBirth] ?? null),
                'section' => $cSection !== null ? trim((string) ($r[$cSection] ?? '')) : '',
            ];
        }

        return $rows;
    }

    private function apply(array $rows, bool $sync): void
    {
        $order = (int) GradeLevel::max('sort_order');
        $grades = [];
        foreach ($rows as $r) {
            if (! isset($grades[$r['grade']])) {
                $grades[$r['grade']] = GradeLevel::firstOrCreate(['name' => $r['grade']], [
                    'sort_order' => ++$order,
                    'split_by_gender' => ! in_array($r['grade'], SectionSplitter::MIXED_GRADES, true),
                ]);
            }
        }

        // كل الطلاب الحاليين (المفعّلون والموقوفون) مع صفوفهم
        $pool = Student::with('section')->get()->keyBy('id');
        $gradeOf = fn (Student $s) => $s->section->grade_level_id;
        $matched = [];

        // الجولة 1: رقم الهوية، ثم الاسم الكامل في نفس الصف
        $pending = [];
        foreach ($rows as $r) {
            $gid = $grades[$r['grade']]->id;
            $hit = $r['national_id'] ? $pool->first(fn ($s) => $s->national_id === $r['national_id']) : null;
            $hit ??= $pool->first(fn ($s) => (int) $gradeOf($s) === (int) $gid && self::normalize($s->name) === self::normalize($r['name']));
            if ($hit) {
                $matched[] = [$hit, $r];
                $pool->forget($hit->id);
            } else {
                $pending[] = $r;
            }
        }

        // الجولة 2: الاسم الأول + اسم العائلة في نفس الصف (مرشّح واحد فقط)
        $new = [];
        foreach ($pending as $r) {
            $gid = $grades[$r['grade']]->id;
            $t = explode(' ', self::normalize($r['name']));
            $candidates = $pool->filter(function ($s) use ($gid, $t, $gradeOf) {
                $u = explode(' ', self::normalize($s->name));

                return (int) $gradeOf($s) === (int) $gid && $u[0] === $t[0] && end($u) === end($t);
            });
            if ($candidates->count() === 1) {
                $hit = $candidates->first();
                $this->report['corrected'][] = $hit->name.' ← '.$r['name'];
                $matched[] = [$hit, $r];
                $pool->forget($hit->id);
            } else {
                $new[] = $r;
            }
        }

        foreach ($matched as [$student, $r]) {
            $grade = $grades[$r['grade']];
            $data = ['name' => $r['name'], 'gender' => $r['gender'], 'birth_date' => $r['birth_date'] ?? $student->birth_date, 'is_active' => true];
            if ($r['national_id']) {
                $data['national_id'] = $r['national_id'];
            }
            // لا نمسح جوالاً محفوظاً إذا كانت خانته في الملف فارغة
            if ($r['phone']) {
                $data['phone'] = $r['phone'];
                $data['phone2'] = $r['phone2'];
            }
            $section = $this->targetSection($grade, $r, $student->section);
            if ((int) $section->id !== (int) $student->section_id) {
                $data['section_id'] = $section->id;
                $this->report['moved'][] = $r['name'].' ← '.$section->full_name;
            }
            if (! empty($data['phone']) && $data['phone'] !== $student->phone) {
                $this->report['phones']++;
            }
            $student->update($data);
            $this->report['updated']++;
        }

        foreach ($new as $r) {
            $section = $this->targetSection($grades[$r['grade']], $r, null);
            Student::create([
                'section_id' => $section->id, 'name' => $r['name'], 'gender' => $r['gender'],
                'national_id' => $r['national_id'], 'birth_date' => $r['birth_date'], 'is_active' => true,
                'phone' => $r['phone'], 'phone2' => $r['phone2'],
            ]);
            $this->report['created'][] = $r['name'].' – '.$section->full_name;
        }

        if ($sync) {
            foreach ($pool->where('is_active', true) as $s) {
                $s->update(['is_active' => false]);
                $this->report['deactivated'][] = $s->name;
            }
        }
    }

    /** الشعبة الصحيحة: عمود الشعبة إن وُجد، وإلا تبقى الشعبة الحالية إن كانت مناسبة، وإلا حسب الصف والجنس */
    private function targetSection(GradeLevel $grade, array $r, ?Section $current): Section
    {
        if ($r['section'] !== '') {
            $s = Section::firstOrCreate(['grade_level_id' => $grade->id, 'name' => $r['section']]);
            if ($s->wasRecentlyCreated) {
                $this->report['newSections'][] = $grade->name.' – '.$s->name;
            }

            return $s->load('gradeLevel');
        }
        if ($current && (int) $current->grade_level_id === (int) $grade->id && $current->accepts($r['gender'])) {
            return $current->load('gradeLevel');
        }

        return SectionSplitter::sectionFor($grade, $r['gender'])->load('gradeLevel');
    }

    private function findColumn(array $header, array $names): ?int
    {
        foreach ($header as $i => $h) {
            if (in_array($h, $names, true)) {
                return $i;
            }
        }

        return null;
    }

    private function parseDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        $ts = strtotime((string) $v);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
