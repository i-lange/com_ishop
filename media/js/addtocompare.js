/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document) {
    'use strict';

    const CompareButtonsManager = {
        init() {
            document.addEventListener('click', this.handleButtonClick.bind(this));
        },

        handleButtonClick(e) {
            const btn = e.target.closest('[data-tocompare]');
            if (!btn) return;

            e.preventDefault();
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

        sendRequest(task, data = {}) {
            const url = `/index.php?option=com_ishop&controller=compare&task=${encodeURIComponent(task)}`;
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
                        this.trackGoal('TO_COMPARE', 'TO_COMPARE');
                    } catch (err) {
                        console.log('Error [addToCompare]');
                    }
                })
                .catch(err => {
                    console.error('Compare add error:', err);
                });
        },

        remove(productId, btn) {
            this.sendRequest('remove', { product_id: productId })
                .then(response => {
                    if (!response || response.success !== true) return;
                    try {
                        this.updateModule(response.data.count);
                        btn.classList.remove('active');
                        this.trackGoal('REMOVE_FROM_COMPARE', 'REMOVE_FROM_COMPARE');
                    } catch (err) {
                        console.log('Error goal [REMOVE_FROM_COMPARE]');
                    }
                })
                .catch(err => {
                    console.error('Compare remove error:', err);
                });
        },

        clear(btn) {
            this.sendRequest('remove', { product_id: 0 })
                .then(response => {
                    if (!response || response.success !== true) return;
                    try {
                        this.updateButtons();
                        this.updateModule(0);
                        btn.classList.remove('active');
                        this.trackGoal('CLEAR_COMPARE', 'CLEAR_COMPARE');
                    } catch (err) {
                        console.log('Error goal [CLEAR_COMPARE]');
                    }
                })
                .catch(err => {
                    console.error('Compare clear error:', err);
                });
        },

        updateModule(count) {
            const modules = document.querySelectorAll('[data-ishop-compare]');
            if (!modules.length) return;

            modules.forEach(module => {
                const counterEl = module.querySelector('small');

                if (counterEl) {
                    counterEl.textContent = count;
                }

                if (count === 0) {
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
})(window, document);
