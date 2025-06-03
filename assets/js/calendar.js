document.addEventListener('DOMContentLoaded', function () {
    const calendarEl   = document.getElementById('ec-calendar');
    const filterForm   = document.getElementById('ec-filter-form');
    const icsButton    = document.getElementById('ec-export-ics');
    const filterInfoEl = document.getElementById('ec-active-filters'); // 📌 блок для отображения фильтров

    if (!calendarEl) return;

    const theme = ec_calendar_data.theme || 'auto';
    if (theme !== 'auto') {
        calendarEl.setAttribute('data-theme', theme);
    }

    let currentEventType   = '';
    let currentOrganizer   = '';
    let currentDateStart   = '';
    let currentDateEnd     = '';
    let currentSearchQuery = '';

    function updateIcsLink() {
        if (!icsButton) return;
        let url = ec_calendar_data.home_url + '?ec_export_ics=1';
        if (currentEventType)   url += '&event_type='  + encodeURIComponent(currentEventType);
        if (currentOrganizer)   url += '&organizer='   + encodeURIComponent(currentOrganizer);
        if (currentDateStart)   url += '&date_start='  + encodeURIComponent(currentDateStart);
        if (currentDateEnd)     url += '&date_end='    + encodeURIComponent(currentDateEnd);
        if (currentSearchQuery) url += '&search='      + encodeURIComponent(currentSearchQuery);
        icsButton.setAttribute('href', url);
    }

    function updateActiveFiltersText() {
        if (!filterInfoEl) return;
        let html = '<strong>Применённые фильтры:</strong><ul style="margin-top: 5px;">';
        if (currentEventType)   html += `<li>Тип: ${currentEventType}</li>`;
        if (currentOrganizer)   html += `<li>Организатор: ${currentOrganizer}</li>`;
        if (currentDateStart)   html += `<li>С даты: ${currentDateStart}</li>`;
        if (currentDateEnd)     html += `<li>По дату: ${currentDateEnd}</li>`;
        if (currentSearchQuery) html += `<li>Поиск: ${currentSearchQuery}</li>`;
        html += '</ul>';

        if (currentEventType || currentOrganizer || currentDateStart || currentDateEnd || currentSearchQuery) {
            filterInfoEl.innerHTML = html;
            filterInfoEl.style.display = 'block';
        } else {
            filterInfoEl.innerHTML = '';
            filterInfoEl.style.display = 'none';
        }
    }

    function fetchEvents(fetchInfo, successCallback, failureCallback) {
        const formData = new FormData();
        if (filterForm) {
            const typeField   = filterForm.querySelector('[name="event_type"]');
            const orgField    = filterForm.querySelector('[name="organizer"]');
            const startField  = filterForm.querySelector('[name="start"]');
            const endField    = filterForm.querySelector('[name="end"]');
            const searchField = filterForm.querySelector('[name="ec_search"]');

            currentEventType   = typeField?.value.trim()   || '';
            currentOrganizer   = orgField?.value.trim()    || '';
            currentDateStart   = startField?.value.trim()  || '';
            currentDateEnd     = endField?.value.trim()    || '';
            currentSearchQuery = searchField?.value.trim() || '';

            if (currentEventType)   formData.append('event_type', currentEventType);
            if (currentOrganizer)   formData.append('organizer',   currentOrganizer);
            if (currentDateStart)   formData.append('date_start',  currentDateStart);
            if (currentDateEnd)     formData.append('date_end',    currentDateEnd);
            if (currentSearchQuery) formData.append('ec_search',   currentSearchQuery);
        }

        formData.append('start', fetchInfo.startStr);
        formData.append('end',   fetchInfo.endStr);
        formData.append('action', 'ec_get_events');
        if (ec_calendar_data.nonce) {
            formData.append('nonce', ec_calendar_data.nonce);
        }

        fetch(ec_calendar_data.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Ошибка получения событий');
            return response.json();
        })
        .then(json => {
            if (json.success) {
                successCallback(json.data);
            } else {
                console.error('AJAX возвратил ошибку:', json);
                failureCallback(json);
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX:', error);
            failureCallback(error);
        });
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        locale:           'ru',
        initialView:      ec_calendar_data.default_view || 'dayGridMonth',
        timeZone:         ec_calendar_data.timezone     || 'local',
        headerToolbar: {
            left:   'prev,today,next',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Сегодня',
            month: 'Месяц',
            week:  'Неделя',
            day:   'День',
            list:  'Список'
        },
        firstDay:         1,
        allDayText:       'Весь день',
        noEventsContent:  'Нет мероприятий для отображения',
        height:           'auto',
        displayEventTime: false,
        dayMaxEvents: ec_calendar_data.max_events_per_day || 5,
        slotDuration:     '02:00:00',
        slotLabelInterval:'02:00',
        slotMinTime:      '09:00:00',
        slotMaxTime:      '18:00:00',
        events:           fetchEvents
    });

    calendar.render();

    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            updateIcsLink();
            calendar.refetchEvents();
            updateActiveFiltersText();
        });

        filterForm.querySelectorAll('select, input').forEach(function (input) {
            input.addEventListener('change', function () {
                updateIcsLink();
                calendar.refetchEvents();
                updateActiveFiltersText();
            });
        });
    }

    updateIcsLink();
    updateActiveFiltersText();

    // ✅ Добавлено: скрытие времени при флаге "весь день"
    const allDayCheckbox = document.querySelector('#ec_all_day');
    const timeInputs     = document.querySelectorAll('.ec-time-only');

    if (allDayCheckbox && timeInputs.length) {
        const toggleTimeInputs = () => {
            timeInputs.forEach(input => {
                input.style.display = allDayCheckbox.checked ? 'none' : '';
            });
        };
        allDayCheckbox.addEventListener('change', toggleTimeInputs);
        toggleTimeInputs(); // стартовое состояние
    }
});
