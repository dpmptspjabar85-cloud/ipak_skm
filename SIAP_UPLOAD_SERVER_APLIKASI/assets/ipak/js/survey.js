(function () {
  'use strict';

  var form = document.getElementById('ipak-survey-form');
  if (!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.wizard-step'));
  var current = 0;
  var progress = document.getElementById('progress-value');
  var caption = document.getElementById('step-caption');

  function visibleRequiredFields(step) {
    return Array.prototype.slice.call(step.querySelectorAll('[required]')).filter(function (field) {
      return !field.disabled && field.offsetParent !== null;
    });
  }

  function validateSearchableSelects(step) {
    var valid = true;
    Array.prototype.slice.call(step.querySelectorAll('.searchable-select')).forEach(function (widget) {
      if (widget.offsetParent === null) return;
      var select = widget.nextElementSibling;
      var input = widget.querySelector('.searchable-select-input');
      if (!select || select.tagName !== 'SELECT' || !input) return;
      if (!select.hasAttribute('required') || select.value !== '') {
        input.setCustomValidity('');
        return;
      }
      input.setCustomValidity('Silakan pilih salah satu jawaban.');
      input.reportValidity();
      valid = false;
    });
    return valid;
  }

  function validateStep(step) {
    var valid = true;
    var seenRadioNames = {};
    visibleRequiredFields(step).forEach(function (field) {
      if (field.type === 'radio') {
        if (seenRadioNames[field.name]) return;
        seenRadioNames[field.name] = true;
        var selected = step.querySelector('input[name="' + field.name + '"]:checked');
        if (!selected) {
          field.setCustomValidity('Silakan pilih salah satu jawaban.');
          field.reportValidity();
          valid = false;
        } else {
          field.setCustomValidity('');
        }
      } else if (!field.checkValidity()) {
        field.reportValidity();
        valid = false;
      }
    });
    return validateSearchableSelects(step) && valid;
  }

  function showStep(index) {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach(function (step, stepIndex) {
      step.classList.toggle('is-active', stepIndex === current);
    });
    progress.style.width = (((current + 1) / steps.length) * 100) + '%';
    caption.textContent = 'Langkah ' + (current + 1) + ' dari ' + steps.length;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  form.addEventListener('click', function (event) {
    var next = event.target.closest('[data-next]');
    var previous = event.target.closest('[data-back]');
    if (next) {
      event.preventDefault();
      if (validateStep(steps[current])) showStep(current + 1);
    }
    if (previous) {
      event.preventDefault();
      showStep(current - 1);
    }
  });

  function conditionalInput(selectId, targetId, expectedValue) {
    var control = document.getElementById(selectId);
    var target = document.getElementById(targetId);
    if (!control || !target) return;
    var refresh = function () {
      var show = String(control.value) === String(expectedValue);
      target.closest('.field').hidden = !show;
      target.required = show;
      if (!show) target.value = '';
    };
    control.addEventListener('change', refresh);
    refresh();
  }

  conditionalInput('job', 'job_other', '5');

  form.addEventListener('submit', function (event) {
    if (!validateStep(steps[current])) {
      event.preventDefault();
      return;
    }
    var button = form.querySelector('[type="submit"]');
    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan...';
    }
  });

  var invalid = form.querySelector('.field-error');
  if (invalid) {
    var containingStep = invalid.closest('.wizard-step');
    var invalidIndex = steps.indexOf(containingStep);
    if (invalidIndex >= 0) current = invalidIndex;
  }
  showStep(current);

  /* Searchable dropdown for <select> elements */
  var EMPTY_PLACEHOLDER = 'Pilih atau cari\u2026';

  function fireChange(element) {
    var event;
    if (typeof Event === 'function') {
      event = new Event('change', { bubbles: true });
    } else if (document.createEvent) {
      event = document.createEvent('HTMLEvents');
      event.initEvent('change', true, false);
    } else {
      return;
    }
    element.dispatchEvent(event);
  }

  function makeSearchable(select) {
    if (select.getAttribute('data-searchable') === '1') return;
    select.setAttribute('data-searchable', '1');

    var container = document.createElement('div');
    container.className = 'searchable-select';

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'searchable-select-input';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('placeholder', EMPTY_PLACEHOLDER);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');

    var dropdown = document.createElement('div');
    dropdown.className = 'searchable-select-dropdown';
    dropdown.setAttribute('role', 'listbox');

    var caret = document.createElement('span');
    caret.className = 'searchable-select-caret';
    caret.innerHTML = '&#9662;';

    var status = document.createElement('div');
    status.className = 'searchable-select-empty';

    select.classList.add('searchable-select-hidden');
    select.parentNode.insertBefore(container, select);
    container.appendChild(input);
    container.appendChild(dropdown);
    container.appendChild(status);
    container.appendChild(caret);
    if (select.id) input.id = select.id + '-search';

    var options = Array.prototype.slice.call(select.querySelectorAll('option'));
    var items = [];
    var remoteUrl = select.getAttribute('data-remote');
    var remoteField = select.getAttribute('data-remote-field') || '';
    var remoteForm = select.getAttribute('data-remote-form') || '';
    var remoteTimer = null;
    var remoteSequence = 0;

    function addOption(value, label) {
      if (!select.querySelector('option[value="' + String(value).replace(/"/g, '\\"') + '"]')) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
      }
    }

    function buildItems() {
      for (var i = dropdown.children.length - 1; i >= 0; i -= 1) {
        dropdown.removeChild(dropdown.children[i]);
      }
      items = [];
      options.forEach(function (opt, index) {
        var item = document.createElement('div');
        item.className = 'searchable-select-option';
        item.textContent = opt.text;
        item.setAttribute('role', 'option');
        item.setAttribute('data-index', String(index));
        item.addEventListener('mousedown', function (event) {
          event.preventDefault();
        });
        item.addEventListener('click', function () {
          select.selectedIndex = index;
          syncFromSelect();
          closeDropdown();
          fireChange(select);
        });
        dropdown.appendChild(item);
        items.push(item);
      });
    }

    function refreshItems() {
      buildItems();
      var keyword = input.value.trim().toLowerCase();
      if (keyword) applyFilter(keyword);
    }

    function fetchRemote(keyword) {
      if (!remoteUrl) return;
      var url = remoteUrl
        + (remoteUrl.indexOf('?') === -1 ? '?' : '&')
        + 'field=' + encodeURIComponent(remoteField)
        + '&q=' + encodeURIComponent(keyword)
        + '&selected=' + encodeURIComponent(select.value || '')
        + '&form=' + encodeURIComponent(remoteForm);
      remoteSequence += 1;
      var sequence = remoteSequence;
      status.style.display = 'block';
      status.textContent = 'Memuat pilihan…';

      var request = window.XMLHttpRequest ? new XMLHttpRequest() : null;
      if (!request) {
        applyFilter(keyword.toLowerCase());
        return;
      }
      request.open('GET', url, true);
      request.onreadystatechange = function () {
        if (request.readyState !== 4) return;
        if (sequence !== remoteSequence) return;
        var payload = null;
        try {
          payload = JSON.parse(request.responseText);
        } catch (error) {
          payload = null;
        }
        if (!payload || !payload.options) {
          status.style.display = 'block';
          status.textContent = 'Daftar pilihan gagal dimuat. Coba ketik ulang.';
          return;
        }
        payload.options.forEach(function (option) {
          addOption(option.value, option.label);
        });
        options = Array.prototype.slice.call(select.querySelectorAll('option'));
        refreshItems();
        openDropdown();
      };
      request.send();
    }

    function scheduleRemoteSearch() {
      if (!remoteUrl) return;
      if (remoteTimer) clearTimeout(remoteTimer);
      remoteTimer = setTimeout(function () {
        fetchRemote(input.value.trim());
      }, 280);
    }

    function matches(item, keyword) {
      return item.textContent.toLowerCase().indexOf(keyword) !== -1;
    }

    function applyFilter(keyword) {
      var visible = 0;
      for (var i = 0; i < items.length; i += 1) {
        if (matches(items[i], keyword)) {
          items[i].classList.remove('is-hidden');
          visible += 1;
        } else {
          items[i].classList.add('is-hidden');
        }
      }
      status.textContent = visible === 0 ? 'Tidak ada pilihan yang cocok.' : '';
      status.style.display = visible === 0 ? 'block' : 'none';
      dropdown.scrollTop = 0;
    }

    function openDropdown() {
      applyFilter(input.value.trim().toLowerCase());
      dropdown.style.display = 'block';
      input.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
      dropdown.style.display = 'none';
      input.setAttribute('aria-expanded', 'false');
    }

    function syncFromSelect() {
      var selected = select.options[select.selectedIndex];
      input.value = '';
      input.setAttribute('placeholder', selected && selected.value !== '' ? selected.text : EMPTY_PLACEHOLDER);
      closeDropdown();
    }

    if (select.hasAttribute('required')) {
      input.setAttribute('data-searchable-required', '1');
    }

    buildItems();
    syncFromSelect();

    input.addEventListener('focus', function () {
      openDropdown();
      if (remoteUrl) scheduleRemoteSearch();
    });

    input.addEventListener('input', function () {
      var keyword = input.value.trim();
      if (!keyword) {
        syncFromSelect();
        openDropdown();
        if (remoteUrl) scheduleRemoteSearch();
        return;
      }
      if (remoteUrl) {
        if (dropdown.style.display === 'none') openDropdown();
        scheduleRemoteSearch();
        return;
      }
      applyFilter(keyword.toLowerCase());
      if (dropdown.style.display === 'none') openDropdown();
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeDropdown();
        return;
      }
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      event.preventDefault();
      if (dropdown.style.display === 'none') openDropdown();
      var active = dropdown.querySelector('.searchable-select-option.is-active');
      var candidates = [];
      for (var i = 0; i < items.length; i += 1) {
        if (!items[i].classList.contains('is-hidden')) candidates.push(items[i]);
      }
      if (!candidates.length) return;
      var position = active ? candidates.indexOf(active) : -1;
      position = event.key === 'ArrowDown'
        ? (position + 1) % candidates.length
        : (position <= 0 ? candidates.length : position) - 1;
      if (active) active.classList.remove('is-active');
      candidates[position].classList.add('is-active');
      candidates[position].scrollIntoView({ block: 'nearest' });
    });

    select.addEventListener('change', syncFromSelect);

    document.addEventListener('click', function (event) {
      if (!container.contains(event.target)) closeDropdown();
    });
  }

  Array.prototype.slice.call(form.querySelectorAll('select')).forEach(makeSearchable);
})();
