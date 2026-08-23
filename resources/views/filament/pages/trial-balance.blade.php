<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pr-4">Code</th>
                        <th class="py-2 pr-4">Account</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4 text-right">Debit</th>
                        <th class="py-2 pr-4 text-right">Credit</th>
                        <th class="py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $row->account->code }}</td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $row->account->name }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ \App\Models\Account::accountTypeOptions()[$row->account->account_type] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format($row->debitTotal, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">Rs. {{ number_format($row->creditTotal, 2) }}</td>
                            <td class="py-2 text-right tabular-nums font-medium text-gray-900 dark:text-white">Rs. {{ number_format($row->balance(), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No active accounts yet — add some in the Chart of Accounts.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-white/20">
                            <td class="py-2 pr-4" colspan="3">Total</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->totalDebit, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-gray-900 dark:text-white">Rs. {{ number_format($this->totalCredit, 2) }}</td>
                            <td class="py-2 text-right">
                                @if ($this->totalDebit === $this->totalCredit)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-success-600 dark:text-success-400">
                                        <x-heroicon-o-check-circle class="h-4 w-4" />
                                        Balanced
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                                        <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                                        Out of balance
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
