/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

(function (window, document) {
  'use strict';

  const SELECTOR = '[data-ishop-products]';

  function parseJsonScript(id) {
    if (!id) {
      return {};
    }

    const element = document.getElementById(id);

    if (!element) {
      return {};
    }

    try {
      return JSON.parse(element.textContent || '{}');
    } catch (error) {
      console.error('com_ishop.products-loader: invalid state JSON', error);
      return {};
    }
  }

  function appendValue(formData, key, value) {
    if (value === null || typeof value === 'undefined' || value === '') {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach((item) => appendValue(formData, `${key}[]`, item));
      return;
    }

    if (typeof value === 'object') {
      Object.keys(value).forEach((name) => appendValue(formData, `${key}[${name}]`, value[name]));
      return;
    }

    formData.append(key, value);
  }

  function appendState(formData, state) {
    Object.keys(state || {}).forEach((key) => appendValue(formData, key, state[key]));
  }

  function toPositiveInt(value, fallback) {
    const parsed = Number.parseInt(value, 10);

    if (Number.isNaN(parsed) || parsed < 0) {
      return fallback;
    }

    return parsed;
  }

  function createSentinel(container) {
    const sentinel = document.createElement('div');
    sentinel.className = 'ishop-products-loader-sentinel';
    sentinel.setAttribute('aria-hidden', 'true');
    container.after(sentinel);

    return sentinel;
  }

  function emitLoadedEvent(container, response) {
    const event = new CustomEvent('com_ishop:products-loaded', {
      bubbles: true,
      detail: {
        container,
        response,
      },
    });

    container.dispatchEvent(event);
  }

  class ProductsLoader {
    constructor(container) {
      this.container = container;
      this.endpoint = container.dataset.ishopEndpoint || '/index.php?option=com_ishop&task=products.load&format=json';
      this.context = container.dataset.ishopContext || 'category';
      this.state = parseJsonScript(container.dataset.ishopState);
      this.limit = toPositiveInt(container.dataset.ishopLimit, 0);
      this.nextLimitstart = toPositiveInt(container.dataset.ishopNextLimitstart, this.limit);
      this.total = toPositiveInt(container.dataset.ishopTotal, 0);
      this.token = container.dataset.ishopToken || '';
      this.loading = false;
      this.finished = container.dataset.ishopHasMore !== '1' || this.limit <= 0;
      this.sentinel = createSentinel(container);
      this.observer = null;

      this.container.dataset.ishopProductsBound = '1';
      this.observe();
    }

    observe() {
      if (this.finished) {
        this.finish();
        return;
      }

      if (!('IntersectionObserver' in window)) {
        window.addEventListener('scroll', () => this.checkScroll(), { passive: true });
        this.checkScroll();
        return;
      }

      this.observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            this.load();
          }
        });
      }, {
        root: null,
        rootMargin: '700px 0px',
        threshold: 0,
      });

      this.observer.observe(this.sentinel);
    }

    checkScroll() {
      if (this.loading || this.finished) {
        return;
      }

      const rect = this.sentinel.getBoundingClientRect();

      if (rect.top < window.innerHeight + 700) {
        this.load();
      }
    }

    buildRequestBody() {
      const formData = new FormData();
      formData.append('context', this.context);
      formData.append('limit', String(this.limit));
      formData.append('limitstart', String(this.nextLimitstart));

      if (this.token) {
        formData.append(this.token, '1');
      }

      appendState(formData, this.state);

      return formData;
    }

    async load() {
      if (this.loading || this.finished) {
        return;
      }

      this.loading = true;
      this.container.dataset.ishopLoading = '1';

      try {
        const response = await fetch(this.endpoint, {
          method: 'POST',
          body: this.buildRequestBody(),
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();

        if (!payload || payload.success !== true) {
          throw new Error(payload && payload.message ? payload.message : 'Invalid response');
        }

        this.append(payload.data || {});
      } catch (error) {
        console.error('com_ishop.products-loader: load failed', error);
        this.finish();
      } finally {
        this.loading = false;
        this.container.dataset.ishopLoading = '0';
      }
    }

    append(data) {
      if (data.html) {
        const template = document.createElement('template');
        template.innerHTML = data.html;
        this.container.append(template.content);
      }

      this.total = toPositiveInt(data.total, this.total);
      this.limit = toPositiveInt(data.limit, this.limit);
      this.nextLimitstart = toPositiveInt(data.nextLimitstart, this.nextLimitstart + this.limit);

      this.container.dataset.ishopTotal = String(this.total);
      this.container.dataset.ishopLimit = String(this.limit);
      this.container.dataset.ishopNextLimitstart = String(this.nextLimitstart);
      this.container.dataset.ishopHasMore = data.hasMore ? '1' : '0';

      if (window.iTheme && typeof window.iTheme.registerEcommerceItems === 'function') {
        window.iTheme.registerEcommerceItems(data.analyticsItems || []);
      }

      if (window.iTheme && typeof window.iTheme.trackViewItemList === 'function') {
        window.iTheme.trackViewItemList({
          currency: data.currency || this.container.dataset.ishopCurrency || 'BYN',
          item_list_id: data.itemList && data.itemList.id ? data.itemList.id : this.context,
          item_list_name: data.itemList && data.itemList.name ? data.itemList.name : this.context,
          items: data.analyticsItems || [],
        });
      }

      emitLoadedEvent(this.container, data);
      document.dispatchEvent(new CustomEvent('joomla:updated', { bubbles: true, detail: { target: this.container } }));

      if (!data.hasMore) {
        this.finish();
      }
    }

    finish() {
      this.finished = true;
      this.container.dataset.ishopHasMore = '0';

      if (this.observer) {
        this.observer.disconnect();
      }

      if (this.sentinel) {
        this.sentinel.remove();
      }
    }
  }

  function init(root) {
    const scope = root && root.querySelectorAll ? root : document;

    scope.querySelectorAll(SELECTOR).forEach((container) => {
      if (container.dataset.ishopProductsBound === '1') {
        return;
      }

      new ProductsLoader(container);
    });
  }

  window.comIshopProductsLoader = {
    init,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init(document), { once: true });
  } else {
    init(document);
  }
})(window, document);
