<?php
$coreFields = ['name', 'email', 'phone', 'identity_number', 'age', 'gender', 'education', 'job', 'service'];
?>
<div class="field-grid">
  <?php foreach ($respondentFields as $fieldKey => $setting): ?>
    <?php
    if ($setting['field_mode'] === 'hidden' || $setting['field_group'] !== $active_group) {
        continue;
    }
    $required = $setting['field_mode'] === 'required';
    $isCore = in_array($fieldKey, $coreFields, true);
    $inputName = $isCore ? $fieldKey : 'extra_' . $fieldKey;
    $fieldType = isset($setting['field_type']) ? $setting['field_type'] : 'text';
    $value = isset($old[$inputName]) ? $old[$inputName] : '';
    $readonly = $requires_resi
        && in_array($fieldKey, ['name', 'email', 'phone', 'identity_number'], true)
        && trim((string) $value) !== '';
    $fullWidth = in_array($fieldType, ['textarea'], true) || $fieldKey === 'gender';
    ?>

    <?php if ($fieldKey === 'gender'): ?>
      <div class="field full">
        <span class="field-label"><?= html_escape($setting['field_label']) ?><?php if ($required): ?> <span class="required-mark">*</span><?php endif; ?></span>
        <div class="choice-grid">
          <label class="choice-card"><input type="radio" name="gender" value="1" <?= $required ? 'required' : '' ?> <?= (string) $value === '1' ? 'checked' : '' ?>> Laki-laki</label>
          <label class="choice-card"><input type="radio" name="gender" value="2" <?= $required ? 'required' : '' ?> <?= (string) $value === '2' ? 'checked' : '' ?>> Perempuan</label>
        </div>
        <small class="field-help"><b>Petunjuk:</b> <?= html_escape($setting['help_text']) ?></small>
        <?php if (isset($validation_errors['gender'])): ?><small class="field-error"><?= html_escape(strip_tags($validation_errors['gender'])) ?></small><?php endif; ?>
      </div>

    <?php elseif (in_array($fieldKey, ['education', 'job', 'service'], true) || $fieldType === 'select'): ?>
      <?php
      $selectOptions = [];
      if ($fieldKey === 'education') {
          $selectOptions = $education;
      } elseif ($fieldKey === 'job') {
          $selectOptions = $jobs;
      } elseif ($fieldKey === 'service') {
          $selectOptions = $services;
      } elseif (!empty($setting['options'])) {
          foreach ($setting['options'] as $option) {
              $selectOptions[$option] = $option;
          }
      }
      ?>
      <?php if ($fieldKey === 'service' && $requires_resi): ?>
        <div class="field full">
          <span class="field-label"><?= html_escape($setting['field_label']) ?></span>
          <div class="readonly-service">
            <strong><?= html_escape($permit['sector_name'] ?: 'Sektor belum tercatat') ?></strong>
            <span><?= html_escape($permit['permit_name'] ?: 'Jenis izin belum tercatat') ?></span>
          </div>
          <small class="field-help"><b>Petunjuk:</b> Data layanan diambil otomatis dari nomor resi.</small>
        </div>
      <?php else: ?>
        <div class="field">
          <label for="<?= html_escape($inputName) ?>"><?= html_escape($setting['field_label']) ?><?php if ($required): ?> <span class="required-mark">*</span><?php endif; ?></label>
          <select id="<?= html_escape($inputName) ?>" name="<?= html_escape($inputName) ?>" <?= $required ? 'required' : '' ?>>
            <option value="">Pilih salah satu</option>
            <?php foreach ($selectOptions as $optionValue => $optionLabel): ?>
              <option value="<?= html_escape($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= html_escape($optionLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="field-help"><b>Petunjuk:</b> <?= html_escape($setting['help_text']) ?></small>
          <?php if (isset($validation_errors[$inputName])): ?><small class="field-error"><?= html_escape(strip_tags($validation_errors[$inputName])) ?></small><?php endif; ?>
        </div>
        <?php if ($fieldKey === 'job'): ?>
          <div class="field full" hidden>
            <label for="job_other">Pekerjaan lainnya <span class="required-mark">*</span></label>
            <input id="job_other" name="job_other" type="text" maxlength="100" value="<?= html_escape(isset($old['job_other']) ? $old['job_other'] : '') ?>" placeholder="Tuliskan pekerjaan Anda">
            <small class="field-help"><b>Petunjuk:</b> Isi apabila pilihan pekerjaan adalah “Lainnya”.</small>
            <?php if (isset($validation_errors['job_other'])): ?><small class="field-error"><?= html_escape(strip_tags($validation_errors['job_other'])) ?></small><?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php else: ?>
      <div class="field <?= $fullWidth ? 'full' : '' ?>">
        <label for="<?= html_escape($inputName) ?>"><?= html_escape($setting['field_label']) ?><?php if ($required): ?> <span class="required-mark">*</span><?php endif; ?></label>
        <?php if ($fieldType === 'textarea'): ?>
          <textarea id="<?= html_escape($inputName) ?>" name="<?= html_escape($inputName) ?>" maxlength="2000" <?= $required ? 'required' : '' ?> placeholder="Tuliskan <?= html_escape(strtolower($setting['field_label'])) ?>"><?= html_escape($value) ?></textarea>
        <?php else: ?>
          <?php
          $htmlType = in_array($fieldType, ['email', 'tel', 'number'], true) ? $fieldType : 'text';
          $maximum = $fieldKey === 'name' ? 100 : ($fieldKey === 'phone' ? 25 : ($fieldKey === 'identity_number' ? 20 : 255));
          ?>
          <input id="<?= html_escape($inputName) ?>" name="<?= html_escape($inputName) ?>" type="<?= html_escape($htmlType) ?>" maxlength="<?= (int) $maximum ?>" <?= $fieldKey === 'age' ? 'min="15" max="100"' : '' ?> <?= $required ? 'required' : '' ?> value="<?= html_escape($value) ?>" placeholder="Isi <?= html_escape(strtolower($setting['field_label'])) ?>" <?= $readonly ? 'readonly' : '' ?>>
        <?php endif; ?>
        <small class="field-help"><b>Petunjuk:</b> <?= html_escape($setting['help_text']) ?></small>
        <?php if (isset($validation_errors[$inputName])): ?><small class="field-error"><?= html_escape(strip_tags($validation_errors[$inputName])) ?></small><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
