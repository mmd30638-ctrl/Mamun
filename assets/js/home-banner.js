'use strict';

(function () {
    var viewport = document.getElementById('hmBannerViewport');
    var track = document.getElementById('hmBannerTrack');
    if (!viewport || !track) {
        return;
    }

    var slides = track.querySelectorAll('.hm-banner-slide');
    var total = slides.length;
    if (total < 2) {
        return;
    }

    var dots = document.querySelectorAll('.hm-banner-dot');
    var current = 0;
    var timer = null;
    var delay = 4000;
    var startX = 0;
    var dragging = false;

    function goTo(index, animate) {
        current = ((index % total) + total) % total;
        if (animate === false) {
            track.style.transition = 'none';
        } else {
            track.style.transition = 'transform 0.55s ease';
        }
        track.style.transform = 'translateX(-' + current * 100 + '%)';
        dots.forEach(function (dot, i) {
            dot.classList.toggle('active', i === current);
        });
    }

    function next() {
        goTo(current + 1);
    }

    function prev() {
        goTo(current - 1);
    }

    function resetTimer() {
        if (timer) {
            clearInterval(timer);
        }
        timer = setInterval(next, delay);
    }

    window.hmGoToSlide = function (index) {
        goTo(index);
        resetTimer();
    };

    var prevBtn = document.getElementById('hmBannerPrev');
    var nextBtn = document.getElementById('hmBannerNext');
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            prev();
            resetTimer();
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            next();
            resetTimer();
        });
    }

    viewport.addEventListener('touchstart', function (ev) {
        if (!ev.touches || !ev.touches[0]) {
            return;
        }
        dragging = true;
        startX = ev.touches[0].clientX;
        if (timer) {
            clearInterval(timer);
        }
    }, { passive: true });

    viewport.addEventListener('touchend', function (ev) {
        if (!dragging) {
            return;
        }
        dragging = false;
        var endX = ev.changedTouches && ev.changedTouches[0] ? ev.changedTouches[0].clientX : startX;
        var dx = endX - startX;
        if (dx > 40) {
            prev();
        } else if (dx < -40) {
            next();
        }
        resetTimer();
    }, { passive: true });

    resetTimer();
    goTo(0, false);
})();
