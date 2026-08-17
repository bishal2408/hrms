<x-filament-panels::page>
    @if ($this->getEmployee())
        {{ $this->profileInfolist }}
    @else
        <x-filament::section>
            <x-slot name="heading">No employee record yet</x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Your account isn't linked to an employee record, so there's nothing to show here yet.
                Ask HR to link your account and your details will appear on this page.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
