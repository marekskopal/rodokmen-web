// Rodokmen – zoom-and-pan viewer for register scans and family photos, no dependencies.
// Opens from links marked data-lightbox (href = the full-size image, the <img> inside = the crop shown on the page):
// the crop the browser already has appears at once, the full resolution replaces it when it has loaded.
(function () {
    'use strict';

    const viewer = document.querySelector('.viewer');
    if (!viewer) return;

    const stage = viewer.querySelector('.viewer-stage');
    const img = viewer.querySelector('.viewer-img');
    const status = viewer.querySelector('.viewer-status');
    const zoomLabel = viewer.querySelector('.viewer-zoom');
    const original = viewer.querySelector('[data-viewer="orig"]');
    const closeButton = viewer.querySelector('[data-viewer="close"]');

    const MaxZoom = 8; // × the scale that fits the window (and at least 4 × the full image's own pixels)
    const MinZoom = 0.5;
    const ButtonStep = 1.6;
    const KeyPan = 80;
    const DragThreshold = 6;
    const DoubleTapMs = 320;
    const SameAspect = 0.02;
    // Free space around a fitted image; the bottom one leaves room for the toolbar.
    const Margin = { top: 16, right: 16, bottom: 76, left: 16 };

    // Size of the image in CSS pixels at scale 1 (the first image shown); the full resolution is drawn into it.
    let base = { w: 1, h: 1 };
    // Natural width of the sharpest image shown, for the zoom label (100 % = one image pixel per screen pixel).
    let naturalWidth = 1;
    let scale = 1;
    let x = 0;
    let y = 0;
    let fitted = true;
    let session = 0;
    let returnFocus = null;

    const pointers = new Map();
    let gesture = null;
    let lastTap = { time: 0, x: 0, y: 0 };

    const stageSize = () => ({ w: stage.clientWidth, h: stage.clientHeight });
    // The part of the window an image is fitted and centred in (the whole window on very small screens).
    const area = () => {
        const s = stageSize();
        const m = s.w < 480 || s.h < 480 ? { top: 0, right: 0, bottom: 64, left: 0 } : Margin;
        return { x: m.left, y: m.top, w: Math.max(1, s.w - m.left - m.right), h: Math.max(1, s.h - m.top - m.bottom) };
    };
    const fitScale = () => {
        const a = area();
        return Math.min(a.w / base.w, a.h / base.h);
    };
    const minScale = () => fitScale() * MinZoom;
    const maxScale = () => Math.max(fitScale() * MaxZoom, 4 * naturalWidth / base.w);

    // Keeps the image on screen: centred along an axis where it fits into the free area, edge to edge otherwise.
    const clamp = () => {
        const s = stageSize();
        const a = area();
        const w = base.w * scale;
        const h = base.h * scale;
        x = w <= a.w ? a.x + (a.w - w) / 2 : Math.min(0, Math.max(s.w - w, x));
        y = h <= a.h ? a.y + (a.h - h) / 2 : Math.min(0, Math.max(s.h - h, y));
    };

    const render = (animate = false) => {
        if (img.hidden) return; // nothing shown yet: the full image is still loading
        clamp();
        img.classList.toggle('animate', animate);
        img.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
        zoomLabel.textContent = `${Math.round(scale * base.w / naturalWidth * 100)} %`;
    };

    const fit = (animate = false) => {
        scale = fitScale();
        fitted = true;
        render(animate);
    };

    // Zooms to `next` keeping the point (cx, cy) of the window where it is.
    const zoomAt = (next, cx, cy, animate = false) => {
        const target = Math.min(maxScale(), Math.max(minScale(), next));
        x = cx - (cx - x) * target / scale;
        y = cy - (cy - y) * target / scale;
        scale = target;
        fitted = false;
        render(animate);
    };

    const zoomCentre = (factor) => {
        const s = stageSize();
        zoomAt(scale * factor, s.w / 2, s.h / 2, true);
    };

    const show = (src, width, height) => {
        base = { w: width, h: height };
        naturalWidth = width;
        img.src = src;
        img.hidden = false;
        img.style.width = `${width}px`;
        img.style.height = `${height}px`;
        fit();
    };

    const setStatus = (text) => {
        status.textContent = text;
        status.hidden = text === '';
    };

    const open = (link) => {
        const current = ++session;
        const thumb = link.querySelector('img');
        const full = link.href;
        returnFocus = document.activeElement;

        img.alt = thumb ? thumb.alt : '';
        original.href = full;
        viewer.hidden = false;
        document.documentElement.classList.add('viewer-open');
        closeButton.focus();

        const hasThumb = thumb && thumb.complete && thumb.naturalWidth > 0;
        if (hasThumb) {
            show(thumb.currentSrc || thumb.src, thumb.naturalWidth, thumb.naturalHeight);
        } else {
            // Nothing to show until the full image loads: hide the previous image's box and zoom.
            img.removeAttribute('src');
            img.hidden = true;
            zoomLabel.textContent = '';
        }
        setStatus('Načítám…');

        const loader = new Image();
        loader.onload = () => {
            if (current !== session) return;
            setStatus('');
            const sameAspect = hasThumb
                && Math.abs(loader.naturalWidth / loader.naturalHeight - base.w / base.h) <= SameAspect * base.w / base.h;
            if (sameAspect) {
                // Same picture in a higher resolution: keep the zoom and position, only sharpen.
                naturalWidth = loader.naturalWidth;
                img.src = full;
                render();
            } else {
                show(full, loader.naturalWidth, loader.naturalHeight);
            }
        };
        loader.onerror = () => {
            if (current !== session) return;
            setStatus(hasThumb ? '' : 'Obrázek se nepodařilo načíst.');
        };
        loader.src = full;
    };

    const close = () => {
        session++;
        viewer.hidden = true;
        document.documentElement.classList.remove('viewer-open');
        img.removeAttribute('src');
        pointers.clear();
        gesture = null;
        setStatus('');
        if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
        returnFocus = null;
    };

    document.addEventListener('click', (ev) => {
        const link = ev.target.closest('a[data-lightbox]');
        if (!link || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
        ev.preventDefault();
        open(link);
    });

    viewer.querySelector('[data-viewer="in"]').addEventListener('click', () => zoomCentre(ButtonStep));
    viewer.querySelector('[data-viewer="out"]').addEventListener('click', () => zoomCentre(1 / ButtonStep));
    viewer.querySelector('[data-viewer="fit"]').addEventListener('click', () => fit(true));
    closeButton.addEventListener('click', close);

    // Point of a pointer event relative to the window (stage).
    const local = (ev) => {
        const r = stage.getBoundingClientRect();
        return { x: ev.clientX - r.left, y: ev.clientY - r.top };
    };

    const startGesture = () => {
        const points = Array.from(pointers.values());
        if (points.length >= 2) {
            const [a, b] = points;
            gesture = {
                pinch: true,
                distance: Math.hypot(b.x - a.x, b.y - a.y) || 1,
                mid: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 },
                scale,
                x,
                y,
                moved: true,
            };
        } else if (points.length === 1) {
            gesture = { pinch: false, start: points[0], x, y, moved: gesture ? gesture.moved : false };
        } else {
            gesture = null;
        }
    };

    stage.addEventListener('pointerdown', (ev) => {
        if (ev.pointerType === 'mouse' && ev.button !== 0) return;
        try { stage.setPointerCapture(ev.pointerId); } catch (e) { /* the pointer is already gone */ }
        pointers.set(ev.pointerId, local(ev));
        if (pointers.size === 1) gesture = null;
        startGesture();
    });

    stage.addEventListener('pointermove', (ev) => {
        if (!pointers.has(ev.pointerId) || !gesture) return;
        pointers.set(ev.pointerId, local(ev));
        const points = Array.from(pointers.values());

        if (gesture.pinch && points.length >= 2) {
            const [a, b] = points;
            const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
            const target = Math.min(maxScale(), Math.max(minScale(), gesture.scale * Math.hypot(b.x - a.x, b.y - a.y) / gesture.distance));
            // The point under the fingers' midpoint at the start follows the midpoint.
            x = mid.x - (gesture.mid.x - gesture.x) * target / gesture.scale;
            y = mid.y - (gesture.mid.y - gesture.y) * target / gesture.scale;
            scale = target;
            fitted = false;
            render();
            return;
        }

        const dx = points[0].x - gesture.start.x;
        const dy = points[0].y - gesture.start.y;
        if (!gesture.moved && Math.hypot(dx, dy) < DragThreshold) return;
        gesture.moved = true;
        stage.classList.add('dragging');
        x = gesture.x + dx;
        y = gesture.y + dy;
        fitted = false;
        render();
    });

    const endPointer = (ev) => {
        if (!pointers.has(ev.pointerId)) return;
        const point = pointers.get(ev.pointerId);
        pointers.delete(ev.pointerId);
        const wasTap = gesture && !gesture.moved && pointers.size === 0 && ev.type === 'pointerup';
        if (pointers.size > 0) {
            startGesture();
            return;
        }
        gesture = null;
        stage.classList.remove('dragging');
        if (!wasTap) return;

        // By position, not by the event target: with pointer capture every event goes to the stage.
        const onImage = !img.hidden && point.x >= x && point.x <= x + base.w * scale && point.y >= y && point.y <= y + base.h * scale;
        if (!onImage) {
            close(); // a click or tap beside the image
            return;
        }
        const now = ev.timeStamp;
        if (now - lastTap.time < DoubleTapMs && Math.hypot(point.x - lastTap.x, point.y - lastTap.y) < 30) {
            lastTap = { time: 0, x: 0, y: 0 };
            if (fitted || scale <= fitScale() * 1.01) {
                zoomAt(Math.max(fitScale() * 2.5, scale * 2.5), point.x, point.y, true);
            } else {
                fit(true);
            }
            return;
        }
        lastTap = { time: now, x: point.x, y: point.y };
    };
    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);

    stage.addEventListener('wheel', (ev) => {
        ev.preventDefault();
        const unit = ev.deltaMode === 1 ? 16 : ev.deltaMode === 2 ? stageSize().h : 1;
        // A trackpad pinch arrives as a wheel event with ctrlKey and small deltas: zoom faster for it.
        const speed = ev.ctrlKey ? 0.01 : 0.0015;
        const p = local(ev);
        zoomAt(scale * Math.exp(-ev.deltaY * unit * speed), p.x, p.y);
    }, { passive: false });

    document.addEventListener('keydown', (ev) => {
        // Leave the browser's own shortcuts (Ctrl/Cmd + -/+/0 page zoom, Alt + arrow history) alone.
        if (viewer.hidden || ev.ctrlKey || ev.metaKey || ev.altKey) return;
        switch (ev.key) {
            case 'Escape': close(); break;
            case '+': case '=': zoomCentre(ButtonStep); break;
            case '-': case '_': zoomCentre(1 / ButtonStep); break;
            case '0': fit(true); break;
            case 'ArrowLeft': x += KeyPan; fitted = false; render(true); break;
            case 'ArrowRight': x -= KeyPan; fitted = false; render(true); break;
            case 'ArrowUp': y += KeyPan; fitted = false; render(true); break;
            case 'ArrowDown': y -= KeyPan; fitted = false; render(true); break;
            case 'Tab': {
                // Keep the focus inside the dialog.
                const items = Array.from(viewer.querySelectorAll('button, a[href]'));
                const index = items.indexOf(document.activeElement);
                const next = ev.shiftKey ? (index <= 0 ? items.length - 1 : index - 1) : (index + 1) % items.length;
                items[next].focus();
                break;
            }
            default: return;
        }
        ev.preventDefault();
    });

    window.addEventListener('resize', () => {
        if (viewer.hidden) return;
        if (fitted) fit(); else render();
    });
}());
