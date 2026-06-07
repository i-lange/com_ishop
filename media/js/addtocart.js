/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document) {
	'use strict';

	const CartButtonsManager = {
		init() {
			document.addEventListener('click', this.handleButtonClick.bind(this));
		},

		handleButtonClick(e) {
			const btn = e.target.closest('[data-tocart]');
			if (!btn) return;

			this.processButtonClick(e, btn);
		},

		processButtonClick(e, btn) {
			const product_id = Number(btn.dataset.tocart);
			if (product_id <= 0) return;

			if (btn.classList.contains('active')) {
				if (e.target.classList.contains('btn_decrease')) {
					this.changeQuantity(product_id, -1, btn);
				} else if (e.target.classList.contains('btn_increase')) {
					this.changeQuantity(product_id, 1, btn);
				}
			} else {
				e.preventDefault();
				this.addToCart(product_id, btn);
			}
		},

		addToCart(product_id, btn) {
			const url = '/index.php?option=com_ishop&controller=cart&task=add';
			const request = new XMLHttpRequest();
			const params = new FormData();
			params.append('product_id', product_id);
			params.append('quantity', 1);

			request.open('POST', url, true);
			request.addEventListener('readystatechange', () => {
				if (request.readyState === XMLHttpRequest.DONE) {
					if (request.status === 200) {
						const response = JSON.parse(request.responseText);
						if (response.success === true) {
							try {
								this.updateCart(response.data.count);
								const quantity = response.data.products[product_id].count || 1;
								this.transformToControlButton(btn, quantity);
								this.trackEcommerce('add_to_cart', product_id, quantity);
							} catch (e) {
								console.log('Error [addToCart]');
							}
						}
					}
				}
			});
			request.send(params);
		},

		transformToControlButton(btn, quantity) {
			btn.dataset.originalHtml = btn.innerHTML;

			btn.innerHTML = `
                    <span class="btn_decrease">-</span>
                    <span class="btn_quantity">${quantity}</span>
                    <span class="btn_increase">+</span>
                `;

			btn.classList.add('active');
		},

		restoreOriginalButton(btn) {
			btn.innerHTML = btn.dataset.originalHtml;
			btn.classList.remove('active');
			delete btn.dataset.originalHtml;
		},

		changeQuantity(product_id, delta, btn) {
			const task = delta === -1 && btn.querySelector('.btn_quantity').textContent === '1'
				? 'remove'
				: 'change';

			const url = `/index.php?option=com_ishop&controller=cart&task=${task}`;
			const request = new XMLHttpRequest();
			const params = new FormData();
			params.append('product_id', product_id);

			if (task === 'change') {
				params.append('quantity', delta);
			}

			request.open('POST', url, true);
			request.addEventListener('readystatechange', () => {
				if (request.readyState === XMLHttpRequest.DONE && request.status === 200) {
					const response = JSON.parse(request.responseText);
					if (response.success) {
						this.updateCart(response.data.count);

						if (task === 'remove') {
							this.restoreOriginalButton(btn);
						} else {
							const newQuantity = response?.data?.products?.[product_id]?.count ?? 0;
							if (newQuantity > 0) {
								btn.querySelector('.btn_quantity').textContent = newQuantity;
							} else {
								this.restoreOriginalButton(btn);
							}
						}

						if (delta > 0) {
							this.trackEcommerce('add_to_cart', product_id, 1);
						} else {
							this.trackEcommerce('remove_from_cart', product_id, 1);
						}
					}
				}
			});
			request.send(params);
		},

		updateCart(count) {
			const carts = document.querySelectorAll('[data-ishop-cart]');
			if (carts) {
				carts.forEach(cart => {
					const counter = cart.querySelector('small');
					if (counter) {
						counter.textContent = count;
					}
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
})(window, document);
