<?php
$average = (float) $summary['average_score'];
$totalAnswers = array_sum($distribution);
$allDimensionLabels = [
    'overall' => 'Keseluruhan',
    'gender' => 'Jenis Kelamin',
    'age' => 'Usia',
    'education' => 'Pendidikan',
    'job' => 'Pekerjaan',
    'service' => 'Sektor Layanan',
];
$dimensionLabels = [];
foreach ($available_dimensions as $availableDimension) {
    if (isset($allDimensionLabels[$availableDimension])) {
        $dimensionLabels[$availableDimension] = $allDimensionLabels[$availableDimension];
    }
}
$showSurveyFilter = count($surveys) > 1;
$showYearFilter = count($available_years) > 1;
$showUnitFilter = count($units) > 0;
$showDimensionFilter = count($dimensionLabels) > 1;
$showDashboardFilters = $has_data && (
    $showSurveyFilter
    || $showYearFilter
    || $showUnitFilter
    || $showDimensionFilter
);
?>
<?php if ($showDashboardFilters): ?>
  <form method="get" action="<?= site_url('admin/dashboard') ?>" class="filter-card dashboard-chart-filter dashboard-auto-filter">
    <?php if ($showSurveyFilter): ?>
      <div class="dashboard-unit-filter">
        <label for="survey_id">Ukuran survei</label>
        <select id="survey_id" name="survey_id">
          <option value="0" <?= (int) $survey_id === 0 ? 'selected' : '' ?>>Semua hasil survei</option>
          <?php foreach ($surveys as $value => $survey): ?>
            <option value="<?= (int) $value ?>" <?= (int) $survey_id === (int) $value ? 'selected' : '' ?>><?= html_escape($survey['index_label']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="filter-help">Hanya survei yang mempunyai respons pada tahun terpilih.</small>
      </div>
    <?php elseif ((int) $survey_id > 0): ?>
      <input type="hidden" name="survey_id" value="<?= (int) $survey_id ?>">
    <?php endif; ?>

    <?php if ($showYearFilter): ?>
      <div class="dashboard-year-filter">
        <label for="year">Tahun laporan</label>
        <select id="year" name="year">
          <?php foreach ($available_years as $optionYear): ?>
            <option value="<?= (int) $optionYear ?>" <?= (int) $optionYear === (int) $year ? 'selected' : '' ?>><?= (int) $optionYear ?></option>
          <?php endforeach; ?>
        </select>
        <small class="filter-help">Hanya tahun yang sudah mempunyai respons.</small>
      </div>
    <?php else: ?>
      <input type="hidden" name="year" value="<?= (int) $year ?>">
    <?php endif; ?>

    <?php if ($showUnitFilter): ?>
      <div class="dashboard-unit-filter">
        <label for="unit_id">Perangkat daerah</label>
        <select id="unit_id" name="unit_id">
          <option value="0">Seluruh perangkat daerah</option>
          <?php foreach ($units as $value => $label): ?>
            <option value="<?= (int) $value ?>" <?= (int) $unit_id === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="filter-help">Diambil otomatis dari respons pada tahun dan survei terpilih.</small>
      </div>
    <?php elseif ((int) $unit_id > 0): ?>
      <input type="hidden" name="unit_id" value="<?= (int) $unit_id ?>">
    <?php endif; ?>

    <?php if ($showDimensionFilter): ?>
      <fieldset class="chart-filter-options">
        <legend>Tampilan grafik</legend>
        <?php foreach ($dimensionLabels as $value => $label): ?>
          <label class="chart-filter-option">
            <input type="radio" name="dimension" value="<?= html_escape($value) ?>" <?= $dimension === $value ? 'checked' : '' ?>>
            <span><?= html_escape($label) ?></span>
          </label>
        <?php endforeach; ?>
        <small class="filter-help">Pilihan hanya muncul jika datanya tersedia.</small>
      </fieldset>
    <?php else: ?>
      <input type="hidden" name="dimension" value="<?= html_escape($dimension) ?>">
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" type="submit">Tampilkan</button>
  </form>
<?php elseif (!$has_data): ?>
  <div class="alert question-readonly-note">Belum ada respons survei. Filter akan muncul otomatis setelah data pertama tersimpan.</div>
<?php endif; ?>

<section class="stats-grid" aria-label="Ringkasan survei">
  <article class="stat-card">
    <span><?= html_escape($index_label) ?></span>
    <strong><?= number_format($average, 2) ?></strong>
    <small style="color:<?= html_escape($category['color']) ?>;font-weight:800"><?= html_escape($category['label']) ?></small>
  </article>
  <article class="stat-card">
    <span>Total responden</span>
    <strong><?= number_format((int) $summary['total_responses']) ?></strong>
    <small>Respons pada tahun <?= (int) $year ?></small>
  </article>
  <article class="stat-card">
    <span>Nilai tertinggi</span>
    <strong><?= number_format((float) $summary['maximum_score'], 2) ?></strong>
    <small>Skala indeks 25–100</small>
  </article>
  <article class="stat-card">
    <span>Nilai terendah</span>
    <strong><?= number_format((float) $summary['minimum_score'], 2) ?></strong>
    <small>Skala indeks 25–100</small>
  </article>
</section>

<div class="dashboard-grid">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2><?= html_escape($chart_data['title']) ?></h2>
        <p><?= html_escape($unit_label) ?> · Tahun <?= (int) $year ?></p>
      </div>
      <span class="badge"><?= (int) $year ?> · <?= html_escape($dimensionLabels[$dimension]) ?></span>
    </div>
    <div id="skm-comparison-chart" class="highchart-frame" role="img" aria-label="<?= html_escape($chart_data['title']) ?>"></div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Distribusi jawaban</h2>
        <p>Akumulasi seluruh indikator</p>
      </div>
    </div>
    <div class="distribution">
      <?php foreach ($answer_labels as $value => $label): ?>
        <?php $percent = $totalAnswers ? (($distribution[$value] / $totalAnswers) * 100) : 0; ?>
        <div class="dist-row">
          <span><?= html_escape($label) ?></span>
          <div class="dist-track"><div class="dist-fill" style="width:<?= round($percent, 2) ?>%"></div></div>
          <b><?= number_format($percent, 0) ?>%</b>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Nilai per indikator</h2>
      <p>Skor rata-rata setiap pertanyaan pada skala 25–100</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/questions') ?>">Lihat pertanyaan</a>
  </div>
  <div class="indicator-list">
    <?php foreach ($question_definitions as $questionId => $question): ?>
      <?php $score = (float) (isset($question_averages[$questionId]) ? $question_averages[$questionId] : 0); ?>
      <div class="indicator-row">
        <span class="indicator-index"><?= html_escape($question['question_code']) ?></span>
        <div class="indicator-copy">
          <p title="<?= html_escape($question['question_text']) ?>">
            <b><?= html_escape($question['measurement_name']) ?></b> · <?= html_escape($question['question_text']) ?>
          </p>
          <div class="mini-track"><div class="mini-fill" style="width:<?= max(0, min(100, $score)) ?>%"></div></div>
        </div>
        <span class="indicator-score"><?= number_format($score, 2) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<script src="<?= base_url('assets/chart/code/highcharts.js') ?>"></script>
<script src="<?= base_url('assets/chart/code/modules/series-label.js') ?>"></script>
<script src="<?= base_url('assets/chart/code/modules/exporting.js') ?>"></script>
<script src="<?= base_url('assets/chart/code/modules/export-data.js') ?>"></script>
<script>
(function () {
  var chartData = <?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var chartTarget = document.getElementById('skm-comparison-chart');
  if (!chartTarget || typeof Highcharts === 'undefined') return;

  Highcharts.setOptions({
    lang: {
      downloadPNG: 'Unduh PNG',
      downloadJPEG: 'Unduh JPEG',
      downloadPDF: 'Unduh PDF',
      downloadSVG: 'Unduh SVG',
      downloadCSV: 'Unduh CSV',
      downloadXLS: 'Unduh XLS',
      viewData: 'Lihat tabel data',
      hideData: 'Sembunyikan tabel data',
      printChart: 'Cetak grafik'
    },
    colors: ['#3049d8', '#12a594', '#e59b2f', '#8b5cf6', '#e35757', '#1594c7', '#64748b']
  });

  Highcharts.chart('skm-comparison-chart', {
    chart: {
      type: chartData.type,
      height: chartData.dimension === 'service' ? Math.max(420, chartData.categories.length * 55) : null,
      backgroundColor: 'transparent',
      spacing: [16, 12, 10, 10]
    },
    title: { text: null },
    subtitle: {
      text: '<?= html_escape($unit_label) ?> · Tahun <?= (int) $year ?>',
      align: 'left',
      style: { color: '#667085', fontSize: '11px' }
    },
    credits: { enabled: false },
    exporting: {
      enabled: true,
      filename: 'grafik-survei-<?= (int) $survey_id ?>-<?= html_escape($dimension) ?>-<?= (int) $year ?>'
    },
    xAxis: {
      categories: chartData.categories,
      lineColor: '#dfe3eb',
      tickColor: '#dfe3eb'
    },
    yAxis: {
      min: 0,
      max: 100,
      tickInterval: 20,
      title: { text: <?= json_encode($index_label) ?> },
      gridLineColor: '#edf0f5'
    },
    legend: {
      align: 'center',
      verticalAlign: 'bottom',
      itemStyle: { color: '#344054', fontSize: '10px' }
    },
    tooltip: {
      shared: true,
      useHTML: true,
      formatter: function () {
        var output = '<strong>' + this.x + '</strong><br>';
        var points = this.points || (this.point ? [this.point] : []);
        points.forEach(function (point) {
          var counts = point.series.options.responseCounts || [];
          var count = counts[point.point.index] || 0;
          output += '<span style="color:' + point.color + '">●</span> ' +
            point.series.name + ': <strong>' + Highcharts.numberFormat(point.y, 2) +
            '</strong> <small>(' + count + ' respons)</small><br>';
        });
        return output;
      }
    },
    plotOptions: {
      column: {
        borderWidth: 0,
        borderRadius: 3,
        groupPadding: 0.12
      },
      spline: {
        lineWidth: 3,
        marker: { radius: 4 }
      },
      line: {
        lineWidth: 3,
        marker: { radius: 4 }
      },
      bar: {
        borderWidth: 0,
        groupPadding: 0.1
      },
      series: {
        animation: { duration: 500 },
        dataLabels: {
          enabled: chartData.dimension === 'overall',
          format: '{y:.1f}',
          style: { fontSize: '9px', textOutline: 'none', color: '#344054' }
        }
      }
    },
    series: chartData.series
  });
})();
</script>
