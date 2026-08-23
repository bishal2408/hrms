<?php

namespace App\Exports;

use App\DTOs\VatRegisterLine;
use App\DTOs\VatRegisterReport;
use App\Services\NepaliCalendar;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The VAT register's downloadable form — same figures as VatRegisterPage,
 * built from the same VatRegisterReport DTO so the two can never disagree.
 *
 * The totals row writes AccountingReportService's already-computed,
 * already-tested totals as literal values, not a SUM/SUMIFS formula. This
 * is a frozen point-in-time compliance snapshot (the same spirit as an
 * issued invoice or a payslip in this app — cancel-don't-delete, never
 * recomputed after the fact), not an editable financial model someone is
 * meant to extend and recalculate; a formula that a viewer's own edit could
 * silently drift from what was actually reviewed on screen is the wrong
 * tool here.
 */
class VatRegisterExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const MONEY_FORMAT = '"Rs. "#,##0.00';

    public function __construct(private readonly VatRegisterReport $report) {}

    /** @return Collection<int, VatRegisterLine> */
    public function collection(): Collection
    {
        return $this->report->lines;
    }

    public function title(): string
    {
        return 'VAT Register';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Date (BS)', 'Invoice #', 'Customer', 'PAN', 'Taxable', 'Exempt', 'VAT', 'Total', 'Status'];
    }

    /**
     * @param  VatRegisterLine  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            NepaliCalendar::adToBs($row->invoice->issue_date),
            $row->invoice->invoice_number,
            $row->invoice->customer->name,
            $row->invoice->customer->pan_number ?? '',
            $row->taxableAmount,
            $row->exemptAmount,
            (float) $row->invoice->vat_amount,
            (float) $row->invoice->total,
            ucfirst($row->invoice->status),
        ];
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 18, 'C' => 28, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 14, 'H' => 14, 'I' => 12];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'E' => self::MONEY_FORMAT,
            'F' => self::MONEY_FORMAT,
            'G' => self::MONEY_FORMAT,
            'H' => self::MONEY_FORMAT,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
            ],
        ];
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->getDelegate();
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

                $totalsRow = $this->report->lines->count() + 2; // header row + 1-indexed data rows

                $sheet->setCellValue("D{$totalsRow}", 'Total (cancelled invoices excluded)');
                $sheet->setCellValue("E{$totalsRow}", $this->report->totalTaxable);
                $sheet->setCellValue("F{$totalsRow}", $this->report->totalExempt);
                $sheet->setCellValue("G{$totalsRow}", $this->report->totalVat);
                $sheet->setCellValue("H{$totalsRow}", round($this->report->totalSales() + $this->report->totalVat, 2));

                $sheet->getStyle("D{$totalsRow}:H{$totalsRow}")
                    ->getFont()->setBold(true);
                $sheet->getStyle("D{$totalsRow}:H{$totalsRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("E{$totalsRow}:H{$totalsRow}")
                    ->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            },
        ];
    }
}
