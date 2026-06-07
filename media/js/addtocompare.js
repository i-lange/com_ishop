/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document, Joomla) {
    'use strict';

    const CompareButtonsManager = {
        init() {
            document.addEventListener('click', this.handleButtonClick.bind(this));
        },

        handleButtonClick(e) {
            const btn = e.target.closest('[data-tocompare]');
            if (!btn) return;

            e.preventDefault();

            if (btn.dataset.ishopPending === '1') {
                return;
            }

            this.processButtonClick(btn);
        },

        processButtonClick(btn) {
            const productId = Number(btn.dataset.tocompare) || 0;

            if (productId <= 0) {
                this.clear(btn);
            } else if (btn.classList.contains('active')) {
                this.remove(productId, btn);
            } else {
                this.add(productId, btn);
            }
        },

        getCsrfToken() {
            if (!Joomla || typeof Joomla.getOptions !== 'function') {
                return '';
            }

            return Joomla.getOptions('csrf.token', '');
        },

        sendRequest(task, data = {}) {
            if (!Joomla || typeof Joomla.request !== 'function') {
                return Promise.reject(new Error('Joomla.request is not available'));
            }

            const url = `/index.php?option=com_ishop&controller=compare&task=${encodeURIComponent(task)}`;
            const formData = new FormData();
            const csrfToken = this.getCsrfToken();

            Object.entries(data).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    formData.append(key, value);
                }
            });

            if (csrfToken) {
                formData.append(csrfToken, '1');
            }

            return new Promise((resolve, reject) => {
                Joomla.request({
                    url,
                    method: 'POST',
                    data: formData,
                    onSuccess: responseText => {
                        try {
                            resolve(this.parseResponse(responseText));
                        } catch (err) {
                            reject(err);
                        }
                    },
                    onError: xhr => {
                        reject(new Error(`Compare ${task} request failed: HTTP status ${xhr.status || 0}`));
                    },
                });
            });
        },

        parseResponse(responseText) {
            const response = JSON.parse(responseText || '{}');

            if (!response || response.success !== true) {
                throw new Error(response && response.message ? response.message : 'Compare request failed');
            }

            return response;
        },

        setButtonPending(btn, isPending) {
            if (isPending) {
                btn.dataset.ishopPending = '1';
                btn.setAttribute('aria-busy', 'true');
            } else {
                delete btn.dataset.ishopPending;
                btn.removeAttribute('aria-busy');
            }

            if ('disabled' in btn) {
                btn.disabled = isPending;
            }
        },

        add(productId, btn) {
            this.setButtonPending(btn, true);

            this.sendRequest('add', { product_id: productId })
                .then(response => {
                    this.updateModule(response.data?.count);
                    btn.classList.add('active');
                    this.trackGoal('TO_COMPARE', 'TO_COMPARE');
                })
                .catch(err => {
                    console.error('Compare add error:', err);
                })
                .finally(() => {
                    this.setButtonPending(btn, false);
                });
        },

        remove(productId, btn) {
            this.setButtonPending(btn, true);

            this.sendRequest('remove', { product_id: productId })
                .then(response => {
                    this.updateModule(response.data?.count);
                    btn.classList.remove('active');
                    this.trackGoal('REMOVE_FROM_COMPARE', 'REMOVE_FROM_COMPARE');
                })
                .catch(err => {
                    console.error('Compare remove error:', err);
                })
                .finally(() => {
                    this.setButtonPending(btn, false);
                });
        },

        clear(btn) {
            this.setButtonPending(btn, true);

            this.sendRequest('remove', { product_id: 0 })
                .then(() => {
                    this.updateButtons();
                    this.updateModule(0);
                    btn.classList.remove('active');
                    this.trackGoal('CLEAR_COMPARE', 'CLEAR_COMPARE');
                })
                .catch(err => {
                    console.error('Compare clear error:', err);
                })
                .finally(() => {
                    this.setButtonPending(btn, false);
                });
        },

        updateModule(count) {
            if (count === undefined || count === null) return;

            const modules = document.querySelectorAll('[data-ishop-compare]');
            if (!modules.length) return;

            modules.forEach(module => {
                const counterEl = module.querySelector('small');

                if (counterEl) {
                    counterEl.textContent = count;
                }

                if (Number(count) === 0) {
                    module.classList.remove('active');
                } else if (!module.classList.contains('active')) {
                    module.classList.add('active');
                }
            });
        },

        updateButtons() {
            const buttons = document.querySelectorAll('[data-tocompare]');
            if (!buttons.length) return;

            buttons.forEach(btn => btn.classList.remove('active'));
        },

        trackGoal(ymGoal, gtagGoal) {
            if (
                window.iTheme
                && typeof window.iTheme.setGoal === 'function'
            ) {
                window.iTheme.setGoal(ymGoal, gtagGoal);
            }
        }
    };

    CompareButtonsManager.init();
})(window, document, window.Joomla);
