<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api_documentation_pdf
{
    public function build(array $client, array $surveys, array $resourceDefinitions, array $dimensionDefinitions, array $detailFieldDefinitions, $endpoint)
    {
        $logoPath = FCPATH . 'assets/images/logo-dpmptsp-document.jpg';
        $pdf = new Api_pdf_canvas($logoPath);
        $documentCode = 'DPMPTSP-API-' . str_pad((int) $client['id'], 4, '0', STR_PAD_LEFT) . '-' . date('Ymd');
        $surveyCodes = [];
        $surveyNames = [];
        foreach ($surveys as $survey) {
            $surveyCodes[] = $survey['survey_code'];
            $surveyNames[] = $survey['survey_name'] . ' (' . $survey['survey_code'] . ')';
        }
        $sampleSurvey = $surveyCodes ? $surveyCodes[0] : 'KODE_SURVEI';
        $year = date('Y');

        $resourceLabels = [];
        foreach ($client['allowed_resources'] as $resource) {
            if (isset($resourceDefinitions[$resource])) {
                $resourceLabels[] = $resourceDefinitions[$resource]['label'];
            }
        }
        $dimensionLabels = [];
        foreach ($client['allowed_dimensions'] as $dimension) {
            if (isset($dimensionDefinitions[$dimension])) {
                $dimensionLabels[] = $dimensionDefinitions[$dimension];
            }
        }
        $detailLabels = [];
        $privateDetailLabels = [];
        foreach ($client['allowed_detail_fields'] as $field) {
            if (!isset($detailFieldDefinitions[$field])) {
                continue;
            }
            $detailLabels[] = $detailFieldDefinitions[$field]['label'];
            if (!empty($detailFieldDefinitions[$field]['privacy'])) {
                $privateDetailLabels[] = $detailFieldDefinitions[$field]['label'];
            }
        }

        $pdf->addCoverPage(
            'DOKUMENTASI TEKNIS INTEGRASI API',
            $client['client_name'],
            'Panduan penggunaan melalui Postman, cURL, aplikasi server, dan aplikasi web',
            $documentCode,
            date('d-m-Y H:i') . ' WIB'
        );

        $pdf->addPage();
        $pdf->sectionTitle('1. Identitas dan Cakupan Akses');
        $pdf->paragraph(
            'Dokumen ini menjelaskan cara aplikasi ' . $client['client_name'] .
            ' membaca data Survei Pelayanan Terpadu DPMPTSP Provinsi Jawa Barat. API bersifat read-only. ' .
            'Aplikasi peminta hanya menerima survei, jenis keluaran, dimensi, dan kolom yang telah diizinkan pada API Builder.'
        );
        $pdf->table(
            ['Komponen', 'Nilai'],
            [
                ['Nama peminta', $client['client_name']],
                ['Kode endpoint', $client['client_code']],
                ['Endpoint dasar', $endpoint],
                ['Awalan kunci', $client['api_key_prefix'] . '...'],
                ['Cakupan survei', $client['survey_scope'] === 'all' ? 'Semua survei aktif' : implode(', ', $surveyNames)],
                ['Jenis keluaran', $resourceLabels ? implode(', ', $resourceLabels) : '-'],
                ['Batas data detail', (int) $client['max_page_size'] . ' data per halaman'],
                ['Batas permintaan', (int) $client['rate_limit_per_minute'] . ' permintaan per menit'],
                ['Masa berlaku', $client['expires_at'] ? date('d-m-Y H:i', strtotime($client['expires_at'])) . ' WIB' : 'Tanpa batas tanggal'],
                ['Status saat dokumen dibuat', $client['is_active'] ? 'Aktif' : 'Nonaktif'],
            ],
            [118, 385]
        );
        $pdf->note(
            'Kunci API lengkap tidak dicetak dalam dokumen ini. Kunci hanya ditampilkan satu kali saat akses dibuat atau dibuat ulang. ' .
            'Masukkan kunci yang diterima melalui jalur resmi ke environment/secret manager aplikasi peminta.'
        );

        $pdf->sectionTitle('2. Autentikasi');
        $pdf->paragraph('Gunakan salah satu header berikut pada setiap permintaan. Bearer Token direkomendasikan.');
        $pdf->codeBlock("Authorization: Bearer {{api_key}}\nAccept: application/json");
        $pdf->paragraph('Alternatif jika sistem peminta tidak mendukung Bearer Token:');
        $pdf->codeBlock("X-API-Key: {{api_key}}\nAccept: application/json");
        $pdf->bullet('Jangan menaruh kunci API pada query string atau parameter URL.');
        $pdf->bullet('Jangan menanam kunci pada JavaScript publik, aplikasi mobile, atau repositori source code.');
        $pdf->bullet('Jika kunci diduga bocor, gunakan tombol "Buat ulang kunci" pada API Builder.');

        $pdf->sectionTitle('3. Penggunaan melalui Postman');
        $pdf->numberedSteps([
            'Buat Environment baru, misalnya "DPMPTSP Jabar API".',
            'Tambahkan variable base_url dengan nilai ' . $endpoint . '.',
            'Tambahkan variable api_key sebagai Secret, lalu isi dengan kunci yang diberikan terpisah.',
            'Buat request baru dengan metode GET dan URL {{base_url}}.',
            'Pada tab Authorization pilih Bearer Token, lalu isi {{api_key}}.',
            'Pada tab Params isi resource, survey, year, dan filter lain sesuai kebutuhan.',
            'Tekan Send. Respons berhasil memiliki HTTP 200 dan success bernilai true.',
        ]);
        $pdf->table(
            ['Variable Postman', 'Contoh nilai', 'Keterangan'],
            [
                ['base_url', $endpoint, 'Endpoint khusus peminta'],
                ['api_key', 'skm_live_xxx', 'Simpan sebagai Secret'],
                ['survey', $sampleSurvey, 'Kode, ID, atau UUID survei'],
                ['year', $year, 'Tahun data'],
            ],
            [105, 175, 223]
        );

        $pdf->sectionTitle('4. Parameter Umum');
        $pdf->table(
            ['Parameter', 'Wajib', 'Contoh', 'Kegunaan'],
            [
                ['resource', 'Ya', 'summary', 'Jenis keluaran: summary, chart, details, atau questions'],
                ['survey', 'Kondisional', $sampleSurvey, 'ID, kode, atau UUID survei. Detail wajib tepat satu survei'],
                ['year', 'Tidak', $year, 'Tahun data; default memakai tahun terbaru yang mempunyai respons'],
                ['date_from', 'Tidak', $year . '-01-01', 'Tanggal awal format YYYY-MM-DD'],
                ['date_to', 'Tidak', $year . '-12-31', 'Tanggal akhir format YYYY-MM-DD'],
                ['unit_id', 'Tidak', '1', 'Membatasi perangkat daerah'],
                ['service', 'Tidak', '16', 'Membatasi sektor layanan'],
                ['gender', 'Tidak', '1', '1 laki-laki, 2 perempuan'],
                ['education', 'Tidak', '5', 'ID pendidikan'],
                ['job', 'Tidak', '2', 'ID pekerjaan'],
            ],
            [75, 66, 105, 257]
        );

        $pdf->sectionTitle('5. Contoh cURL');
        if (in_array('summary', $client['allowed_resources'], true)) {
            $pdf->subTitle('5.1 Ringkasan nilai per survei');
            $pdf->codeBlock(
                'curl --request GET "' . $endpoint . '?resource=summary&survey=' . rawurlencode($sampleSurvey) .
                '&year=' . $year . "\" \\\n  --header \"Authorization: Bearer {{api_key}}\" \\\n  --header \"Accept: application/json\""
            );
        }
        if (in_array('chart', $client['allowed_resources'], true)) {
            $sampleDimension = in_array('overall', $client['allowed_dimensions'], true)
                ? 'overall'
                : ($client['allowed_dimensions'] ? $client['allowed_dimensions'][0] : 'overall');
            $pdf->subTitle('5.2 Data grafik');
            $pdf->codeBlock(
                'curl --request GET "' . $endpoint . '?resource=chart&survey=' . rawurlencode($sampleSurvey) .
                '&dimension=' . rawurlencode($sampleDimension) . '&year=' . $year .
                "\" \\\n  --header \"X-API-Key: {{api_key}}\" \\\n  --header \"Accept: application/json\""
            );
            $pdf->paragraph('Dimensi yang diizinkan untuk akses ini: ' . ($dimensionLabels ? implode(', ', $dimensionLabels) : '-'));
        }
        if (in_array('details', $client['allowed_resources'], true)) {
            $pdf->subTitle('5.3 Detail respons dengan pagination');
            $pdf->codeBlock(
                'curl --request GET "' . $endpoint . '?resource=details&survey=' . rawurlencode($sampleSurvey) .
                '&year=' . $year . "&page=1&per_page=25\" \\\n  --header \"Authorization: Bearer {{api_key}}\" \\\n  --header \"Accept: application/json\""
            );
            $pdf->paragraph('Kolom detail yang dapat diterima: ' . ($detailLabels ? implode(', ', $detailLabels) : '-'));
            if ($privateDetailLabels) {
                $pdf->warning(
                    'Akses ini mengizinkan data pribadi: ' . implode(', ', $privateDetailLabels) .
                    '. Batasi penyimpanan, pencatatan log, dan pengguna yang dapat melihat data tersebut.'
                );
            }
        }
        if (in_array('questions', $client['allowed_resources'], true)) {
            $pdf->subTitle('5.4 Struktur pertanyaan dan pilihan jawaban');
            $pdf->codeBlock(
                'curl --request GET "' . $endpoint . '?resource=questions&survey=' . rawurlencode($sampleSurvey) .
                "\" \\\n  --header \"Authorization: Bearer {{api_key}}\" \\\n  --header \"Accept: application/json\""
            );
        }

        $pdf->sectionTitle('6. Contoh Integrasi Lain');
        $pdf->subTitle('6.1 PHP cURL');
        $pdf->codeBlock(
            "<?php\n" .
            '$url = "' . $endpoint . '?resource=summary&survey=' . $sampleSurvey . '&year=' . $year . '";' . "\n" .
            '$ch = curl_init($url);' . "\n" .
            'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);' . "\n" .
            'curl_setopt($ch, CURLOPT_HTTPHEADER, [' . "\n" .
            '    "Authorization: Bearer " . getenv("DPMPTSP_API_KEY"),' . "\n" .
            '    "Accept: application/json"' . "\n" .
            ']);' . "\n" .
            '$response = curl_exec($ch);' . "\n" .
            '$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);' . "\n" .
            'curl_close($ch);' . "\n" .
            'if ($status !== 200) { throw new RuntimeException($response); }' . "\n" .
            '$data = json_decode($response, true);'
        );
        $pdf->subTitle('6.2 JavaScript fetch dari server/backend');
        $pdf->codeBlock(
            'const url = new URL("' . $endpoint . '");' . "\n" .
            'url.searchParams.set("resource", "summary");' . "\n" .
            'url.searchParams.set("survey", "' . $sampleSurvey . '");' . "\n" .
            'url.searchParams.set("year", "' . $year . '");' . "\n" .
            'const response = await fetch(url, {' . "\n" .
            '  headers: { Authorization: `Bearer ${process.env.DPMPTSP_API_KEY}` }' . "\n" .
            '});' . "\n" .
            'if (!response.ok) throw new Error(`API error ${response.status}`);' . "\n" .
            'const payload = await response.json();'
        );
        $pdf->subTitle('6.3 PowerShell');
        $pdf->codeBlock(
            '$headers = @{ Authorization = "Bearer $env:DPMPTSP_API_KEY" }' . "\n" .
            '$uri = "' . $endpoint . '?resource=summary&survey=' . $sampleSurvey . '&year=' . $year . '"' . "\n" .
            '$result = Invoke-RestMethod -Method Get -Uri $uri -Headers $headers'
        );

        $pdf->sectionTitle('7. Bentuk Respons');
        $pdf->subTitle('7.1 Respons berhasil - summary');
        $pdf->codeBlock(
            "{\n" .
            '  "success": true,' . "\n" .
            '  "api_version": "v1",' . "\n" .
            '  "resource": "summary",' . "\n" .
            '  "client": {"code": "' . $client['client_code'] . '", "name": "' . $client['client_name'] . '"},' . "\n" .
            '  "request": {"resource": "summary", "filters": {"year": ' . $year . '}},' . "\n" .
            '  "generated_at": "' . date(DATE_ATOM) . '",' . "\n" .
            '  "data": [{' . "\n" .
            '    "survey": {"code": "' . $sampleSurvey . '", "name": "Nama survei"},' . "\n" .
            '    "total_responses": 125,' . "\n" .
            '    "average_score": 88.40,' . "\n" .
            '    "minimum_score": 62.50,' . "\n" .
            '    "maximum_score": 100,' . "\n" .
            '    "category": {"label": "Sangat Baik", "color": "#16a34a"}' . "\n" .
            "  }]\n" .
            '}'
        );
        if (in_array('details', $client['allowed_resources'], true)) {
            $pdf->subTitle('7.2 Pagination data detail');
            $pdf->codeBlock(
                "{\n" .
                '  "success": true,' . "\n" .
                '  "resource": "details",' . "\n" .
                '  "data": [/* kolom yang diizinkan */],' . "\n" .
                '  "pagination": {' . "\n" .
                '    "page": 1, "per_page": 25,' . "\n" .
                '    "total_records": 125, "total_pages": 5' . "\n" .
                "  }\n" .
                '}'
            );
        }
        $pdf->subTitle('7.3 Respons gagal');
        $pdf->codeBlock(
            "{\n" .
            '  "success": false,' . "\n" .
            '  "api_version": "v1",' . "\n" .
            '  "error": {' . "\n" .
            '    "status": 403,' . "\n" .
            '    "message": "Survei tidak ditemukan atau tidak diizinkan untuk kunci API tersebut."' . "\n" .
            "  }\n" .
            '}'
        );

        $pdf->sectionTitle('8. Kondisi HTTP dan Penanganannya');
        $pdf->table(
            ['Status', 'Kondisi', 'Tindakan aplikasi peminta'],
            [
                ['200', 'Permintaan berhasil', 'Baca data dan metadata pagination jika tersedia'],
                ['400', 'Parameter tidak lengkap atau tidak valid', 'Periksa resource, survey, dimension, tanggal, dan pagination'],
                ['401', 'Kunci kosong, salah, atau kunci lama', 'Periksa secret; minta kunci baru jika diperlukan'],
                ['403', 'Akses nonaktif, kedaluwarsa, IP salah, atau data tidak diizinkan', 'Hubungi pengelola API Builder'],
                ['404', 'Alamat endpoint tidak ditemukan', 'Periksa kode endpoint dan base URL'],
                ['405', 'Metode selain GET/OPTIONS', 'Ubah metode menjadi GET'],
                ['429', 'Batas permintaan per menit tercapai', 'Tunggu, gunakan retry dengan exponential backoff'],
                ['500', 'Gangguan internal sementara', 'Catat waktu kejadian dan ulangi secara terbatas'],
            ],
            [55, 190, 258]
        );
        $pdf->paragraph(
            'Untuk status 429 dan 500, jangan mengulang terus-menerus. Gunakan jeda 2, 4, 8, lalu maksimal 16 detik. ' .
            'Hentikan setelah beberapa percobaan dan simpan catatan kegagalan tanpa mencatat kunci API.'
        );

        $pdf->sectionTitle('9. Skenario dan Kondisi Khusus');
        $pdf->table(
            ['Kondisi', 'Perilaku API'],
            [
                ['Tidak mengirim parameter survey', 'Summary, chart, dan questions mengembalikan seluruh survei yang diizinkan. Details meminta tepat satu survei.'],
                ['Survei gabungan', 'Setiap hasil tetap dikembalikan sebagai survei terpisah berdasarkan kode dan UUID survei.'],
                ['Tahun tidak mempunyai data', 'Respons tetap HTTP 200 dengan jumlah nol atau seri grafik kosong.'],
                ['per_page melebihi batas', 'Sistem otomatis menurunkan nilai ke batas maksimal konfigurasi.'],
                ['Kunci dibuat ulang', 'Kunci lama langsung menghasilkan HTTP 401.'],
                ['Akses dinonaktifkan/kedaluwarsa', 'Seluruh permintaan menghasilkan HTTP 403.'],
                ['Origin browser berbeda', 'Browser hanya menerima respons jika Origin sesuai pengaturan CORS.'],
                ['IP server berubah', 'Permintaan ditolak jika IP baru belum masuk daftar yang diizinkan.'],
                ['Kolom detail tidak dipilih', 'Kolom tersebut tidak pernah dikirim, meskipun tersedia pada database.'],
            ],
            [175, 328]
        );

        $pdf->sectionTitle('10. Keamanan dan Operasional');
        $pdf->bullet('Simpan kunci pada environment variable atau secret manager; jangan masukkan ke source code.');
        $pdf->bullet('Gunakan HTTPS pada lingkungan produksi agar header autentikasi terenkripsi selama perjalanan.');
        $pdf->bullet('Berikan hak minimum: survei, resource, dimensi, dan kolom hanya sesuai kebutuhan resmi.');
        $pdf->bullet('Tinjau log pada API Builder untuk melihat status, IP, jumlah data, dan durasi permintaan.');
        $pdf->bullet('Jangan menyimpan isi data pribadi pada log aplikasi peminta.');
        $pdf->bullet('Nonaktifkan akses setelah kerja sama selesai; data survei tidak ikut terhapus.');
        $pdf->bullet('Dokumentasi ini tidak memuat kunci lengkap dan aman dibagikan kepada tim teknis yang berwenang.');

        $pdf->sectionTitle('11. Pengaturan Keamanan Akses Ini');
        $pdf->table(
            ['Pengaturan', 'Nilai'],
            [
                ['CORS / Origin', $client['allowed_origin'] !== '' ? $client['allowed_origin'] : 'Tidak ditetapkan; penggunaan server-to-server'],
                ['IP yang diizinkan', $client['allowed_ip_addresses'] !== '' ? $client['allowed_ip_addresses'] : 'Semua IP; disarankan batasi jika server sudah tetap'],
                ['Kolom detail pribadi', $privateDetailLabels ? implode(', ', $privateDetailLabels) : 'Tidak ada'],
                ['Dimensi grafik', $dimensionLabels ? implode(', ', $dimensionLabels) : 'Tidak diizinkan'],
                ['Kontak pengelola', 'Administrator Backoffice Survei DPMPTSP Provinsi Jawa Barat'],
            ],
            [145, 358]
        );
        $pdf->sectionTitle('12. Pengesahan Dokumen Sistem');
        $pdf->paragraph(
            'Dokumentasi ini dibuat otomatis dari konfigurasi API Builder yang berlaku pada waktu penerbitan. ' .
            'Dokumen dapat digunakan sebagai panduan teknis resmi integrasi, tetapi tidak menggantikan perjanjian kerja sama, ' .
            'persetujuan pemrosesan data, atau tanda tangan elektronik tersertifikasi.'
        );
        $pdf->table(
            ['Elemen verifikasi', 'Nilai'],
            [
                ['Kode dokumen', $documentCode],
                ['Peminta API', $client['client_name']],
                ['Kode endpoint', $client['client_code']],
                ['Tanggal penerbitan', date('d-m-Y H:i') . ' WIB'],
            ],
            [145, 358]
        );
        $pdf->systemStamp(
            'DOKUMEN SISTEM',
            'DPMPTSP PROVINSI JAWA BARAT',
            'Dibuat otomatis: ' . date('d-m-Y H:i') . ' WIB',
            $documentCode
        );

        return $pdf->output($documentCode);
    }
}

class Api_pdf_canvas
{
    private $pageWidth = 595.28;
    private $pageHeight = 841.89;
    private $marginLeft = 46;
    private $marginRight = 46;
    private $contentBottom = 784;
    private $pages = [];
    private $currentPage = -1;
    private $y = 90;
    private $logoData = '';
    private $logoWidth = 1;
    private $logoHeight = 1;
    private $documentCode = '';

    public function __construct($logoPath)
    {
        if (is_file($logoPath)) {
            $info = getimagesize($logoPath);
            if ($info && isset($info[0], $info[1])) {
                $this->logoWidth = (int) $info[0];
                $this->logoHeight = (int) $info[1];
                $this->logoData = file_get_contents($logoPath);
            }
        }
    }

    public function addCoverPage($title, $clientName, $subtitle, $documentCode, $generatedAt)
    {
        $this->documentCode = $documentCode;
        $this->newPage(false);
        $this->fillRect(0, 0, $this->pageWidth, 12, [0.04, 0.45, 0.24]);
        $this->fillRect(0, 12, $this->pageWidth, 5, [0.05, 0.42, 0.72]);
        $this->image(($this->pageWidth - 142) / 2, 64, 142, 114);
        $this->centerText(206, 'PEMERINTAH DAERAH PROVINSI JAWA BARAT', 'bold', 11, [0.12, 0.18, 0.28]);
        $this->centerText(224, 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU', 'bold', 10, [0.12, 0.18, 0.28]);
        $this->line(70, 247, $this->pageWidth - 70, 247, [0.04, 0.45, 0.24], 1.4);
        $this->centerText(294, $title, 'bold', 23, [0.08, 0.16, 0.31]);
        $this->centerText(329, 'SURVEI PELAYANAN TERPADU', 'bold', 13, [0.05, 0.42, 0.72]);
        $this->roundedPanel(74, 380, $this->pageWidth - 148, 118, [0.96, 0.97, 1], [0.78, 0.82, 0.92]);
        $this->centerText(402, 'DOKUMEN KHUSUS UNTUK', 'bold', 8, [0.38, 0.43, 0.52]);
        $this->centerWrappedText(427, $clientName, $this->pageWidth - 190, 'bold', 17, 21, [0.1, 0.18, 0.34]);
        $this->centerWrappedText(471, $subtitle, $this->pageWidth - 190, 'regular', 9, 13, [0.38, 0.43, 0.52]);
        $this->tableAt(
            90,
            555,
            ['Kode dokumen', 'Versi API', 'Tanggal dibuat', 'Klasifikasi'],
            [[$documentCode, 'v1', $generatedAt, 'Terbatas - Teknis']],
            [145, 55, 120, 95],
            7.5
        );
        $this->strokeRect(183, 676, 229, 69, [0.05, 0.42, 0.72], 1.3);
        $this->centerText(690, 'DOKUMEN KELUARAN SISTEM', 'bold', 10, [0.05, 0.42, 0.72]);
        $this->centerText(708, 'DPMPTSP PROVINSI JAWA BARAT', 'bold', 8, [0.04, 0.45, 0.24]);
        $this->centerText(725, 'Tidak memuat kunci API lengkap', 'regular', 8, [0.38, 0.43, 0.52]);
    }

    public function addPage()
    {
        $this->newPage(true);
    }

    public function sectionTitle($text)
    {
        $this->ensure(150);
        $this->fillRect($this->marginLeft, $this->y, 5, 24, [0.04, 0.45, 0.24]);
        $this->text($this->marginLeft + 13, $this->y + 3, $text, 'bold', 14, [0.08, 0.16, 0.31]);
        $this->y += 34;
    }

    public function subTitle($text)
    {
        $this->ensure(84);
        $this->text($this->marginLeft, $this->y, $text, 'bold', 10, [0.05, 0.42, 0.72]);
        $this->y += 20;
    }

    public function paragraph($text)
    {
        $lines = $this->wrap($text, $this->pageWidth - $this->marginLeft - $this->marginRight, 'regular', 9.3);
        foreach ($lines as $line) {
            $this->ensure(14);
            $this->text($this->marginLeft, $this->y, $line, 'regular', 9.3, [0.25, 0.3, 0.38]);
            $this->y += 13.5;
        }
        $this->y += 6;
    }

    public function bullet($text)
    {
        $width = $this->pageWidth - $this->marginLeft - $this->marginRight - 18;
        $lines = $this->wrap($text, $width, 'regular', 9);
        $this->ensure(max(16, count($lines) * 13));
        $this->fillRect($this->marginLeft + 2, $this->y + 5, 5, 5, [0.05, 0.42, 0.72]);
        foreach ($lines as $index => $line) {
            $this->text($this->marginLeft + 17, $this->y + ($index * 13), $line, 'regular', 9, [0.25, 0.3, 0.38]);
        }
        $this->y += count($lines) * 13 + 4;
    }

    public function numberedSteps(array $steps)
    {
        foreach ($steps as $index => $step) {
            $width = $this->pageWidth - $this->marginLeft - $this->marginRight - 32;
            $lines = $this->wrap($step, $width, 'regular', 9);
            $height = max(24, count($lines) * 13 + 5);
            $this->ensure($height);
            $this->fillRect($this->marginLeft, $this->y, 22, 22, [0.05, 0.42, 0.72]);
            $this->text($this->marginLeft + 7, $this->y + 4, (string) ($index + 1), 'bold', 9, [1, 1, 1]);
            foreach ($lines as $lineIndex => $line) {
                $this->text($this->marginLeft + 32, $this->y + ($lineIndex * 13), $line, 'regular', 9, [0.25, 0.3, 0.38]);
            }
            $this->y += $height;
        }
        $this->y += 3;
    }

    public function codeBlock($code)
    {
        $logicalLines = preg_split('/\r\n|\r|\n/', (string) $code);
        $lines = [];
        foreach ($logicalLines as $logicalLine) {
            $wrapped = $this->wrap($logicalLine === '' ? ' ' : $logicalLine, 467, 'mono', 7.5);
            foreach ($wrapped as $line) {
                $lines[] = $line;
            }
        }
        $chunks = array_chunk($lines, 34);
        foreach ($chunks as $chunk) {
            $height = count($chunk) * 10.5 + 18;
            $this->ensure($height + 4);
            $this->fillRect($this->marginLeft, $this->y, 503, $height, [0.96, 0.97, 0.99]);
            $this->strokeRect($this->marginLeft, $this->y, 503, $height, [0.84, 0.86, 0.91], 0.6);
            foreach ($chunk as $index => $line) {
                $this->text($this->marginLeft + 9, $this->y + 8 + ($index * 10.5), $line, 'mono', 7.5, [0.12, 0.2, 0.36]);
            }
            $this->y += $height + 8;
        }
    }

    public function note($text)
    {
        $this->callout($text, [0.93, 0.97, 1], [0.05, 0.42, 0.72], 'CATATAN');
    }

    public function warning($text)
    {
        $this->callout($text, [1, 0.96, 0.91], [0.78, 0.36, 0.05], 'PERHATIAN');
    }

    private function callout($text, array $background, array $accent, $label)
    {
        $lines = $this->wrap($text, 397, 'regular', 8.7);
        $height = max(43, count($lines) * 12 + 20);
        $this->ensure($height + 5);
        $this->fillRect($this->marginLeft, $this->y, 503, $height, $background);
        $this->fillRect($this->marginLeft, $this->y, 6, $height, $accent);
        $this->text($this->marginLeft + 17, $this->y + 10, $label, 'bold', 8, $accent);
        foreach ($lines as $index => $line) {
            $this->text($this->marginLeft + 90, $this->y + 8 + ($index * 12), $line, 'regular', 8.7, [0.28, 0.32, 0.38]);
        }
        $this->y += $height + 8;
    }

    public function table(array $headers, array $rows, array $widths)
    {
        $this->drawFlowTable($headers, $rows, $widths, 8.2);
        $this->y += 8;
    }

    public function systemStamp($title, $department, $generatedAt, $documentCode)
    {
        $this->ensure(122);
        $x = $this->marginLeft + 104;
        $width = 295;
        $this->strokeRect($x, $this->y, $width, 103, [0.05, 0.42, 0.72], 1.5);
        $this->strokeRect($x + 5, $this->y + 5, $width - 10, 93, [0.04, 0.45, 0.24], 0.8);
        $this->centerTextWithin($x, $width, $this->y + 12, $title, 'bold', 12, [0.05, 0.42, 0.72]);
        $this->centerTextWithin($x, $width, $this->y + 32, $department, 'bold', 9, [0.04, 0.45, 0.24]);
        $this->centerTextWithin($x, $width, $this->y + 49, $generatedAt, 'regular', 8, [0.32, 0.37, 0.45]);
        $this->centerTextWithin($x, $width, $this->y + 64, $documentCode, 'mono', 7.5, [0.32, 0.37, 0.45]);
        $this->centerTextWithin($x, $width, $this->y + 80, 'Bukan tanda tangan elektronik tersertifikasi', 'regular', 7.2, [0.42, 0.46, 0.53]);
        $this->y += 115;
    }

    public function output($documentCode)
    {
        $this->documentCode = $documentCode;
        $pageCount = count($this->pages);
        foreach ($this->pages as $index => $page) {
            if ($index === 0) {
                $this->pages[$index] .= $this->footerCommands($index + 1, $pageCount, true);
            } else {
                $this->pages[$index] .= $this->footerCommands($index + 1, $pageCount, false);
            }
        }

        $objects = [];
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';
        if ($this->logoData !== '') {
            $objects[6] = '<< /Type /XObject /Subtype /Image /Width ' . $this->logoWidth .
                ' /Height ' . $this->logoHeight .
                ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' .
                strlen($this->logoData) . " >>\nstream\n" . $this->logoData . "\nendstream";
        } else {
            $objects[6] = '<< /Length 0 >> stream' . "\n" . 'endstream';
        }

        $pageObjectIds = [];
        foreach ($this->pages as $index => $content) {
            $contentId = 7 + ($index * 2);
            $pageId = $contentId + 1;
            $pageObjectIds[] = $pageId . ' 0 R';
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' .
                $this->format($this->pageWidth) . ' ' . $this->format($this->pageHeight) .
                '] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> /XObject << /Im1 6 0 R >> >>' .
                ' /Contents ' . $contentId . ' 0 R >>';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjectIds) . '] /Count ' . count($pageObjectIds) . ' >>';
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R /PageLayout /OneColumn >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        $maxObjectId = max(array_keys($objects));
        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objects[$objectId] . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$objectId]) . "\n";
        }
        $pdf .= 'trailer << /Size ' . ($maxObjectId + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";
        return $pdf;
    }

    private function newPage($withHeader)
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
        $this->y = $withHeader ? 92 : 0;
        if ($withHeader) {
            $this->image($this->marginLeft, 26, 55, 44);
            $this->text(112, 29, 'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU', 'bold', 8.5, [0.08, 0.16, 0.31]);
            $this->text(112, 44, 'PEMERINTAH DAERAH PROVINSI JAWA BARAT', 'bold', 8, [0.04, 0.45, 0.24]);
            $this->text(112, 59, 'Dokumentasi Teknis Integrasi API Survei', 'regular', 7.8, [0.38, 0.43, 0.52]);
            $this->line($this->marginLeft, 77, $this->pageWidth - $this->marginRight, 77, [0.05, 0.42, 0.72], 1.1);
            $this->line($this->marginLeft, 80, $this->pageWidth - $this->marginRight, 80, [0.04, 0.45, 0.24], 0.6);
        }
    }

    private function ensure($height)
    {
        if ($this->currentPage < 0) {
            $this->newPage(true);
        }
        if ($this->y + $height > $this->contentBottom) {
            $this->newPage(true);
        }
    }

    private function drawFlowTable(array $headers, array $rows, array $widths, $fontSize)
    {
        $this->ensure(32);
        $this->drawTableHeader($headers, $widths, $fontSize);
        foreach ($rows as $row) {
            $cellLines = [];
            $rowHeight = 23;
            foreach ($widths as $index => $width) {
                $value = isset($row[$index]) ? (string) $row[$index] : '';
                $cellLines[$index] = $this->wrap($value, $width - 12, 'regular', $fontSize);
                $rowHeight = max($rowHeight, count($cellLines[$index]) * 11 + 10);
            }
            if ($this->y + $rowHeight > $this->contentBottom) {
                $this->newPage(true);
                $this->drawTableHeader($headers, $widths, $fontSize);
            }
            $x = $this->marginLeft;
            foreach ($widths as $index => $width) {
                $this->fillRect($x, $this->y, $width, $rowHeight, [1, 1, 1]);
                $this->strokeRect($x, $this->y, $width, $rowHeight, [0.85, 0.87, 0.91], 0.45);
                foreach ($cellLines[$index] as $lineIndex => $line) {
                    $this->text($x + 6, $this->y + 6 + ($lineIndex * 11), $line, 'regular', $fontSize, [0.25, 0.3, 0.38]);
                }
                $x += $width;
            }
            $this->y += $rowHeight;
        }
    }

    private function drawTableHeader(array $headers, array $widths, $fontSize)
    {
        $height = 25;
        $x = $this->marginLeft;
        foreach ($widths as $index => $width) {
            $this->fillRect($x, $this->y, $width, $height, [0.08, 0.16, 0.31]);
            $this->strokeRect($x, $this->y, $width, $height, [1, 1, 1], 0.4);
            $label = isset($headers[$index]) ? $headers[$index] : '';
            $this->text($x + 6, $this->y + 7, $label, 'bold', $fontSize, [1, 1, 1]);
            $x += $width;
        }
        $this->y += $height;
    }

    private function tableAt($x, $y, array $headers, array $rows, array $widths, $fontSize)
    {
        $originalY = $this->y;
        $originalLeft = $this->marginLeft;
        $this->marginLeft = $x;
        $this->y = $y;
        $this->drawTableHeader($headers, $widths, $fontSize);
        foreach ($rows as $row) {
            $rowHeight = 29;
            $cellX = $x;
            foreach ($widths as $index => $width) {
                $this->fillRect($cellX, $this->y, $width, $rowHeight, [1, 1, 1]);
                $this->strokeRect($cellX, $this->y, $width, $rowHeight, [0.82, 0.85, 0.9], 0.45);
                $this->text($cellX + 6, $this->y + 9, isset($row[$index]) ? $row[$index] : '', 'regular', $fontSize, [0.25, 0.3, 0.38]);
                $cellX += $width;
            }
            $this->y += $rowHeight;
        }
        $this->marginLeft = $originalLeft;
        $this->y = $originalY;
    }

    private function wrap($text, $maxWidth, $font, $size)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            return [''];
        }
        $words = explode(' ', $text);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($this->measure($word, $font, $size) > $maxWidth) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $parts = $this->splitLongWord($word, $maxWidth, $font, $size);
                foreach ($parts as $partIndex => $part) {
                    if ($partIndex === count($parts) - 1) {
                        $line = $part;
                    } else {
                        $lines[] = $part;
                    }
                }
                continue;
            }
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->measure($candidate, $font, $size) <= $maxWidth) {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }

    private function splitLongWord($word, $maxWidth, $font, $size)
    {
        $parts = [];
        $part = '';
        $length = strlen($word);
        for ($index = 0; $index < $length; $index++) {
            $candidate = $part . $word[$index];
            if ($part !== '' && $this->measure($candidate, $font, $size) > $maxWidth) {
                $parts[] = $part;
                $part = $word[$index];
            } else {
                $part = $candidate;
            }
        }
        if ($part !== '') {
            $parts[] = $part;
        }
        return $parts;
    }

    private function measure($text, $font, $size)
    {
        $factor = $font === 'mono' ? 0.6 : 0.51;
        return strlen($this->encodeText($text)) * $size * $factor;
    }

    private function text($x, $top, $text, $font, $size, array $color)
    {
        $fontResource = $font === 'bold' ? 'F2' : ($font === 'mono' ? 'F3' : 'F1');
        $baseline = $this->pageHeight - $top - $size;
        $encoded = $this->escapeText($this->encodeText($text));
        $this->command(
            "BT /" . $fontResource . ' ' . $this->format($size) . ' Tf ' .
            $this->color($color) . ' rg ' . $this->format($x) . ' ' . $this->format($baseline) .
            ' Td (' . $encoded . ") Tj ET\n"
        );
    }

    private function centerText($top, $text, $font, $size, array $color)
    {
        $width = $this->measure($text, $font, $size);
        $this->text(max(20, ($this->pageWidth - $width) / 2), $top, $text, $font, $size, $color);
    }

    private function centerTextWithin($x, $width, $top, $text, $font, $size, array $color)
    {
        $textWidth = $this->measure($text, $font, $size);
        $this->text($x + max(4, ($width - $textWidth) / 2), $top, $text, $font, $size, $color);
    }

    private function centerWrappedText($top, $text, $maxWidth, $font, $size, $lineHeight, array $color)
    {
        foreach ($this->wrap($text, $maxWidth, $font, $size) as $index => $line) {
            $this->centerText($top + ($index * $lineHeight), $line, $font, $size, $color);
        }
    }

    private function image($x, $top, $width, $height)
    {
        if ($this->logoData === '') {
            return;
        }
        $bottom = $this->pageHeight - $top - $height;
        $this->command(
            'q ' . $this->format($width) . ' 0 0 ' . $this->format($height) . ' ' .
            $this->format($x) . ' ' . $this->format($bottom) . " cm /Im1 Do Q\n"
        );
    }

    private function fillRect($x, $top, $width, $height, array $color)
    {
        $bottom = $this->pageHeight - $top - $height;
        $this->command(
            $this->color($color) . ' rg ' . $this->format($x) . ' ' . $this->format($bottom) . ' ' .
            $this->format($width) . ' ' . $this->format($height) . " re f\n"
        );
    }

    private function strokeRect($x, $top, $width, $height, array $color, $lineWidth)
    {
        $bottom = $this->pageHeight - $top - $height;
        $this->command(
            $this->color($color) . ' RG ' . $this->format($lineWidth) . ' w ' .
            $this->format($x) . ' ' . $this->format($bottom) . ' ' .
            $this->format($width) . ' ' . $this->format($height) . " re S\n"
        );
    }

    private function roundedPanel($x, $top, $width, $height, array $fill, array $stroke)
    {
        $this->fillRect($x, $top, $width, $height, $fill);
        $this->strokeRect($x, $top, $width, $height, $stroke, 0.8);
    }

    private function line($x1, $top1, $x2, $top2, array $color, $lineWidth)
    {
        $y1 = $this->pageHeight - $top1;
        $y2 = $this->pageHeight - $top2;
        $this->command(
            $this->color($color) . ' RG ' . $this->format($lineWidth) . ' w ' .
            $this->format($x1) . ' ' . $this->format($y1) . ' m ' .
            $this->format($x2) . ' ' . $this->format($y2) . " l S\n"
        );
    }

    private function footerCommands($pageNumber, $pageCount, $cover)
    {
        $commands = '';
        $originalPage = $this->currentPage;
        $tempPage = $this->pages[$this->currentPage];
        $this->pages[$this->currentPage] = '';
        if (!$cover) {
            $this->line($this->marginLeft, 804, $this->pageWidth - $this->marginRight, 804, [0.82, 0.85, 0.9], 0.5);
            $this->text($this->marginLeft, 812, 'DPMPTSP Provinsi Jawa Barat | Dokumentasi Integrasi API', 'regular', 7, [0.42, 0.46, 0.53]);
            $pageLabel = 'Halaman ' . $pageNumber . ' dari ' . $pageCount;
            $this->text($this->pageWidth - $this->marginRight - $this->measure($pageLabel, 'regular', 7), 812, $pageLabel, 'regular', 7, [0.42, 0.46, 0.53]);
        } else {
            $this->centerText(806, $this->documentCode . ' | Halaman 1 dari ' . $pageCount, 'regular', 7, [0.42, 0.46, 0.53]);
        }
        $commands = $this->pages[$this->currentPage];
        $this->pages[$this->currentPage] = $tempPage;
        $this->currentPage = $originalPage;
        return $commands;
    }

    private function command($command)
    {
        if ($this->currentPage >= 0) {
            $this->pages[$this->currentPage] .= $command;
        }
    }

    private function encodeText($text)
    {
        $text = str_replace(["\r", "\n", "\t"], [' ', ' ', '    '], (string) $text);
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        return $encoded !== false ? $encoded : preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    private function escapeText($text)
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function color(array $color)
    {
        return $this->format($color[0]) . ' ' . $this->format($color[1]) . ' ' . $this->format($color[2]);
    }

    private function format($number)
    {
        return rtrim(rtrim(number_format((float) $number, 3, '.', ''), '0'), '.');
    }
}
