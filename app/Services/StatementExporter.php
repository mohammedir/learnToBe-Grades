<?php

namespace App\Services;

use App\Models\Supplier;
use App\Support\Money;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** كشف حساب المطبعة في ملف Excel */
class StatementExporter
{
    public static function download(Supplier $supplier, array $st): StreamedResponse
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('كشف الحساب');
        $ws->setRightToLeft(true);
        $ws->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        $head = ['التاريخ', 'المرجع', 'البيان', 'التفاصيل', 'بواسطة', 'مدين (طلبات)', 'دائن (دفعات)', 'الرصيد'];
        $last = Coordinate::stringFromColumnIndex(count($head));
        $period = ($st['from'] ? 'من '.$st['from']->format('Y/m/d') : 'من البداية').' '.($st['to'] ? 'إلى '.$st['to']->format('Y/m/d') : 'حتى اليوم');
        $titles = [
            ['مدرسة تعلم لتكون الخاصة — كشف حساب', 15, '1F3864', 'FFFFFF'],
            [$supplier->name.'   |   '.$period.'   |   العملة: '.Money::currency(), 12, 'D9E2F3', '1F3864'],
        ];
        $r = 1;
        foreach ($titles as [$text, $size, $bg, $fg]) {
            $ws->mergeCells("A{$r}:{$last}{$r}");
            $ws->setCellValue("A{$r}", $text);
            $s = $ws->getStyle("A{$r}:{$last}{$r}");
            $s->getFont()->setBold(true)->setSize($size)->setName('Arial')->getColor()->setRGB($fg);
            $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getRowDimension($r)->setRowHeight($size + 11);
            $r++;
        }
        $r++;
        $headRow = $r;
        $rows = [$head, ['', '', 'رصيد افتتاحي', '', '', null, null, $st['opening']]];
        foreach ($st['rows'] as $l) {
            $rows[] = [$l['date']->format('Y-m-d'), $l['ref'], $l['desc'], $l['detail'], $l['by'], $l['debit'] ?: null, $l['credit'] ?: null, $l['balance']];
        }
        $rows[] = ['', '', 'الإجمالي', '', '', $st['debit'], $st['credit'], $st['closing']];
        $ws->fromArray($rows, null, "A{$r}", true);
        $end = $r + count($rows) - 1;

        $h = $ws->getStyle("A{$headRow}:{$last}{$headRow}");
        $h->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $h->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F5DA8');
        $h->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $all = $ws->getStyle("A{$headRow}:{$last}{$end}");
        $all->getFont()->setName('Arial');
        $all->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B7C3D6');
        $ws->getStyle('F'.($headRow + 1).":H{$end}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach ([$headRow + 1, $end] as $bold) {
            $ws->getStyle("A{$bold}:{$last}{$bold}")->getFont()->setBold(true);
            $ws->getStyle("A{$bold}:{$last}{$bold}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F9');
        }
        foreach (['A' => 12, 'B' => 12, 'C' => 38, 'D' => 46, 'E' => 18, 'F' => 15, 'G' => 15, 'H' => 15] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A'.($headRow + 1));
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headRow, $headRow);

        $name = 'كشف حساب '.$supplier->name.' '.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
