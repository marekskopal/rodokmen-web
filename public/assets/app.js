// Rodokmen – small progressive enhancements, no dependencies (Leaflet only on map pages).
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Mobile navigation
    const toggle = $('.nav-toggle');
    if (toggle) {
        toggle.addEventListener('click', () => {
            const nav = $('#mainnav');
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    // Theme toggle (remembered per browser)
    $$('[data-theme-toggle]').forEach((btn) => btn.addEventListener('click', () => {
        const root = document.documentElement;
        const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        root.dataset.theme = dark ? 'light' : 'dark';
        try { localStorage.setItem('rk-theme', root.dataset.theme); } catch (e) { /* storage blocked */ }
    }));

    // Printing: buttons, and the transcriptions in <details> open on paper (CSS cannot open them)
    // Forms that apply a choice at once (ancestor poster, numbered list of ancestors): the page is fetched again
    // with the new query and its live parts ([data-live]) are swapped in, so that the preview, printing and the
    // download always follow the controls. Without JavaScript the submit button does the same with a page load.
    let pending = null;
    $$('form[data-live-form]').forEach((form) => {
        const queryOf = () => {
            const params = new URLSearchParams();
            new FormData(form).forEach((value, key) => { if (value !== '') params.append(key, String(value)); });
            return params.toString();
        };
        let shown = queryOf();
        let request = null;
        let timer = 0;
        const apply = () => {
            clearTimeout(timer);
            const query = queryOf();
            if (query === shown) return;
            shown = query;
            const url = location.pathname + (query ? '?' + query : '');
            if (request) request.abort();
            const current = request = new AbortController();
            const busy = $$('[data-live-busy]');
            busy.forEach((el) => el.setAttribute('aria-busy', 'true'));
            pending = fetch(url, { signal: current.signal, credentials: 'same-origin' })
                .then((response) => {
                    if (!response.ok || response.redirected) throw new Error('page load needed');
                    return response.text();
                })
                .then((html) => {
                    const next = new DOMParser().parseFromString(html, 'text/html');
                    $$('[data-live]').forEach((el) => {
                        const fresh = $(`[data-live="${el.dataset.live}"]`, next);
                        if (!fresh) return;
                        Array.from(el.attributes).forEach((a) => { if (!fresh.hasAttribute(a.name) && a.name !== 'aria-busy') el.removeAttribute(a.name); });
                        Array.from(fresh.attributes).forEach((a) => el.setAttribute(a.name, a.value));
                        el.innerHTML = fresh.innerHTML;
                    });
                    history.replaceState(history.state, '', url);
                })
                .catch((error) => { if (error.name !== 'AbortError') location.assign(url); })
                .finally(() => {
                    if (request !== current) return;
                    busy.forEach((el) => el.removeAttribute('aria-busy'));
                    request = null;
                    pending = null;
                });
        };
        $$('[data-live-submit]', form).forEach((btn) => { btn.hidden = true; });
        form.addEventListener('change', apply);
        form.addEventListener('input', (event) => {
            if (event.target instanceof HTMLInputElement && event.target.type === 'text') {
                clearTimeout(timer);
                timer = setTimeout(apply, 600);
            }
        });
        form.addEventListener('submit', (event) => { event.preventDefault(); apply(); });
    });

    // Printing waits for a choice that is still loading.
    $$('[data-print]').forEach((btn) => btn.addEventListener('click', () => (pending ? pending.then(() => window.print()) : window.print())));
    window.addEventListener('beforeprint', () => {
        $$('details:not([open])').forEach((d) => { d.open = true; d.dataset.printOpened = ''; });
    });
    window.addEventListener('afterprint', () => {
        $$('details[data-print-opened]').forEach((d) => { d.open = false; delete d.dataset.printOpened; });
    });

    // Filters (generations table, records grid)
    const filterRoot = $('[data-filter-root]');
    if (filterRoot) {
        const text = $('[data-filter-text]', filterRoot);
        const docOnly = $('[data-filter-doc]', filterRoot);
        const items = $$('tr[data-text], a.record-tile');
        const state = {
            line: ($('[data-filter-line].on', filterRoot) || {}).dataset?.filterLine || '',
            kind: '',
        };

        const apply = () => {
            const q = norm(text.value.trim());
            let visible = 0;
            items.forEach((el) => {
                const ok = (!q || norm(el.dataset.text).includes(q))
                    && (!state.line || el.dataset.line === state.line)
                    && (!state.kind || el.dataset.kind === state.kind)
                    && (!docOnly || !docOnly.checked || el.dataset.st === 'doc');
                el.hidden = !ok;
                if (ok) visible++;
            });
            $$('[data-gen]').forEach((sec) => {
                const n = $$('tr[data-text]', sec).filter((tr) => !tr.hidden).length;
                sec.hidden = n === 0;
                const c = $('[data-gen-count]', sec);
                if (c) c.textContent = n;
            });
            const empty = $('[data-filter-empty]');
            if (empty) empty.hidden = visible > 0;
        };

        const chipGroup = (attr, key) => $$(`[${attr}]`, filterRoot).forEach((chip) => chip.addEventListener('click', () => {
            $$(`[${attr}]`, filterRoot).forEach((c) => c.classList.remove('on'));
            chip.classList.add('on');
            state[key] = chip.getAttribute(attr);
            if (key === 'line') {
                const url = new URL(location.href);
                state.line ? url.searchParams.set('rod', state.line) : url.searchParams.delete('rod');
                history.replaceState(null, '', url);
            }
            apply();
        }));
        chipGroup('data-filter-line', 'line');
        chipGroup('data-filter-kind', 'kind');
        text.addEventListener('input', apply);
        if (docOnly) docOnly.addEventListener('change', apply);
        apply();
    }

    // Anchors for markdown headings (links from the home page)
    $$('[data-anchor-headings] h2').forEach((h) => { h.id = encodeURIComponent(h.textContent.trim()); });
    if (location.hash) {
        const target = document.getElementById(location.hash.slice(1)) || document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (target) {
            if (target.tagName === 'DETAILS') target.open = true;
            setTimeout(() => target.scrollIntoView({ block: 'start' }), 50);
        }
    }

    // Maps
    // OpenStreetMap tiles; the tile policy requires a referrer, so send at least the page origin.
    const tiles = (map) => L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const marker = (lat, lon, colors) => {
        const ring = colors.length > 1
            ? `conic-gradient(${colors.map((c, i) => `${c} ${(i / colors.length) * 360}deg ${((i + 1) / colors.length) * 360}deg`).join(',')})`
            : colors[0];
        return L.marker([lat, lon], {
            icon: L.divIcon({
                className: '',
                html: `<span style="display:block;width:18px;height:18px;border-radius:50%;background:${ring};border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></span>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9],
            }),
        });
    };

    const inCzechLands = ([lat, lon]) => lat > 48.4 && lat < 51.2 && lon > 12 && lon < 19;

    const whenLeaflet = (fn) => {
        if (window.L) return fn();
        window.addEventListener('load', () => window.L && fn());
    };

    const mapEl = $('#map');
    if (mapEl) whenLeaflet(() => {
        const places = JSON.parse(mapEl.dataset.places);
        const map = L.map(mapEl, { scrollWheelZoom: true });
        tiles(map);
        const bounds = [];
        const byName = {};
        places.forEach((p) => {
            const m = marker(p.lat, p.lon, p.groups.map((g) => g.color)).addTo(map);
            const list = p.groups.map((g) => g.people.map((x) => `<li><a href="${esc(x.url)}">${esc(x.name)}</a> <span class="muted">${esc(x.y)}</span></li>`).join('')).join('');
            m.bindPopup(`<div class="map-pop"><h4>${esc(p.name)}</h4><ul>${list}</ul></div>`);
            m.on('click', () => {
                $$('.place').forEach((d) => { d.open = d.dataset.place === p.name; });
                const d = $(`.place[data-place="${CSS.escape(p.name)}"]`);
                if (d) d.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
            byName[p.name] = m;
            bounds.push([p.lat, p.lon]);
        });
        // Fit to the Czech lands; far-away places (e.g. Vilnius) stay reachable by zooming out.
        const home = bounds.filter(inCzechLands);
        const fit = home.length ? home : bounds;
        if (fit.length) map.fitBounds(fit, { padding: [30, 30] });
        $$('.place').forEach((d) => d.addEventListener('toggle', () => {
            const m = byName[d.dataset.place];
            if (d.open && m) { map.setView(m.getLatLng(), Math.max(map.getZoom(), 10)); m.openPopup(); }
        }));
        const hash = decodeURIComponent(location.hash.slice(1));
        if (byName[hash]) { map.setView(byName[hash].getLatLng(), 11); byName[hash].openPopup(); }
    });

    $$('.minimap').forEach((el) => whenLeaflet(() => {
        const places = JSON.parse(el.dataset.map);
        const map = L.map(el, { scrollWheelZoom: false, attributionControl: false, zoomControl: false });
        tiles(map);
        const bounds = places.map((p) => {
            const m = marker(p.lat, p.lon, p.colors ?? [el.dataset.color]).addTo(map).bindTooltip(p.name);
            // The home page map leads to the place on the full map.
            if (el.dataset.link) m.on('click', () => { location.href = `${el.dataset.link}#${encodeURIComponent(p.name)}`; });
            return [p.lat, p.lon];
        });
        const home = bounds.filter(inCzechLands);
        const fit = home.length ? home : bounds;
        if (fit.length === 1) map.setView(fit[0], 10);
        else map.fitBounds(fit, { padding: [20, 20], maxZoom: 11 });
        // Places outside the Czech lands are left out of the view: without wheel zoom they need the buttons.
        if (fit.length < bounds.length) L.control.zoom().addTo(map);
    }));

    // Full ancestor tree: collapse/expand, depth presets, zoom, find, drag to pan
    const ft = $('[data-ft-tree]');
    if (ft) {
        const scroll = $('[data-ft-scroll]');
        const nodes = $$('li.ft-has', ft);
        const depthOf = (li) => { let d = 0; for (let el = li.parentElement; el && el !== ft; el = el.parentElement) if (el.tagName === 'UL') d++; return d; };
        const setCollapsed = (li, collapsed) => {
            li.classList.toggle('collapsed', collapsed);
            const btn = $(':scope > .ft-card .ft-toggle', li);
            if (btn) btn.setAttribute('aria-expanded', String(!collapsed));
        };
        ft.addEventListener('click', (ev) => {
            const btn = ev.target.closest('.ft-toggle');
            if (!btn) return;
            const li = btn.closest('li');
            setCollapsed(li, !li.classList.contains('collapsed'));
        });
        const toolbar = $('[data-ft-toolbar]');
        $('[data-ft="expand"]', toolbar).addEventListener('click', () => nodes.forEach((li) => setCollapsed(li, false)));
        $$('[data-ft="depth"]', toolbar).forEach((b) => b.addEventListener('click', () => {
            const max = Number(b.dataset.depth) - 1;
            nodes.forEach((li) => setCollapsed(li, depthOf(li) >= max));
        }));
        const zoom = $('[data-ft="zoom"]', toolbar);
        zoom.addEventListener('input', () => ft.style.setProperty('--ft-zoom', zoom.value / 100));
        // Initial zoom: fit the full width when that keeps the text readable (≥ 60 %).
        const fitZoom = Math.max(0.6, Math.min(1, (scroll.clientWidth - 32) / ft.scrollWidth));
        zoom.value = Math.round(fitZoom * 20) * 5;
        ft.style.setProperty('--ft-zoom', zoom.value / 100);
        const find = $('[data-ft="find"]', toolbar);
        find.addEventListener('input', () => {
            const q = norm(find.value.trim());
            const cards = $$('.ft-card', ft);
            let first = null;
            cards.forEach((c) => {
                const hit = q.length > 1 && norm(c.textContent).includes(q);
                c.classList.toggle('hit', hit);
                c.classList.toggle('dim', q.length > 1 && !hit);
                if (hit) {
                    for (let li = c.parentElement.parentElement.closest('li'); li; li = li.parentElement.closest('li')) setCollapsed(li, false);
                    first = first || c;
                }
            });
            if (first) first.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
        });
        let drag = null;
        scroll.addEventListener('pointerdown', (ev) => {
            if (ev.pointerType !== 'mouse' || ev.target.closest('a, button')) return;
            drag = { x: ev.clientX, y: ev.clientY, left: scroll.scrollLeft, top: scroll.scrollTop };
            scroll.classList.add('dragging');
        });
        window.addEventListener('pointermove', (ev) => {
            if (!drag) return;
            scroll.scrollLeft = drag.left - (ev.clientX - drag.x);
            scroll.scrollTop = drag.top - (ev.clientY - drag.y);
        });
        window.addEventListener('pointerup', () => { drag = null; scroll.classList.remove('dragging'); });
    }

    // Fan chart: zoom (wheel, pinch, buttons) and pan by moving the SVG viewBox; find zooms to the ancestor
    const fanStage = $('[data-fan-stage]');
    if (fanStage) {
        const svg = $('svg', fanStage);
        const b = svg.viewBox.baseVal;
        const home = { x: b.x, y: b.y, w: b.width, h: b.height };
        let vb = { ...home };
        const apply = () => svg.setAttribute('viewBox', `${vb.x} ${vb.y} ${vb.w} ${vb.h}`);
        // client point → SVG coordinates (the viewBox is centred with "meet" scaling)
        const toSvg = (cx, cy) => {
            const r = svg.getBoundingClientRect();
            const s = Math.max(vb.w / r.width, vb.h / r.height);
            const ox = (r.width * s - vb.w) / 2, oy = (r.height * s - vb.h) / 2;
            return { x: vb.x - ox + (cx - r.left) * s, y: vb.y - oy + (cy - r.top) * s, s };
        };
        const zoomAt = (f, cx, cy) => {
            const r = svg.getBoundingClientRect();
            const p = toSvg(cx ?? r.left + r.width / 2, cy ?? r.top + r.height / 2);
            const w = Math.min(home.w * 1.2, Math.max(home.w / 60, vb.w / f));
            const k = w / vb.w;
            vb = { x: p.x - (p.x - vb.x) * k, y: p.y - (p.y - vb.y) * k, w, h: vb.h * k };
            apply();
        };
        // Zoom proportional to the scroll delta: a mouse wheel step ~1.25×, touchpad smooth; pinch (ctrlKey) faster.
        fanStage.addEventListener('wheel', (ev) => {
            ev.preventDefault();
            let d = ev.deltaY;
            if (ev.deltaMode === 1) d *= 33; else if (ev.deltaMode === 2) d *= 300;
            d = Math.max(-120, Math.min(120, d));
            zoomAt(Math.exp(-d * (ev.ctrlKey ? 0.012 : 0.0022)), ev.clientX, ev.clientY);
        }, { passive: false });
        const pts = new Map();
        let moved = 0, pinch = 0;
        fanStage.addEventListener('pointerdown', (ev) => { pts.set(ev.pointerId, { x: ev.clientX, y: ev.clientY }); moved = 0; });
        fanStage.addEventListener('pointermove', (ev) => {
            const prev = pts.get(ev.pointerId);
            if (!prev) return;
            pts.set(ev.pointerId, { x: ev.clientX, y: ev.clientY });
            if (pts.size === 2) {
                const [a, c] = [...pts.values()];
                const d = Math.hypot(a.x - c.x, a.y - c.y);
                if (pinch) zoomAt(d / pinch, (a.x + c.x) / 2, (a.y + c.y) / 2);
                pinch = d; moved += 10;
                return;
            }
            const { s } = toSvg(ev.clientX, ev.clientY);
            const dx = ev.clientX - prev.x, dy = ev.clientY - prev.y;
            moved += Math.abs(dx) + Math.abs(dy);
            if (moved > 4) {
                if (!fanStage.hasPointerCapture(ev.pointerId)) fanStage.setPointerCapture(ev.pointerId);
                fanStage.classList.add('dragging');
                vb.x -= dx * s; vb.y -= dy * s; apply();
            }
        });
        const up = (ev) => { pts.delete(ev.pointerId); if (pts.size < 2) pinch = 0; fanStage.classList.remove('dragging'); };
        fanStage.addEventListener('pointerup', up);
        fanStage.addEventListener('pointercancel', up);
        // a drag must not open the person under the pointer
        fanStage.addEventListener('click', (ev) => { if (moved > 4) { ev.preventDefault(); ev.stopPropagation(); } }, true);

        const toolbar = $('[data-fan-toolbar]');
        $('[data-fan="in"]', toolbar).addEventListener('click', () => zoomAt(1.5));
        $('[data-fan="out"]', toolbar).addEventListener('click', () => zoomAt(1 / 1.5));
        $('[data-fan="reset"]', toolbar).addEventListener('click', () => { vb = { ...home }; apply(); });
        const find = $('[data-fan="find"]', toolbar);
        const people = $$('a.fan-p', svg);
        const search = (focus) => {
            const q = norm(find.value.trim());
            const hits = people.filter((a) => q.length > 1 && norm(a.dataset.name).includes(q));
            people.forEach((a) => a.classList.toggle('hit', hits.includes(a)));
            // draw highlighted segments last so that their outline is not covered by neighbours
            hits.forEach((a) => a.parentNode.appendChild(a));
            if (!focus || !hits.length) return;
            const bb = hits[0].querySelector('path, circle').getBBox();
            const w = Math.min(home.w, Math.max(bb.width, bb.height) * 5);
            vb = { x: bb.x + bb.width / 2 - w / 2, y: bb.y + bb.height / 2 - w / 2, w, h: w };
            apply();
        };
        find.addEventListener('input', () => search(false));
        find.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); search(true); } });
    }

    // Client-side search
    const search = $('[data-search]');
    if (search) {
        const input = $('.search-input', search);
        const results = $('.search-results', search);
        const count = $('.search-count', search);
        let index = null;

        const highlight = (s, q) => {
            if (!q) return esc(s);
            const i = norm(s).indexOf(q);
            return i < 0 ? esc(s) : esc(s.slice(0, i)) + '<mark>' + esc(s.slice(i, i + q.length)) + '</mark>' + esc(s.slice(i + q.length));
        };

        const run = () => {
            const q = norm(input.value.trim());
            const url = new URL(location.href);
            q ? url.searchParams.set('q', input.value.trim()) : url.searchParams.delete('q');
            history.replaceState(null, '', url);
            if (!index || q.length < 2) { results.innerHTML = ''; count.textContent = q.length ? 'Zadejte aspoň dva znaky.' : ''; return; }
            const hits = index
                .map((it) => {
                    const name = norm(it.n);
                    const score = name.startsWith(q) ? 3 : name.includes(q) ? 2 : norm(it.d + ' ' + it.x).includes(q) ? 1 : 0;
                    return { it, score };
                })
                .filter((h) => h.score > 0)
                .sort((a, b) => b.score - a.score || a.it.n.localeCompare(b.it.n, 'cs'))
                .slice(0, 60);
            count.textContent = hits.length ? `Nalezeno: ${hits.length}${hits.length === 60 ? '+' : ''}` : 'Nic nenalezeno.';
            results.innerHTML = hits.map(({ it }) => `<li><a href="${esc(it.u)}" style="--c:${esc(it.c)}"><span class="t">${esc(it.t)}</span><span><strong>${highlight(it.n, q)}</strong><br><span class="muted">${esc(it.d)}</span></span></a></li>`).join('');
        };

        fetch('/data/hledani.json', { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((data) => { index = data; run(); });
        input.addEventListener('input', run);
    }
})();
