<?php
$average = (float) $summary['average_score'];
$totalResponses = (int) $summary['total_responses'];
$totalAnswers = array_sum($distribution);
$dimensionLabels = [
    'overall' => 'Keseluruhan',
    'gender' => 'Jenis Kelamin',
    'age' => 'Usia',
    'education' => 'Pendidikan',
    'job' => 'Pekerjaan',
    'service' => 'Sektor Layanan',
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Survei | <?= html_escape($agency) ?></title>
  <meta name="description" content="Dashboard publik hasil survei pelayanan DPMPTSP Provinsi Jawa Barat">
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
</head>
<body class="public-dashboard">
<header class="public-header">
  <div class="public-container public-nav">
    <a class="public-brand" href="<?= site_url('/') ?>" aria-label="Dashboard Survei">
      <img src="<?= base_url('assets/images/logo-dinas.png') ?>" alt="">
      <span>
        <strong>Dashboard Survei</strong>
        <small>DPMPTSP Jawa Barat</small>
      </span>
    </a>
    <nav class="public-nav-links" aria-label="Navigasi utama">
      <a href="#ringkasan">Ringkasan</a>
      <a href="#pilih-survei">Pilih Survei</a>
      <a href="#statistik">Statistik</a>
      <a href="#indikator">Indikator</a>
      <a href="<?= site_url('admin/login') ?>">Backoffice</a>
      <a class="btn btn-primary btn-sm" href="<?= site_url('surveys') ?>">Isi survei</a>
    </nav>
  </div>
</header>

<main>
  <section class="public-hero" id="ringkasan">
    <div class="public-container hero-grid">
      <div class="hero-copy">
        <span class="public-eyebrow">Evaluasi Pelayanan Terpadu <?= (int) $year ?></span>
        <h1>Kualitas dan integritas pelayanan, terlihat dari data.</h1>
        <p>
          Dashboard ini menyajikan hasil Survei Kepuasan Masyarakat dan
          Survei Persepsi Anti Korupsi dalam satu tampilan yang dapat difilter.
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= site_url('surveys') ?>">Pilih survei</a>
          <a class="btn btn-light" href="#statistik">Lihat statistik</a>
        </div>
        <div class="hero-note">
          <span><?= count($question_definitions) ?> indikator aktif</span>
          <span>Skala 25–100</span>
          <span>Data diperbarui otomatis</span>
        </div>
      </div>

      <div class="score-showcase" aria-label="<?= html_escape($index_label) ?> tahun <?= (int) $year ?>">
        <div class="score-ring">
          <span><?= html_escape($index_label) ?></span>
          <strong><?= $totalResponses ? number_format($average, 2) : '—' ?></strong>
          <small><?= html_escape($category['label']) ?></small>
        </div>
        <div class="score-context">
          <b><?= number_format($totalResponses) ?> responden</b>
          <span>Periode Januari–Desember <?= (int) $year ?></span>
        </div>
      </div>
    </div>
  </section>

  <section class="public-section public-summary-section">
    <div class="public-container">
      <?php if (!$totalResponses): ?>
        <div class="public-empty-note">
          <div>
            <strong>Belum ada respons untuk ukuran ini pada <?= (int) $year ?>.</strong>
            <span>Grafik akan terisi otomatis setelah survei pertama dikirim.</span>
          </div>
          <a class="btn btn-primary btn-sm" href="<?= site_url('surveys') ?>">Pilih survei</a>
        </div>
      <?php endif; ?>

      <div class="stats-grid public-stats" aria-label="Ringkasan hasil survei">
        <article class="stat-card public-stat-card">
          <span>Nilai rata-rata</span>
          <strong><?= $totalResponses ? number_format($average, 2) : '—' ?></strong>
          <small style="color:<?= html_escape($category['color']) ?>"><?= html_escape($category['label']) ?></small>
        </article>
        <article class="stat-card public-stat-card">
          <span>Total responden</span>
          <strong><?= number_format($totalResponses) ?></strong>
          <small>Respons tahun <?= (int) $year ?></small>
        </article>
        <article class="stat-card public-stat-card">
          <span>Nilai tertinggi</span>
          <strong><?= $totalResponses ? number_format((float) $summary['maximum_score'], 2) : '—' ?></strong>
          <small>Skala indeks 25–100</small>
        </article>
        <article class="stat-card public-stat-card">
          <span>Nilai terendah</span>
          <strong><?= $totalResponses ? number_format((float) $summary['minimum_score'], 2) : '—' ?></strong>
          <small>Skala indeks 25–100</small>
        </article>
      </div>
    </div>
  </section>

  <section class="public-section public-shortcut-section" id="pilih-survei">
    <div class="public-container">
      <div class="public-section-heading">
        <div>
          <span class="section-kicker">Pintasan survei</span>
          <h2>Pilih formulir yang sesuai.</h2>
          <p>Formulir aktif ditampilkan otomatis dari backoffice. Survei SKM meminta nomor resi izin yang sudah terbit.</p>
        </div>
        <?php if ($public_form_count > count($public_forms)): ?>
          <a class="btn btn-primary btn-sm" href="<?= site_url('surveys') ?>">Lihat semua <?= number_format($public_form_count) ?> formulir</a>
        <?php endif; ?>
      </div>

      <?php if ($public_forms): ?>
        <div class="survey-shortcut-grid dashboard-shortcut-grid">
          <?php foreach ($public_forms as $form): ?>
            <?php
              $surveyUrl = site_url('survey') . '?form=' . rawurlencode($form['form_code']);
              $visibleNames = array_slice($form['survey_names'], 0, 2);
              $remainingNames = max(0, count($form['survey_names']) - count($visibleNames));
            ?>
            <article class="survey-shortcut-card" style="--card-accent:<?= html_escape($form['accent_color']) ?>">
              <div class="shortcut-card-top">
                <span class="shortcut-kind"><?= $form['survey_count'] > 1 ? 'Survei gabungan' : 'Survei mandiri' ?></span>
                <?php if ($form['is_default']): ?><span class="shortcut-default">Form utama</span><?php endif; ?>
              </div>
              <div class="shortcut-card-copy">
                <h3><?= html_escape($form['form_name']) ?></h3>
                <p><?= html_escape($form['description'] ?: 'Formulir evaluasi pelayanan publik.') ?></p>
              </div>
              <div class="shortcut-surveys" aria-label="Survei di dalam formulir">
                <?php foreach ($visibleNames as $surveyName): ?>
                  <span><?= html_escape($surveyName) ?></span>
                <?php endforeach; ?>
                <?php if ($remainingNames): ?><span>+<?= $remainingNames ?> lainnya</span><?php endif; ?>
              </div>
              <div class="shortcut-meta">
                <span><?= number_format($form['question_count']) ?> pertanyaan</span>
                <span><?= $form['requires_resi'] ? 'Nomor resi wajib' : 'Tanpa nomor resi SKM' ?></span>
              </div>
              <a class="btn btn-primary shortcut-open" href="<?= $surveyUrl ?>">
                <?= $form['requires_resi'] ? 'Masukkan resi dan isi' : 'Isi survei ini' ?>
              </a>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if ($public_form_count <= count($public_forms)): ?>
          <div class="shortcut-all-link">
            <a href="<?= site_url('surveys') ?>">Buka halaman daftar survei</a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="catalog-empty">
          <strong>Belum ada formulir yang siap diisi.</strong>
          <p>Formulir akan tampil otomatis setelah diaktifkan dan memiliki pertanyaan aktif.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="public-section" id="statistik">
    <div class="public-container">
      <div class="public-section-heading">
        <div>
          <span class="section-kicker">Statistik Survei</span>
          <h2>Perkembangan <?= html_escape($index_label) ?></h2>
          <p>Pilih tahun dan kelompok data untuk melihat perbandingan nilai rata-rata setiap bulan.</p>
        </div>
        <span class="data-badge">Data agregat · tanpa identitas responden</span>
      </div>

      <form method="get" action="<?= site_url('/') ?>" class="filter-card dashboard-chart-filter public-chart-filter">
        <div class="dashboard-unit-filter">
          <label for="survey_id">Ukuran survei</label>
          <select id="survey_id" name="survey_id">
            <option value="0" <?= (int) $survey_id === 0 ? 'selected' : '' ?>>Semua hasil survei</option>
            <?php foreach ($surveys as $value => $survey): ?>
              <option value="<?= (int) $value ?>" <?= (int) $survey_id === (int) $value ? 'selected' : '' ?>><?= html_escape($survey['index_label']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="filter-help">Dashboard dibuka pada SKM lama secara default agar tren historis tetap konsisten.</small>
        </div>
        <div class="dashboard-year-filter">
          <label for="year">Tahun laporan</label>
          <select id="year" name="year">
            <?php for ($optionYear = (int) date('Y'); $optionYear >= ((int) date('Y') - 5); $optionYear--): ?>
              <option value="<?= $optionYear ?>" <?= $optionYear === $year ? 'selected' : '' ?>><?= $optionYear ?></option>
            <?php endfor; ?>
          </select>
          <small class="filter-help">Tentukan tahun data yang ingin ditampilkan.</small>
        </div>
        <div class="dashboard-unit-filter">
          <label for="unit_id">Perangkat daerah</label>
          <select id="unit_id" name="unit_id">
            <option value="0">Seluruh Perangkat Daerah</option>
            <?php foreach ($units as $value => $label): ?>
              <option value="<?= (int) $value ?>" <?= (int) $unit_id === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="filter-help">Batasi data berdasarkan perangkat daerah pengelola izin.</small>
        </div>
        <fieldset class="chart-filter-options">
          <legend>Tampilan grafik</legend>
          <?php foreach ($dimensionLabels as $value => $label): ?>
            <label class="chart-filter-option">
              <input type="radio" name="dimension" value="<?= html_escape($value) ?>" <?= $dimension === $value ? 'checked' : '' ?>>
              <span><?= html_escape($label) ?></span>
            </label>
          <?php endforeach; ?>
          <small class="filter-help">Pilih cara pengelompokan nilai pada grafik.</small>
        </fieldset>
        <button class="btn btn-primary btn-sm" type="submit">Tampilkan</button>
      </form>

      <div class="dashboard-grid public-chart-grid">
        <section class="panel public-panel">
          <div class="panel-head">
            <div>
              <h2><?= html_escape($chart_data['title']) ?></h2>
              <p><?= html_escape($unit_label) ?> · Tahun <?= (int) $year ?></p>
            </div>
            <span class="badge"><?= html_escape($dimensionLabels[$dimension]) ?></span>
          </div>
          <div id="public-ipak-chart" class="highchart-frame" role="img" aria-label="<?= html_escape($chart_data['title']) ?>"></div>
        </section>

        <section class="panel public-panel">
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
          <div class="distribution-total">
            <span>Total jawaban terukur</span>
            <strong><?= number_format($totalAnswers) ?></strong>
          </div>
        </section>
      </div>
    </div>
  </section>

  <section class="public-section indicator-section" id="indikator">
    <div class="public-container">
      <div class="public-section-heading">
        <div>
          <span class="section-kicker"><?= count($question_definitions) ?> indikator terpilih</span>
          <h2>Nilai setiap indikator <?= html_escape($index_label) ?></h2>
          <p>Skor setiap jawaban dikonversi ke skala 0–100 sesuai konfigurasi pertanyaannya.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= site_url('surveys') ?>">Pilih survei</a>
      </div>

      <div class="indicator-public-grid">
        <?php foreach ($question_definitions as $questionId => $question): ?>
          <?php $score = (float) (isset($question_averages[$questionId]) ? $question_averages[$questionId] : 0); ?>
          <article class="indicator-public-card">
            <div class="indicator-card-head">
              <span><?= html_escape($question['question_code']) ?></span>
              <strong><?= $totalResponses ? number_format($score, 2) : '—' ?></strong>
            </div>
            <div class="indicator-taxonomy">
              <b><?= html_escape($question['measurement_name']) ?></b>
              <small><?= html_escape($question['category_name']) ?></small>
            </div>
            <p><?= html_escape($question['question_text']) ?></p>
            <div class="mini-track" aria-hidden="true">
              <div class="mini-fill" style="width:<?= max(0, min(100, $score)) ?>%"></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="public-cta">
    <div class="public-container cta-inner">
      <div>
        <span class="section-kicker">Partisipasi Anda berarti</span>
        <h2>Bantu kami menjaga layanan tetap bersih dan transparan.</h2>
        <p>Pengisian membutuhkan sekitar empat menit dan terdiri dari <?= count($question_definitions) ?> pertanyaan.</p>
      </div>
      <a class="btn btn-light" href="<?= site_url('surveys') ?>">Pilih dan isi survei</a>
    </div>
  </section>
</main>

<footer class="public-footer">
  <div class="public-container">
    <span>© <?= date('Y') ?> DPMPTSP Provinsi Jawa Barat</span>
    <span>Dashboard Survei Pelayanan Terpadu</span>
  </div>
</footer>

<script src="<?= base_url('assets/chart/code/highcharts.js') ?>"></script>
<script src="<?= base_url('assets/chart/code/modules/series-label.js') ?>"></script>
<script src="<?= base_url('assets/chart/code/modules/exporting.js') ?>"></script>
<script src="<?= base_url('assets/chart/code/modules/export-data.js') ?>"></script>
<script>
(function () {
  var chartData = <?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var chartTarget = document.getElementById('public-ipak-chart');
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

  Highcharts.chart('public-ipak-chart', {
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
      style: { color: '#667085', fontSize: '10px' }
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
</body>
</html>
