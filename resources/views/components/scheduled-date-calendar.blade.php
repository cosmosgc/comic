<div data-scheduled-calendar
     data-input-id="{{ $inputId }}"
     data-admin="{{ ($admin ?? false) ? 'true' : 'false' }}"
     data-scheduled-dates='@json($scheduledDateCounts)'
     @class([
         'mt-3 max-w-sm rounded-xl border p-3',
         'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300' => $admin ?? false,
         'border-zinc-800 bg-zinc-950/60 text-zinc-300' => ! ($admin ?? false),
     ])>
    <div class="mb-3 flex items-center justify-between">
        <button type="button" data-calendar-previous
                class="rounded-lg px-2 py-1 text-sm hover:bg-zinc-200 dark:hover:bg-zinc-800"
                aria-label="Mês anterior">‹</button>
        <p data-calendar-month class="text-sm font-semibold capitalize"></p>
        <button type="button" data-calendar-next
                class="rounded-lg px-2 py-1 text-sm hover:bg-zinc-200 dark:hover:bg-zinc-800"
                aria-label="Próximo mês">›</button>
    </div>
    <div class="mb-1 grid grid-cols-7 text-center text-xs text-zinc-500" aria-hidden="true">
        <span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span>
    </div>
    <div data-calendar-days class="grid grid-cols-7 gap-1"></div>
    <p class="mt-3 flex items-center gap-2 text-xs text-zinc-500">
        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
        Data com comic(s) agendado(s)
    </p>
</div>
