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
