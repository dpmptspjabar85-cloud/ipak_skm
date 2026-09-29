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
    return valid;
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
  function makeSearchable(select) {
    if (select.classList.contains('searchable-select-hidden')) return;
    var originalId = select.id;
    var isRequired = select.hasAttribute('required');
    var container = document.createElement('div');
    container.className = 'searchable-select';

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'searchable-select-input';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('placeholder', 'Pilih atau cari…');
    if (isRequired) input.required = true;

    var dropdown = document.createElement('div');
    dropdown.className = 'searchable-select-dropdown';

    var caret = document.createElement('span');
    caret.className = 'searchable-select-caret';

    select.classList.add('searchable-select-hidden');
    select.parentNode.insertBefore(container, select);
    container.appendChild(input);
    container.appendChild(dropdown);
    container.appendChild(caret);
    if (originalId) input.id = originalId + '-search';

    var options = Array.prototype.slice.call(select.querySelectorAll('option'));

    function renderOptions() {
      dropdown.innerHTML = '';
      options.forEach(function (opt) {
        var item = document.createElement('div');
        item.className = 'searchable-select-option';
        item.textContent = opt.text;
        item.dataset.value = opt.value;
        item.addEventListener('click', function () {
          select.value = opt.value;
          input.value = opt.text;
          dropdown.style.display = 'none';
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        dropdown.appendChild(item);
      });
    }

    renderOptions();

    input.addEventListener('focus', function () {
      dropdown.style.display = 'block';
    });

    input.addEventListener('input', function () {
      var keyword = input.value.toLowerCase();
      var items = dropdown.querySelectorAll('.searchable-select-option');
      items.forEach(function (item) {
        item.style.display = item.textContent.toLowerCase().indexOf(keyword) !== -1
          ? 'block'
          : 'none';
      });
    });

    var firstOption = options[0];
    if (firstOption && !firstOption.value) {
      firstOption = options[0];
    }

    var syncFromSelect = function () {
      var selected = select.options[select.selectedIndex];
      if (selected && selected.value !== '') {
        input.value = '';
        input.setAttribute('placeholder', selected.text);
      } else {
        input.value = '';
        input.setAttribute('placeholder', 'Pilih atau cari…');
      }
    };

    syncFromSelect();
    select.addEventListener('change', syncFromSelect);

    document.addEventListener('click', function (e) {
      if (!container.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  Array.prototype.slice.call(form.querySelectorAll('select')).forEach(makeSearchable);
})();
