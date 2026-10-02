<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Menerjemah data jawaban panjang menjadi matriks lebar.
 *
 * Aturan utamanya:
 *   1 NOMOR REFERENSI = 1 BARIS
 *   1 KODE PERTANYAAN  = 1 KOLOM
 *
 * Urutan prosesnya: screening struktur data, parsing field responden, parsing
 * kode pertanyaan, validasi duplikat, group by nomor referensi, transformasi
 * pertanyaan menjadi kolom, penyusunan kolom, lalu validasi hasil.
 *
 * Tidak ada kolom yang ditetapkan lebih dulu. Nama kolom identitas dan kolom
 * pertanyaan diambil dari data yang benar-benar ada; daftar $identityOrder
 * hanya menentukan urutan tampilan, bukan menyaring.
 */
class Response_matrix_builder
{
    /**
     * Urutan tampilan label identitas yang umum.
     *
     * @var array
     */
    private static $identityOrder = array(
        'Nama',
        'Surel',
        'Telepon',
        'Nomor Identitas',
        'Usia',
        'Jenis Kelamin',
        'Pendidikan',
        'Pekerjaan',
        'Perangkat Daerah',
    );

    /** @var object Model penyedia metadata dan master label. */
    private $model;

    /** @var array Bundle jawaban dari get_response_export_bundle(). */
    private $bundle;

    /** @var array Opsi tambahan. */
    private $options;

    /** @var array Master label pendidikan. */
    private $education = array();

    /** @var array Master label pekerjaan. */
    private $jobs = array();

    /** @var array Master label layanan. */
    private $services = array();

    /** @var array Master label perangkat daerah. */
    private $unitNames = array();

    /** @var array Label identitas yang ditemukan, urut saat kemunculan. */
    private $identityLabels = array();

    /** @var array Nama kolom tanggal hasil screening. */
    private $dateLabel = '';

    /** @var array Kolom pertanyaan urut kemunculan. */
    private $questionColumns = array();

    /** @var array Pemetaan kode pertanyaan ke nama kolom yang dipakainya. */
    private $columnByCode = array();

    /** @var array Nama kolom yang sudah dipakai, untuk menghindari bentrok nama. */
    private $usedColumns = array();

    /** @var array Catatan duplikat dan konflik. */
    private $audit = array();

    /** @var array Penghitung screening dan validasi. */
    private $counters = array(
        'bundle_rows' => 0,
        'answer_items' => 0,
        'distinct_pairs' => 0,
        'duplicate_identical' => 0,
        'duplicate_newest_wins' => 0,
        'duplicate_unresolved' => 0,
        'profile_multi_value' => 0,
        'empty_reference' => 0,
    );

    /** @var array Grup per nomor referensi, hasil group by. */
    private $groups = array();

    /** @var bool Tanda matriks sudah dibangun. */
    private $built = false;

    /** @var array|null Susunan kolom hasil akhir. */
    private $layout = null;

    /**
     * @param object $model   Model survei.
     * @param array  $bundle  Bundle jawaban.
     * @param array  $lookups education, jobs, services, unitNames.
     * @param array  $options include_scores(bool): sertakan kolom "Nilai <kode>".
     */
    public function __construct($model, array $bundle, array $lookups = array(), array $options = array())
    {
        $this->model = $model;
        $this->bundle = $bundle;
        $this->options = $options + array('include_scores' => false);

        $this->education = isset($lookups['education']) ? $lookups['education'] : array();
        $this->jobs = isset($lookups['jobs']) ? $lookups['jobs'] : array();
        $this->services = isset($lookups['services']) ? $lookups['services'] : array();
        $this->unitNames = isset($lookups['unit_names']) ? $lookups['unit_names'] : array();
    }

    /**
     * Menjalankan seluruh transformasi sekali, lalu dipakai ulang.
     *
     * @return void
     */
    private function build()
    {
        if ($this->built) {
            return;
        }
        $this->built = true;
        $this->counters['bundle_rows'] = count($this->bundle);

        foreach ($this->bundle as $entry) {
            if (!isset($entry['response']) || !is_array($entry['response'])) {
                continue;
            }
            $row = $entry['response'];
            $meta = $this->model->decode_metadata($row['keterangan']);
            $reference = $this->reference_of($row);

            if ($reference === '') {
                // Tanpa nomor referensi baris tetap dipertahankan agar tidak
                // hilang, dan diberi penanda agar bisa dibedakan dari resi asli.
                $this->counters['empty_reference']++;
                $reference = '(tanpa referensi) ' . $row['response_key'];
            }

            // Grup dibuat begitu baris terlihat, bukan begitu ada jawaban, agar
            // responden yang menjawab nol tetap muncul sebagai satu baris.
            if (!isset($this->groups[$reference])) {
                $this->groups[$reference] = array(
                    'reference' => $reference,
                    'profile' => array(),
                    'answers' => array(),
                );
            }

            $this->collect_profile($this->groups[$reference], $row, $meta, $entry);
            $this->collect_answers($this->groups[$reference], $entry, $row);
        }
    }

    /**
     * Nomor referensi. resi adalah nomor yang dipakai responden; kode hanya
     * dipakai bila resi kosong.
     *
     * @param  array $row
     * @return string
     */
    private function reference_of(array $row)
    {
        $resi = trim((string) $row['resi']);
        return $resi !== '' ? $resi : trim((string) $row['kode']);
    }

    /**
     * Memecah identitas responden menjadi field terpisah.
     *
     * Field yang nama aslinya tidak bermakna tidak dibuang. Label yang hanya
     * berisi angka, misalnya jenis_ijin yang tersimpan sebagai id, diberi nama
     * aman Field_<angka> supaya tidak hilang dan tidak pernah menggeser kolom.
     *
     * @param array $group
     * @param array $row
     * @param array $meta
     * @param array $entry
     * @return void
     */
    private function collect_profile(array &$group, array $row, array $meta, array $entry)
    {
        $fields = array();

        $name = trim((string) $row['nama_responden']);
        if ($name !== '') {
            $fields['Nama'] = $name;
        }

        $email = !empty($meta['email'])
            ? trim((string) $meta['email'])
            : trim((string) $row['responden']);
        if ($email !== '') {
            $fields['Surel'] = $email;
        }

        $phone = trim((string) $row['mobile']);
        if ($phone !== '') {
            $fields['Telepon'] = $phone;
        }

        $identityNumber = trim((string) $row['nib']);
        if ($identityNumber !== '') {
            $fields[$this->safe_label((string) $row['jenis_ijin'], 'Nomor Identitas')] = $identityNumber;
        }

        if ((int) $row['usia'] > 0) {
            $fields['Usia'] = (string) (int) $row['usia'];
        }

        $gender = (int) $row['gender'];
        if ($gender === 1 || $gender === 2) {
            $fields['Jenis Kelamin'] = $gender === 1 ? 'Laki-laki' : 'Perempuan';
        }

        $educationId = (int) $row['pendidikan_id'];
        if (isset($this->education[$educationId])) {
            $fields['Pendidikan'] = (string) $this->education[$educationId];
        }

        $jobId = (int) $row['pekerjaan_id'];
        if ($jobId === 5 && !empty($meta['job_other'])) {
            $fields['Pekerjaan'] = (string) $meta['job_other'];
        } elseif (isset($this->jobs[$jobId])) {
            $fields['Pekerjaan'] = (string) $this->jobs[$jobId];
        }

        $unitId = isset($meta['unit_id']) ? (int) $meta['unit_id'] : 0;
        if ($unitId > 0 && isset($this->unitNames[$unitId])) {
            $fields['Perangkat Daerah'] = (string) $this->unitNames[$unitId];
        }

        $fields['Kategori'] = $this->service_label($row, $meta);
        $fields['Survei'] = trim((string) $entry['survey_name']) !== ''
            ? (string) $entry['survey_name']
            : '-';

        foreach ($fields as $label => $value) {
            $this->merge_profile_value($group, $label, $value);
        }

        // Tanggal punya kolom tersendiri di depan, jadi tidak didaftarkan
        // sebagai label identitas supaya tidak muncul dua kali.
        $date = $this->screen_date($row);
        if ($date['value'] !== '') {
            $this->dateLabel = $date['label'];
            $this->merge_profile_value($group, $date['label'], $date['value'], true);
        }
    }

    /**
     * Menyimpan satu nilai profil pada grupnya.
     *
     * Nilai berbeda pada referensi yang sama digabung dengan pemisah " | "
     * supaya tidak ada data yang hilang, lalu dicatat di audit. Penyatuan ini
     * hanya berlaku untuk baris yang memang berbeda sumber, bukan untuk
     * isian kosong pada baris kedua.
     *
     * @param array  $group
     * @param string $label
     * @param mixed  $value
     * @param bool   $silent true untuk kolom tanggal, tidak didaftarkan sebagai identitas.
     * @return void
     */
    private function merge_profile_value(array &$group, $label, $value, $silent = false)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }
        if (!$silent && !in_array($label, $this->identityLabels, true)) {
            $this->identityLabels[] = $label;
        }
        if (!isset($group['profile'][$label])) {
            $group['profile'][$label] = array($value);
            return;
        }
        if (in_array($value, $group['profile'][$label], true)) {
            return;
        }
        $this->counters['profile_multi_value']++;
        $group['profile'][$label][] = $value;
        $this->audit[] = sprintf(
            'profil "%s" pada referensi %s punya nilai berbeda: %s (digabung dengan pemisah " | ")',
            $label,
            $group['reference'],
            implode(' | ', $group['profile'][$label])
        );
    }

    /**
     * Menentukan field tanggal yang dipakai beserta nama kolomnya.
     *
     * @param  array $row
     * @return array
     */
    private function screen_date(array $row)
    {
        $created = trim((string) $row['tgl_buat']);
        if ($created !== '') {
            return array('label' => 'Tanggal', 'value' => $created);
        }
        $filled = trim((string) $row['tgl_pengisian']);
        if ($filled !== '') {
            return array('label' => 'Tanggal Pengisian', 'value' => $filled);
        }
        return array('label' => '', 'value' => '');
    }

    /**
     * Nama kolom untuk field identitas yang nama aslinya tidak bermakna.
     *
     * @param  string $raw      Nilai mentah jenis ijin.
     * @param  string $fallback Nama yang dipakai bila tidak ada nama.
     * @return string
     */
    private function safe_label($raw, $fallback)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0') {
            return $fallback;
        }
        return ctype_digit($raw) ? 'Field_' . $raw : $raw;
    }

    /**
     * Label kategori atau layanan milik satu responden.
     *
     * @param  array $row
     * @param  array $meta
     * @return string
     */
    private function service_label(array $row, array $meta)
    {
        if (!empty($meta['sector_name'])) {
            return (string) $meta['sector_name'];
        }
        if (!empty($meta['service_other'])) {
            return (string) $meta['service_other'];
        }
        $sectorId = (int) $row['sektor'];
        return $sectorId > 0 && isset($this->services[$sectorId])
            ? (string) $this->services[$sectorId]
            : '-';
    }

    /**
     * Mengumpulkan jawaban dan mendaftarkan kolom pertanyaan.
     *
     * Nama kolom memakai kode pertanyaan. Kode yang sama pada dua survei
     * berbeda mendapat akhiran nama survei supaya tidak saling menimpa.
     *
     * @param array $group
     * @param array $entry
     * @param array $row
     * @return void
     */
    private function collect_answers(array &$group, array $entry, array $row)
    {
        $surveyCode = (string) $entry['survey_code'];
        $timestamp = trim((string) $row['tgl_buat']);
        if ($timestamp === '') {
            $timestamp = trim((string) $row['tgl_pengisian']);
        }

        foreach ($entry['items'] as $item) {
            $this->counters['answer_items']++;
            $code = trim((string) $item['question_code']);
            if ($code === '') {
                $code = 'Tanpa Kode';
            }
            $column = $this->question_column($code, $surveyCode);

            if (!isset($this->questionColumns[$column])) {
                $this->questionColumns[$column] = array(
                    'code' => $code,
                    'column' => $column,
                    'label' => $column,
                    'text' => (string) $item['question_text'],
                    'survey' => (string) $entry['survey_name'],
                );
            }

            if (!isset($group['answers'][$column])) {
                $group['answers'][$column] = array();
                $this->counters['distinct_pairs']++;
            }

            $answer = trim((string) $item['option_label']);
            $group['answers'][$column][] = array(
                'answer' => $answer,
                'score' => (float) $item['score'],
                'timestamp' => $timestamp,
                'survey' => (string) $entry['survey_name'],
                'option_code' => (string) $item['option_code'],
            );
        }
    }

    /**
     * Menentukan nama kolom untuk satu kode pertanyaan.
     *
     * Kode dipakai apa adanya selama belum dipakai survei lain. Kalau kode
     * yang sama muncul pada survei berbeda, nama diberi akhiran kode survei
     * agar kedua kolom tetap terpisah.
     *
     * @param  string $code
     * @param  string $surveyCode
     * @return string
     */
    private function question_column($code, $surveyCode)
    {
        $ownerKey = $code . "\x00" . $surveyCode;
        if (isset($this->columnByCode[$ownerKey])) {
            return $this->columnByCode[$ownerKey];
        }

        if (!isset($this->usedColumns[$code])) {
            $column = $code;
        } else {
            $base = $code . ' (' . $surveyCode . ')';
            $column = $base;
            $suffix = 2;
            while (isset($this->usedColumns[$column])) {
                $column = $base . ' ' . $suffix;
                $suffix++;
            }
        }

        $this->usedColumns[$column] = true;
        $this->columnByCode[$ownerKey] = $column;
        return $column;
    }

    /**
     * Menyusun susunan kolom akhir.
     *
     * @return array
     */
    private function layout()
    {
        if ($this->layout !== null) {
            return $this->layout;
        }
        $headers = array('No', 'Nomor Referensi');
        $kinds = array('No' => 'row_number', 'Nomor Referensi' => 'reference');

        if ($this->dateLabel !== '') {
            $headers[] = $this->dateLabel;
            $kinds[$this->dateLabel] = 'date';
        }

        foreach ($this->ordered_identity_labels() as $label) {
            $headers[] = $label;
            $kinds[$label] = 'identity';
        }

        foreach (array('Kategori', 'Survei') as $label) {
            if (in_array($label, $this->identityLabels, true)) {
                $headers[] = $label;
                $kinds[$label] = 'metadata';
            }
        }

        foreach ($this->questionColumns as $column) {
            $headers[] = $column['label'];
            $kinds[$column['label']] = 'answer';
            if (!empty($this->options['include_scores'])) {
                $scoreLabel = 'Nilai ' . $column['code'];
                $headers[] = $scoreLabel;
                $kinds[$scoreLabel] = 'score';
            }
        }

        $this->layout = array('headers' => $headers, 'kinds' => $kinds);
        return $this->layout;
    }

    /**
     * Label identitas terurut: daftar umum lebih dulu, lalu label lain.
     *
     * Kategori dan Survei tidak termasuk karena keduanya kelompok metadata
     * dan ditempatkan setelah identitas.
     *
     * @return array
     */
    private function ordered_identity_labels()
    {
        $ordered = array();
        foreach (self::$identityOrder as $label) {
            if (in_array($label, $this->identityLabels, true)) {
                $ordered[] = $label;
            }
        }
        foreach ($this->identityLabels as $label) {
            if ($label === 'Kategori' || $label === 'Survei') {
                continue;
            }
            if (!in_array($label, $ordered, true)) {
                $ordered[] = $label;
            }
        }
        return $ordered;
    }

    /**
     * Menyelesaikan satu grup menjadi isi sel.
     *
     * @param  array $group
     * @return array
     */
    private function finalize_group(array $group)
    {
        $cells = array();
        foreach ($group['profile'] as $label => $values) {
            $cells[$label] = implode(' | ', $values);
        }

        $answers = array();
        foreach ($group['answers'] as $column => $records) {
            $answers[$column] = $this->resolve_answer($group['reference'], $column, $records);
        }

        return array(
            'reference' => $group['reference'],
            'cells' => $cells,
            'answers' => $answers,
        );
    }

    /**
     * Memilih satu jawaban ketika satu kolom terisi lebih dari sekali.
     *
     * Aturannya:
     *   - jawaban identik: dipipihkan, tidak ada yang tertimpa;
     *   - jawaban berbeda dengan waktu berbeda: dipakai yang terbaru;
     *   - jawaban berbeda dengan waktu sama: tidak dapat dipastikan, dipakai
     *     yang pertama dan dicatat sebagai konflik.
     *
     * @param  string $reference
     * @param  string $column
     * @param  array  $records
     * @return array
     */
    private function resolve_answer($reference, $column, array $records)
    {
        if (count($records) === 1) {
            return $records[0];
        }

        $signatures = array();
        foreach ($records as $record) {
            $signatures[$record['answer'] . "\x00" . $record['score']] = true;
        }
        if (count($signatures) === 1) {
            // Sama persis, hanya berbeda kali tulis. Pakai yang pertama.
            $this->counters['duplicate_identical'] += count($records) - 1;
            return $records[0];
        }

        // Urutkan terbaru lebih dulu. Urutan stabilize pada waktu yang sama
        // supaya hasilnya deterministik, bukan bergantung urutan acak.
        $indexed = array();
        foreach ($records as $position => $record) {
            $indexed[] = array('position' => $position, 'record' => $record);
        }
        usort($indexed, function ($a, $b) {
            if ($a['record']['timestamp'] === $b['record']['timestamp']) {
                return $a['position'] - $b['position'];
            }
            return $a['record']['timestamp'] < $b['record']['timestamp'] ? 1 : -1;
        });

        $chosen = $indexed[0]['record'];
        $tied = false;
        foreach ($indexed as $entry) {
            if ($entry['record']['timestamp'] === $chosen['timestamp']
                && $entry['record']['answer'] !== $chosen['answer']) {
                $tied = true;
                break;
            }
        }

        $others = array();
        foreach ($records as $record) {
            if ($record['answer'] !== $chosen['answer']) {
                $others[] = $record['answer'] . ' (' . $record['timestamp'] . ')';
            }
        }

        if ($tied) {
            $this->counters['duplicate_unresolved'] += count($records) - 1;
            $this->audit[] = sprintf(
                'konflik %s / %s: waktu sama sehingga pilihan tidak pasti, dipakai "%s"; ada juga %s',
                $reference,
                $column,
                $chosen['answer'],
                implode(', ', $others)
            );
        } else {
            $this->counters['duplicate_newest_wins'] += count($records) - 1;
            $this->audit[] = sprintf(
                'duplikat %s / %s: dipakai jawaban terbaru "%s"; ada juga %s',
                $reference,
                $column,
                $chosen['answer'],
                implode(', ', $others)
            );
        }

        return $chosen;
    }

    /**
     * Baris akhir untuk Excel, satu baris per nomor referensi.
     *
     * @return array
     */
    public function rows()
    {
        $this->build();
        $layout = $this->layout();
        $rows = array();
        $number = 0;

        foreach ($this->groups as $group) {
            $number++;
            $final = $this->finalize_group($group);
            $cells = array(
                'No' => (string) $number,
                'Nomor Referensi' => $final['reference'],
            );
            foreach ($layout['headers'] as $header) {
                if (isset($cells[$header])) {
                    continue;
                }
                if (isset($final['cells'][$header])) {
                    $cells[$header] = $final['cells'][$header];
                    continue;
                }
                $cells[$header] = '';
            }

            foreach ($this->questionColumns as $column) {
                $record = isset($final['answers'][$column['column']])
                    ? $final['answers'][$column['column']]
                    : null;
                $cells[$column['label']] = $record === null ? '' : $record['answer'];
                if (!empty($this->options['include_scores'])) {
                    $cells['Nilai ' . $column['code']] = $record === null
                        ? ''
                        : $this->format_score($record['score']);
                }
            }

            $line = array();
            foreach ($layout['headers'] as $header) {
                $line[] = isset($cells[$header]) ? $cells[$header] : '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Skor ditulis tanpa nol di belakang koma: 25.00 jadi 25, 76.60 jadi 76.6.
     *
     * @param  float $score
     * @return string
     */
    private function format_score($score)
    {
        if ((float) $score <= 0) {
            return '';
        }
        $formatted = rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * Daftar kolom akhir beserta sifatnya.
     *
     * @return array
     */
    public function headers()
    {
        $this->build();
        return $this->layout();
    }

    /**
     * Laporan screening struktur data.
     *
     * @return array
     */
    public function screen()
    {
        $this->build();
        $layout = $this->layout();

        $rawFields = array();
        $emptyFields = array();
        foreach ($this->bundle as $entry) {
            if (!isset($entry['response']) || !is_array($entry['response'])) {
                continue;
            }
            foreach ($entry['response'] as $field => $value) {
                $rawFields[$field] = isset($rawFields[$field]) ? $rawFields[$field] + 1 : 1;
                if ($value === null || $value === '' || $value === '0' || $value === 0) {
                    $emptyFields[$field] = isset($emptyFields[$field]) ? $emptyFields[$field] + 1 : 1;
                }
            }
        }
        ksort($rawFields);
        ksort($emptyFields);

        $surveyCodes = array();
        $categoryValues = array();
        foreach ($this->bundle as $entry) {
            $key = (string) $entry['survey_code'];
            $surveyCodes[$key] = isset($surveyCodes[$key]) ? $surveyCodes[$key] + 1 : 1;
            if (isset($entry['response'])) {
                $meta = $this->model->decode_metadata($entry['response']['keterangan']);
                $category = $this->service_label($entry['response'], $meta);
                $categoryValues[$category] = isset($categoryValues[$category])
                    ? $categoryValues[$category] + 1
                    : 1;
            }
        }
        ksort($categoryValues);

        $codes = array();
        $renamed = array();
        foreach ($this->questionColumns as $column) {
            $codes[] = array(
                'code' => $column['code'],
                'column' => $column['column'],
                'text' => $column['text'],
                'survey' => $column['survey'],
            );
            if ($column['column'] !== $column['code']) {
                $renamed[] = $column['code'] . ' -> ' . $column['column'];
            }
        }

        return array(
            'raw_fields' => $rawFields,
            'empty_fields' => $emptyFields,
            'bundle_rows' => $this->counters['bundle_rows'],
            'answer_items' => $this->counters['answer_items'],
            'identity_labels' => $this->identityLabels,
            'date_label' => $this->dateLabel,
            'survey_codes' => $surveyCodes,
            'category_values' => $categoryValues,
            'question_codes' => $codes,
            'renamed_question_columns' => $renamed,
            'headers' => $layout['headers'],
            'header_kinds' => $layout['kinds'],
            'counters' => $this->counters,
            'audit' => $this->audit,
        );
    }

    /**
     * Validasi hasil transformasi.
     *
     * @return array
     */
    public function validation()
    {
        $this->build();
        $layout = $this->layout();

        $references = array();
        foreach ($this->bundle as $entry) {
            if (!isset($entry['response']) || !is_array($entry['response'])) {
                continue;
            }
            $reference = $this->reference_of($entry['response']);
            $references[$reference === '' ? '(tanpa referensi) ' . $entry['response']['response_key'] : $reference] = true;
        }

        $produced = array();
        $answerCells = 0;
        foreach ($this->rows() as $line) {
            $cells = array_combine($layout['headers'], $line);
            $produced[(string) $cells['Nomor Referensi']] = true;
            foreach ($this->questionColumns as $column) {
                if (isset($cells[$column['label']]) && trim((string) $cells[$column['label']]) !== '') {
                    $answerCells++;
                }
            }
        }

        $missing = array_values(array_diff(array_keys($references), array_keys($produced)));
        $extra = array_values(array_diff(array_keys($produced), array_keys($references)));

        return array(
            'unique_references_raw' => count($references),
            'rows_produced' => count($produced),
            'one_row_per_reference' => count($produced) === count($references),
            'missing_references' => $missing,
            'unexpected_references' => $extra,
            'answer_items_raw' => $this->counters['answer_items'],
            'answer_cells_expected' => $this->counters['distinct_pairs'],
            'answer_cells_filled' => $answerCells,
            'no_answer_lost' => $answerCells === $this->counters['distinct_pairs'],
            'column_count' => count($layout['headers']),
            'question_columns' => count($this->questionColumns),
            'duplicate_question_columns' => $this->renamed_columns(),
            'duplicate_identical' => $this->counters['duplicate_identical'],
            'duplicate_newest_wins' => $this->counters['duplicate_newest_wins'],
            'duplicate_unresolved' => $this->counters['duplicate_unresolved'],
            'profile_multi_value' => $this->counters['profile_multi_value'],
            'empty_reference' => $this->counters['empty_reference'],
            'audit' => $this->audit,
        );
    }

    /**
     * @return array
     */
    private function renamed_columns()
    {
        $renamed = array();
        foreach ($this->questionColumns as $column) {
            if ($column['column'] !== $column['code']) {
                $renamed[] = $column['code'] . ' -> ' . $column['column'];
            }
        }
        return $renamed;
    }

    /**
     * Indeks kolom yang wajib ditulis sebagai teks, bukan angka.
     *
     * Nomor identitas, nomor referensi, dan telepon bisa consisted dari angka
     * panjang. Bila Excel membacanya sebagai number, digit terakhir hilang dan
     * kolom tampil sebagai ####. Sebaliknya kolom yang memang angka, seperti
     * No, Usia, dan kolom nilai, dibiarkan sebagai number supaya tetap bisa
     * diurutkan dan difilter secara numerik.
     *
     * @return array
     */
    public function text_columns()
    {
        $this->build();
        $layout = $this->layout();
        $numericLabels = array('No');
        foreach (self::$identityOrder as $label) {
            if ($label === 'Usia') {
                $numericLabels[] = $label;
            }
        }
        if ($this->dateLabel !== '') {
            $numericLabels[] = $this->dateLabel;
        }

        $textColumns = array();
        foreach ($layout['kinds'] as $header => $kind) {
            if ($kind === 'answer') {
                // Jawaban boleh berupa angka dan tetap berguna sebagai angka.
                continue;
            }
            if (in_array($header, $numericLabels, true)) {
                continue;
            }
            if ($kind === 'score') {
                continue;
            }
            $textColumns[] = array_search($header, $layout['headers'], true);
        }
        return $textColumns;
    }

    /**
     * Lebar kolom sesuai isi, supaya sheet mudah dibaca.
     *
     * Nilai balik memakai satuan ss:Width milik SpreadsheetML, bukan satuan
     * karakter yang dilaporkan Excel. Pada berkas ini ss:Width 60 tampil
     * sebagai sekitar 10,7 karakter di Excel, jadi lebar dihitung dengan
     * faktor pembalik supaya kolom tetap lega. Batas atas 255 mengikuti
     * nilai ss:Width tertinggi yang diterima Excel.
     *
     * @param  array $headers
     * @param  array $rows
     * @return array
     */
    public function widths(array $headers, array $rows)
    {
        $widths = array();
        foreach ($headers as $index => $header) {
            $max = $this->strlen((string) $header) + 2;
            foreach ($rows as $row) {
                $length = $this->strlen(isset($row[$index]) ? (string) $row[$index] : '') + 2;
                if ($length > $max) {
                    $max = $length;
                }
            }
            // Kolom angka tidak bisa membungkus teks seperti kolom biasa, jadi
            // dilebihkan sedikit agar tidak muncul sebagai ####.
            $max += 4;
            $widths[] = self::to_ss_width($max);
        }
        return $widths;
    }

    /**
     * Mengubah lebar dalam satuan karakter menjadi satuan ss:Width.
     *
     * @param  int $characters Lebar yang diinginkan dalam karakter.
     * @return int
     */
    private static function to_ss_width($characters)
    {
        if ($characters < 11) {
            $characters = 11;
        }
        if ($characters > 45) {
            $characters = 45;
        }
        $ssWidth = (int) round(5.238 * $characters + 4.19);
        return max(12, min(255, $ssWidth));
    }

    /**
     * Panjang teks aman tanpa bergantung mbstring.
     *
     * @param  string $value
     * @return int
     */
    private function strlen($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
