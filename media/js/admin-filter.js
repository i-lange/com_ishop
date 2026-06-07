/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */
((document, Joomla) => {
  const options = Joomla.getOptions('com_ishop.adminFilter') || {};
  const messages = options.messages || {};

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));

  const getSelectedValues = container => {
    const selected = {};

    container.querySelectorAll('[data-filter-field]').forEach(field => {
      const fieldId = field.dataset.filterField;
      const type = Number(field.dataset.filterFieldType || 0);

      if (type === 0) {
        const min = Number(field.querySelector(`[name="jform[ishop_fields][${fieldId}][min]"]`)?.value || 0);
        const max = Number(field.querySelector(`[name="jform[ishop_fields][${fieldId}][max]"]`)?.value || 0);
        if (min > 0 || max > 0) {
          selected[fieldId] = { min, max };
        }
      } else if (type === 1) {
        const values = Array.from(field.querySelectorAll(`select[name="jform[ishop_fields][${fieldId}][]"] option:checked`))
          .map(option => Number(option.value))
          .filter(Boolean);
        if (values.length) {
          selected[fieldId] = values;
        }
      } else if (type === 2) {
        const checked = field.querySelector(`[name="jform[ishop_fields][${fieldId}]"]`)?.checked;
        if (checked) {
          selected[fieldId] = 1;
        }
      }
    });

    return selected;
  };

  const renderFields = (container, fields, selected = {}) => {
      if (!fields.length) {
      container.innerHTML = `<div class="alert alert-info">${escapeHtml(messages.noFields || '')}</div>`;
      return;
    }

    container.innerHTML = fields.map(field => {
      const fieldId = Number(field.id);
      const selectedValue = selected[fieldId] || selected[String(fieldId)];
      const title = escapeHtml(field.title);
      const type = Number(field.type);

      if (type === 0) {
        const min = selectedValue && typeof selectedValue === 'object' ? Number(selectedValue.min || 0) : 0;
        const max = selectedValue && typeof selectedValue === 'object' ? Number(selectedValue.max || 0) : 0;

        return `
          <fieldset class="options-form mb-3" data-filter-field="${fieldId}" data-filter-field-type="${type}">
            <legend>${title}</legend>
            <div class="row">
              <div class="col-6">
                <label class="form-label" for="jform_ishop_fields_${fieldId}_min">${escapeHtml(messages.min || 'Min')}</label>
                <input type="number" min="0" class="form-control" id="jform_ishop_fields_${fieldId}_min" name="jform[ishop_fields][${fieldId}][min]" value="${min}">
              </div>
              <div class="col-6">
                <label class="form-label" for="jform_ishop_fields_${fieldId}_max">${escapeHtml(messages.max || 'Max')}</label>
                <input type="number" min="0" class="form-control" id="jform_ishop_fields_${fieldId}_max" name="jform[ishop_fields][${fieldId}][max]" value="${max}">
              </div>
            </div>
          </fieldset>`;
      }

      if (type === 1) {
        const selectedIds = Array.isArray(selectedValue) ? selectedValue.map(Number) : [];
        const values = (field.values || []).map(option => {
          const optionId = Number(option.id);
          return `<option value="${optionId}"${selectedIds.includes(optionId) ? ' selected' : ''}>${escapeHtml(option.value)}</option>`;
        }).join('');

        return `
          <fieldset class="options-form mb-3" data-filter-field="${fieldId}" data-filter-field-type="${type}">
            <legend>${title}</legend>
            <select multiple class="form-select advancedSelect" name="jform[ishop_fields][${fieldId}][]">${values}</select>
          </fieldset>`;
      }

      const checked = Number(selectedValue || 0) > 0 ? ' checked' : '';
      return `
        <fieldset class="options-form mb-3" data-filter-field="${fieldId}" data-filter-field-type="${type}">
          <legend>${title}</legend>
          <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" id="jform_ishop_fields_${fieldId}" name="jform[ishop_fields][${fieldId}]" value="1"${checked}>
            <label class="form-check-label" for="jform_ishop_fields_${fieldId}">${escapeHtml(messages.yes || 'Yes')}</label>
          </div>
        </fieldset>`;
    }).join('');
  };

  const init = () => {
    const category = document.getElementById('jform_category_id');
    const container = document.querySelector('[data-filter-fields-container]');

    if (!container && category && options.endpoint) {
      console.warn('com_ishop.admin-filter: filter fields container is missing. Check the Joomla custom field type registration.');
    }

    if (!category || !container || !options.endpoint) {
      return;
    }

    category.addEventListener('change', () => {
      const categoryId = Number(category.value || 0);
      const selected = getSelectedValues(container);

      if (categoryId <= 0) {
        container.innerHTML = `<div class="alert alert-info">${escapeHtml(messages.selectCategory || '')}</div>`;
        return;
      }

      Joomla.request({
        url: `${options.endpoint}&category_id=${categoryId}`,
        method: 'GET',
        onSuccess: response => {
          const data = JSON.parse(response);
          renderFields(container, data.data?.fields || [], selected);
        },
        onError: () => {
          container.innerHTML = `<div class="alert alert-danger">${escapeHtml(Joomla.Text?._('JERROR_AN_ERROR_HAS_OCCURRED') || 'Error')}</div>`;
        },
      });
    });
  };

  document.addEventListener('DOMContentLoaded', init);
})(document, Joomla);
