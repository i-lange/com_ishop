/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document) {
    'use strict';

    const WishlistButtonsManager = {
        init() {
            document.addEventListener('click', this.handleButtonClick.bind(this));
        },

        handleButtonClick(e) {
            const btn = e.target.closest('[data-towishlist]');
            if (!btn) return;

            e.preventDefault();
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

        sendRequest(task, data = {}) {
            const url = `/index.php?option=com_ishop&controller=wishlist&task=${encodeURIComponent(task)}`;
            const formData = new FormData();

            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
            });

            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                xhr.open('POST', url, true);
                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== XMLHttpRequest.DONE) return;
                    if (xhr.status !== 200) {
                        return reject(new Error(`HTTP status ${xhr.status}`));
                    }
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (err) {
                        reject(err);
                    }
                };

                xhr.onerror = () => reject(new Error('Network error'));

                xhr.send(formData);
            });
        },

        add(productId, btn) {
            this.sendRequest('add', { product_id: productId })
                .then(response => {
                    if (!response || response.success !== true) return;
                    try {
                        this.updateModule(response.data.count);
                        btn.classList.add('active');
                        this.trackGoal('TO_WISHLIST', 'TO_WISHLIST');
                    } catch (err) {
                        console.log('Error [addToWishlist]');
                    }
                })
                .catch(err => {
                    console.error('Wishlist add error:', err);
                });
        },

        remove(productId, btn) {
            this.sendRequest('remove', { product_id: productId })
                .then(response => {
                    if (!response || response.success !== true) return;
                    try {
                        this.updateModule(response.data.count);
                        btn.classList.remove('active');
                        this.trackGoal('REMOVE_FROM_WISHLIST', 'REMOVE_FROM_WISHLIST');
                    } catch (err) {
                        console.log('Error [removeFromWishlist]');
                    }
                })
                .catch(err => {
                    console.error('Wishlist remove error:', err);
                });
        },

        clear(btn) {
            this.sendRequest('clear')
                .then(response => {
                    if (!response || response.success !== true) return;
                    try {
                        this.updateButtons();
                        this.updateModule(0);
                        btn.classList.remove('active');
                        this.trackGoal('CLEAR_WISHLIST', 'CLEAR_WISHLIST');
                    } catch (err) {
                        console.log('Error goal [CLEAR_WISHLIST]');
                    }
                })
                .catch(err => {
                    console.error('Wishlist clear error:', err);
                });
        },

        updateModule(count) {
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
})(window, document);
