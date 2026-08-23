<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">PF total</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalPf(), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">SSF total</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalSsf(), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Grand total</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-primary-600 dark:text-primary-400">Rs. {{ number_format($this->report->grandTotal(), 2) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pr-4">Period (BS)</th>
                        <th class="py-2 pr-4">Employee</th>
                        <th class="py-2 pr-4">Code</th>
                        <th class="py-2 pr-4 text-right">PF (employee)</th>
                        <th class="py-2 pr-4 text-right">PF (employer)</th>
                        <th class="py-2 pr-4 text-right">SSF (employee)</th>
                        <th class="py-2 text-right">SSF (employer)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->report->payslips as $payslip)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ \App\Services\NepaliCalendar::adToBs($payslip->payrollRun->period_start) }}</td>
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $payslip->employee->full_name }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $payslip->employee->employee_code }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format((float) $payslip->pf_employee, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format((float) $payslip->pf_employer, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format((float) $payslip->ssf_employee, 2) }}</td>
                            <td class="py-2 text-right tabular-nums font-medium text-gray-900 dark:text-white">Rs. {{ number_format((float) $payslip->ssf_employer, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No finalized payslips in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->report->payslips->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-white/20">
                            <td class="py-2 pr-4" colspan="3">Total</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalPfEmployee, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalPfEmployer, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalSsfEmployee, 2) }}</td>
                            <td class="py-2 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->report->totalSsfEmployer, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
