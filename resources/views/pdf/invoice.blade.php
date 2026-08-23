<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; }
        .company-name { font-size: 18px; font-weight: bold; margin: 0; }
        .company-meta { font-size: 10px; color: #555; margin: 2px 0; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 16px 0; text-transform: uppercase; letter-spacing: 1px; }
        .cancelled-stamp { text-align: center; font-size: 20px; font-weight: bold; color: #b91c1c; border: 2px solid #b91c1c; padding: 6px; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 2px; }
        table.meta { width: 100%; margin-bottom: 16px; }
        table.meta td { padding: 2px 0; font-size: 11px; vertical-align: top; }
        table.meta td.label { color: #555; width: 120px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.lines th { text-align: left; border-bottom: 1px solid #1a1a1a; padding: 4px 6px; font-size: 10px; text-transform: uppercase; }
        table.lines td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        table.lines td.amount, table.lines th.amount { text-align: right; }
        table.totals { width: 260px; margin-left: auto; margin-top: 8px; }
        table.totals td { padding: 3px 6px; font-size: 11px; }
        table.totals td.amount { text-align: right; }
        table.totals tr.total td { font-weight: bold; font-size: 14px; border-top: 1px solid #1a1a1a; }
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

    <div class="title">Tax Invoice</div>

    @if ($invoice->isCancelled())
        <div class="cancelled-stamp">Cancelled</div>
    @endif

    <table class="meta">
        <tr>
            <td class="label">Invoice #</td>
            <td>{{ $invoice->invoice_number }}</td>
            <td class="label">Issue date (BS)</td>
            <td>{{ \App\Services\NepaliCalendar::adToBs($invoice->issue_date) }}</td>
        </tr>
        <tr>
            <td class="label">Bill to</td>
            <td>{{ $invoice->customer->name }}</td>
            <td class="label">Customer PAN</td>
            <td>{{ $invoice->customer->pan_number ?? '—' }}</td>
        </tr>
        @if ($invoice->customer->address)
            <tr>
                <td class="label">Address</td>
                <td colspan="3">{{ $invoice->customer->address }}</td>
            </tr>
        @endif
    </table>

    <table class="lines">
        <tr>
            <th>Description</th>
            <th class="amount">Qty</th>
            <th class="amount">Unit price</th>
            <th class="amount">VAT</th>
            <th class="amount">Amount (NPR)</th>
        </tr>
        @foreach ($invoice->lineItems as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="amount">{{ number_format((float) $line->quantity, 2) }}</td>
                <td class="amount">{{ number_format((float) $line->unit_price, 2) }}</td>
                <td class="amount">{{ $line->is_vatable ? 'Yes' : 'Exempt' }}</td>
                <td class="amount">{{ number_format((float) $line->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="amount">{{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>VAT</td>
            <td class="amount">{{ number_format((float) $invoice->vat_amount, 2) }}</td>
        </tr>
        <tr class="total">
            <td>Total (NPR)</td>
            <td class="amount">{{ number_format((float) $invoice->total, 2) }}</td>
        </tr>
    </table>

    @if ($invoice->notes)
        <p style="margin-top: 20px; font-size: 11px;"><strong>Notes:</strong> {{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Generated {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
