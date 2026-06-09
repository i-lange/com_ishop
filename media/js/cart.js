/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document, Joomla) {
	'use strict';

	const CartPageManager = {
		init(root = document) {
			this.form = root.getElementById ? root.getElementById('cart-submit') : root.querySelector('#cart-submit');

			if (!this.form || this.form.dataset.ishopCartPageBound === '1') {
				return;
			}

			this.form.dataset.ishopCartPageBound = '1';
			this.cartTotal = this.form.querySelector('[data-cart-total]');
			this.cartTotalDiscount = this.form.querySelector('[data-cart-total-discount]');
			this.cartSummary = this.form.querySelector('[data-cart-summary]');
			this.reloadRequestId = 0;

			this.form.addEventListener('click', this.handleButtonClick.bind(this));
			this.form.addEventListener('change', this.handleCheckboxChange.bind(this));
		},

		handleButtonClick(event) {
			const btn = event.target.closest('[data-cart-button]');

			if (!btn) {
				return;
			}

			event.preventDefault();

			const product = btn.closest('[data-product-incart-id]');

			if (!product || product.dataset.ishopPending === '1') {
				return;
			}

			this.processButtonClick(btn, product);
		},

		handleCheckboxChange(event) {
			if (event.target.matches('input[name="products[]"]')) {
				this.reloadSelectedProducts();
			}
		},

		processButtonClick(btn, product) {
			const productId = Number(product.dataset.productIncartId) || 0;

			if (productId <= 0) {
				return;
			}

			const type = btn.dataset.cartButton;

			if (type === 'plus') {
				this.changeQuantity(productId, 1, product);
			} else if (type === 'minus' && this.getCount(btn) > 1) {
				this.changeQuantity(productId, -1, product);
			} else {
				this.removeProduct(productId, product);
			}
		},

		getCount(btn) {
			const input = btn.parentNode ? btn.parentNode.querySelector('[data-quantity]') : null;
			const count = Number(input?.value || 0);

			return Number.isFinite(count) ? count : 0;
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
			const formData = data instanceof FormData ? data : this.buildFormData(data);
			const csrfToken = this.getCsrfToken();

			if (csrfToken && !formData.has(csrfToken)) {
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
						} catch (error) {
							reject(error);
						}
					},
					onError: xhr => {
						reject(new Error(`Cart ${task} request failed: HTTP status ${xhr.status || 0}`));
					},
				});
			});
		},

		buildFormData(data) {
			const formData = new FormData();

			Object.entries(data).forEach(([key, value]) => {
				if (value === undefined || value === null) {
					return;
				}

				if (Array.isArray(value)) {
					value.forEach(item => formData.append(`${key}[]`, item));
					return;
				}

				formData.append(key, value);
			});

			return formData;
		},

		parseResponse(responseText) {
			const response = JSON.parse(responseText || '{}');

			if (!response || response.success !== true) {
				throw new Error(response && response.message ? response.message : 'Cart request failed');
			}

			return response;
		},

		setProductPending(product, isPending) {
			if (isPending) {
				product.dataset.ishopPending = '1';
				product.setAttribute('aria-busy', 'true');
			} else {
				delete product.dataset.ishopPending;
				product.removeAttribute('aria-busy');
			}

			product.querySelectorAll('[data-cart-button]').forEach(btn => {
				btn.disabled = isPending;
			});
		},

		async reloadSelectedProducts() {
			const requestId = ++this.reloadRequestId;
			const selectedProducts = Array.from(
				this.form.querySelectorAll('input[name="products[]"]:checked')
			).map(checkbox => checkbox.value);

			if (selectedProducts.length === 0) {
				this.setCartTotals({ total: 0, total_discount: 0, summary: 0 });
				return;
			}

			const formData = this.buildFormData({ filter_products: selectedProducts });

			try {
				const response = await this.sendRequest('reload', formData);

				if (requestId === this.reloadRequestId) {
					this.setCartTotals(response.data);
				}
			} catch (error) {
				console.error('Cart reload error:', error);
			}
		},

		setProductInfo(productId, product, data) {
			const productData = data?.products?.[productId];

			if (!productData) {
				return;
			}

			const input = product.querySelector('[data-quantity]');

			if (input) {
				input.value = String(this.normalizeCount(productData.count));
			}

			const price = product.querySelector('[data-price]');

			if (price) {
				price.textContent = this.formatNumber(productData.incart_total);
			}

			const oldPrice = product.querySelector('[data-old-price]');

			if (oldPrice) {
				oldPrice.textContent = this.formatNumber(productData.incart_old_total);
			}
		},

		setCartTotals(data = {}) {
			if (this.cartTotal) {
				this.cartTotal.textContent = this.formatNumber(data.total);
			}

			if (this.cartTotalDiscount) {
				this.cartTotalDiscount.textContent = this.formatNumber(data.total_discount);
			}

			if (this.cartSummary) {
				this.cartSummary.textContent = this.formatNumber(data.summary);
			}
		},

		setCartInfo(data = {}) {
			this.updateCartTotals(data);
			this.updateCartModules(data.count);
			this.dispatchCartUpdated(data);
		},

		updateCartTotals(data = {}) {
			if (this.hasPartialSelection()) {
				this.reloadSelectedProducts();
				return;
			}

			this.setCartTotals(data);
		},

		hasPartialSelection() {
			const checkboxes = Array.from(this.form.querySelectorAll('input[name="products[]"]'));

			return checkboxes.length > 0 && checkboxes.some(checkbox => !checkbox.checked);
		},

		updateCartModules(count) {
			if (count === undefined || count === null) {
				return;
			}

			const normalizedCount = this.normalizeCount(count);

			document.querySelectorAll('[data-ishop-cart]').forEach(cart => {
				const countNode = cart.querySelector('[data-ishop-cart-count]');
				const countTextNode = cart.querySelector('[data-ishop-cart-count-text]');

				if (countNode) {
					countNode.textContent = String(normalizedCount);
				}

				if (countTextNode) {
					countTextNode.textContent = this.getCartCountText(cart, normalizedCount);
				}

				if (cart.hasAttribute('aria-label')) {
					const label = cart.getAttribute('aria-label') || '';
					const prefix = label.includes(':') ? label.split(':')[0] : label;

					cart.setAttribute('aria-label', prefix ? `${prefix}: ${normalizedCount}` : String(normalizedCount));
				}

				cart.dataset.ishopCartEmpty = normalizedCount === 0 ? '1' : '0';
			});
		},

		getCartCountText(cart, count) {
			if (count === 0) {
				return cart.dataset.ishopCartEmptyText || '';
			}

			return String(count);
		},

		formatNumber(value, fallback = 0) {
			const number = Number(value);

			if (!Number.isFinite(number)) {
				return Number(fallback).toLocaleString('ru-RU');
			}

			return number.toLocaleString('ru-RU');
		},

		normalizeCount(value) {
			const count = Number.parseInt(value, 10);

			return Number.isFinite(count) && count >= 0 ? count : 0;
		},

		async removeProduct(productId, product) {
			this.setProductPending(product, true);

			try {
				const response = await this.sendRequest('remove', { product_id: productId });

				product.remove();
				this.setCartInfo(response.data);
				this.trackEcommerce('remove_from_cart', productId, 1);
				this.updateEmptyState();
			} catch (error) {
				console.error('Cart remove error:', error);
				this.setProductPending(product, false);
			}
		},

		async changeQuantity(productId, delta, product) {
			this.setProductPending(product, true);

			try {
				const response = await this.sendRequest('change', {
					product_id: productId,
					quantity: delta,
				});

				const count = Number(response.data?.products?.[productId]?.count) || 0;

				if (count > 0) {
					this.setProductInfo(productId, product, response.data);
					this.setProductPending(product, false);
				} else {
					product.remove();
					this.updateEmptyState();
				}

				this.setCartInfo(response.data);
				this.trackEcommerce(delta > 0 ? 'add_to_cart' : 'remove_from_cart', productId, 1);
			} catch (error) {
				console.error('Cart quantity error:', error);
				this.setProductPending(product, false);
			}
		},

		updateEmptyState() {
			if (this.form.querySelector('[data-product-incart-id]')) {
				return;
			}

			const emptyText = this.form.dataset.cartEmptyText || '';
			const empty = document.createElement('div');

			empty.className = 'module-cart-empty';

			if (emptyText) {
				const text = document.createElement('p');

				text.textContent = emptyText;
				empty.append(text);
			}

			this.form.after(empty);
			this.form.hidden = true;
		},

		dispatchCartUpdated(data) {
			document.dispatchEvent(new CustomEvent('com_ishop:cart-updated', {
				bubbles: true,
				detail: {
					data,
					form: this.form,
				},
			}));
		},

		trackEcommerce(action, productId, quantity) {
			if (
				window.iTheme
				&& typeof window.iTheme.setEcommerce === 'function'
			) {
				window.iTheme.setEcommerce(action, productId, quantity);
				return;
			}

			if (typeof window.eCommerceCart === 'function') {
				window.eCommerceCart(action, productId, quantity);
			}
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => CartPageManager.init(document), { once: true });
	} else {
		CartPageManager.init(document);
	}

	window.comIshopCartPage = CartPageManager;
})(window, document, window.Joomla);
