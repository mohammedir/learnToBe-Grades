<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * إعادة الطلاب إلى كلمة المرور الموحدة (123456789) وتنزيل بياناتهم في ملف Excel مع رسالة جاهزة للإرسال.
 */
class StudentCredentials
{
    /** @return Collection<int, array{student: Student, password: string}> */
    public function issue(Collection $students): Collection
    {
        return $students->filter(fn (Student $s) => filled($s->national_id))->map(function (Student $s) {
            \App\Support\StudentPassword::reset($s);

            return ['student' => $s, 'password' => \App\Support\StudentPassword::default()];
        })->values();
    }

    public function download(Collection $issued, string $title): StreamedResponse
    {
        $url = route('student.login');
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('حسابات الطلاب');
        $ws->setRightToLeft(true);

        $rows = [['م', 'اسم الطالب', 'الشعبة', 'رقم الهوية (اسم المستخدم)', 'كلمة المرور لأول دخول', 'رسالة جاهزة للإرسال لولي الأمر', 'الجوال', 'رابط واتساب']];
        foreach ($issued as $i => $row) {
            $s = $row['student'];
            $rows[] = [
                $i + 1, $s->name, $s->section->full_name, $s->national_id, $row['password'],
                ($real = ! in_array($row['password'], ['اختارها الطالب', 'لا يوجد حساب'], true)) ? $msg = "مدرسة تعلم لتكون الخاصة\nبوابة درجات الطالب: {$s->name}\nرابط الدخول: {$url}\nرقم الهوية: {$s->national_id}\nكلمة المرور لأول دخول: {$row['password']}\nسيُطلب تغيير كلمة المرور عند أول دخول." : '',
                $s->phone ?? '',
                $real ? ($s->whatsappUrl($msg) ?? '') : '',
            ];
        }
        $ws->fromArray($rows, null, 'A1', true);
        $ws->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $last = count($rows);
        $ws->getStyle("A1:H{$last}")->getFont()->setName('Arial');
        $ws->getStyle("F2:F{$last}")->getAlignment()->setWrapText(true);
        // رقم الهوية وكلمة المرور كنص حتى لا تُحذف الأصفار أو تتحول لصيغة علمية
        for ($r = 2; $r <= $last; $r++) {
            $ws->setCellValueExplicit("D{$r}", (string) $rows[$r - 1][3], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("E{$r}", (string) $rows[$r - 1][4], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("G{$r}", (string) $rows[$r - 1][6], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if ($rows[$r - 1][7]) {
                $ws->setCellValue("H{$r}", 'إرسال');
                $ws->getCell("H{$r}")->getHyperlink()->setUrl($rows[$r - 1][7]);
            }
        }
        foreach (['A' => 6, 'B' => 30, 'C' => 26, 'D' => 20, 'E' => 18, 'F' => 60, 'G' => 14, 'H' => 12] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A2');

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'),
            'حسابات الطلاب - '.$title.' - '.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
