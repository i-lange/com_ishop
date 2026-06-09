/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document, Joomla) {
	'use strict';

	const CartButtonsManager = {
		init() {
			document.addEventListener('click', this.handleButtonClick.bind(this));
		},

		handleButtonClick(e) {
			const btn = e.target.closest('[data-tocart]');
			if (!btn) return;

			if (btn.dataset.ishopPending === '1') {
				e.preventDefault();
				return;
			}

			this.processButtonClick(e, btn);
		},

		processButtonClick(e, btn) {
			const productId = Number(btn.dataset.tocart) || 0;
			if (productId <= 0) return;

			if (btn.classList.contains('active')) {
				if (e.target.classList.contains('btn_decrease')) {
					e.preventDefault();
					this.changeQuantity(productId, -1, btn);
				} else if (e.target.classList.contains('btn_increase')) {
					e.preventDefault();
					this.changeQuantity(productId, 1, btn);
				}
			} else {
				e.preventDefault();
				this.addToCart(productId, btn);
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

			const url = `/index.php?option=com_ishop&task=cart.${encodeURIComponent(task)}&format=json`;
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
						reject(new Error(`Cart ${task} request failed: HTTP status ${xhr.status || 0}`));
					},
				});
			});
		},

		parseResponse(responseText) {
			const response = JSON.parse(responseText || '{}');

			if (!response || response.success !== true) {
				throw new Error(response && response.message ? response.message : 'Cart request failed');
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

		addToCart(productId, btn) {
			this.setButtonPending(btn, true);

			this.sendRequest('add', {
				product_id: productId,
				quantity: 1,
			})
				.then(response => {
					this.updateCart(response.data?.count);

					const quantity = Number(response.data?.products?.[productId]?.count) || 1;
					this.transformToControlButton(btn, quantity);
					this.trackEcommerce('add_to_cart', productId, quantity);
				})
				.catch(err => {
					console.error('Cart add error:', err);
				})
				.finally(() => {
					this.setButtonPending(btn, false);
				});
		},

		transformToControlButton(btn, quantity) {
			if (btn.dataset.originalHtml === undefined) {
				btn.dataset.originalHtml = btn.innerHTML;
			}

			btn.innerHTML = `
                    <span class="btn_decrease">-</span>
                    <span class="btn_quantity">${quantity}</span>
                    <span class="btn_increase">+</span>
                `;

			btn.classList.add('active');
		},

		restoreOriginalButton(btn) {
			if (btn.dataset.originalHtml !== undefined) {
				btn.innerHTML = btn.dataset.originalHtml;
			}

			btn.classList.remove('active');
			delete btn.dataset.originalHtml;
		},

		changeQuantity(productId, delta, btn) {
			const quantityEl = btn.querySelector('.btn_quantity');
			const currentQuantity = Number(quantityEl?.textContent || 0);
			const task = delta === -1 && currentQuantity <= 1 ? 'remove' : 'change';
			const data = {
				product_id: productId,
			};

			if (task === 'change') {
				data.quantity = delta;
			}

			this.setButtonPending(btn, true);

			this.sendRequest(task, data)
				.then(response => {
					this.updateCart(response.data?.count);

					if (task === 'remove') {
						this.restoreOriginalButton(btn);
					} else {
						const newQuantity = Number(response.data?.products?.[productId]?.count) || 0;

						if (newQuantity > 0 && quantityEl) {
							quantityEl.textContent = newQuantity;
						} else {
							this.restoreOriginalButton(btn);
						}
					}

					if (delta > 0) {
						this.trackEcommerce('add_to_cart', productId, 1);
					} else {
						this.trackEcommerce('remove_from_cart', productId, 1);
					}
				})
				.catch(err => {
					console.error('Cart quantity error:', err);
				})
				.finally(() => {
					this.setButtonPending(btn, false);
				});
		},

		updateCart(count) {
			if (count === undefined || count === null) return;

			const normalizedCount = Math.max(0, Number.parseInt(count, 10) || 0);
			const carts = document.querySelectorAll('[data-ishop-cart]');
			if (carts) {
				carts.forEach(cart => {
					const counter = cart.querySelector('[data-ishop-cart-count]');
					const countText = cart.querySelector('[data-ishop-cart-count-text]');

					if (counter) {
						counter.textContent = String(normalizedCount);
					}

					if (countText) {
						countText.textContent = normalizedCount === 0
							? cart.dataset.ishopCartEmptyText || ''
							: String(normalizedCount);
					}

					cart.dataset.ishopCartEmpty = normalizedCount === 0 ? '1' : '0';
				});
			}
		},

		trackEcommerce(action, productId, quantity) {
			if (
				window.iTheme
				&& typeof window.iTheme.setEcommerce === 'function'
			) {
				window.iTheme.setEcommerce(action, productId, quantity);
			}
		}
	};

	CartButtonsManager.init();
})(window, document, window.Joomla);
