<?php

namespace App\Exports;

use App\DTOs\TdsReport;
use App\Models\Payslip;
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
 * The TDS report's downloadable form — same figures as TdsReportPage, built
 * from the same TdsReport DTO so the two can never disagree. Mirrors
 * VatRegisterExport/PfSsfRemittanceExport exactly.
 */
class TdsReportExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const MONEY_FORMAT = '"Rs. "#,##0.00';

    public function __construct(private readonly TdsReport $report) {}

    /** @return Collection<int, Payslip> */
    public function collection(): Collection
    {
        return $this->report->payslips;
    }

    public function title(): string
    {
        return 'TDS Report';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Period (BS)', 'Employee', 'PAN', 'Taxable income', 'TDS'];
    }

    /**
     * @param  Payslip  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return [
            NepaliCalendar::adToBs($row->payrollRun->period_start),
            $row->employee->full_name,
            $row->employee->pan_number ?? '',
            (float) $row->taxable_income,
            (float) $row->tds,
        ];
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 24, 'C' => 16, 'D' => 16, 'E' => 16];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'D' => self::MONEY_FORMAT,
            'E' => self::MONEY_FORMAT,
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

                $totalsRow = $this->report->payslips->count() + 2; // header row + 1-indexed data rows

                $sheet->setCellValue("C{$totalsRow}", 'Total');
                $sheet->setCellValue("D{$totalsRow}", $this->report->totalTaxableIncome);
                $sheet->setCellValue("E{$totalsRow}", $this->report->totalTds);

                $sheet->getStyle("C{$totalsRow}:E{$totalsRow}")
                    ->getFont()->setBold(true);
                $sheet->getStyle("C{$totalsRow}:E{$totalsRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("D{$totalsRow}:E{$totalsRow}")
                    ->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            },
        ];
    }
}
