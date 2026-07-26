/**
 * LiteNote animated icons — vanilla port of lucide-animated (MIT, pqoqubbw).
 * Path-level hover/focus animations via Web Animations API.
 */
(function (global) {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var reducedMotion =
        typeof matchMedia === 'function' &&
        matchMedia('(prefers-reduced-motion: reduce)').matches;

    var SUN_RAYS = [
        'M12 2v2',
        'm19.07 4.93-1.41 1.41',
        'M20 12h2',
        'm17.66 17.66 1.41 1.41',
        'M12 20v2',
        'm6.34 17.66-1.41 1.41',
        'M2 12h2',
        'm4.93 4.93 1.41 1.41',
    ];

    /** @type {Record<string, object>} */
    var ICONS = {
        home: {
            parts: [
                {
                    tag: 'path',
                    d: 'M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
                },
                {
                    tag: 'path',
                    d: 'M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8',
                    role: 'draw',
                },
            ],
        },
        activity: {
            parts: [
                {
                    tag: 'path',
                    d: 'M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2',
                    role: 'draw',
                    duration: 600,
                },
            ],
        },
        'chart-column-increasing': {
            parts: [
                { tag: 'path', d: 'M3 3v16a2 2 0 0 0 2 2h16' },
                { tag: 'path', d: 'M8 17v-3', role: 'draw-stagger', delay: 0 },
                { tag: 'path', d: 'M13 17V9', role: 'draw-stagger', delay: 100 },
                { tag: 'path', d: 'M18 17V5', role: 'draw-stagger', delay: 200 },
            ],
        },
        'message-circle': {
            parts: [{ tag: 'path', d: 'M7.9 20A9 9 0 1 0 4 16.1L2 22Z' }],
            svg: {
                animate: [
                    { transform: 'scale(1) rotate(0deg)' },
                    { transform: 'scale(1.05) rotate(-7deg)' },
                    { transform: 'scale(1.05) rotate(7deg)' },
                    { transform: 'scale(1.05) rotate(0deg)' },
                ],
                timing: { duration: 500, easing: 'ease-in-out' },
            },
        },
        'audio-lines': {
            parts: [
                { tag: 'path', d: 'M2 10v3' },
                {
                    tag: 'path',
                    d: 'M6 6v11',
                    role: 'morph',
                    frames: ['M6 6v11', 'M6 10v3', 'M6 6v11'],
                    duration: 1500,
                    infinite: true,
                },
                {
                    tag: 'path',
                    d: 'M10 3v18',
                    role: 'morph',
                    frames: ['M10 3v18', 'M10 9v5', 'M10 3v18'],
                    duration: 1000,
                    infinite: true,
                },
                {
                    tag: 'path',
                    d: 'M14 8v7',
                    role: 'morph',
                    frames: ['M14 8v7', 'M14 6v11', 'M14 8v7'],
                    duration: 800,
                    infinite: true,
                },
                {
                    tag: 'path',
                    d: 'M18 5v13',
                    role: 'morph',
                    frames: ['M18 5v13', 'M18 7v9', 'M18 5v13'],
                    duration: 1500,
                    infinite: true,
                },
                { tag: 'path', d: 'M22 10v3' },
            ],
        },
        archive: {
            parts: [
                {
                    tag: 'rect',
                    attrs: { x: '2', y: '3', width: '20', height: '5', rx: '1' },
                    role: 'lift',
                },
                {
                    tag: 'path',
                    d: 'M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8',
                    role: 'morph',
                    frames: [
                        'M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8',
                        'M4 11v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11',
                    ],
                    duration: 220,
                    fill: 'forwards',
                },
                {
                    tag: 'path',
                    d: 'M10 12h4',
                    role: 'morph',
                    frames: ['M10 12h4', 'M10 15h4'],
                    duration: 220,
                    fill: 'forwards',
                },
            ],
        },
        users: {
            parts: [
                { tag: 'path', d: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2' },
                { tag: 'circle', attrs: { cx: '9', cy: '7', r: '4' } },
                {
                    tag: 'path',
                    d: 'M22 21v-2a4 4 0 0 0-3-3.87',
                    role: 'slide-in',
                },
                {
                    tag: 'path',
                    d: 'M16 3.13a4 4 0 0 1 0 7.75',
                    role: 'slide-in',
                },
            ],
        },
        search: {
            parts: [
                { tag: 'circle', attrs: { cx: '11', cy: '11', r: '8' } },
                { tag: 'path', d: 'm21 21-4.3-4.3' },
            ],
            svg: {
                animate: [
                    { transform: 'translate(0px, 0px)' },
                    { transform: 'translate(0px, -4px)' },
                    { transform: 'translate(-3px, 0px)' },
                    { transform: 'translate(0px, 0px)' },
                ],
                timing: { duration: 500, easing: 'ease-in-out' },
            },
        },
        moon: {
            parts: [{ tag: 'path', d: 'M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z' }],
            svg: {
                animate: [
                    { transform: 'rotate(0deg)' },
                    { transform: 'rotate(-10deg)' },
                    { transform: 'rotate(10deg)' },
                    { transform: 'rotate(-5deg)' },
                    { transform: 'rotate(5deg)' },
                    { transform: 'rotate(0deg)' },
                ],
                timing: { duration: 600, easing: 'ease-in-out' },
            },
        },
        sun: {
            parts: (function () {
                var parts = [{ tag: 'circle', attrs: { cx: '12', cy: '12', r: '4' } }];
                for (var i = 0; i < SUN_RAYS.length; i++) {
                    parts.push({
                        tag: 'path',
                        d: SUN_RAYS[i],
                        role: 'fade-in',
                        delay: (i + 1) * 100,
                    });
                }
                return parts;
            })(),
        },
        heart: {
            parts: [
                {
                    tag: 'path',
                    d: 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z',
                },
            ],
            svg: {
                animate: [
                    { transform: 'scale(1)' },
                    { transform: 'scale(1.08)' },
                    { transform: 'scale(1)' },
                ],
                timing: { duration: 450, easing: 'ease-in-out' },
            },
        },
        play: {
            parts: [
                {
                    tag: 'polygon',
                    attrs: { points: '6 3 20 12 6 21 6 3' },
                    role: 'nudge',
                },
            ],
        },
        pause: {
            parts: [
                {
                    tag: 'rect',
                    attrs: { x: '6', y: '4', width: '4', height: '16', rx: '1' },
                    role: 'bounce-y',
                    frames: [0, 2, 0, 0],
                },
                {
                    tag: 'rect',
                    attrs: { x: '14', y: '4', width: '4', height: '16', rx: '1' },
                    role: 'bounce-y',
                    frames: [0, 0, 2, 0],
                },
            ],
        },
        send: {
            parts: [
                {
                    tag: 'g',
                    role: 'fly',
                    children: [
                        {
                            tag: 'path',
                            d: 'M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z',
                        },
                        { tag: 'path', d: 'm21.854 2.147-10.94 10.939' },
                    ],
                },
                {
                    tag: 'path',
                    d: 'M -3 28 C -0.5 26.8 1.6 24.6 3.3 22 C 4.8 19.7 5.2 17.6 4.2 16.1 C 3.2 14.7 1.4 14.5 0.3 15.8 C -0.9 17.2 -0.6 19.4 1.2 20.4 C 3.4 21.5 6.4 19.4 9 15.8',
                    role: 'trail',
                },
            ],
        },
        'map-pin': {
            parts: [
                {
                    tag: 'path',
                    d: 'M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0',
                },
                {
                    tag: 'circle',
                    attrs: { cx: '12', cy: '10', r: '3' },
                    role: 'fade-in',
                    delay: 300,
                },
            ],
            svg: {
                animate: [
                    { transform: 'translateY(0px)' },
                    { transform: 'translateY(-5px)' },
                    { transform: 'translateY(-3px)' },
                ],
                timing: {
                    duration: 500,
                    easing: 'ease-out',
                    fill: 'forwards',
                },
                reset: [{ transform: 'translateY(0px)' }],
            },
        },
        lock: {
            parts: [
                {
                    tag: 'rect',
                    attrs: {
                        x: '3',
                        y: '11',
                        width: '18',
                        height: '11',
                        rx: '2',
                        ry: '2',
                    },
                },
                {
                    tag: 'path',
                    d: 'M7 11V7a5 5 0 0 1 10 0v4',
                    role: 'draw-partial',
                },
            ],
            svg: {
                animate: [
                    { transform: 'rotate(0deg) scale(1)' },
                    { transform: 'rotate(-3deg) scale(0.95)' },
                    { transform: 'rotate(1deg) scale(1.05)' },
                    { transform: 'rotate(-2deg) scale(0.98)' },
                    { transform: 'rotate(0deg) scale(1)' },
                ],
                timing: { duration: 400, easing: 'ease-in-out' },
            },
        },
        key: {
            parts: [
                {
                    tag: 'path',
                    d: 'm15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4',
                },
                { tag: 'path', d: 'm21 2-9.6 9.6' },
                { tag: 'circle', attrs: { cx: '7.5', cy: '15.5', r: '5.5' } },
            ],
            svg: {
                animate: [
                    { transform: 'rotate(0deg)' },
                    { transform: 'rotate(-3deg)' },
                    { transform: 'rotate(-33deg)' },
                    { transform: 'rotate(-25deg)' },
                    { transform: 'rotate(-28deg)' },
                ],
                timing: {
                    duration: 600,
                    easing: 'ease-in-out',
                    fill: 'forwards',
                },
                reset: [{ transform: 'rotate(0deg)' }],
            },
        },
        user: {
            parts: [
                {
                    tag: 'circle',
                    attrs: { cx: '12', cy: '8', r: '5' },
                    role: 'draw-scale',
                },
                {
                    tag: 'path',
                    d: 'M20 21a8 8 0 0 0-16 0',
                    role: 'draw',
                    delay: 200,
                    duration: 400,
                },
            ],
        },
        'file-text': {
            parts: [
                {
                    tag: 'path',
                    d: 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z',
                },
                { tag: 'path', d: 'M14 2v4a2 2 0 0 0 2 2h4' },
                { tag: 'path', d: 'M10 9H8', role: 'draw', delay: 300 },
                { tag: 'path', d: 'M16 13H8', role: 'draw', delay: 500 },
                { tag: 'path', d: 'M16 17H8', role: 'draw', delay: 700 },
            ],
            svg: {
                animate: [
                    { transform: 'scale(1)' },
                    { transform: 'scale(1.05)' },
                    { transform: 'scale(1)' },
                ],
                timing: { duration: 350, easing: 'ease-out' },
            },
        },
        gauge: {
            parts: [
                {
                    tag: 'path',
                    d: 'm12 14 4-4',
                    role: 'nudge',
                },
                {
                    tag: 'path',
                    d: 'M3.34 19a10 10 0 1 1 17.32 0',
                },
            ],
        },
        menu: {
            parts: [
                { tag: 'path', d: 'M4 5h16', role: 'menu-line', index: 0 },
                { tag: 'path', d: 'M4 12h16', role: 'menu-line', index: 1 },
                { tag: 'path', d: 'M4 19h16', role: 'menu-line', index: 2 },
            ],
        },
        x: {
            parts: [
                { tag: 'path', d: 'M18 6 6 18' },
                { tag: 'path', d: 'm6 6 12 12' },
            ],
            svg: {
                animate: [
                    { transform: 'rotate(0deg) scale(1)' },
                    { transform: 'rotate(90deg) scale(1.05)' },
                    { transform: 'rotate(0deg) scale(1)' },
                ],
                timing: { duration: 350, easing: 'ease-out' },
            },
        },
    };

    function createEl(tag, attrs) {
        var el = document.createElementNS(NS, tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                el.setAttribute(k, attrs[k]);
            });
        }
        return el;
    }

    function appendPart(parent, part) {
        if (part.tag === 'g') {
            var g = createEl('g');
            (part.children || []).forEach(function (child) {
                appendPart(g, child);
            });
            parent.appendChild(g);
            return g;
        }
        var attrs = Object.assign({}, part.attrs || {});
        if (part.d) attrs.d = part.d;
        var node = createEl(part.tag, attrs);
        parent.appendChild(node);
        return node;
    }

    function cancelAnims(host) {
        var list = host._lnAnims || [];
        list.forEach(function (a) {
            try {
                a.cancel();
            } catch (e) {}
        });
        host._lnAnims = [];
        var nodes = host.querySelectorAll('[data-ln-part]');
        nodes.forEach(function (node) {
            node.style.transform = '';
            node.style.opacity = '';
            node.style.strokeDasharray = '';
            node.style.strokeDashoffset = '';
            if (node._lnBaseD) node.setAttribute('d', node._lnBaseD);
        });
        var svg = host.querySelector('svg');
        if (svg) svg.style.transform = '';
    }

    function track(host, anim) {
        if (!anim) return;
        host._lnAnims = host._lnAnims || [];
        host._lnAnims.push(anim);
    }

    function drawPath(path, duration, delay) {
        var len = 0;
        try {
            len = path.getTotalLength();
        } catch (e) {
            return null;
        }
        if (!len) return null;
        path.style.strokeDasharray = String(len);
        path.style.strokeDashoffset = String(len);
        return path.animate(
            [{ strokeDashoffset: len, opacity: 0 }, { strokeDashoffset: 0, opacity: 1 }],
            {
                duration: duration || 400,
                delay: delay || 0,
                easing: 'ease-out',
                fill: 'forwards',
            }
        );
    }

    function play(host) {
        if (!host || !host._lnDef || reducedMotion) return;
        cancelAnims(host);
        var def = host._lnDef;
        var svg = host.querySelector('svg');
        if (!svg) return;

        if (def.svg && def.svg.animate) {
            track(
                host,
                svg.animate(def.svg.animate, Object.assign({ fill: 'none' }, def.svg.timing || {}))
            );
        }

        var partNodes = host.querySelectorAll('[data-ln-part]');
        partNodes.forEach(function (node) {
            var role = node.getAttribute('data-ln-part');
            var delay = parseInt(node.getAttribute('data-ln-delay') || '0', 10) || 0;
            var duration = parseInt(node.getAttribute('data-ln-duration') || '0', 10) || 0;

            if (role === 'draw' || role === 'draw-stagger') {
                track(host, drawPath(node, duration || (role === 'draw-stagger' ? 300 : 400), delay));
                return;
            }
            if (role === 'draw-partial') {
                var plen = 0;
                try {
                    plen = node.getTotalLength();
                } catch (e) {
                    return;
                }
                node.style.strokeDasharray = String(plen);
                track(
                    host,
                    node.animate(
                        [{ strokeDashoffset: 0 }, { strokeDashoffset: plen * 0.3 }],
                        { duration: 300, easing: 'ease-in-out', fill: 'forwards' }
                    )
                );
                return;
            }
            if (role === 'draw-scale') {
                track(host, drawPath(node, 400, delay));
                track(
                    host,
                    node.animate(
                        [{ transform: 'scale(0.5)' }, { transform: 'scale(1)' }],
                        { duration: 400, delay: delay, easing: 'ease-out', fill: 'forwards' }
                    )
                );
                return;
            }
            if (role === 'fade-in') {
                track(
                    host,
                    node.animate([{ opacity: 0 }, { opacity: 1 }], {
                        duration: 300,
                        delay: delay,
                        easing: 'ease-out',
                        fill: 'forwards',
                    })
                );
                return;
            }
            if (role === 'lift') {
                track(
                    host,
                    node.animate(
                        [{ transform: 'translateY(0px)' }, { transform: 'translateY(-1.5px)' }],
                        { duration: 200, easing: 'ease-out', fill: 'forwards' }
                    )
                );
                return;
            }
            if (role === 'slide-in') {
                track(
                    host,
                    node.animate(
                        [{ transform: 'translateX(-6px)' }, { transform: 'translateX(0px)' }],
                        {
                            duration: 450,
                            delay: 100,
                            easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
                            fill: 'forwards',
                        }
                    )
                );
                return;
            }
            if (role === 'morph') {
                var frames = (node.getAttribute('data-ln-frames') || '').split('|').filter(Boolean);
                if (frames.length < 2) return;
                var infinite = node.getAttribute('data-ln-infinite') === '1';
                // WAAPI 要求 d 使用 CSS path('...') 语法,裸路径字符串会报 Invalid keyframe
                var keyframes = frames.map(function (d) {
                    return { d: "path('" + String(d).replace(/'/g, '') + "')" };
                });
                var anim;
                try {
                    anim = node.animate(keyframes, {
                        duration: duration || 1000,
                        easing: 'ease-in-out',
                        iterations: infinite ? Infinity : 1,
                        fill: node.getAttribute('data-ln-fill') || (infinite ? 'none' : 'forwards'),
                    });
                } catch (err) {
                    if (!infinite) {
                        node.setAttribute('d', frames[frames.length - 1]);
                    }
                    return;
                }
                if (!anim) {
                    if (!infinite) node.setAttribute('d', frames[frames.length - 1]);
                    return;
                }
                track(host, anim);
                return;
            }
            if (role === 'nudge') {
                track(
                    host,
                    node.animate(
                        [
                            { transform: 'translateX(0px) rotate(0deg)' },
                            { transform: 'translateX(-1px) rotate(-10deg)' },
                            { transform: 'translateX(2px) rotate(0deg)' },
                            { transform: 'translateX(0px) rotate(0deg)' },
                        ],
                        { duration: 500, easing: 'ease-in-out' }
                    )
                );
                return;
            }
            if (role === 'bounce-y') {
                var ys = (node.getAttribute('data-ln-yframes') || '0,2,0,0')
                    .split(',')
                    .map(function (v) {
                        return parseFloat(v) || 0;
                    });
                track(
                    host,
                    node.animate(
                        ys.map(function (y) {
                            return { transform: 'translateY(' + y + 'px)' };
                        }),
                        { duration: 500, easing: 'ease-in-out' }
                    )
                );
                return;
            }
            if (role === 'fly') {
                track(
                    host,
                    node.animate(
                        [
                            { transform: 'translate(0px, 0px) scale(1)' },
                            { transform: 'translate(3px, -3px) scale(0.8)' },
                        ],
                        { duration: 400, easing: 'ease-out', fill: 'forwards' }
                    )
                );
                return;
            }
            if (role === 'trail') {
                track(host, drawPath(node, 550, 100));
                track(
                    host,
                    node.animate(
                        [
                            { transform: 'translate(-3px, 3px)', opacity: 0 },
                            { transform: 'translate(0px, 0px)', opacity: 1 },
                        ],
                        { duration: 550, delay: 100, easing: 'ease-out', fill: 'forwards' }
                    )
                );
                return;
            }
            if (role === 'menu-line') {
                var idx = parseInt(node.getAttribute('data-ln-index') || '0', 10) || 0;
                track(
                    host,
                    node.animate(
                        [
                            { transform: 'translateX(0px)' },
                            { transform: 'translateX(' + (idx === 1 ? 3 : -2) + 'px)' },
                            { transform: 'translateX(0px)' },
                        ],
                        { duration: 350, delay: idx * 40, easing: 'ease-in-out' }
                    )
                );
            }
        });
    }

    function reset(host) {
        if (!host || !host._lnDef) return;
        cancelAnims(host);
        var def = host._lnDef;
        var svg = host.querySelector('svg');
        if (svg && def.svg && def.svg.reset) {
            track(
                host,
                svg.animate(def.svg.reset, { duration: 200, easing: 'ease-out', fill: 'forwards' })
            );
        }
        // restore morph base paths
        host.querySelectorAll('[data-ln-part="morph"]').forEach(function (node) {
            if (node._lnBaseD) node.setAttribute('d', node._lnBaseD);
        });
        host.querySelectorAll('[data-ln-part="lift"]').forEach(function (node) {
            track(
                host,
                node.animate(
                    [{ transform: 'translateY(-1.5px)' }, { transform: 'translateY(0px)' }],
                    { duration: 180, easing: 'ease-out', fill: 'forwards' }
                )
            );
        });
        host.querySelectorAll('[data-ln-part="fly"]').forEach(function (node) {
            track(
                host,
                node.animate(
                    [
                        { transform: 'translate(3px, -3px) scale(0.8)' },
                        { transform: 'translate(0px, 0px) scale(1)' },
                    ],
                    { duration: 200, easing: 'ease-out', fill: 'forwards' }
                )
            );
        });
    }

    function buildSvg(def) {
        var svg = createEl('svg', {
            viewBox: '0 0 24 24',
            fill: 'none',
            xmlns: NS,
            'aria-hidden': 'true',
        });
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');

        (def.parts || []).forEach(function (part) {
            var node = appendPart(svg, part);
            if (!node) return;
            if (part.role) {
                node.setAttribute('data-ln-part', part.role);
                if (part.delay) node.setAttribute('data-ln-delay', String(part.delay));
                if (part.duration) node.setAttribute('data-ln-duration', String(part.duration));
                if (part.infinite) node.setAttribute('data-ln-infinite', '1');
                if (part.fill) node.setAttribute('data-ln-fill', part.fill);
                if (part.index != null) node.setAttribute('data-ln-index', String(part.index));
                if (part.frames) {
                    node.setAttribute('data-ln-frames', part.frames.join('|'));
                    node._lnBaseD = part.d || (part.frames[0] || '');
                }
                if (part.role === 'bounce-y' && part.frames) {
                    node.setAttribute('data-ln-yframes', part.frames.join(','));
                }
                if (part.role === 'morph' && part.d) {
                    node._lnBaseD = part.d;
                }
            }
            if (part.tag === 'g' && part.role) {
                // children already appended
            }
        });
        return svg;
    }

    function bindHost(host) {
        if (host._lnBound) return;
        host._lnBound = true;
        var trigger = host.getAttribute('data-ln-icon-trigger') || 'hover';
        var target = host.closest('a, button, label, [data-ln-icon-host]') || host;

        function onEnter() {
            play(host);
        }
        function onLeave() {
            reset(host);
        }

        if (trigger === 'hover' || trigger === 'both') {
            target.addEventListener('pointerenter', onEnter);
            target.addEventListener('pointerleave', onLeave);
            target.addEventListener('focusin', onEnter);
            target.addEventListener('focusout', onLeave);
        }
        if (trigger === 'click' || trigger === 'both') {
            target.addEventListener('click', function () {
                play(host);
            });
        }
    }

    function mount(el) {
        if (!el || el.nodeType !== 1) return el;
        var name = el.getAttribute('data-ln-icon');
        if (!name || !ICONS[name]) return el;
        // Already built for this icon — just ensure listeners.
        if (el.getAttribute('data-ln-mounted') === name && el.querySelector('svg')) {
            el._lnDef = ICONS[name];
            el.classList.toggle('is-filled', el.getAttribute('data-ln-filled') === '1');
            bindHost(el);
            return el;
        }
        el.classList.add('ln-icon');
        el._lnDef = ICONS[name];
        el._lnBound = false;
        cancelAnims(el);
        el.innerHTML = '';
        el.appendChild(buildSvg(el._lnDef));
        el.setAttribute('data-ln-mounted', name);
        el.classList.toggle('is-filled', el.getAttribute('data-ln-filled') === '1');
        bindHost(el);
        return el;
    }

    function set(el, name, opts) {
        if (!el) return null;
        opts = opts || {};
        if (!ICONS[name]) return el;
        var filledChanged = false;
        if (opts.filled != null) {
            var nextFilled = opts.filled ? '1' : '0';
            filledChanged = el.getAttribute('data-ln-filled') !== nextFilled;
            el.setAttribute('data-ln-filled', nextFilled);
            el.classList.toggle('is-filled', !!opts.filled);
        }
        if (el.getAttribute('data-ln-icon') === name && el.getAttribute('data-ln-mounted') === name && el.querySelector('svg')) {
            if (filledChanged) {
                el.classList.toggle('is-filled', el.getAttribute('data-ln-filled') === '1');
            }
            return el;
        }
        el.setAttribute('data-ln-icon', name);
        el.removeAttribute('data-ln-mounted');
        el._lnBound = false;
        cancelAnims(el);
        return mount(el);
    }

    function hydrate(root) {
        root = root || document;
        root.querySelectorAll('[data-ln-icon]').forEach(mount);
    }

    var api = {
        icons: ICONS,
        mount: mount,
        set: set,
        play: play,
        reset: reset,
        hydrate: hydrate,
    };

    global.LnIcons = api;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            hydrate(document);
        });
    } else {
        hydrate(document);
    }

    // Re-hydrate dynamically inserted cards
    if (typeof MutationObserver === 'function') {
        var pending = null;
        var obs = new MutationObserver(function (mutations) {
            var need = false;
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                    need = true;
                    break;
                }
            }
            if (!need) return;
            if (pending) return;
            pending = requestAnimationFrame(function () {
                pending = null;
                hydrate(document);
            });
        });
        if (document.documentElement) {
            obs.observe(document.documentElement, { childList: true, subtree: true });
        }
    }
})(typeof window !== 'undefined' ? window : this);
