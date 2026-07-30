(function () {
  'use strict';

  var form = document.getElementById('form-builder');
  if (!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('[data-builder-step]'));
  var progressItems = Array.prototype.slice.call(document.querySelectorAll('[data-step-jump]'));
  var currentStep = 0;
  var highestStep = 0;
  var customCounters = {
    initial: document.querySelectorAll('#initial-custom-list .custom-field-builder-row').length,
    identity: document.querySelectorAll('#identity-custom-list .custom-field-builder-row').length
  };
  var questionCounter = document.querySelectorAll('#inline-question-list .inline-question-card').length;

  function showStep(index) {
    currentStep = Math.max(0, Math.min(index, steps.length - 1));
    highestStep = Math.max(highestStep, currentStep);
    steps.forEach(function (step, stepIndex) {
      step.classList.toggle('is-active', stepIndex === currentStep);
    });
    progressItems.forEach(function (item, itemIndex) {
      item.classList.toggle('is-active', itemIndex === currentStep);
      item.classList.toggle('is-complete', itemIndex < currentStep);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function validateStep(index) {
    var step = steps[index];
    if (index === 0) {
      var checked = step.querySelector('input[name="initial_fields[]"]:checked');
      var customLabels = Array.prototype.slice.call(step.querySelectorAll('#initial-custom-list input[name$="[label]"]'));
      var hasCustom = customLabels.some(function (input) { return input.value.trim() !== ''; });
      if (!checked && !hasCustom) {
        window.alert('Pilih atau buat minimal satu input awal wajib.');
        return false;
      }
    }
    var requiredFields = Array.prototype.slice.call(step.querySelectorAll('[required]'));
    for (var i = 0; i < requiredFields.length; i++) {
      if (!requiredFields[i].checkValidity()) {
        requiredFields[i].reportValidity();
        return false;
      }
    }
    return true;
  }

  form.addEventListener('click', function (event) {
    var next = event.target.closest('[data-builder-next]');
    var back = event.target.closest('[data-builder-back]');
    var removeRow = event.target.closest('[data-remove-row]');
    var removeQuestion = event.target.closest('[data-remove-question]');
    if (next) {
      event.preventDefault();
      if (validateStep(currentStep)) showStep(currentStep + 1);
    }
    if (back) {
      event.preventDefault();
      showStep(currentStep - 1);
    }
    if (removeRow) {
      event.preventDefault();
      var row = removeRow.closest('.custom-field-builder-row');
      if (row) row.remove();
    }
    if (removeQuestion) {
      event.preventDefault();
      var card = removeQuestion.closest('.inline-question-card');
      if (card) card.remove();
      renumberQuestions();
    }
  });

  progressItems.forEach(function (item) {
    item.addEventListener('click', function () {
      var target = parseInt(item.getAttribute('data-step-jump'), 10);
      if (target <= highestStep) {
        showStep(target);
      } else if (target === currentStep + 1 && validateStep(currentStep)) {
        showStep(target);
      }
    });
  });

  function refreshOptionVisibility(row) {
    var typeSelect = row.querySelector('select[name$="[type]"], select[data-name="type"]');
    var optionsField = row.querySelector('.custom-options-field');
    if (!typeSelect || !optionsField) return;
    optionsField.hidden = typeSelect.value !== 'select';
    typeSelect.addEventListener('change', function () {
      optionsField.hidden = typeSelect.value !== 'select';
    });
  }

  Array.prototype.slice.call(document.querySelectorAll('.custom-field-builder-row')).forEach(refreshOptionVisibility);

  function addCustomField(group) {
    var template = document.getElementById('custom-field-template');
    var list = document.getElementById(group + '-custom-list');
    if (!template || !list) return;
    var fragment = document.importNode(template.content, true);
    var row = fragment.querySelector('.custom-field-builder-row');
    var index = customCounters[group]++;
    var prefix = group === 'initial' ? 'initial_custom' : 'identity_custom';
    Array.prototype.slice.call(row.querySelectorAll('[data-name]')).forEach(function (input) {
      input.name = prefix + '[' + index + '][' + input.getAttribute('data-name') + ']';
    });
    if (group === 'initial') {
      var modeHolder = row.querySelector('.custom-mode-holder');
      if (modeHolder) modeHolder.hidden = true;
    }
    list.appendChild(fragment);
    refreshOptionVisibility(list.lastElementChild);
    var firstInput = list.lastElementChild.querySelector('input');
    if (firstInput) firstInput.focus();
  }

  Array.prototype.slice.call(document.querySelectorAll('[data-add-custom]')).forEach(function (button) {
    button.addEventListener('click', function () {
      addCustomField(button.getAttribute('data-add-custom'));
    });
  });

  function renumberQuestions() {
    Array.prototype.slice.call(document.querySelectorAll('#inline-question-list .inline-question-card')).forEach(function (card, index) {
      var number = card.querySelector('[data-question-number]');
      if (number) number.textContent = index + 1;
    });
  }

  function addQuestion() {
    var template = document.getElementById('inline-question-template');
    var list = document.getElementById('inline-question-list');
    if (!template || !list) return;
    var fragment = document.importNode(template.content, true);
    var card = fragment.querySelector('.inline-question-card');
    var index = questionCounter++;
    Array.prototype.slice.call(card.querySelectorAll('[data-question-name]')).forEach(function (input) {
      input.name = 'new_questions[' + index + '][' + input.getAttribute('data-question-name') + ']';
    });
    Array.prototype.slice.call(card.querySelectorAll('[data-option-name]')).forEach(function (input) {
      input.name = 'new_questions[' + index + '][options][' + input.getAttribute('data-option-index') + '][' + input.getAttribute('data-option-name') + ']';
    });
    list.appendChild(fragment);
    renumberQuestions();
    var firstInput = list.lastElementChild.querySelector('textarea');
    if (firstInput) firstInput.focus();
  }

  var addQuestionButton = document.getElementById('add-inline-question');
  if (addQuestionButton) addQuestionButton.addEventListener('click', addQuestion);

  function toCode(value) {
    return value.toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
  }

  var formName = form.querySelector('[name="form_name"]');
  var formCode = form.querySelector('[name="form_code"]');
  var surveyName = form.querySelector('[name="survey_name"]');
  var surveyCode = form.querySelector('[name="survey_code"]');
  var lastAutoSurveyName = '';
  var lastAutoFormCode = '';
  var lastAutoSurveyCode = '';
  if (formName) {
    formName.addEventListener('input', function () {
      var generatedCode = toCode(formName.value);
      if (surveyName && (surveyName.value === '' || surveyName.value === lastAutoSurveyName)) {
        surveyName.value = formName.value;
        lastAutoSurveyName = formName.value;
      }
      if (formCode && (formCode.value === '' || formCode.value === lastAutoFormCode)) {
        formCode.value = generatedCode;
        lastAutoFormCode = generatedCode;
      }
      if (surveyCode && (surveyCode.value === '' || surveyCode.value === lastAutoSurveyCode)) {
        surveyCode.value = generatedCode.slice(0, 24);
        lastAutoSurveyCode = generatedCode.slice(0, 24);
      }
    });
  }

  form.addEventListener('submit', function (event) {
    if (!validateStep(2) || !form.checkValidity()) {
      event.preventDefault();
      var invalid = form.querySelector(':invalid');
      if (invalid) {
        showStep(parseInt(invalid.closest('[data-builder-step]').getAttribute('data-builder-step'), 10));
        invalid.reportValidity();
      }
      return;
    }
    var button = form.querySelector('[type="submit"]');
    if (button) {
      button.disabled = true;
      button.textContent = 'Membuat form...';
    }
  });

  showStep(0);
})();
