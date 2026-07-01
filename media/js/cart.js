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
			this.cartTotals = Array.from(this.form.querySelectorAll('[data-cart-total]'));
			this.cartTotalDiscounts = Array.from(this.form.querySelectorAll('[data-cart-total-discount]'));
			this.cartPromoDiscounts = Array.from(this.form.querySelectorAll('[data-cart-promo-discount]'));
			this.cartSummaries = Array.from(this.form.querySelectorAll('[data-cart-summary]'));
			this.promocodeInput = this.form.querySelector('[data-cart-promocode]');
			this.promocodeMessage = this.form.querySelector('[data-cart-promocode-message]');
			this.promocodeButton = this.form.querySelector('[data-cart-apply-promocode]');
			this.selectAll = this.form.querySelector('[data-cart-select-all]');
			this.removeSelectedButton = this.form.querySelector('[data-cart-remove-selected]');
			this.checkoutButtons = Array.from(this.form.querySelectorAll('button[type="submit"], input[type="submit"]'));
			this.mobileActions = this.form.querySelector('[data-cart-mobile-actions]');
			this.removedAlert = this.form.querySelector('[data-cart-removed-alert]');
			this.removedMessage = this.form.querySelector('[data-cart-removed-message]');
			this.lastRemovedItems = [];
			this.reloadRequestId = 0;

			this.form.addEventListener('click', this.handleButtonClick.bind(this));
			this.form.addEventListener('change', this.handleCheckboxChange.bind(this));

			if (this.promocodeInput) {
				this.promocodeInput.addEventListener('keydown', this.handlePromocodeKeydown.bind(this));
			}

			this.updateSelectionControls();
		},

		handleButtonClick(event) {
			const applyPromocode = event.target.closest('[data-cart-apply-promocode]');

			if (applyPromocode && this.form.contains(applyPromocode)) {
				event.preventDefault();
				this.applyPromocode();
				return;
			}

			const removeSelected = event.target.closest('[data-cart-remove-selected]');

			if (removeSelected && this.form.contains(removeSelected)) {
				event.preventDefault();
				this.removeSelectedProducts();
				return;
			}

			const restore = event.target.closest('[data-cart-restore]');

			if (restore && this.form.contains(restore)) {
				event.preventDefault();
				this.restoreRemovedProducts();
				return;
			}

			const closeAlert = event.target.closest('[data-cart-alert-close]');

			if (closeAlert && this.form.contains(closeAlert)) {
				event.preventDefault();
				this.hideRemovedAlert(true);
				return;
			}

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
			if (event.target.matches('[data-cart-select-all]')) {
				this.toggleAllProducts(event.target.checked);
				return;
			}

			if (event.target.matches('input[name="products[]"]')) {
				this.updateSelectionControls();
				this.reloadSelectedProducts();
			}
		},

		handlePromocodeKeydown(event) {
			if (event.key !== 'Enter') {
				return;
			}

			event.preventDefault();
			this.applyPromocode();
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
				this.appendFormValue(formData, key, value);
			});

			return formData;
		},

		appendFormValue(formData, key, value) {
			if (value === undefined || value === null) {
				return;
			}

			if (Array.isArray(value)) {
				value.forEach(item => this.appendFormValue(formData, `${key}[]`, item));
				return;
			}

			if (typeof value === 'object') {
				Object.entries(value).forEach(([childKey, childValue]) => {
					this.appendFormValue(formData, `${key}[${childKey}]`, childValue);
				});
				return;
			}

			formData.append(key, value);
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

			product.querySelectorAll('[data-cart-button], input[name="products[]"]').forEach(control => {
				control.disabled = isPending;
			});
		},

		setPromocodePending(isPending) {
			[this.promocodeInput, this.promocodeButton].forEach(control => {
				if (control) {
					control.disabled = isPending;
				}
			});
		},

		getProductCheckboxes() {
			return Array.from(this.form.querySelectorAll('input[name="products[]"]'));
		},

		getSelectedCheckboxes() {
			return this.getProductCheckboxes().filter(checkbox => checkbox.checked && !checkbox.disabled);
		},

		toggleAllProducts(checked) {
			this.getProductCheckboxes().forEach(checkbox => {
				if (!checkbox.disabled) {
					checkbox.checked = checked;
				}
			});

			this.updateSelectionControls();
			this.reloadSelectedProducts();
		},

		updateSelectionControls() {
			const checkboxes = this.getProductCheckboxes().filter(checkbox => !checkbox.disabled);
			const checkedCount = checkboxes.filter(checkbox => checkbox.checked).length;

			if (this.selectAll) {
				this.selectAll.disabled = checkboxes.length === 0;
				this.selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
				this.selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
			}

			if (this.removeSelectedButton) {
				this.removeSelectedButton.disabled = checkedCount === 0;
			}

			this.checkoutButtons.forEach(button => {
				button.disabled = checkedCount === 0;
			});

			this.updateMobileActionsVisibility();
		},

		async reloadSelectedProducts() {
			const requestId = ++this.reloadRequestId;
			const selectedProducts = this.getSelectedCheckboxes().map(checkbox => checkbox.value);

			if (selectedProducts.length === 0) {
				this.setCartTotals({ total: 0, total_discount: 0, promo_discount: 0, summary: 0 });
				return;
			}

			const formData = this.buildFormData({ filter_products: selectedProducts });

			try {
				const response = await this.sendRequest('reload', formData);

				if (requestId === this.reloadRequestId) {
					this.setCartTotals(response.data);
					this.updatePromocodeState(response.data, false);
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
			if (data.total !== undefined) {
				this.setTextContent(this.cartTotals, this.formatNumber(data.total));
			}

			if (data.total_discount !== undefined) {
				this.setTextContent(this.cartTotalDiscounts, this.formatNumber(data.total_discount));
			}

			if (data.promo_discount !== undefined) {
				this.setTextContent(this.cartPromoDiscounts, this.formatNumber(data.promo_discount));
			}

			if (data.summary !== undefined) {
				this.setTextContent(this.cartSummaries, this.formatNumber(data.summary));
			}

			this.updateMobileActionsVisibility(data.summary === undefined ? null : data.summary);
		},

		setTextContent(elements, value) {
			elements.forEach(element => {
				element.textContent = value;
			});
		},

		updateMobileActionsVisibility(summary = null) {
			if (!this.mobileActions) {
				return;
			}

			const hasSelectedProducts = this.getSelectedCheckboxes().length > 0;
			const normalizedSummary = summary === null
				? this.getElementNumber(this.cartSummaries[0])
				: Number(summary);
			const isVisible = hasSelectedProducts && Number.isFinite(normalizedSummary) && normalizedSummary > 0;

			this.mobileActions.hidden = !isVisible;
		},

		setCartInfo(data = {}) {
			this.updateCartTotals(data);
			this.updatePromocodeState(data, true);
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
			const checkboxes = this.getProductCheckboxes();

			return checkboxes.length > 0 && checkboxes.some(checkbox => !checkbox.checked);
		},

		updatePromocodeState(data = {}, updateInput = true) {
			const code = data.promocode ?? data.promo_code;
			const message = data.promocode_message ?? data.promo_message ?? '';
			const valid = Boolean(data.promocode_valid ?? data.promo_valid);

			if (updateInput && this.promocodeInput && code !== undefined) {
				this.promocodeInput.value = code;
			}

			if (!this.promocodeMessage) {
				return;
			}

			this.promocodeMessage.textContent = message;
			this.promocodeMessage.classList.toggle('d-none', message === '');
			this.promocodeMessage.classList.toggle('text-success', message !== '' && valid);
			this.promocodeMessage.classList.toggle('text-danger', message !== '' && !valid);
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

		getElementNumber(element) {
			if (!element) {
				return 0;
			}

			const value = String(element.textContent || '')
				.replace(/\s+/g, '')
				.replace(',', '.');
			const number = Number(value);

			return Number.isFinite(number) ? number : 0;
		},

		normalizeCount(value) {
			const count = Number.parseInt(value, 10);

			return Number.isFinite(count) && count >= 0 ? count : 0;
		},

		collectRemovedProducts(products) {
			return products.map((product, index) => {
				const productId = Number(product.dataset.productIncartId) || 0;

				return {
					id: productId,
					index,
					node: product,
					parent: product.parentNode,
					placeholder: null,
					quantity: this.getProductQuantity(product),
				};
			}).filter(item => item.id > 0);
		},

		getProductQuantity(product) {
			const quantityInput = product.querySelector('[data-quantity]');
			const quantity = Number.parseInt(quantityInput?.value || '1', 10);

			return Number.isFinite(quantity) && quantity > 0 ? quantity : 1;
		},

		detachRemovedItems(items) {
			items.forEach(item => {
				if (!item.parent || !item.node.parentNode) {
					return;
				}

				const placeholder = document.createComment('com_ishop_cart_removed');

				item.parent.insertBefore(placeholder, item.node);
				item.placeholder = placeholder;
				item.node.remove();
			});
		},

		restoreRemovedNodes(items) {
			this.removeGeneratedEmptyState();
			this.form.hidden = false;

			items
				.slice()
				.sort((a, b) => a.index - b.index)
				.forEach(item => {
					if (item.placeholder?.parentNode) {
						item.placeholder.parentNode.insertBefore(item.node, item.placeholder);
						item.placeholder.remove();
					} else if (item.parent) {
						item.parent.append(item.node);
					}

					item.placeholder = null;
					this.setProductPending(item.node, false);
				});
		},

		clearRemovedItems(removePlaceholders = true) {
			if (removePlaceholders) {
				this.lastRemovedItems.forEach(item => {
					if (item.placeholder?.parentNode) {
						item.placeholder.remove();
					}
				});
			}

			this.lastRemovedItems = [];
		},

		showRemovedAlert(items) {
			this.lastRemovedItems = items;

			if (!this.removedAlert) {
				return;
			}

			const count = items.reduce((total, item) => total + item.quantity, 0);
			const template = this.removedAlert.dataset.cartRemovedText || '';

			if (this.removedMessage) {
				this.removedMessage.textContent = template.includes('%d')
					? template.replace('%d', String(count))
					: `${template} ${count}`;
			}

			this.removedAlert.classList.remove('d-none');
			this.removedAlert.classList.add('d-flex', 'show');
		},

		hideRemovedAlert(clearItems = false) {
			if (this.removedAlert) {
				this.removedAlert.classList.add('d-none');
				this.removedAlert.classList.remove('d-flex', 'show');
			}

			if (clearItems) {
				this.clearRemovedItems(true);
				this.updateEmptyState();
			}
		},

		async removeProduct(productId, product) {
			const removedItems = this.collectRemovedProducts([product]);

			if (removedItems.length === 0) {
				return;
			}

			this.clearRemovedItems(true);
			this.hideRemovedAlert(false);
			this.setProductPending(product, true);

			try {
				const response = await this.sendRequest('remove', { product_id: productId });

				this.detachRemovedItems(removedItems);
				this.showRemovedAlert(removedItems);
				this.setCartInfo(response.data);
				this.trackRemovedItems(removedItems);
				this.updateSelectionControls();
				this.updateEmptyState();
			} catch (error) {
				console.error('Cart remove error:', error);
				this.setProductPending(product, false);
			}
		},

		async removeSelectedProducts() {
			const selectedCheckboxes = this.getSelectedCheckboxes();
			const products = selectedCheckboxes
				.map(checkbox => checkbox.closest('[data-product-incart-id]'))
				.filter(Boolean);
			const removedItems = this.collectRemovedProducts(products);

			if (removedItems.length === 0) {
				return;
			}

			this.clearRemovedItems(true);
			this.hideRemovedAlert(false);
			products.forEach(product => this.setProductPending(product, true));

			try {
				const response = await this.sendRequest('removeSelected', {
					product_ids: removedItems.map(item => item.id),
				});

				this.detachRemovedItems(removedItems);
				this.showRemovedAlert(removedItems);
				this.setCartInfo(response.data);
				this.trackRemovedItems(removedItems);
				this.updateSelectionControls();
				this.updateEmptyState();
			} catch (error) {
				console.error('Cart remove selected error:', error);
				products.forEach(product => this.setProductPending(product, false));
			}
		},

		async restoreRemovedProducts() {
			if (this.lastRemovedItems.length === 0) {
				return;
			}

			const restoreItems = {};

			this.lastRemovedItems.forEach(item => {
				restoreItems[item.id] = item.quantity;
			});

			try {
				const response = await this.sendRequest('restore', { restore_items: restoreItems });
				const restoredItems = this.lastRemovedItems.slice();

				this.restoreRemovedNodes(restoredItems);
				restoredItems.forEach(item => this.setProductInfo(item.id, item.node, response.data));
				this.clearRemovedItems(false);
				this.hideRemovedAlert(false);
				this.setCartInfo(response.data);
				this.updateSelectionControls();
			} catch (error) {
				console.error('Cart restore error:', error);
			}
		},

		async applyPromocode() {
			if (!this.promocodeInput) {
				return;
			}

			this.setPromocodePending(true);

			try {
				const response = await this.sendRequest('promocode', {
					promocode: this.promocodeInput.value.trim(),
				});

				this.setCartInfo(response.data);
			} catch (error) {
				console.error('Cart promocode error:', error);
			} finally {
				this.setPromocodePending(false);
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
				this.updateSelectionControls();
				this.trackEcommerce(delta > 0 ? 'add_to_cart' : 'remove_from_cart', productId, 1);
			} catch (error) {
				console.error('Cart quantity error:', error);
				this.setProductPending(product, false);
			}
		},

		trackRemovedItems(items) {
			items.forEach(item => {
				this.trackEcommerce('remove_from_cart', item.id, item.quantity);
			});
		},

		updateEmptyState() {
			if (this.form.querySelector('[data-product-incart-id]')) {
				return;
			}

			if (this.lastRemovedItems.length > 0) {
				this.removeGeneratedEmptyState();
				this.form.hidden = false;

				return;
			}

			window.location.reload();
		},

		removeGeneratedEmptyState() {
			document.querySelectorAll('[data-cart-generated-empty]').forEach(empty => empty.remove());
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
