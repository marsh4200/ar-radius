/* AR Radius - main JS */
(function () {
    'use strict';

    // ---------- CSRF token ----------
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    window.AR_CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // ---------- Sidebar toggle (mobile) ----------
    const toggle = document.getElementById('arToggle');
    const sidebar = document.getElementById('arSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768
                && sidebar.classList.contains('open')
                && !sidebar.contains(e.target)
                && e.target !== toggle) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ---------- API helper ----------
    window.ARapi = async function (path, options = {}) {
        const opts = Object.assign({
            method: 'GET',
            headers: {},
            credentials: 'same-origin'
        }, options);
        opts.headers['X-CSRF-Token'] = window.AR_CSRF;
        if (opts.body && typeof opts.body !== 'string') {
            opts.body = JSON.stringify(opts.body);
            opts.headers['Content-Type'] = 'application/json';
        }
        const res = await fetch(path, opts);
        const ct = res.headers.get('content-type') || '';
        const data = ct.includes('application/json') ? await res.json() : await res.text();
        if (!res.ok) {
            const msg = (data && data.error) ? data.error : ('HTTP ' + res.status);
            throw new Error(msg);
        }
        return data;
    };

    // ---------- Modal ----------
    window.ARmodal = {
        open(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('show');
        },
        close(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('show');
        }
    };
    document.querySelectorAll('[data-modal-open]').forEach(b => {
        b.addEventListener('click', () => ARmodal.open(b.dataset.modalOpen));
    });
    document.querySelectorAll('[data-modal-close]').forEach(b => {
        b.addEventListener('click', () => {
            const m = b.closest('.ar-modal-backdrop');
            if (m) m.classList.remove('show');
        });
    });
    document.querySelectorAll('.ar-modal-backdrop').forEach(b => {
        b.addEventListener('click', (e) => {
            if (e.target === b) b.classList.remove('show');
        });
    });

    // ---------- Toast / inline message ----------
    window.ARtoast = function (msg, type) {
        const t = document.createElement('div');
        t.className = 'ar-alert ' + (type === 'success' ? 'ar-alert-success' :
                                     type === 'info' ? 'ar-alert-info' : '');
        t.style.cssText = 'position:fixed;top:80px;right:20px;z-index:2000;max-width:340px;box-shadow:0 4px 20px rgba(0,0,0,.3);';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 4000);
    };

    // ---------- Confirm dialog ----------
    window.ARconfirm = function (msg) {
        return window.confirm(msg);
    };
})();
