(function () {
    var VER = '10.98.775';
    var FLAG = 'ameSeenApp';
    var SW_URL = '/sw.js?v=10.98.775';
    var KEEP = 'ame-pwa-v1098775';

    function readFlag() {
        try { return sessionStorage.getItem(FLAG) || localStorage.getItem(FLAG); } catch (e) { return VER; }
    }
    function writeFlag() {
        try { sessionStorage.setItem(FLAG, VER); } catch (e) {}
        try { localStorage.setItem(FLAG, VER); } catch (e) {}
    }
    function register() {
        if (!('serviceWorker' in navigator)) return Promise.resolve();
        return navigator.serviceWorker.register(SW_URL).catch(function () {});
    }

    if (readFlag() === VER) {
        register();
        return;
    }

    var tasks = [];
    if (window.caches && caches.keys) {
        tasks.push(caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key === KEEP) return null;
                return caches.delete(key);
            }));
        }));
    }
    tasks.push(register());
    Promise.all(tasks).then(function () {
        writeFlag();
        location.reload();
    }).catch(function () {
        writeFlag();
    });
})();
