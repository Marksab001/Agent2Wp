(function () {
    'use strict';

    var cfg = window.agent2wpEasy || {};
    var wrap = document.querySelector('.agent2wp-easy-wrap');
    if (!wrap) {
        return;
    }

    var startForm = document.getElementById('agent2wp-easy-start-form');
    var startBtn = document.getElementById('agent2wp-easy-start-btn');
    var progress = document.getElementById('agent2wp-easy-progress');
    var copyBtn = document.getElementById('agent2wp-easy-copy-btn');
    var pasteArea = document.getElementById('agent2wp-easy-paste');
    var copyHint = document.getElementById('agent2wp-easy-copy-hint');
    var copyPwBtn = document.getElementById('agent2wp-easy-copy-pw');

    function copyText(text, onDone) {
        if (!text) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onDone).catch(function () {
                fallbackCopy(text, onDone);
            });
        } else {
            fallbackCopy(text, onDone);
        }
    }

    function fallbackCopy(text, onDone) {
        if (!pasteArea) {
            return;
        }
        pasteArea.value = text;
        pasteArea.hidden = false;
        pasteArea.select();
        try {
            document.execCommand('copy');
            if (onDone) {
                onDone();
            }
        } catch (e) {
            /* ignore */
        }
    }

    function flashButton(btn, label) {
        if (!btn) {
            return;
        }
        var original = btn.textContent;
        btn.textContent = label;
        btn.classList.add('agent2wp-easy-btn--done');
        setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('agent2wp-easy-btn--done');
        }, 2200);
    }

    function runProgressSteps(steps, onDone) {
        if (!progress) {
            onDone();
            return;
        }
        progress.hidden = false;
        var items = progress.querySelectorAll('.agent2wp-easy-progress__item');
        var i = 0;

        function tick() {
            if (i >= items.length) {
                onDone();
                return;
            }
            items[i].classList.add('is-active');
            setTimeout(function () {
                items[i].classList.remove('is-active');
                items[i].classList.add('is-done');
                i += 1;
                tick();
            }, 420);
        }
        tick();
    }

    if (startForm && cfg.ajaxUrl && cfg.nonce) {
        startForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (startBtn) {
                startBtn.disabled = true;
                startBtn.classList.add('is-loading');
            }

            var steps = (cfg.setupSteps || []).map(function (label) {
                return { label: label };
            });

            runProgressSteps(steps, function () {
                var body = new FormData();
                body.append('action', 'agent2wp_magic_setup');
                body.append('nonce', cfg.nonce);

                fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            window.location.href = data.data.redirect || cfg.readyUrl;
                            return;
                        }
                        var msg = (data.data && data.data.message) || cfg.errorLabel || 'Setup failed';
                        alert(msg);
                        if (startBtn) {
                            startBtn.disabled = false;
                            startBtn.classList.remove('is-loading');
                        }
                        if (progress) {
                            progress.hidden = true;
                        }
                    })
                    .catch(function () {
                        startForm.submit();
                    });
            });
        });
    }

    if (copyBtn && pasteArea) {
        copyBtn.addEventListener('click', function () {
            copyText(pasteArea.value, function () {
                if (copyHint) {
                    copyHint.hidden = false;
                }
                flashButton(copyBtn, cfg.copiedLabel || 'Copied!');
                wrap.classList.add('agent2wp-easy-wrap--copied');
            });
        });
    }

    if (copyPwBtn) {
        copyPwBtn.addEventListener('click', function () {
            var pw = copyPwBtn.getAttribute('data-password') || '';
            copyText(pw, function () {
                flashButton(copyPwBtn, cfg.copiedLabel || 'Copied!');
            });
        });
    }

    if (cfg.autoCopy && copyBtn) {
        setTimeout(function () {
            copyBtn.click();
        }, 500);
    }
})();
