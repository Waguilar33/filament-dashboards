<x-filament-widgets::widget>
    <x-filament::section heading="Growing list">
        <div x-data="{ rows: 0 }" x-init="setTimeout(() => rows = 3, 150)">
            <template x-for="row in rows" :key="row">
                <div class="growing-list-row" style="height: 37px" x-text="`Row ${row}`"></div>
            </template>

            <button type="button" class="growing-list-add" x-on:click="rows += 4">Add rows</button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
