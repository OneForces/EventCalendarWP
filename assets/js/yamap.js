document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById("yamap-container");
    if (!container) return;

    const rawAddress = container.dataset.address;
    if (!rawAddress || rawAddress.trim() === "") return;

    const address = rawAddress.replace(/\s+/g, ' ').trim(); // нормализация пробелов

    ymaps.ready(() => {
        ymaps.geocode(address).then(function (res) {
            const geoObj = res.geoObjects.get(0);
            if (!geoObj) {
                console.warn('Адрес не найден на карте:', address);
                return;
            }

            const coords = geoObj.geometry.getCoordinates();

            const map = new ymaps.Map("yamap-container", {
                center: coords,
                zoom: 14,
                controls: ['zoomControl']
            });

            const placemark = new ymaps.Placemark(coords, {
                balloonContent: address
            }, {
                preset: 'islands#redDotIcon'
            });

            map.geoObjects.add(placemark);
        }).catch(err => {
            console.error('Ошибка геокодирования:', err);
        });
    });
});
