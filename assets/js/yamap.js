document.addEventListener('DOMContentLoaded', function () {
    // Подождём появления адреса, если карта загружена раньше данных
    function waitForAddress(callback, retries = 10) {
        if (typeof ymaps === 'undefined') return;
        if (window.ec_event_address) {
            callback();
        } else if (retries > 0) {
            setTimeout(() => waitForAddress(callback, retries - 1), 300);
        }
    }

    waitForAddress(function () {
        ymaps.ready(function () {
            ymaps.geocode(window.ec_event_address).then(function (res) {
                const coords = res.geoObjects.get(0).geometry.getCoordinates();
                const map = new ymaps.Map("yandex-map", {
                    center: coords,
                    zoom: 15,
                    controls: ['zoomControl']
                });
                const placemark = new ymaps.Placemark(coords, {
                    balloonContent: window.ec_event_address
                });
                map.geoObjects.add(placemark);
            });
        });
    });
});