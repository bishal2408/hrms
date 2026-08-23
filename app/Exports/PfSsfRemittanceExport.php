<?php

namespace App\Exports;

use App\DTOs\PfSsfRemittanceReport;
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
 * The PF/SSF remittance report's downloadable form — same figures as
 * PfSsfRemittancePage, built from the same PfSsfRemittanceReport DTO so the
 * two can never disagree. Mirrors VatRegisterExport exactly, including its
 * totals-row-as-literal-values reasoning (a frozen point-in-time compliance
 * snapshot, not an editable model — see that class's docblock).
 */
class PfSsfRemittanceExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const MONEY_FORMAT = '"Rs. "#,##0.00';

    public function __construct(private readonly PfSsfRemittanceReport $report) {}

    /** @return Collection<int, Payslip> */
    public function collection(): Collection
    {
        return $this->report->payslips;
    }

    public function title(): string
    {
        return 'PF-SSF Remittance';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Period (BS)', 'Employee', 'Code', 'PF (employee)', 'PF (employer)', 'SSF (employee)', 'SSF (employer)'];
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
            $row->employee->employee_code,
            (float) $row->pf_employee,
            (float) $row->pf_employer,
            (float) $row->ssf_employee,
            (float) $row->ssf_employer,
        ];
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 24, 'C' => 14, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 16];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'D' => self::MONEY_FORMAT,
            'E' => self::MONEY_FORMAT,
            'F' => self::MONEY_FORMAT,
            'G' => self::MONEY_FORMAT,
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
                $sheet->setCellValue("D{$totalsRow}", $this->report->totalPfEmployee);
                $sheet->setCellValue("E{$totalsRow}", $this->report->totalPfEmployer);
                $sheet->setCellValue("F{$totalsRow}", $this->report->totalSsfEmployee);
                $sheet->setCellValue("G{$totalsRow}", $this->report->totalSsfEmployer);

                $sheet->getStyle("C{$totalsRow}:G{$totalsRow}")
                    ->getFont()->setBold(true);
                $sheet->getStyle("C{$totalsRow}:G{$totalsRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("D{$totalsRow}:G{$totalsRow}")
                    ->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            },
        ];
    }
}
