@extends('layouts.admin-layout')

@section('title', 'Agenda de lançamentos')

@section('page-title', 'Agenda de lançamentos')

@section('content')
    <div data-schedule-feedback hidden role="status" aria-live="polite" class="mb-4 rounded-xl border px-4 py-3 text-sm"></div>

    <div class="admin-card mb-6">
        <div class="admin-card-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Agenda de lançamentos</p>
                <p class="text-sm opacity-70">Somente comics agendados são exibidos; os já publicados ficam fixos. Arraste um comic para outra data ou para os controles de navegação para movê-lo entre meses.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($calendarView === 'month')
                    <a href="{{ route('admin.comics.schedule-view', ['view' => 'month', 'month' => $previousMonth->format('Y-m')]) }}"
                       data-month-shift="-1" data-calendar-nav
                       class="admin-btn admin-btn-sm" aria-label="Mês anterior">← Mês anterior</a>
                    <span class="min-w-36 text-center text-sm font-semibold capitalize">{{ $currentMonth->translatedFormat('F \d\e Y') }}</span>
                    <a href="{{ route('admin.comics.schedule-view', ['view' => 'month', 'month' => $nextMonth->format('Y-m')]) }}"
                       data-month-shift="1" data-calendar-nav
                       class="admin-btn admin-btn-sm" aria-label="Próximo mês">Próximo mês →</a>
                @else
                    <a href="{{ route('admin.comics.schedule-view', ['view' => 'week', 'week' => $previousWeek->format('Y-m-d')]) }}"
                       data-week-shift="-1" data-calendar-nav
                       class="admin-btn admin-btn-sm" aria-label="Semana anterior">← Semana anterior</a>
                    <span class="min-w-44 text-center text-sm font-semibold">
                        {{ $weekStart->format('d/m') }} – {{ $weekStart->copy()->addDays(6)->format('d/m/Y') }}
                    </span>
                    <a href="{{ route('admin.comics.schedule-view', ['view' => 'week', 'week' => $nextWeek->format('Y-m-d')]) }}"
                       data-week-shift="1" data-calendar-nav
                       class="admin-btn admin-btn-sm" aria-label="Próxima semana">Próxima semana →</a>
                @endif
            </div>
        </div>
        <div class="admin-card-body">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <nav class="flex gap-2" aria-label="Modo de visualização da agenda">
                    <a href="{{ route('admin.comics.schedule-view', ['view' => 'month', 'month' => $currentMonth->format('Y-m')]) }}"
                       @if ($calendarView === 'month') aria-current="page" @endif
                       class="admin-btn admin-btn-sm {{ $calendarView === 'month' ? 'admin-btn-success' : '' }}">Mês</a>
                    <a href="{{ route('admin.comics.schedule-view', ['view' => 'week', 'week' => $weekStart->format('Y-m-d')]) }}"
                       @if ($calendarView === 'week') aria-current="page" @endif
                       class="admin-btn admin-btn-sm {{ $calendarView === 'week' ? 'admin-btn-success' : '' }}">Semana</a>
                </nav>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">As datas fora do mês atual também aceitam lançamentos.</p>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-[720px]">
                    <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <span>Seg</span>
                        <span>Ter</span>
                        <span>Qua</span>
                        <span>Qui</span>
                        <span>Sex</span>
                        <span>Sáb</span>
                        <span>Dom</span>
                    </div>
                    <div class="mt-3 grid grid-cols-7 gap-2">
                        @foreach ($days as $day)
                            @php
                                $dayKey = $day->format('Y-m-d');
                                $items = $scheduledByDate[$dayKey] ?? [];
                            @endphp
                            <div
                                class="min-h-36 rounded-xl border border-zinc-200 bg-zinc-50/80 p-2 shadow-sm transition duration-200 dark:border-zinc-800 dark:bg-zinc-950/60 {{ $calendarView === 'month' && $day->month !== $currentMonth->month ? 'opacity-55' : '' }}"
                                data-drop-slot
                                data-date="{{ $dayKey }}"
                                data-comic-count="{{ count($items) }}">
                                <div data-day-heading class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold {{ $day->isToday() ? 'text-indigo-500' : 'text-zinc-500 dark:text-zinc-400' }}">
                                        {{ $calendarView === 'week' ? $day->translatedFormat('D, d/m') : $day->day }}
                                    </span>
                                    @if (count($items) > 0)
                                        <span data-count-badge class="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                                            {{ count($items) }}
                                        </span>
                                    @endif
                                </div>
                                <div data-day-comics class="space-y-2">
                                    @forelse ($items as $comic)
                                        <div
                                            draggable="true"
                                            data-comic-id="{{ $comic->id }}"
                                            data-current-date="{{ $dayKey }}"
                                            data-published-time="{{ $comic->published_at->format('H:i') }}"
                                            class="cursor-grab rounded-lg border border-amber-400/40 bg-amber-500/10 p-2 text-left transition duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:bg-amber-500/15 hover:shadow-md active:cursor-grabbing">
                                            <p class="line-clamp-2 text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $comic->title }}</p>
                                            <p data-comic-time class="mt-1 text-[10px] text-zinc-500 dark:text-zinc-400">{{ $comic->published_at->format('H:i') }}</p>
                                            <p class="mt-1 text-[10px] text-zinc-500 dark:text-zinc-400">{{ $comic->author ?: 'Autor sem nome' }}</p>
                                        </div>
                                    @empty
                                        <div data-empty-slot class="flex h-16 items-center justify-center rounded-lg border border-dashed border-zinc-300 text-[10px] uppercase tracking-wide text-zinc-400 dark:border-zinc-700 dark:text-zinc-500">
                                            Livre
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const scheduleUrlTemplate = @json(route('admin.comics.schedule', ['comic' => '__ID__']));
        const calendarUrl = @json(route('admin.comics.schedule-view'));
        const calendarView = @json($calendarView);
        const csrfToken = @json(csrf_token());
        const feedback = document.querySelector('[data-schedule-feedback]');
        let draggedComic = null;

        function storeFeedback(message) {
            try {
                sessionStorage.setItem('comic-schedule-feedback', message);
            } catch (error) {
                console.warn('Não foi possível guardar o aviso de reagendamento:', error);
            }
        }

        function showFeedback(message, isError = false) {
            feedback.hidden = false;
            feedback.textContent = message;
            feedback.className = `mb-4 rounded-xl border px-4 py-3 text-sm ${isError
                ? 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-200'
                : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200'}`;
        }

        let previousFeedback = null;
        try {
            previousFeedback = sessionStorage.getItem('comic-schedule-feedback');
        } catch (error) {
            console.warn('Não foi possível recuperar o aviso de reagendamento:', error);
        }
        if (previousFeedback) {
            try {
                sessionStorage.removeItem('comic-schedule-feedback');
            } catch (error) {
                console.warn('Não foi possível limpar o aviso de reagendamento:', error);
            }
            showFeedback(previousFeedback);
        }

        function shiftDate(dateString, amount, unit) {
            const [year, month, day] = dateString.split('-').map(Number);
            if (unit === 'week') {
                const date = new Date(year, month - 1, day + amount * 7, 12);
                return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            }

            const targetMonth = new Date(year, month - 1 + amount, 1, 12);
            const lastDay = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
            targetMonth.setDate(Math.min(day, lastDay));

            return `${targetMonth.getFullYear()}-${String(targetMonth.getMonth() + 1).padStart(2, '0')}-${String(targetMonth.getDate()).padStart(2, '0')}`;
        }

        function countComics(slot, amount) {
            const count = Number(slot.dataset.comicCount) + amount;
            slot.dataset.comicCount = String(count);

            let badge = slot.querySelector('[data-count-badge]');
            if (count > 0 && !badge) {
                badge = document.createElement('span');
                badge.dataset.countBadge = '';
                badge.className = 'rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300';
                slot.querySelector('[data-day-heading]').append(badge);
            }

            if (badge) {
                badge.textContent = String(count);
                badge.hidden = count === 0;
            }
        }

        function calendarLocation(dateString) {
            const params = new URLSearchParams({ view: calendarView });
            params.set(calendarView === 'week' ? 'week' : 'month', calendarView === 'week' ? dateString : dateString.slice(0, 7));
            return `${calendarUrl}?${params.toString()}`;
        }

        async function reschedule(comicCard, newDate, slot) {
            if (!newDate || newDate === comicCard.dataset.currentDate) {
                return;
            }

            comicCard.draggable = false;
            comicCard.classList.add('opacity-60', 'scale-95');
            const time = comicCard.dataset.publishedTime || '12:00';
            const params = new URLSearchParams({ published_at: `${newDate}T${time}` });

            try {
                const response = await fetch(scheduleUrlTemplate.replace('__ID__', comicCard.dataset.comicId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: params,
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Não foi possível alterar a data do lançamento.');
                }

                const oldDate = comicCard.dataset.currentDate;
                const oldSlot = document.querySelector(`[data-drop-slot][data-date="${oldDate}"]`);
                const targetSlot = slot || document.querySelector(`[data-drop-slot][data-date="${newDate}"]`);
                if (!targetSlot) {
                    comicCard.animate(
                        [{ opacity: 0.6, transform: 'scale(0.95)' }, { opacity: 0, transform: 'translateY(-12px) scale(0.9)' }],
                        { duration: 240, easing: 'ease-in', fill: 'forwards' },
                    );
                    storeFeedback(result.message);
                    window.setTimeout(() => window.location.assign(calendarLocation(newDate)), 260);
                    return;
                }

                const oldRect = comicCard.getBoundingClientRect();
                oldSlot.querySelector('[data-empty-slot]')?.remove();
                targetSlot.querySelector('[data-empty-slot]')?.remove();
                countComics(oldSlot, -1);
                countComics(targetSlot, 1);
                targetSlot.querySelector('[data-day-comics]').append(comicCard);
                comicCard.dataset.currentDate = newDate;
                comicCard.dataset.publishedTime = time;
                comicCard.draggable = true;
                comicCard.classList.remove('opacity-60', 'scale-95');
                const newRect = comicCard.getBoundingClientRect();
                comicCard.animate(
                    [
                        { opacity: 0.35, transform: `translate(${oldRect.left - newRect.left}px, ${oldRect.top - newRect.top}px) scale(0.96)` },
                        { opacity: 1, transform: 'translate(0, 0) scale(1)' },
                    ],
                    { duration: 360, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
                );

                if (oldSlot.dataset.comicCount === '0') {
                    const emptyState = document.createElement('div');
                    emptyState.dataset.emptySlot = '';
                    emptyState.className = 'flex h-16 items-center justify-center rounded-lg border border-dashed border-zinc-300 text-[10px] uppercase tracking-wide text-zinc-400 dark:border-zinc-700 dark:text-zinc-500';
                    emptyState.textContent = 'Livre';
                    oldSlot.querySelector('[data-day-comics]').append(emptyState);
                }

                showFeedback(result.message);
                if (newDate.slice(0, 7) !== @json($currentMonth->format('Y-m'))) {
                    storeFeedback(result.message);
                    window.setTimeout(() => window.location.assign(calendarLocation(newDate)), 380);
                }
            } catch (error) {
                comicCard.draggable = true;
                comicCard.classList.remove('opacity-60', 'scale-95');
                showFeedback(error.message || 'Falha de conexão ao reagendar o comic. Tente novamente.', true);
                console.error('Falha ao reagendar comic:', error);
            }
        }

        document.querySelectorAll('[draggable="true"]').forEach((comicCard) => {
            comicCard.addEventListener('dragstart', (event) => {
                draggedComic = comicCard;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', comicCard.dataset.comicId);
                comicCard.classList.add('opacity-60', 'scale-95');
            });

            comicCard.addEventListener('dragend', () => {
                comicCard.classList.remove('opacity-60', 'scale-95');
                draggedComic = null;
            });
        });

        document.querySelectorAll('[data-drop-slot], [data-calendar-nav]').forEach((target) => {
            const highlight = () => target.classList.add('ring-2', 'ring-indigo-400', 'ring-inset', 'bg-indigo-500/10', 'scale-[1.02]');
            const clearHighlight = () => target.classList.remove('ring-2', 'ring-indigo-400', 'ring-inset', 'bg-indigo-500/10', 'scale-[1.02]');

            target.addEventListener('dragover', (event) => {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                highlight();
            });

            target.addEventListener('dragleave', (event) => {
                if (!target.contains(event.relatedTarget)) {
                    clearHighlight();
                }
            });

            target.addEventListener('drop', (event) => {
                event.preventDefault();
                clearHighlight();
                const comicId = event.dataTransfer.getData('text/plain');
                const comicCard = draggedComic?.dataset.comicId === comicId ? draggedComic : null;
                if (!comicCard) {
                    return;
                }

                let newDate = target.dataset.date;
                if (target.hasAttribute('data-calendar-nav')) {
                    const unit = calendarView === 'week' ? 'week' : 'month';
                    const amount = Number(target.dataset[unit === 'week' ? 'weekShift' : 'monthShift']);
                    newDate = shiftDate(comicCard.dataset.currentDate, amount, unit);
                    reschedule(comicCard, newDate, null);
                    return;
                }

                reschedule(comicCard, newDate, target);
            });
        });
    </script>
@endpush
