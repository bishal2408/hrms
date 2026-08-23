<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Taxable sales</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalTaxable, 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Exempt sales</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalExempt, 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">VAT collected</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-primary-600 dark:text-primary-400">Rs. {{ number_format($this->report->totalVat, 2) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pr-4">Date (BS)</th>
                        <th class="py-2 pr-4">Invoice #</th>
                        <th class="py-2 pr-4">Customer</th>
                        <th class="py-2 pr-4">PAN</th>
                        <th class="py-2 pr-4 text-right">Taxable</th>
                        <th class="py-2 pr-4 text-right">Exempt</th>
                        <th class="py-2 pr-4 text-right">VAT</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->report->lines as $line)
                        <tr @class(['border-b border-gray-100 dark:border-white/5', 'opacity-50' => $line->invoice->isCancelled()])>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ \App\Services\NepaliCalendar::adToBs($line->invoice->issue_date) }}</td>
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                {{ $line->invoice->invoice_number }}
                                @if ($line->invoice->isCancelled())
                                    <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-400">Cancelled</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $line->invoice->customer->name }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $line->invoice->customer->pan_number ?? '—' }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format($line->taxableAmount, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format($line->exemptAmount, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format((float) $line->invoice->vat_amount, 2) }}</td>
                            <td class="py-2 text-right tabular-nums font-medium text-gray-900 dark:text-white">Rs. {{ number_format((float) $line->invoice->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No invoices in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->report->lines->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-white/20">
                            <td class="py-2 pr-4" colspan="4">Total (cancelled invoices excluded)</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalTaxable, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalExempt, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalVat, 2) }}</td>
                            <td class="py-2 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalSales() + $this->report->totalVat, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
