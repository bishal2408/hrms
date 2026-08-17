<x-filament-panels::page>
    @php
        $employee = $this->getEmployee();
        $today = $employee ? $this->getTodayLog() : null;
        $recent = $employee ? $this->getRecentLogs() : collect();
    @endphp

    @if (! $employee)
        <x-filament::section>
            <x-slot name="heading">No employee record yet</x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Your account isn't linked to an employee record, so there's nothing to clock in for yet.
                Ask HR to link your account and this page will let you start clocking in.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    @if (! $today)
                        <p class="text-lg font-semibold">Not clocked in today</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Clock in when you start work.</p>
                    @elseif ($today->is_open)
                        <p class="text-lg font-semibold">Clocked in at {{ $today->clock_in->format('h:i A') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Clock out when you finish for the day.</p>
                    @else
                        <p class="text-lg font-semibold">
                            Clocked out at {{ $today->clock_out->format('h:i A') }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Worked {{ intdiv($today->worked_minutes, 60) }}h {{ str_pad((string) ($today->worked_minutes % 60), 2, '0', STR_PAD_LEFT) }}m today.
                        </p>
                    @endif
                </div>

                <div>
                    @if (! $today)
                        <x-filament::button wire:click="clockIn" icon="heroicon-o-play">
                            Clock in
                        </x-filament::button>
                    @elseif ($today->is_open)
                        <x-filament::button wire:click="clockOut" color="gray" icon="heroicon-o-stop">
                            Clock out
                        </x-filament::button>
                    @else
                        <x-filament::badge color="success" icon="heroicon-o-check-circle">
                            Done for today
                        </x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Recent days</x-slot>

            @if ($recent->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Nothing recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="py-2 pe-4">Date (BS)</th>
                                <th class="py-2 pe-4">Clock in</th>
                                <th class="py-2 pe-4">Clock out</th>
                                <th class="py-2 text-end">Worked</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($recent as $log)
                                <tr>
                                    <td class="py-2 pe-4">{{ \App\Services\NepaliCalendar::adToBs($log->date) }}</td>
                                    <td class="py-2 pe-4">{{ $log->clock_in->format('h:i A') }}</td>
                                    <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">
                                        {{ $log->clock_out?->format('h:i A') ?? 'Still clocked in' }}
                                    </td>
                                    <td class="py-2 text-end">
                                        @if ($log->worked_minutes === null)
                                            —
                                        @else
                                            {{ intdiv($log->worked_minutes, 60) }}h {{ str_pad((string) ($log->worked_minutes % 60), 2, '0', STR_PAD_LEFT) }}m
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
