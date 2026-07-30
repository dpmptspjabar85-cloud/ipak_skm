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
})();
