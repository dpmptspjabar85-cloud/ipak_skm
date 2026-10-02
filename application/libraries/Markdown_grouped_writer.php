<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengelompokan baris jadi Markdown berdasarkan satu kunci, tanpa mengulang
 * profil.
 *
 * Data datang sebagai baris datar: satu baris = satu pasang pertanyaan dan
 * jawaban, sementara profil responden terulang di tiap baris. Tanpa
 * pengelompokan, profil ikut tercetak sebanyak jumlah pertanyaan sehingga
 * tabel menjadi lebar dan sulit dibaca.
 *
 * Kelas ini menggabungkan semua baris yang memiliki kunci grup sama menjadi
 * satu catatan: profil ditampilkan sekali, seluruh pertanyaan/jawaban
 * dipertahankan.
 *
 * Tidak ada asumsi tentang nama kolom. Pemanggil yang menentukan pemetaan
 * label ke kunci baris lewat set_identity_fields() dan set_answer_columns(),
 * sehingga kelas ini aman dipakai pada sumber data apa pun.
 *
 * Kompatibel PHP 5.6 ke atas.
 */
class Markdown_grouped_writer
{
    /** @var string Label untuk kolom/heading kunci pengelompokan. */
    private $referenceLabel = 'Nomor Referensi';

    /** @var array Label profil => kunci pada baris. */
    private $identityFields = [];

    /** @var array Label kolom jawaban => kunci pada baris. */
    private $answerColumns = [];

    /** @var array Label kolom jawaban yang isinya angka. */
    private $numericColumns = [];

    /** @var int|null Digit desimal untuk kolom angka; null = buang nol belakang. */
    private $numberDecimals = null;

    /** @var string Nilai yang dipakai untuk kolom kosong. */
    private $emptyValue = '-';

    /** @var bool Tampilkan heading level 3 untuk tiap referensi. */
    private $useHeadings = true;

    /**
     * @param  string $label
     * @return $this
     */
    public function set_reference_label($label)
    {
        $this->referenceLabel = (string) $label;
        return $this;
    }

    /**
     * Daftar field profil. Urutan menentukan urutan baris pada tabel.
     *
     * @param  array $fields Label => kunci baris
     * @return $this
     */
    public function set_identity_fields(array $fields)
    {
        $this->identityFields = $fields;
        return $this;
    }

    /**
     * Daftar kolom pertanyaan/jawaban. Urutan menentukan urutan kolom.
     *
     * Nilai kolom "Nilai" boleh berupa angka; kolom ini ditulis rata kanan.
     *
     * @param  array $columns Label => kunci baris
     * @return $this
     */
    public function set_answer_columns(array $columns)
    {
        $this->answerColumns = $columns;
        return $this;
    }

    /**
     * Menandai kolom jawaban yang isinya angka, supaya tabel Markdown
     * mensejajarkannya rata kanan dengan pemisah "---:".
     *
     * Hanya label di sini yang diperlakukan angka. Tanpa pemanggilan ini
     * semua kolom dianggap teks.
     *
     * @param  array $labels Label kolom jawaban yang numerik.
     * @return $this
     */
    public function set_numeric_columns(array $labels)
    {
        $this->numericColumns = [];
        foreach ($labels as $label) {
            $this->numericColumns[$label] = true;
        }
        return $this;
    }

    /**
     * Menentukan cara menulis kolom angka.
     *
     * null (default) membuang nol di belakang koma, sehingga 25.00 tampil 25
     * dan 76.60 tampil 76.6. Nilai aslinya tidak diubah, hanya ditulis ulang.
     * Jika diisi integer, angka ditulis dengan digit desimal tetap.
     *
     * @param  int|null $decimals
     * @return $this
     */
    public function set_number_decimals($decimals)
    {
        $this->numberDecimals = $decimals === null ? null : (int) $decimals;
        return $this;
    }

    /**
     * @param  string $value
     * @return $this
     */
    public function set_empty_value($value)
    {
        $this->emptyValue = (string) $value;
        return $this;
    }

    /**
     * @param  bool $enabled
     * @return $this
     */
    public function set_headings($enabled)
    {
        $this->useHeadings = (bool) $enabled;
        return $this;
    }

    /**
     * Mengelompokkan baris datar menjadi satu catatan per kunci grup.
     *
     * Sekuensial tunggal, tanpa pencarian berulang, sehingga biaya tetap linier
     * terhadap jumlah baris. Baris yang kunci grupnya kosong TIDAK digabung
     * satu sama lain, karena-record tanpa nomor referensi tidak saling
     * berkaitan dan menggabungkanya akan memalsukan data.
     * @param  array $rows        Baris datar.
     * @param  string $referenceKey Kunci baris yang jadi primary grouping key.
     * @return array
     */
    public function group(array $rows, $referenceKey)
    {
        $groups = [];
        $order = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $reference = $this->normalize($this->field($row, $referenceKey));

            if ($reference === '') {
                $key = "\0anonim-" . $index;
            } else {
                $key = $reference;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'reference' => $reference,
                    'identity' => [],
                    'answers' => [],
                ];
                $order[] = $key;
            }

            $this->absorb_identity($groups[$key]['identity'], $row);
            $groups[$key]['answers'][] = $this->extract_answer($row);
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $groups[$key];
        }
        return $result;
    }

    /**
     * Menyerap nilai profil satu baris ke group's profil.
     *
     * Nilai pertama yang tersedia dipakai sebagai nilai utama. Jika baris lain
     * dalam referensi yang sama punya nilai berbeda, nilai itu tidak
     * dihapus, melainkan disimpan sebagai baris lanjutan supaya tidak ada data
     * yang hilang.
     *
     * @param  array $identity
     * @param  array $row
     * @return void
     */
    private function absorb_identity(array &$identity, array $row)
    {
        foreach ($this->identityFields as $label => $key) {
            $value = $this->normalize($this->field($row, $key));
            if ($value === '') {
                continue;
            }
            if (!isset($identity[$label])) {
                $identity[$label] = [$value];
                continue;
            }
            if (!in_array($value, $identity[$label], true)) {
                $identity[$label][] = $value;
            }
        }
    }

    /**
     * Mengambil satu baris jawaban sesuai pemetaan kolom.
     *
     * @param  array $row
     * @return array
     */
    private function extract_answer(array $row)
    {
        $answer = [];
        foreach ($this->answerColumns as $label => $key) {
            $answer[$label] = $this->field($row, $key);
        }
        return $answer;
    }

    /**
     * Merakit Markdown akhir.
     *
     * @param  array  $rows
     * @param  string $referenceKey
     * @return string
     */
    public function render(array $rows, $referenceKey)
    {
        return $this->render_groups($this->group($rows, $referenceKey));
    }

    /**
     * Merakit Markdown dari hasil group() yang sudah dihitung.
     *
     * Pemanggil cukup memanggilnya satu kali. Pada data besar, memanggil
     * group() lalu render() sekaligus akan mengulang seluruh pengelompokan.
     *
     * @param  array $groups Hasil group().
     * @return string
     */
    public function render_groups(array $groups)
    {
        if (!$groups) {
            return '';
        }

        $numericColumns = $this->numericColumns;
        $out = [];

        foreach ($groups as $group) {
            $reference = isset($group['reference']) ? $group['reference'] : '';
            $title = $reference !== '' ? $reference : $this->emptyValue;
            if ($this->useHeadings) {
                $out[] = '### ' . $this->referenceLabel . ': ' . $this->escape($title);
                $out[] = '';
            }

            $identity = isset($group['identity']) ? $group['identity'] : [];
            $answers = isset($group['answers']) ? $group['answers'] : [];

            $out = array_merge($out, $this->render_identity($identity));
            $out = array_merge($out, $this->render_answers($answers, $numericColumns));
            $out[] = '';
        }

        return rtrim(implode("\n", $out)) . "\n";
    }

    /**
     * Tabel profil: satu baris per field, bukan satu sel berisi string panjang.
     *
     * @param  array $identity
     * @return array
     */
    private function render_identity(array $identity)
    {
        $rows = [];
        foreach ($this->identityFields as $label => $key) {
            $values = isset($identity[$label]) ? $identity[$label] : [];
            if (!$values) {
                $rows[] = [$label, $this->emptyValue];
                continue;
            }
            $first = true;
            foreach ($values as $value) {
                // Nilai berbeda dalam satu referensi tidak dibuang; ditandai
                // agar tetap terbaca bahwa field itu punya lebih dari satu isi.
                $rows[] = [
                    $first ? $label : $label . ' (nilai lain)',
                    $value,
                ];
                $first = false;
            }
        }

        if (!$rows) {
            return [];
        }

        $table = [$this->row(['Field', 'Nilai'])];
        $table[] = $this->row(['---', '---']);
        foreach ($rows as $entry) {
            $table[] = $this->row($entry);
        }

        $table[] = '';
        return $table;
    }

    /**
     * Tabel pertanyaan/jawaban.
     *
     * @param  array $answers
     * @param  array $numericColumns Label kolom yang diratakan kanan.
     * @return array
     */
    private function render_answers(array $answers, array $numericColumns)
    {
        if (!$answers || !$this->answerColumns) {
            return [];
        }

        $labels = array_keys($this->answerColumns);
        // Peta posisi kolom (0-based) yang isinya angka, dipakai baik untuk
        // pemisah tabel maupun isi baris.
        $numericFlags = [];
        foreach ($labels as $position => $label) {
            $numericFlags[$position] = isset($numericColumns[$label]);
        }

        $table = [];
        $table[] = $this->row($labels);
        $table[] = $this->row($this->separator($labels, $numericFlags), $numericFlags);

        foreach ($answers as $answer) {
            $cells = [];
            foreach ($labels as $label) {
                $cells[] = $this->field($answer, $label);
            }
            $table[] = $this->row($cells, $numericFlags);
        }

        $table[] = '';
        return $table;
    }

    /**
     * Baris pemisah tabel. Kolom angka memakai "---:" agar rata kanan.
     *
     * @param  array $labels
     * @param  array $numericFlags Peta posisi => true.
     * @return array
     */
    private function separator(array $labels, array $numericFlags)
    {
        $cells = [];
        foreach ($labels as $position => $label) {
            $cells[] = !empty($numericFlags[$position]) ? '---:' : '---';
        }
        return $cells;
    }

    /**
     * Menyusun satu baris tabel Markdown.
     *
     * @param  array $cells
     * @param  array $numericFlags Peta posisi => true, sesuai indeks sel.
     * @return string
     */
    private function row(array $cells, array $numericFlags = [])
    {
        $values = array_values($cells);
        $parts = [];
        foreach ($values as $position => $value) {
            $parts[] = !empty($numericFlags[$position])
                ? $this->escape_number($value)
                : $this->escape($value);
        }
        return '| ' . implode(' | ', $parts) . ' |';
    }

    /**
     * Merakit tabel Markdown lebar: satu baris untuk setiap Nomor Referensi.
     *
     * Berbeda dengan render() yang memecah tiap pertanyaan menjadi baris,
     * format ini menyatukan seluruh pertanyaan milik satu referensi ke dalam
     * satu baris, sehingga profil tidak lagi terulang dan tabel bisa dibaca
     * sebagai laporan.
     *
     * Kolom pertanyaan dibuat dinamis mengikuti soal yang benar-benar muncul.
     * Setiap soal menghasilkan dua kolom: teks soal dan nilai. Responden yang
     * tidak menjawab soal tersebut tetap mendapat baris, dengan sel kosong
     * diisi tanda kosong, bukan dengan baris baru.
     *
     * Konfigurasi $config:
     *   question_key    Kunci baris yang membedakan soal. Boleh string atau
     *                   array kunci yang digabung, berguna bila satu kode soal
     *                   dipakai lebih dari satu survei.
     *   question_label  Kunci baris untuk judul kolom soal.
     *   answer_key      Kunci baris untuk isi jawaban.
     *   score_key       Kunci baris untuk nilai jawaban.
     *   score_header    Judul kolom nilai, default "Nilai".
     *   score_keys      Kolom nilai tambahan per soal (opsional), misal
     *                   array('Kode Opsi' => 'option_code_snapshot').
     *
     * @param  array  $rows
     * @param  string $referenceKey
     * @param  array  $config
     * @return string
     */
    public function render_wide(array $rows, $referenceKey, array $config)
    {
        if (!$rows) {
            return '';
        }

        $questionKey = isset($config['question_key']) ? $config['question_key'] : 'question_code';
        $questionLabelKey = isset($config['question_label']) ? $config['question_label'] : 'question_text';
        $answerKey = isset($config['answer_key']) ? $config['answer_key'] : 'option_label_snapshot';
        $scoreKey = isset($config['score_key']) ? $config['score_key'] : 'normalized_score';
        $scoreHeader = isset($config['score_header']) ? (string) $config['score_header'] : 'Nilai';
        $extraScoreKeys = isset($config['score_keys']) && is_array($config['score_keys'])
            ? $config['score_keys']
            : [];

        // Tahap 1: kelompokkan per referensi, kumpulkan soal per kelompok.
        $groups = [];
        $groupOrder = [];
        $columnOrder = [];
        $headerText = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $reference = $this->normalize($this->field($row, $referenceKey));
            $key = $reference === '' ? "\0anonim-" . $index : $reference;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'reference' => $reference,
                    'identity' => [],
                    'cells' => [],
                ];
                $groupOrder[] = $key;
            }

            $this->absorb_identity($groups[$key]['identity'], $row);

            $columnKey = $this->compose_key($row, $questionKey);
            if ($columnKey === '') {
                // Baris tanpa identitas soal tetap harus tampil agar tidak
                // ada jawaban yang hilang.
                $columnKey = "\0tanpa-soal-" . $index;
            }

            if (!isset($columnOrder[$columnKey])) {
                $columnOrder[$columnKey] = count($columnOrder);
                $label = $this->normalize($this->field($row, $questionLabelKey));
                $headerText[$columnKey] = $label !== '' ? $label : $columnKey;
            }

            // Satu referensi menjawab satu soal satu kali; baris pengulangan
            // untuk soal yang sama ditimpa, bukan digandakan.
            $groups[$key]['cells'][$columnKey] = [
                'answer' => $this->field($row, $answerKey),
                'score' => $this->field($row, $scoreKey),
                'extra' => $this->collect_extra_scores($row, $extraScoreKeys),
            ];
        }

        if (!$groups) {
            return '';
        }

        // Tahap 2: susun judul kolom.
        // Kolom pertama adalah kunci pengelompokan. Judulnya wajib ikut,
        // karena baris data diawali nilai referensi; tanpa ini seluruh kolom
        // bergeser satu posisi dan angka akan dibaca dari sel yang salah.
        $headers = [$this->referenceLabel];
        $numericFlags = [false];
        foreach ($this->identityFields as $label => $key) {
            $headers[] = $label;
            $numericFlags[] = false;
        }

        $usedHeaders = [];
        foreach ($columnOrder as $columnKey => $position) {
            $headers[] = $this->unique_header($headerText[$columnKey], $usedHeaders);
            $numericFlags[] = false;
            $headers[] = $scoreHeader;
            $numericFlags[] = true;
            foreach ($extraScoreKeys as $extraLabel => $extraKey) {
                $headers[] = $this->unique_header($extraLabel, $usedHeaders);
                $numericFlags[] = true;
            }
        }

        $separator = [];
        foreach ($headers as $position => $label) {
            $separator[] = !empty($numericFlags[$position]) ? '---:' : '---';
        }

        // Tahap 3: isi tabel.
        $table = [$this->row($headers)];
        $table[] = $this->row($separator, $numericFlags);

        foreach ($groupOrder as $key) {
            $group = $groups[$key];
            $cells = [];
            $flags = [];

            $cells[] = $group['reference'];
            $flags[] = false;
            foreach ($this->identityFields as $label => $identityKey) {
                $values = isset($group['identity'][$label]) ? $group['identity'][$label] : [];
                $cells[] = $values ? $this->merge_identity_values($values) : '';
                $flags[] = false;
            }

            foreach ($columnOrder as $columnKey => $position) {
                $cell = isset($group['cells'][$columnKey]) ? $group['cells'][$columnKey] : null;
                $cells[] = $cell === null ? '' : $cell['answer'];
                $flags[] = false;
                $cells[] = $cell === null ? '' : $cell['score'];
                $flags[] = true;
                foreach ($extraScoreKeys as $extraLabel => $extraKey) {
                    $cells[] = $cell === null ? '' : $cell['extra'][$extraLabel];
                    $flags[] = true;
                }
            }

            $table[] = $this->row($cells, $flags);
        }

        return rtrim(implode("\n", $table)) . "\n";
    }

    /**
     * Menggabungkan beberapa nilai field identitas menjadi satu sel.
     *
     * Nilai berbeda dalam satu referensi tidak boleh hilang, jadi digabung
     * dengan pemisah yang masih terbaca di dalam sel tabel.
     *
     * @param  array $values
     * @return string
     */
    private function merge_identity_values(array $values)
    {
        return implode(' / ', $values);
    }

    /**
     * Menyusun kunci kolom dari satu atau beberapa kunci baris.
     *
     * @param  array        $row
     * @param  string|array $keys
     * @return string
     */
    private function compose_key(array $row, $keys)
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $parts = [];
        foreach ($keys as $key) {
            $part = $this->normalize($this->field($row, $key));
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        return implode(' - ', $parts);
    }

    /**
     * Mengambil kolom nilai tambahan yang diminta pemanggil.
     *
     * @param  array $row
     * @param  array $extraKeys Label => kunci baris.
     * @return array
     */
    private function collect_extra_scores(array $row, array $extraKeys)
    {
        $values = [];
        foreach ($extraKeys as $label => $key) {
            $values[$label] = $this->field($row, $key);
        }
        return $values;
    }

    /**
     * Menjagam judul kolom tetap unik ketika dua soal berbeda memakai teks
     * yang sama.
     *
     * @param  string $label
     * @param  array  $used Kunci => true, diisi langsung.
     * @return string
     */
    private function unique_header($label, array &$used)
    {
        if (!isset($used[$label])) {
            $used[$label] = true;
            return $label;
        }
        $suffix = 2;
        while (isset($used[$label . ' (' . $suffix . ')'])) {
            $suffix++;
        }
        $unique = $label . ' (' . $suffix . ')';
        $used[$unique] = true;
        return $unique;
    }

    /**
     * Mengambil nilai dari baris dengan fallback sederhana.
     *
     * @param  array  $row
     * @param  string $key
     * @return mixed
     */
    private function field(array $row, $key)
    {
        if (!array_key_exists($key, $row)) {
            return '';
        }
        return $row[$key];
    }

    /**
     * Membersihkan nilai teks tanpa mengubah isi datanya.
     *
     * Yang dibersihkan hanya whitespace berulang, karakter kontrol, dan
     * escape pipe ganda. Huruf, angka, tanda baca, serta isi teks tetap utuh.
     *
     * @param  mixed $value
     * @return string
     */
    private function normalize($value)
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return '';
        }
        $value = (string) $value;
        if (trim($value) === '') {
            return '';
        }

        // Karakter kontrol termasuk newline/tab yang membuat baris Markdown
        // pecah sendiri.
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        // Beberapa engine PCRE lama tidak punya properti u; ulangi tanpa u.
        if ($value === null) {
            $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $value);
        }

        $value = preg_replace('/\s+/u', ' ', $value);
        if ($value === null) {
            $value = preg_replace('/\s+/', ' ', (string) $value);
        }
        return trim($value);
    }

    /**
     * Menyamakan nilai sel: dibersihkan, diisi bila kosong, lalu di-escape.
     *
     * @param  mixed $value
     * @return string
     */
    private function escape($value)
    {
        $value = $this->normalize($value);
        if ($value === '') {
            return $this->emptyValue;
        }
        // Pipe yang sudah ter-escape dinormalkan lebih dulu supaya tidak
        // ter-escape dua kali.
        $value = str_replace('\\|', '|', $value);
        return str_replace('|', '\\|', $value);
    }

    /**
     * Nilai angka tetap berupa angka, tapi escape pipe tetap berlaku bila
     * data ternyata berupa teks.
     *
     * @param  mixed $value
     * @return string
     */
    private function escape_number($value)
    {
        $value = $this->normalize($value);
        if ($value === '') {
            return $this->emptyValue;
        }
        $value = str_replace('|', '\\|', $value);
        if (!is_numeric($value)) {
            return $value;
        }
        return $this->format_number($value);
    }

    /**
     * Menulis angka tanpa mengubah nilainya.
     *
     * @param  string $value
     * @return string
     */
    private function format_number($value)
    {
        if ($this->numberDecimals !== null) {
            return number_format((float) $value, $this->numberDecimals, '.', '');
        }
        if (strpos($value, '.') === false) {
            return $value;
        }
        // Buang nol di belakang koma tanpa menyentuh digit berarti: 25.00 jadi
        // 25, 76.60 jadi 76.6, 76.61 tetap 76.61.
        $trimmed = rtrim(rtrim($value, '0'), '.');
        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }
}