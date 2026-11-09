<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarksExporter
{
    /** كشف واحد (شعبة + مادة) بكل العناصر */
    public function single(Section $section, Subject $subject): StreamedResponse
    {
        $book = new Spreadsheet;
        $this->fillSheet($book->getActiveSheet(), new GradeSheet($section, $subject), $subject->name);

        return $this->download($book, 'درجات '.$subject->name.' - '.$section->full_name.'.xlsx');
    }

    /** كل الكشوف المكلّفة: ورقة لكل شعبة فيها مجموع كل مادة، وورقة تفصيلية */
    public function all(): StreamedResponse
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $components = AssessmentComponent::active()->get();

        $detail = $book->createSheet();
        $detail->setTitle('تفصيلي');
        $detailRows = [array_merge(['الشعبة', 'المادة', 'المدرس', 'اسم الطالب', 'الجنس'],
            $components->map(fn ($c) => $c->name.' ('.$this->num($c->max_score).')')->all(), ['المجموع'])];

        $sections = Section::ordered()->with('gradeLevel')->get();
        foreach ($sections as $section) {
            $assignments = TeachingAssignment::with(['subject', 'teacher'])
                ->where('section_id', $section->id)->get()->sortBy('subject.sort_order');
            if ($assignments->isEmpty()) {
                continue;
            }
            $sheets = $assignments->map(fn ($a) => [$a, new GradeSheet($section, $a->subject)]);
            $students = $sheets->first()[1]->students;

            $ws = $book->createSheet();
            $ws->setTitle($this->sheetTitle($section->full_name));
            $rows = [array_merge(['م', 'اسم الطالب', 'الجنس'], $assignments->map(fn ($a) => $a->subject->name)->all())];
            foreach ($students as $i => $st) {
                $rows[] = array_merge([$i + 1, $st->name, $st->gender_label],
                    $sheets->map(fn ($p) => $p[1]->total($st->id))->all());
            }
            $ws->fromArray($rows, null, 'A1', true);
            $this->style($ws, count($rows[0]), count($rows));

            foreach ($sheets as [$a, $sheet]) {
                foreach ($sheet->students as $st) {
                    if (! isset($sheet->marks[$st->id])) {
                        continue;
                    }
                    $detailRows[] = array_merge([$section->full_name, $a->subject->name, $a->teacher->name, $st->name, $st->gender_label],
                        $sheet->components->map(fn ($c) => $sheet->score($st->id, $c->id))->all(), [$sheet->total($st->id)]);
                }
            }
        }

        $detail->fromArray($detailRows, null, 'A1', true);
        $this->style($detail, count($detailRows[0]), count($detailRows));
        $book->setActiveSheetIndex(0);

        return $this->download($book, 'درجات الطلاب - كل الشعب.xlsx');
    }

    /**
     * كل كشوف مدرس واحد في ملف واحد:
     * ورقة «كل الكشوف» فيها الكشوف متتالية، فوق كل كشف ترويسة (المدرسة، الصف والشعبة، المادة، المدرس)
     * مع فاصل صفحات للطباعة، ثم ورقة مستقلة لكل كشف بنفس الترويسة.
     */
    public function teacher(\App\Models\User $teacher): StreamedResponse
    {
        $assignments = $teacher->assignments()->with(['section.gradeLevel', 'subject'])->get()
            ->sortBy(fn ($a) => sprintf('%05d|%s|%05d', $a->section->gradeLevel->sort_order ?? 0, $a->section->name, $a->subject->sort_order ?? 0))->values();

        $book = new Spreadsheet;
        $all = $book->getActiveSheet();
        $all->setTitle('كل الكشوف');
        $this->pageSetup($all);
        $row = 1;
        $maxCols = 1;
        $parts = [];

        if ($assignments->isEmpty()) {
            $all->setCellValue('A1', 'لا توجد كشوف مسندة للمدرس '.$teacher->name);
        }

        foreach ($assignments as $i => $a) {
            $sheet = new GradeSheet($a->section, $a->subject);
            $parts[] = [$a, $sheet];
            if ($i > 0) {
                $all->setBreak('A'.($row - 1), Worksheet::BREAK_ROW);
            }
            [$row, $cols] = $this->writeBlock($all, $row, $sheet, $a->section, $a->subject->name, $teacher->name);
            $maxCols = max($maxCols, $cols);
            $row += 2;
        }
        for ($c = 1; $c <= $maxCols; $c++) {
            $all->getColumnDimensionByColumn($c)->setWidth($c === 1 ? 6 : ($c === 2 ? 32 : ($c === 3 ? 8 : 14)));
        }

        $used = ['كل الكشوف' => true];
        foreach ($parts as [$a, $sheet]) {
            $ws = $book->createSheet();
            $title = $this->sheetTitle($a->subject->name.' '.$a->section->full_name);
            for ($n = 2; isset($used[$title]); $n++) {
                $title = mb_substr($title, 0, 28).' '.$n;
            }
            $used[$title] = true;
            $ws->setTitle($title);
            $this->pageSetup($ws);
            [, $cols] = $this->writeBlock($ws, 1, $sheet, $a->section, $a->subject->name, $teacher->name);
            for ($c = 1; $c <= $cols; $c++) {
                $ws->getColumnDimensionByColumn($c)->setWidth($c === 1 ? 6 : ($c === 2 ? 32 : ($c === 3 ? 8 : 14)));
            }
            $ws->freezePane('A6');
            $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);
        }
        $book->setActiveSheetIndex(0);

        return $this->download($book, 'كشوف المدرس '.$teacher->name.'.xlsx');
    }

    /** يكتب كشفاً واحداً بترويسته بدءاً من صف معيّن، ويعيد [الصف التالي, عدد الأعمدة] */
    private function writeBlock(Worksheet $ws, int $r, GradeSheet $sheet, Section $section, string $subject, string $teacher): array
    {
        $head = array_merge(['م', 'اسم الطالب', 'الجنس'],
            $sheet->components->map(fn ($c) => $c->name.' ('.$this->num($c->max_score).')')->all(),
            ['المجموع ('.$this->num($sheet->maxTotal()).')']);
        $cols = count($head);
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);

        $lines = [
            ['مدرسة تعلم لتكون الخاصة — كشف درجات', 15, '1F3864', 'FFFFFF'],
            ['الصف: '.($section->gradeLevel->name ?? '').'   |   الشعبة: '.$section->short_name, 13, 'D9E2F3', '1F3864'],
            ['المادة: '.$subject.'   |   المدرس: '.$teacher.'   |   عدد الطلاب: '.$sheet->students->count(), 12, 'EEF2F9', '1F3864'],
        ];
        foreach ($lines as [$text, $size, $bg, $fg]) {
            $ws->mergeCells("A{$r}:{$last}{$r}");
            $ws->setCellValue("A{$r}", $text);
            $st = $ws->getStyle("A{$r}:{$last}{$r}");
            $st->getFont()->setBold(true)->setSize($size)->setName('Arial')->getColor()->setRGB($fg);
            $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getRowDimension($r)->setRowHeight($size + 11);
            $r++;
        }
        $r++; // سطر فارغ

        $headRow = $r;
        $rows = [$head];
        foreach ($sheet->students as $i => $stu) {
            $rows[] = array_merge([$i + 1, $stu->name, $stu->gender_label],
                $sheet->components->map(fn ($c) => $sheet->score($stu->id, $c->id))->all(), [$sheet->total($stu->id)]);
        }
        $ws->fromArray($rows, null, "A{$r}", true);
        $end = $r + count($rows) - 1;

        $h = $ws->getStyle("A{$headRow}:{$last}{$headRow}");
        $h->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $h->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F5DA8');
        $h->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getRowDimension($headRow)->setRowHeight(32);
        $body = $ws->getStyle("A{$headRow}:{$last}{$end}");
        $body->getFont()->setName('Arial');
        $body->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('B7C3D6');
        if ($end > $headRow) {
            $ws->getStyle("A".($headRow + 1).":A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("C".($headRow + 1).":{$last}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("{$last}".($headRow + 1).":{$last}{$end}")->getFont()->setBold(true);
        }

        return [$end + 1, $cols];
    }

    private function pageSetup(Worksheet $ws): void
    {
        $ws->setRightToLeft(true);
        $ws->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        $ws->getSheetView()->setZoomScale(100);
    }

    private function fillSheet(Worksheet $ws, GradeSheet $sheet, string $title): void
    {
        $ws->setTitle($this->sheetTitle($title));
        $rows = [array_merge(['م', 'اسم الطالب', 'الجنس'],
            $sheet->components->map(fn ($c) => $c->name.' ('.$this->num($c->max_score).')')->all(),
            ['المجموع ('.$this->num($sheet->maxTotal()).')'])];
        foreach ($sheet->students as $i => $st) {
            $rows[] = array_merge([$i + 1, $st->name, $st->gender_label],
                $sheet->components->map(fn ($c) => $sheet->score($st->id, $c->id))->all(), [$sheet->total($st->id)]);
        }
        $ws->fromArray($rows, null, 'A1', true);
        $this->style($ws, count($rows[0]), count($rows));
    }

    private function style(Worksheet $ws, int $cols, int $rows): void
    {
        $ws->setRightToLeft(true);
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);
        $head = $ws->getStyle("A1:{$last}1");
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $ws->getStyle("A1:{$last}{$rows}")->getFont()->setName('Arial');
        for ($i = 1; $i <= $cols; $i++) {
            $ws->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $ws->freezePane('A2');
    }

    private function sheetTitle(string $name): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private function download(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
