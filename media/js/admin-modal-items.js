import JoomlaDialog from 'joomla.dialog';

((document, window) => {
  const fieldSelector = '[data-modal-items-field]';
  const listSelector = '[data-modal-items-list]';
  const itemSelector = '[data-modal-item]';
  const templateSelector = '[data-modal-items-template]';
  const itemCheckboxSelector = '[data-modal-item-checkbox]';
  const checkedSelector = `${itemCheckboxSelector}:checked`;
  const selectAllSelector = '[data-modal-items-check-all]';
  const messageType = 'com_ishop:modal-items-select';

  const translate = (key, fallback) => {
    if (window.Joomla && typeof window.Joomla.Text === 'object' && typeof window.Joomla.Text._ === 'function') {
      return window.Joomla.Text._(key, fallback);
    }

    return fallback;
  };

  const formatCountText = (key, count, fallback) => {
    const text = translate(key, fallback);

    return text.replace('%d', count).replace('%s', count);
  };

  const getSelectedIds = field => Array.from(field.querySelectorAll(itemSelector))
    .map(item => item.dataset.id)
    .filter(Boolean);

  const updateState = field => {
    const count = field.querySelectorAll(itemSelector).length;
    const titleInput = field.querySelector('.form-control');
    const clearButton = field.querySelector('[data-modal-items-clear]');
    const requiredInput = field.querySelector('[data-modal-items-required]');
    const oneKey = field.dataset.selectedOneKey;
    const manyKey = field.dataset.selectedManyKey;

    if (titleInput) {
      titleInput.value = count === 1
        ? translate(oneKey, '1 item selected')
        : formatCountText(manyKey, count, `${count} items selected`);
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

  const addItem = (field, itemData) => {
    const id = Number.parseInt(itemData.id || itemData.value, 10);
    const title = `${itemData.title || itemData.text || id}`.trim();

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
    const titleNode = item.querySelector('.modal-item-title');

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

  const addItems = (field, items) => {
    items.forEach(item => addItem(field, item));
    updateState(field);
    dispatchChange(field);
  };

  const removeItem = item => {
    const field = item.closest(fieldSelector);

    item.remove();

    if (field) {
      updateState(field);
      dispatchChange(field);
    }
  };

  const moveItem = (item, direction) => {
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
      textHeader: field.dataset.modalTitle || translate('JSELECT', 'Select'),
      width: '90vw',
      height: '80vh',
    });

    const messageHandler = event => {
      if (event.origin !== window.location.origin || !event.data) {
        return;
      }

      if (event.data.messageType === messageType) {
        addItems(field, Array.isArray(event.data.items) ? event.data.items : []);
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

  const clearItems = field => {
    field.querySelectorAll(itemSelector).forEach(item => item.remove());
    updateState(field);
    dispatchChange(field);
  };

  const setupField = field => {
    if (field.dataset.modalItemsBound === '1') {
      return;
    }

    field.dataset.modalItemsBound = '1';
    updateState(field);

    field.addEventListener('click', event => {
      const selectButton = event.target.closest('[data-modal-items-select]');
      const clearButton = event.target.closest('[data-modal-items-clear]');
      const removeButton = event.target.closest('[data-modal-item-remove]');
      const moveButton = event.target.closest('[data-modal-item-move]');

      if (selectButton) {
        event.preventDefault();
        openDialog(field);
      } else if (clearButton) {
        event.preventDefault();
        clearItems(field);
      } else if (removeButton) {
        event.preventDefault();
        removeItem(removeButton.closest(itemSelector));
      } else if (moveButton) {
        event.preventDefault();
        moveItem(moveButton.closest(itemSelector), moveButton.dataset.modalItemMove);
      }
    });
  };

  const setupFields = container => {
    (container.querySelectorAll ? container : document)
      .querySelectorAll(fieldSelector)
      .forEach(field => setupField(field));
  };

  const setupModal = () => {
    const addButton = document.querySelector('[data-modal-items-add]');
    const selectAll = document.querySelector(selectAllSelector);

    if (!addButton && !selectAll) {
      return;
    }

    const updateSelectAll = () => {
      if (!selectAll) {
        return;
      }

      const checkboxes = Array.from(document.querySelectorAll(itemCheckboxSelector));
      const checkedCount = checkboxes.filter(input => input.checked).length;

      selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    };

    if (addButton && addButton.dataset.modalItemsBound !== '1') {
      addButton.dataset.modalItemsBound = '1';

      addButton.addEventListener('click', event => {
        event.preventDefault();

        const items = Array.from(document.querySelectorAll(checkedSelector)).map(input => ({
          id: input.value,
          title: input.dataset.title || input.value,
        }));

        if (!items.length) {
          window.alert(translate(addButton.dataset.emptySelectionKey, 'Select at least one item.'));
          return;
        }

        window.parent.postMessage({
          messageType,
          items,
        }, window.location.origin);
      });
    }

    if (selectAll && selectAll.dataset.modalItemsBound !== '1') {
      selectAll.dataset.modalItemsBound = '1';

      selectAll.addEventListener('change', () => {
        document.querySelectorAll(itemCheckboxSelector).forEach(input => {
          input.checked = selectAll.checked;
        });

        updateSelectAll();
      });

      document.addEventListener('change', event => {
        if (event.target.closest(itemCheckboxSelector)) {
          updateSelectAll();
        }
      });
    }

    updateSelectAll();
  };

  const setup = container => {
    setupFields(container || document);
    setupModal();
  };

  document.addEventListener('DOMContentLoaded', () => setup(document));
  document.addEventListener('joomla:updated', event => setup(event.target || document));
})(document, window);
