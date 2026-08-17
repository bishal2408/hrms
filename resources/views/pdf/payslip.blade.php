<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; }
        .company-name { font-size: 18px; font-weight: bold; margin: 0; }
        .company-meta { font-size: 10px; color: #555; margin: 2px 0; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 16px 0; text-transform: uppercase; letter-spacing: 1px; }
        table.meta { width: 100%; margin-bottom: 16px; }
        table.meta td { padding: 2px 0; font-size: 11px; }
        table.meta td.label { color: #555; width: 120px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.lines th { text-align: left; border-bottom: 1px solid #1a1a1a; padding: 4px 6px; font-size: 10px; text-transform: uppercase; }
        table.lines td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        table.lines td.amount, table.lines th.amount { text-align: right; }
        .section-title { font-weight: bold; font-size: 11px; text-transform: uppercase; margin: 14px 0 4px; }
        .net-pay { margin-top: 16px; padding: 10px; background: #f2f2f2; border: 1px solid #1a1a1a; }
        .net-pay .label { font-size: 11px; text-transform: uppercase; }
        .net-pay .amount { font-size: 18px; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 9px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <p class="company-name">{{ $company->name }}</p>
        @if ($company->address)
            <p class="company-meta">{{ $company->address }}</p>
        @endif
        <p class="company-meta">
            @if ($company->pan_number) PAN: {{ $company->pan_number }} @endif
            @if ($company->vat_number) &nbsp;&nbsp;VAT: {{ $company->vat_number }} @endif
        </p>
    </div>

    <div class="title">
        Payslip &mdash; {{ \App\Services\NepaliCalendar::adToBs($payslip->payrollRun->period_start, 'F Y') }}
    </div>

    <table class="meta">
        <tr>
            <td class="label">Employee</td>
            <td>{{ $payslip->employee->full_name }} ({{ $payslip->employee->employee_code }})</td>
            <td class="label">Period (BS)</td>
            <td>
                {{ \App\Services\NepaliCalendar::adToBs($payslip->payrollRun->period_start) }}
                &ndash;
                {{ \App\Services\NepaliCalendar::adToBs($payslip->payrollRun->period_end) }}
            </td>
        </tr>
        <tr>
            <td class="label">Department</td>
            <td>{{ $payslip->employee->department?->name ?? '—' }}</td>
            <td class="label">Job title</td>
            <td>{{ $payslip->employee->designation?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Days paid</td>
            <td>{{ $payslip->total_days - $payslip->unpaid_days }} of {{ $payslip->total_days }}</td>
            <td class="label">Status</td>
            <td>{{ ucfirst($payslip->payrollRun->status) }}</td>
        </tr>
    </table>

    <div class="section-title">Earnings</div>
    <table class="lines">
        <tr><th>Description</th><th class="amount">Amount (NPR)</th></tr>
        <tr>
            <td>Basic salary</td>
            <td class="amount">{{ number_format((float) $payslip->basic_after_attendance, 2) }}</td>
        </tr>
        @foreach ($payslip->allowance_items ?? [] as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td class="amount">{{ number_format((float) $item['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td><strong>Gross pay</strong></td>
            <td class="amount"><strong>{{ number_format((float) $payslip->gross_pay, 2) }}</strong></td>
        </tr>
    </table>

    <div class="section-title">Deductions</div>
    <table class="lines">
        <tr><th>Description</th><th class="amount">Amount (NPR)</th></tr>
        <tr>
            <td>Provident Fund (employee share)</td>
            <td class="amount">{{ number_format((float) $payslip->pf_employee, 2) }}</td>
        </tr>
        <tr>
            <td>Social Security Fund (employee share)</td>
            <td class="amount">{{ number_format((float) $payslip->ssf_employee, 2) }}</td>
        </tr>
        <tr>
            <td>Tax Deducted at Source (TDS)</td>
            <td class="amount">{{ number_format((float) $payslip->tds, 2) }}</td>
        </tr>
        @foreach ($payslip->deduction_items ?? [] as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td class="amount">{{ number_format((float) $item['amount'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    @if ($payslip->adjustments->isNotEmpty())
        <div class="section-title">Adjustments</div>
        <table class="lines">
            <tr><th>Reason</th><th class="amount">Amount (NPR)</th></tr>
            @foreach ($payslip->adjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->reason }}</td>
                    <td class="amount">{{ number_format((float) $adjustment->amount, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="net-pay">
        <span class="label">Net Pay</span><br>
        <span class="amount">NPR {{ number_format($payslip->adjusted_net_pay, 2) }}</span>
    </div>

    <div class="footer">
        Generated {{ \App\Services\NepaliCalendar::adToBs(now()) }} (BS) &middot; Computer-generated document, no signature required.
    </div>
</body>
</html>
