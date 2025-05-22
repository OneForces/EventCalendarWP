document.addEventListener('DOMContentLoaded', function () {
    if (typeof ymaps === 'undefined' || !window.ec_event_address) return;

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
