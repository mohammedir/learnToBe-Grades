<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بيانات المدرسين في Excel: ورقة «المدرسون» (البيانات ونسبة إكمال الرصد) وورقة «التكليفات» (المواد والشعب).
 * كلمات المرور لا تُصدَّر لأنها محفوظة مشفّرة.
 */
class TeachersExporter
{
    public static function download(): StreamedResponse
    {
        $teachers = User::teachers()->with(['assignments.subject', 'assignments.section.gradeLevel'])
            ->orderByDesc('is_active')->orderBy('name')->get();
        $allAssignments = $teachers->flatMap->assignments;
        $progress = GradeSheet::progressFor($allAssignments);
        $month = AttendanceRecord::where('type', 'in')->where('occurred_at', '>=', now()->startOfMonth())
            ->selectRaw('user_id, COUNT(DISTINCT DATE(occurred_at)) d')->groupBy('user_id')->pluck('d', 'user_id');

        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('المدرسون');
        $head = ['م', 'الاسم', 'اسم المستخدم', 'الجوال', 'رقم ظاهر للطلاب', 'الحالة', 'آخر دخول', 'عدد الكشوف', 'المواد', 'الشعب', 'خانات مرصودة', 'خانات مطلوبة', 'نسبة إكمال الرصد', 'أيام حضور هذا الشهر'];
        $rows = [];
        foreach ($teachers as $i => $t) {
            $filled = $expected = 0;
            foreach ($t->assignments as $a) {
                $filled += $progress[$a->id]['filled'] ?? 0;
                $expected += $progress[$a->id]['expected'] ?? 0;
            }
            $rows[] = [
                $i + 1, $t->name, $t->username, $t->phone ?: '', $t->show_phone_to_students ? 'نعم' : 'لا',
                $t->is_active ? 'مفعّل' : 'موقوف', $t->last_login_at?->format('Y-m-d H:i') ?? 'لم يدخل',
                $t->assignments->count(),
                $t->assignments->pluck('subject.name')->filter()->unique()->implode('، '),
                $t->assignments->map(fn ($a) => $a->section?->full_name)->filter()->unique()->implode('، '),
                $filled, $expected, $expected ? $filled / $expected : null, (int) ($month[$t->id] ?? 0),
            ];
        }
        self::table($ws, 'بيانات المدرسين — '.now()->format('Y/m/d'), $head, $rows,
            ['A' => 5, 'B' => 28, 'C' => 16, 'D' => 14, 'E' => 10, 'F' => 9, 'G' => 17, 'H' => 9, 'I' => 34, 'J' => 44, 'K' => 10, 'L' => 10, 'M' => 11, 'N' => 11]);
        $last = 3 + count($rows);
        $ws->getStyle("M4:M{$last}")->getNumberFormat()->setFormatCode('0%');
        $ws->getStyle("D4:D{$last}")->getNumberFormat()->setFormatCode('@');
        foreach (['B', 'I', 'J'] as $col) {
            $ws->getStyle("{$col}4:{$col}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setWrapText(true);
        }

        // التكليفات: سطر لكل مادة × شعبة
        $w2 = $book->createSheet();
        $w2->setTitle('التكليفات');
        $rows2 = [];
        $n = 0;
        foreach ($teachers as $t) {
            foreach ($t->assignments->sortBy(fn ($a) => sprintf('%05d|%s', $a->section?->gradeLevel?->sort_order ?? 0, $a->section?->name)) as $a) {
                $p = $progress[$a->id] ?? null;
                $rows2[] = [++$n, $t->name, $a->section?->gradeLevel?->name, $a->section?->full_name, $a->subject?->name,
                    $p['students'] ?? 0, $p ? ($p['expected'] ? $p['filled'] / $p['expected'] : null) : null, $p && $p['components'] && $p['approved'] >= $p['components'] ? 'معتمد' : ($p && $p['approved'] ? 'معتمد جزئياً' : '')];
            }
        }
        self::table($w2, 'تكليفات المدرسين (المواد والشعب)', ['م', 'المدرس', 'الصف', 'الشعبة', 'المادة', 'عدد الطلاب', 'إكمال الرصد', 'الاعتماد'], $rows2,
            ['A' => 5, 'B' => 28, 'C' => 16, 'D' => 30, 'E' => 18, 'F' => 10, 'G' => 11, 'H' => 10]);
        $w2->getStyle('G4:G'.(3 + count($rows2)))->getNumberFormat()->setFormatCode('0%');
        $w2->getStyle('B4:E'.(3 + count($rows2)))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'بيانات المدرسين '.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private static function table(Worksheet $ws, string $title, array $head, array $rows, array $widths): void
    {
        $ws->setRightToLeft(true);
        $last = Coordinate::stringFromColumnIndex(count($head));
        $ws->mergeCells("A1:{$last}1");
        $ws->setCellValue('A1', 'مدرسة تعلم لتكون الخاصة — '.$title);
        $ws->getStyle("A1:{$last}1")->getFont()->setBold(true)->setSize(14)->setName('Arial')->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getRowDimension(1)->setRowHeight(26);
        $ws->fromArray(array_merge([$head], $rows), null, 'A3', true);
        $end = 3 + count($rows);
        $h = $ws->getStyle("A3:{$last}3");
        $h->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $h->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F5DA8');
        $h->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $all = $ws->getStyle("A3:{$last}{$end}");
        $all->getFont()->setName('Arial');
        $all->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B7C3D6');
        $all->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        foreach ($widths as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('C4');
        $ws->setAutoFilter("A3:{$last}{$end}");
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
    }
}
