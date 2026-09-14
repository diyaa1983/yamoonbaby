(function (global) {
    var fireworkRaf = 0;
    var fireworkTimer = null;
    var fireworkResize = null;
    var WHEEL_COLORS = ['#ff7eb3', '#5ec8d8', '#ffd36a', '#6aa9ff', '#ff9a7a', '#7ed6b8', '#f4c430', '#4f8fc9', '#ffb3c7'];

    function digitsFor(index) {
        return index === 0 ? [0, 1, 2, 3, 4] : [1, 2, 3, 4, 5, 6, 7, 8, 9];
    }

    function stepFor(index) {
        return 360 / digitsFor(index).length;
    }

    function conicFor(index) {
        var digits = digitsFor(index);
        var step = stepFor(index);
        var parts = digits.map(function (_, n) {
            return WHEEL_COLORS[n % WHEEL_COLORS.length] + ' ' + (n * step) + 'deg ' + ((n + 1) * step) + 'deg';
        });
        return 'conic-gradient(' + parts.join(',') + ')';
    }

    function digitFromAngle(angle, index) {
        var step = stepFor(index);
        var digits = digitsFor(index);
        var local = ((-angle % 360) + 360) % 360;
        var pos = Math.floor(local / step) % digits.length;
        return digits[pos];
    }

    function ensureCelebrate() {
        if (document.getElementById('yamoonCelebrate')) return;
        var style = document.createElement('style');
        style.textContent = [
            '#yamoonCelebrate{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(6,10,28,.82);z-index:80;overflow:hidden}',
            '#yamoonCelebrate.show{display:flex}',
            '#ycFireworks{position:absolute;inset:0;width:100%;height:100%;z-index:1}',
            '#yamoonCelebrate .yc-card{position:relative;z-index:3;width:min(720px,94vw);background:linear-gradient(180deg,#fff,#fff4d6);border:4px solid #f4c430;border-radius:36px;padding:28px 22px 24px;text-align:center;box-shadow:0 28px 90px rgba(0,0,0,.45)}',
            '#yamoonCelebrate .yc-label{color:#e11d48;font-weight:900;font-size:clamp(1.2rem,3vw,1.7rem);margin-bottom:14px}',
            '#yamoonCelebrate .yc-grid{display:grid;gap:10px}',
            '#yamoonCelebrate .yc-field{background:#fff;border:2px solid #fde68a;border-radius:18px;padding:10px 14px}',
            '#yamoonCelebrate .yc-k{display:block;color:#0b4f86;font-weight:800;font-size:.95rem;margin-bottom:2px}',
            '#yamoonCelebrate .yc-num{direction:ltr;unicode-bidi:isolate;font-size:clamp(2.4rem,8vw,4.4rem);font-weight:900;letter-spacing:8px;color:#111}',
            '#yamoonCelebrate .yc-name{font-size:clamp(1.5rem,4vw,2.3rem);font-weight:900;color:#0b4f86}',
            '#yamoonCelebrate .yc-phone{direction:ltr;unicode-bidi:isolate;font-size:clamp(1.35rem,3.6vw,2rem);font-weight:800;color:#be185d}',
            '#yamoonCelebrate .yc-city{font-size:clamp(1.25rem,3.2vw,1.8rem);font-weight:800;color:#12315a}',
            '#yamoonCelebrate .yc-close{margin-top:18px;border:0;border-radius:999px;padding:12px 28px;font:inherit;font-weight:800;background:linear-gradient(135deg,#ffe66d,#ffb703);color:#3b2a00;cursor:pointer}'
        ].join('');
        document.head.appendChild(style);
        var box = document.createElement('div');
        box.id = 'yamoonCelebrate';
        box.innerHTML =
            '<canvas id="ycFireworks"></canvas>' +
            '<div class="yc-card">' +
                '<div class="yc-label">مبارك للفائز</div>' +
                '<div class="yc-grid">' +
                    '<div class="yc-field"><span class="yc-k">رقم البطاقة</span><div class="yc-num" id="ycNum">------</div></div>' +
                    '<div class="yc-field"><span class="yc-k">اسم الفائز</span><div class="yc-name" id="ycName"></div></div>' +
                    '<div class="yc-field"><span class="yc-k">رقم الهاتف</span><div class="yc-phone" id="ycPhone"></div></div>' +
                    '<div class="yc-field"><span class="yc-k">المحافظة</span><div class="yc-city" id="ycCity"></div></div>' +
                '</div>' +
                '<button type="button" class="yc-close" id="ycClose">إغلاق</button>' +
            '</div>';
        document.body.appendChild(box);
        document.getElementById('ycClose').onclick = hideCelebrate;
    }

    function stopFireworks() {
        if (fireworkRaf) {
            cancelAnimationFrame(fireworkRaf);
            fireworkRaf = 0;
        }
        if (fireworkTimer) {
            clearInterval(fireworkTimer);
            fireworkTimer = null;
        }
        if (fireworkResize) {
            window.removeEventListener('resize', fireworkResize);
            fireworkResize = null;
        }
    }

    function startFireworks() {
        stopFireworks();
        var canvas = document.getElementById('ycFireworks');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var rockets = [];
        var sparks = [];
        var colors = ['#ff6b9d', '#4ecdc4', '#ffe66d', '#e11d48', '#ffc107', '#7ec8e3', '#ffffff', '#fb7185'];

        function sizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        fireworkResize = sizeCanvas;
        sizeCanvas();
        window.addEventListener('resize', sizeCanvas);

        function launch() {
            rockets.push({
                x: canvas.width * (0.1 + Math.random() * 0.8),
                y: canvas.height + 8,
                vx: (Math.random() - 0.5) * 2,
                vy: -(8.5 + Math.random() * 7),
                color: colors[Math.floor(Math.random() * colors.length)]
            });
        }

        function burst(x, y, color) {
            var count = 72 + Math.floor(Math.random() * 36);
            for (var i = 0; i < count; i++) {
                var angle = (Math.PI * 2 * i) / count + Math.random() * 0.18;
                var speed = 1.1 + Math.random() * 5.2;
                sparks.push({
                    x: x,
                    y: y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    life: 1,
                    decay: 0.01 + Math.random() * 0.018,
                    color: i % 7 === 0 ? '#ffffff' : color,
                    size: 1.4 + Math.random() * 2.4
                });
            }
        }

        function frame() {
            ctx.globalCompositeOperation = 'source-over';
            ctx.fillStyle = 'rgba(6, 10, 28, 0.22)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.globalCompositeOperation = 'lighter';

            for (var i = rockets.length - 1; i >= 0; i--) {
                var rocket = rockets[i];
                rocket.x += rocket.vx;
                rocket.y += rocket.vy;
                rocket.vy += 0.055;
                ctx.beginPath();
                ctx.fillStyle = rocket.color;
                ctx.arc(rocket.x, rocket.y, 2.6, 0, Math.PI * 2);
                ctx.fill();
                if (rocket.vy >= -1.1 || rocket.y < canvas.height * 0.22) {
                    burst(rocket.x, rocket.y, rocket.color);
                    rockets.splice(i, 1);
                }
            }

            for (var s = sparks.length - 1; s >= 0; s--) {
                var spark = sparks[s];
                spark.x += spark.vx;
                spark.y += spark.vy;
                spark.vy += 0.034;
                spark.vx *= 0.99;
                spark.life -= spark.decay;
                if (spark.life <= 0) {
                    sparks.splice(s, 1);
                    continue;
                }
                ctx.globalAlpha = Math.max(spark.life, 0);
                ctx.beginPath();
                ctx.fillStyle = spark.color;
                ctx.arc(spark.x, spark.y, spark.size, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.globalAlpha = 1;
            fireworkRaf = requestAnimationFrame(frame);
        }

        launch();
        launch();
        launch();
        fireworkTimer = setInterval(function () {
            var overlay = document.getElementById('yamoonCelebrate');
            if (!overlay || !overlay.classList.contains('show')) return;
            launch();
            if (Math.random() > 0.4) launch();
        }, 380);
        fireworkRaf = requestAnimationFrame(frame);
    }

    function showCelebrate(num, hit) {
        ensureCelebrate();
        document.getElementById('ycNum').textContent = num;
        document.getElementById('ycName').textContent = hit && hit.full_name ? hit.full_name : 'لا يوجد مشارك بهذا الرقم';
        document.getElementById('ycPhone').textContent = hit && hit.phone ? hit.phone : '—';
        document.getElementById('ycCity').textContent = hit && hit.governorate ? hit.governorate : '—';
        document.getElementById('yamoonCelebrate').classList.add('show');
        startFireworks();
    }

    function hideCelebrate() {
        var el = document.getElementById('yamoonCelebrate');
        if (el) el.classList.remove('show');
        stopFireworks();
    }

    function pad6(parts) {
        return parts.map(function (n) { return n === null ? '-' : String(n); }).join('');
    }

    function padCoupon(value) {
        var digits = String(value == null ? '' : value).replace(/\D/g, '');
        return digits ? ('000000' + digits).slice(-6) : '';
    }

    function findEntry(entries, coupon) {
        if (!coupon || String(coupon).indexOf('-') !== -1) return null;
        var want = padCoupon(coupon);
        if (!want) return null;
        for (var i = 0; i < entries.length; i++) {
            if (padCoupon(entries[i].coupon) === want) return entries[i];
        }
        return null;
    }

    function createWheels(opts) {
        var root = document.querySelector(opts.root);
        var board = document.querySelector(opts.board);
        var info = document.querySelector(opts.info);
        var entries = opts.entries || [];
        var count = opts.count || 6;
        var wheels = [];
        var values = [];
        var celebrated = false;
        var walking = false;
        var walkTimer = 0;

        root.innerHTML = '';
        for (var i = 0; i < count; i++) {
            values[i] = null;
            var unit = document.createElement('div');
            unit.className = 'unit';
            unit.innerHTML =
                '<h3>العجلة ' + (i + 1) + '</h3>' +
                '<div class="stage"><div class="pin"></div><div class="rim"><div class="wheel"></div><div class="hub"></div></div></div>' +
                '<div class="btns">' +
                    '<button type="button" class="start" data-i="' + i + '">تشغيل</button>' +
                    '<button type="button" class="stop" data-i="' + i + '" disabled>إيقاف</button>' +
                '</div>';
            var wheel = unit.querySelector('.wheel');
            var digits = digitsFor(i);
            var step = stepFor(i);
            wheel.style.background = conicFor(i);
            digits.forEach(function (d, n) {
                var s = document.createElement('span');
                s.textContent = String(d);
                var ang = n * step + step / 2;
                s.style.transform = 'rotate(' + ang + 'deg) translateY(' + (opts.radius || -48) + 'px) rotate(' + (-ang) + 'deg)';
                if (digits.length > 5) s.style.fontSize = '12px';
                var light = ['#ffd36a', '#5ec8d8', '#ff9a7a', '#7ed6b8', '#f4c430', '#ffb3c7', '#6aa9ff'];
                if (light.indexOf(WHEEL_COLORS[n % WHEEL_COLORS.length]) !== -1) {
                    s.style.color = '#12315a';
                    s.style.textShadow = 'none';
                }
                wheel.appendChild(s);
            });
            root.appendChild(unit);
            wheels[i] = {
                el: wheel,
                startBtn: unit.querySelector('.start'),
                stopBtn: unit.querySelector('.stop'),
                running: false,
                stopping: false,
                angle: 0,
                speed: 0,
                stopAge: 0,
                stopStart: 0
            };
        }

        function render() {
            var num = pad6(values);
            if (board) board.textContent = num;
            if (values.some(function (n) { return n === null; })) {
                if (info) info.textContent = info.getAttribute('data-idle') || '';
                return;
            }
            var hit = findEntry(entries, num);
            if (info) {
                info.textContent = hit
                    ? (hit.full_name + ' — ' + hit.phone + ' — ' + hit.governorate)
                    : 'رقم البطاقة: ' + num;
            }
            if (!celebrated) {
                celebrated = true;
                showCelebrate(num, hit);
                if (typeof opts.onComplete === 'function') opts.onComplete(num, hit);
            }
        }

        function frame() {
            wheels.forEach(function (w, idx) {
                if (!w.running && !w.stopping) return;
                if (w.stopping) {
                    w.stopAge += 1;
                    var t = Math.min(w.stopAge / 420, 1);
                    var remain = 1 - t;
                    w.speed = w.stopStart * remain * remain * remain;
                }
                w.angle += w.speed;
                if (w.angle >= 3600) w.angle -= 3600;
                w.el.style.transform = 'rotate(' + w.angle + 'deg)';
                if (w.stopping && w.speed < 0.006) {
                    w.speed = 0;
                    w.stopping = false;
                    w.running = false;
                    w.stopBtn.disabled = true;
                    values[idx] = digitFromAngle(w.angle, idx);
                    syncButtons();
                    render();
                }
            });
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);

        function isBusy() {
            return walking || wheels.some(function (w) { return w.running || w.stopping; });
        }

        function canStart(i) {
            if (isBusy() || values[i] !== null) return false;
            for (var k = 0; k < i; k++) {
                if (values[k] === null) return false;
            }
            return true;
        }

        function syncButtons() {
            var busy = isBusy();
            wheels.forEach(function (w, i) {
                if (w.running || w.stopping) {
                    w.startBtn.disabled = true;
                    w.stopBtn.disabled = !w.running || w.stopping;
                    return;
                }
                w.stopBtn.disabled = true;
                w.startBtn.disabled = busy || !canStart(i);
            });
        }

        function nextIndex() {
            for (var i = 0; i < wheels.length; i++) {
                if (values[i] === null) return i;
            }
            return -1;
        }

        function beginSpin(i) {
            var w = wheels[i];
            w.running = true;
            w.stopping = false;
            w.stopAge = 0;
            w.stopStart = 0;
            w.speed = 14 + Math.random() * 4;
            values[i] = null;
            celebrated = false;
            hideCelebrate();
            if (typeof opts.onResetCelebrate === 'function') opts.onResetCelebrate();
            syncButtons();
            render();
        }

        function startWheel(i) {
            if (!canStart(i)) return;
            if (typeof opts.onStart === 'function') opts.onStart(i);
            var delay = opts.walkDelay || 0;
            if (delay) {
                walking = true;
                syncButtons();
                if (walkTimer) clearTimeout(walkTimer);
                walkTimer = setTimeout(function () {
                    walking = false;
                    walkTimer = 0;
                    beginSpin(i);
                    if (typeof opts.onSpin === 'function') opts.onSpin(i);
                }, delay);
                return;
            }
            beginSpin(i);
        }

        function stopWheel(i) {
            var w = wheels[i];
            if (!w.running || w.stopping) return;
            w.stopping = true;
            w.stopAge = 0;
            w.stopStart = w.speed;
            w.stopBtn.disabled = true;
            if (typeof opts.onStop === 'function') opts.onStop(i);
        }

        function resetAll() {
            if (walkTimer) {
                clearTimeout(walkTimer);
                walkTimer = 0;
            }
            walking = false;
            wheels.forEach(function (w, i) {
                w.running = false;
                w.stopping = false;
                w.speed = 0;
                w.stopAge = 0;
                w.stopStart = 0;
                w.angle = 0;
                w.el.style.transform = 'rotate(0deg)';
                w.stopBtn.disabled = true;
                values[i] = null;
            });
            celebrated = false;
            hideCelebrate();
            if (typeof opts.onResetCelebrate === 'function') opts.onResetCelebrate();
            if (typeof opts.onReset === 'function') opts.onReset();
            syncButtons();
            render();
        }

        root.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            var i = Number(b.getAttribute('data-i'));
            if (b.classList.contains('start')) startWheel(i);
            if (b.classList.contains('stop')) stopWheel(i);
        });

        syncButtons();
        render();
        return {
            startWheel: startWheel,
            stopWheel: stopWheel,
            resetAll: resetAll,
            startAll: function () {
                var n = nextIndex();
                if (n >= 0) startWheel(n);
            },
            stopAll: function () {
                wheels.forEach(function (w, i) {
                    if (w.running && !w.stopping) stopWheel(i);
                });
            }
        };
    }

    function createReels(opts) {
        var root = document.querySelector(opts.root);
        var board = document.querySelector(opts.board);
        var info = document.querySelector(opts.info);
        var entries = opts.entries || [];
        var count = opts.count || 6;
        var cell = opts.cell || 120;
        var reels = [];
        var values = [];
        var celebrated = false;

        root.innerHTML = '';
        for (var i = 0; i < count; i++) {
            values[i] = null;
            var unit = document.createElement('div');
            unit.className = 'unit';
            unit.innerHTML =
                '<h3>العداد ' + (i + 1) + '</h3>' +
                '<div class="window"><div class="strip"></div></div>' +
                '<div class="btns">' +
                    '<button type="button" class="start" data-i="' + i + '">تشغيل</button>' +
                    '<button type="button" class="stop" data-i="' + i + '" disabled>إيقاف</button>' +
                '</div>';
            var strip = unit.querySelector('.strip');
            var digits = digitsFor(i);
            var copies = digits.length * 6;
            for (var n = 0; n < copies; n++) {
                var d = document.createElement('div');
                d.textContent = String(digits[n % digits.length]);
                strip.appendChild(d);
            }
            root.appendChild(unit);
            reels[i] = {
                el: strip,
                startBtn: unit.querySelector('.start'),
                stopBtn: unit.querySelector('.stop'),
                running: false,
                stopping: false,
                y: 0,
                speed: 0,
                copies: copies,
                count: digits.length
            };
        }

        function render() {
            var num = pad6(values);
            if (board) board.textContent = num;
            if (values.some(function (n) { return n === null; })) {
                if (info) info.textContent = info.getAttribute('data-idle') || '';
                return;
            }
            var hit = findEntry(entries, num);
            if (info) {
                info.textContent = hit
                    ? (hit.full_name + ' — ' + hit.phone + ' — ' + hit.governorate)
                    : 'رقم البطاقة: ' + num;
            }
            if (!celebrated) {
                celebrated = true;
                showCelebrate(num, hit);
                if (typeof opts.onComplete === 'function') opts.onComplete(num, hit);
            }
        }

        function digitFromY(y, idx) {
            var digits = digitsFor(idx);
            var index = Math.round(Math.abs(y) / cell) % digits.length;
            return digits[index];
        }

        function frame() {
            reels.forEach(function (r, idx) {
                if (!r.running && !r.stopping) return;
                if (r.stopping) {
                    r.speed *= 0.975;
                    if (r.speed < 0.35) {
                        r.speed = 0;
                        r.stopping = false;
                        r.running = false;
                        r.y = -Math.round(Math.abs(r.y) / cell) * cell;
                        r.el.style.transform = 'translateY(' + r.y + 'px)';
                        r.stopBtn.disabled = true;
                        values[idx] = digitFromY(r.y, idx);
                        syncButtons();
                        render();
                        return;
                    }
                }
                r.y -= r.speed;
                if (r.y < -cell * (r.copies - r.count)) r.y += cell * r.count;
                r.el.style.transform = 'translateY(' + r.y + 'px)';
            });
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);

        function isBusy() {
            return reels.some(function (r) { return r.running || r.stopping; });
        }

        function canStart(i) {
            if (isBusy() || values[i] !== null) return false;
            for (var k = 0; k < i; k++) {
                if (values[k] === null) return false;
            }
            return true;
        }

        function syncButtons() {
            var busy = isBusy();
            reels.forEach(function (r, i) {
                if (r.running || r.stopping) {
                    r.startBtn.disabled = true;
                    r.stopBtn.disabled = !r.running || r.stopping;
                    return;
                }
                r.stopBtn.disabled = true;
                r.startBtn.disabled = busy || !canStart(i);
            });
        }

        function startReel(i) {
            var r = reels[i];
            if (!canStart(i)) return;
            r.running = true;
            r.speed = 18 + Math.random() * 8;
            values[i] = null;
            celebrated = false;
            hideCelebrate();
            if (typeof opts.onResetCelebrate === 'function') opts.onResetCelebrate();
            syncButtons();
            render();
        }

        function stopReel(i) {
            var r = reels[i];
            if (!r.running || r.stopping) return;
            r.stopping = true;
            r.stopBtn.disabled = true;
        }

        function resetAll() {
            reels.forEach(function (r, i) {
                r.running = false;
                r.stopping = false;
                r.speed = 0;
                r.y = 0;
                r.el.style.transform = 'translateY(0)';
                r.stopBtn.disabled = true;
                values[i] = null;
            });
            celebrated = false;
            hideCelebrate();
            if (typeof opts.onResetCelebrate === 'function') opts.onResetCelebrate();
            syncButtons();
            render();
        }

        root.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            var i = Number(b.getAttribute('data-i'));
            if (b.classList.contains('start')) startReel(i);
            if (b.classList.contains('stop')) stopReel(i);
        });

        syncButtons();
        render();
        return { resetAll: resetAll };
    }

    global.YamoonDraw = {
        createWheels: createWheels,
        createReels: createReels
    };
})(window);
