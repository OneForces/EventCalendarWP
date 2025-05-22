document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('ec-calendar');
    const filterForm = document.getElementById('ec-filters');

    if (!calendarEl) return;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: ec_calendar_data.default_view || 'dayGridMonth',
        timeZone: ec_calendar_data.timezone || 'local',
        headerToolbar: {
            left: 'prev,today,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            const formData = new FormData();

            if (filterForm) {
                const type = filterForm.querySelector('[name="type"]')?.value;
                const org = filterForm.querySelector('[name="organizer"]')?.value;
                const dateStart = filterForm.querySelector('[name="start"]')?.value;
                const dateEnd = filterForm.querySelector('[name="end"]')?.value;
                const search = filterForm.querySelector('[name="search"]')?.value;

                if (type) formData.append('type', type);
                if (org) formData.append('organizer', org);
                if (dateStart) formData.append('date_start', dateStart);
                if (dateEnd) formData.append('date_end', dateEnd);
                if (search) formData.append('search', search);
            }

            formData.append('start', fetchInfo.startStr);
            formData.append('end', fetchInfo.endStr);
            formData.append('action', 'ec_get_events');

            fetch(ec_calendar_data.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error("Ошибка загрузки данных");
                return response.json();
            })
            .then(data => successCallback(data))
            .catch(error => {
                console.error("Ошибка календаря:", error);
                failureCallback(error);
            });
        }
    });

    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            calendar.refetchEvents();
        });
    }

    calendar.render();
});
