/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document, Joomla) {
    'use strict';

    const WishlistButtonsManager = {
        init() {
            document.addEventListener('click', this.handleButtonClick.bind(this));
        },

        handleButtonClick(e) {
            const btn = e.target.closest('[data-towishlist]');
            if (!btn) return;

            e.preventDefault();

            if (btn.dataset.ishopPending === '1') {
                return;
            }

            this.processButtonClick(btn);
        },

        processButtonClick(btn) {
            const productId = Number(btn.dataset.towishlist) || 0;

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

            const url = `/index.php?option=com_ishop&controller=wishlist&task=${encodeURIComponent(task)}`;
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
                        reject(new Error(`Wishlist ${task} request failed: HTTP status ${xhr.status || 0}`));
                    },
                });
            });
        },

        parseResponse(responseText) {
            const response = JSON.parse(responseText || '{}');

            if (!response || response.success !== true) {
                throw new Error(response && response.message ? response.message : 'Wishlist request failed');
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
                    this.trackGoal('TO_WISHLIST', 'TO_WISHLIST');
                })
                .catch(err => {
                    console.error('Wishlist add error:', err);
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
                    this.trackGoal('REMOVE_FROM_WISHLIST', 'REMOVE_FROM_WISHLIST');
                })
                .catch(err => {
                    console.error('Wishlist remove error:', err);
                })
                .finally(() => {
                    this.setButtonPending(btn, false);
                });
        },

        clear(btn) {
            this.setButtonPending(btn, true);

            this.sendRequest('clear')
                .then(() => {
                    this.updateButtons();
                    this.updateModule(0);
                    btn.classList.remove('active');
                    this.trackGoal('CLEAR_WISHLIST', 'CLEAR_WISHLIST');
                })
                .catch(err => {
                    console.error('Wishlist clear error:', err);
                })
                .finally(() => {
                    this.setButtonPending(btn, false);
                });
        },

        updateModule(count) {
            if (count === undefined || count === null) return;

            const modules = document.querySelectorAll('[data-ishop-wishlist]');
            if (!modules.length) return;

            modules.forEach(module => {
                const counterEl = module.querySelector('small');

                if (counterEl) {
                    counterEl.textContent = count;
                }
            });
        },

        updateButtons() {
            const buttons = document.querySelectorAll('[data-towishlist]');
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

    WishlistButtonsManager.init();
})(window, document, window.Joomla);
