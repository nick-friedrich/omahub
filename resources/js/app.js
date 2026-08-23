import hljs from 'highlight.js/lib/core';

import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import dockerfile from 'highlight.js/lib/languages/dockerfile';
import ini from 'highlight.js/lib/languages/ini';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import lua from 'highlight.js/lib/languages/lua';
import markdown from 'highlight.js/lib/languages/markdown';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('css', css);
hljs.registerLanguage('dockerfile', dockerfile);
hljs.registerLanguage('ini', ini);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('lua', lua);
hljs.registerLanguage('markdown', markdown);
hljs.registerLanguage('php', php);
hljs.registerLanguage('python', python);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('yaml', yaml);

// Highlight code blocks in rendered plugin READMEs. This is a progressive
// enhancement: without JavaScript the blocks still render as plain prose code.
function highlightReadmes() {
    document.querySelectorAll('article.prose pre code:not(.hljs)').forEach((block) => {
        hljs.highlightElement(block);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', highlightReadmes);
} else {
    highlightReadmes();
}

// Copy-to-clipboard for `data-copy-command` buttons. Progressive enhancement:
// without JavaScript the command stays visible and selectable, and the button
// simply does nothing.
function setupCopyButtons() {
    document.querySelectorAll('button[data-copy-command]').forEach((button) => {
        button.addEventListener('click', async () => {
            const box = button.closest('.install-command');
            const code = box && box.querySelector('code');
            const text = (code && (code.textContent ?? ''))
                .replace(/^\s*\$\s+/, '')
                .trim();

            if (!text || !navigator.clipboard) {
                return;
            }

            try {
                await navigator.clipboard.writeText(text);

                const label = button.querySelector('[data-copy-label]');
                const icon = button.querySelector('[data-copy-icon]');
                const successIcon = button.querySelector('[data-copy-success-icon]');

                if (label) label.textContent = 'Copied';
                if (icon) icon.classList.add('hidden');
                if (successIcon) successIcon.classList.remove('hidden');

                setTimeout(() => {
                    if (label) label.textContent = 'Copy';
                    if (icon) icon.classList.remove('hidden');
                    if (successIcon) successIcon.classList.add('hidden');
                }, 2000);
            } catch {
                // Leave the button unchanged; the command is still selectable.
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCopyButtons);
} else {
    setupCopyButtons();
}

// Mobile navigation drawer. Progressive enhancement: on small screens the
// inline nav links are hidden (md:) and this drawer is opened via the
// hamburger button. Without JavaScript the drawer stays closed and the page
// remains fully usable.
function setupMobileNav() {
    const toggle = document.getElementById('nav-toggle');
    const closeBtn = document.getElementById('nav-close');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('nav-overlay');
    if (!toggle || !menu || !overlay) {
        return;
    }

    let isOpen = false;

    function openMenu() {
        isOpen = true;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation menu');
        overlay.classList.remove('hidden');
        menu.classList.remove('translate-x-full');
        document.body.classList.add('overflow-hidden');
        // Two frames so the browser registers the open (opacity-0) state
        // before we fade the backdrop in.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => overlay.classList.remove('opacity-0'));
        });
    }

    function closeMenu() {
        if (!isOpen) {
            return;
        }
        isOpen = false;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation menu');
        overlay.classList.add('opacity-0');
        menu.classList.add('translate-x-full');
        document.body.classList.remove('overflow-hidden');
        setTimeout(() => {
            if (!isOpen) {
                overlay.classList.add('hidden');
            }
        }, 200);
    }

    toggle.addEventListener('click', () => (isOpen ? closeMenu() : openMenu()));
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMenu);
    }
    overlay.addEventListener('click', closeMenu);
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        // Reset state if we cross into a viewport where the drawer is hidden.
        if (window.innerWidth >= 768) {
            closeMenu();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupMobileNav);
} else {
    setupMobileNav();
}

// A stale plugin is refreshed after the initial response. Poll its lightweight
// status endpoint and reload once so every imported field changes together.
function setupPluginRefreshStatus() {
    document.querySelectorAll('[data-plugin-refresh-status]').forEach((status) => {
        const url = status.dataset.refreshUrl;
        const indexedAt = status.dataset.indexedAt || null;
        const label = status.querySelector('[data-refresh-label]');
        let attempts = 0;

        if (!url || !label) {
            return;
        }

        const poll = async () => {
            attempts += 1;

            try {
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('Plugin refresh status request failed.');
                }

                const result = await response.json();

                if (result.indexed_at && result.indexed_at !== indexedAt) {
                    label.textContent = 'Updating page…';
                    window.location.reload();
                    return;
                }

                if (!result.refreshing) {
                    label.textContent = 'Could not check GitHub right now.';
                    return;
                }
            } catch {
                // Brief network errors are retried within the polling window.
            }

            if (attempts === 20) {
                label.textContent = 'GitHub check is taking longer than expected.';
            }

            window.setTimeout(poll, attempts >= 20 ? 5000 : 1500);
        };

        window.setTimeout(poll, 1000);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupPluginRefreshStatus);
} else {
    setupPluginRefreshStatus();
}
