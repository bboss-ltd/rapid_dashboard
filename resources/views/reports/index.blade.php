<x-layouts.app :title="__('Reports')">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Reports</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Rendered in-app using the same queries as exports.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="{{ route('reports.velocity') }}"
               class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">Velocity by Sprint</div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300">END snapshots → done/scope/remaining.</div>
            </a>
        </div>
    </div>
</x-layouts.app>
