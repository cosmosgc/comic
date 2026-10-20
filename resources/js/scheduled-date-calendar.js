const calendars = document.querySelectorAll('[data-scheduled-calendar]');

calendars.forEach((calendar) => {
    const input = document.getElementById(calendar.dataset.inputId);
    const monthLabel = calendar.querySelector('[data-calendar-month]');
    const daysGrid = calendar.querySelector('[data-calendar-days]');
    const scheduledDates = JSON.parse(calendar.dataset.scheduledDates);
    const isAdminCalendar = calendar.dataset.admin === 'true';
    const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' });
    let visibleMonth = input.value
        ? new Date(`${input.value.slice(0, 10)}T12:00:00`)
        : new Date();

    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);

    function dateKey(year, month, day) {
        return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }

    function renderCalendar() {
        const year = visibleMonth.getFullYear();
        const month = visibleMonth.getMonth();
        const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const selectedDate = input.value.slice(0, 10);
        const fragment = document.createDocumentFragment();

        monthLabel.textContent = monthFormatter.format(visibleMonth);
        daysGrid.replaceChildren();

        for (let offset = 0; offset < firstWeekday; offset++) {
            const emptyCell = document.createElement('span');
            emptyCell.setAttribute('aria-hidden', 'true');
            fragment.append(emptyCell);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const key = dateKey(year, month, day);
            const count = Number(scheduledDates[key] || 0);
            const button = document.createElement('button');

            button.type = 'button';
            button.dataset.calendarDate = key;
            button.className = 'relative flex aspect-square w-full items-center justify-center rounded-lg text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400';
            button.textContent = String(day);

            if (count > 0) {
                button.classList.add('bg-amber-500/15', 'font-semibold', 'text-amber-200', 'ring-1', 'ring-amber-500/50', 'hover:bg-amber-500/25');
                button.title = `${count} comic(s) agendado(s)`;
                button.setAttribute('aria-label', `${day} ${monthFormatter.format(visibleMonth)}, ${count} comic(s) agendado(s)`);

                const marker = document.createElement('span');
                marker.className = 'absolute bottom-1 h-1 w-1 rounded-full bg-amber-400';
                marker.setAttribute('aria-hidden', 'true');
                button.append(marker);
            } else {
                button.classList.add('text-zinc-600');
                if (isAdminCalendar) {
                    button.classList.add('hover:bg-zinc-100', 'hover:text-zinc-900', 'dark:hover:bg-zinc-800', 'dark:hover:text-zinc-200');
                } else {
                    button.classList.add('hover:bg-zinc-800', 'hover:text-zinc-200');
                }
                button.setAttribute('aria-label', `${day} ${monthFormatter.format(visibleMonth)}`);
            }

            if (key === selectedDate) {
                button.classList.add('outline', 'outline-2', 'outline-indigo-400');
            }

            fragment.append(button);
        }

        daysGrid.append(fragment);
    }

    calendar.addEventListener('click', (event) => {
        const button = event.target.closest('button');

        if (!button || !calendar.contains(button)) {
            return;
        }

        if (button.matches('[data-calendar-previous]')) {
            visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
            renderCalendar();
        } else if (button.matches('[data-calendar-next]')) {
            visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
            renderCalendar();
        } else if (button.dataset.calendarDate) {
            const currentTime = input.value.split('T')[1] || '12:00';
            input.value = `${button.dataset.calendarDate}T${currentTime}`;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            renderCalendar();
        }
    });

    input.addEventListener('change', () => {
        if (input.value) {
            const [year, month] = input.value.slice(0, 10).split('-').map(Number);
            visibleMonth = new Date(year, month - 1, 1);
        }

        renderCalendar();
    });

    renderCalendar();
});
