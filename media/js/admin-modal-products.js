import JoomlaDialog from 'joomla.dialog';

((document, window) => {
  const fieldSelector = '[data-modal-products-field]';
  const listSelector = '[data-modal-products-list]';
  const itemSelector = '[data-modal-product-item]';
  const templateSelector = '[data-modal-products-template]';
  const checkedSelector = '[data-modal-product-checkbox]:checked';
  const noSelectionMessage = 'COM_ISHOP_MODAL_PRODUCTS_SELECT_AT_LEAST_ONE';

  const translate = (key, fallback) => {
    if (window.Joomla && typeof window.Joomla.Text === 'object' && typeof window.Joomla.Text._ === 'function') {
      return window.Joomla.Text._(key, fallback);
    }

    return fallback;
  };

  const getSelectedIds = field => Array.from(field.querySelectorAll(itemSelector))
    .map(item => item.dataset.id)
    .filter(Boolean);

  const updateState = field => {
    const count = field.querySelectorAll(itemSelector).length;
    const titleInput = field.querySelector('.form-control');
    const clearButton = field.querySelector('[data-modal-products-clear]');
    const requiredInput = field.querySelector('[data-modal-products-required]');

    if (titleInput) {
      const manyText = translate('COM_ISHOP_N_PRODUCTS_SELECTED', `${count} products selected`);
      titleInput.value = count === 1
        ? translate('COM_ISHOP_1_PRODUCT_SELECTED', '1 product selected')
        : manyText.replace('%d', count).replace('%s', count);
    }

    if (clearButton) {
      clearButton.hidden = count === 0;
    }

    if (requiredInput) {
      requiredInput.value = count > 0 ? '1' : '';
    }
  };

  const dispatchChange = field => {
    field.dispatchEvent(new CustomEvent('change', {
      bubbles: true,
      cancelable: true,
      detail: {
        selectedIds: getSelectedIds(field),
      },
    }));
  };

  const addProduct = (field, product) => {
    const id = Number.parseInt(product.id || product.value, 10);
    const title = `${product.title || product.text || id}`.trim();

    if (!id || field.querySelector(`${itemSelector}[data-id="${id}"]`)) {
      return;
    }

    const list = field.querySelector(listSelector);
    const template = field.querySelector(templateSelector);

    if (!list || !template) {
      return;
    }

    const item = template.content.firstElementChild.cloneNode(true);
    const input = item.querySelector('input[type="hidden"]');
    const titleNode = item.querySelector('.modal-product-title');

    item.dataset.id = String(id);
    item.dataset.title = title;

    if (input) {
      input.value = String(id);
    }

    if (titleNode) {
      titleNode.textContent = title || String(id);
    }

    list.append(item);
  };

  const addProducts = (field, products) => {
    products.forEach(product => addProduct(field, product));
    updateState(field);
    dispatchChange(field);
  };

  const removeProduct = item => {
    const field = item.closest(fieldSelector);

    item.remove();

    if (field) {
      updateState(field);
      dispatchChange(field);
    }
  };

  const moveProduct = (item, direction) => {
    const field = item.closest(fieldSelector);

    if (direction === 'up' && item.previousElementSibling) {
      item.parentNode.insertBefore(item, item.previousElementSibling);
    } else if (direction === 'down' && item.nextElementSibling) {
      item.parentNode.insertBefore(item.nextElementSibling, item);
    }

    if (field) {
      updateState(field);
      dispatchChange(field);
    }
  };

  const openDialog = field => {
    const url = new URL(field.dataset.modalUrl, window.location.origin);
    const selected = getSelectedIds(field);

    if (selected.length) {
      url.searchParams.set('selected', selected.join(','));
    }

    const dialog = new JoomlaDialog({
      popupType: 'iframe',
      src: url.toString(),
      textHeader: field.dataset.modalTitle || translate('COM_ISHOP_SELECT_PRODUCTS', 'Select products'),
      width: '90vw',
      height: '80vh',
    });

    const messageHandler = event => {
      if (event.origin !== window.location.origin || !event.data) {
        return;
      }

      if (event.data.messageType === 'com_ishop:products-select') {
        addProducts(field, Array.isArray(event.data.products) ? event.data.products : []);
        dialog.close();
      } else if (event.data.messageType === 'joomla:cancel') {
        dialog.close();
      }
    };

    dialog.addEventListener('joomla-dialog:close', () => {
      window.removeEventListener('message', messageHandler);
      dialog.destroy();
    });

    window.addEventListener('message', messageHandler);
    dialog.show();
  };

  const clearProducts = field => {
    field.querySelectorAll(itemSelector).forEach(item => item.remove());
    updateState(field);
    dispatchChange(field);
  };

  const setupField = field => {
    if (field.dataset.modalProductsBound === '1') {
      return;
    }

    field.dataset.modalProductsBound = '1';
    updateState(field);

    field.addEventListener('click', event => {
      const selectButton = event.target.closest('[data-modal-products-select]');
      const clearButton = event.target.closest('[data-modal-products-clear]');
      const removeButton = event.target.closest('[data-modal-product-remove]');
      const moveButton = event.target.closest('[data-modal-product-move]');

      if (selectButton) {
        event.preventDefault();
        openDialog(field);
      } else if (clearButton) {
        event.preventDefault();
        clearProducts(field);
      } else if (removeButton) {
        event.preventDefault();
        removeProduct(removeButton.closest(itemSelector));
      } else if (moveButton) {
        event.preventDefault();
        moveProduct(moveButton.closest(itemSelector), moveButton.dataset.modalProductMove);
      }
    });
  };

  const setupFields = container => {
    (container.querySelectorAll ? container : document)
      .querySelectorAll(fieldSelector)
      .forEach(field => setupField(field));
  };

  const setupModal = () => {
    const addButton = document.querySelector('[data-modal-products-add]');

    if (!addButton || addButton.dataset.modalProductsBound === '1') {
      return;
    }

    addButton.dataset.modalProductsBound = '1';

    addButton.addEventListener('click', event => {
      event.preventDefault();

      const products = Array.from(document.querySelectorAll(checkedSelector)).map(input => ({
        id: input.value,
        title: input.dataset.title || input.value,
      }));

      if (!products.length) {
        window.alert(translate(noSelectionMessage, 'Select at least one product.'));
        return;
      }

      window.parent.postMessage({
        messageType: 'com_ishop:products-select',
        products,
      }, window.location.origin);
    });
  };

  const setup = container => {
    setupFields(container || document);
    setupModal();
  };

  document.addEventListener('DOMContentLoaded', () => setup(document));
  document.addEventListener('joomla:updated', event => setup(event.target || document));
})(document, window);
