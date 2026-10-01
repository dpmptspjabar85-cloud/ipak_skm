<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penulis berkas Excel .xls tanpa dependensi pihak ketiga.
 *
 * Menghasilkan SpreadsheetML 2003 (XML) yang disimpan dengan ekstensi .xls.
 * Format ini dibuka langsung oleh Microsoft Excel dan LibreOffice Calc tanpa
 * perlu ekstensi atau pustaka tambahan, berbeda dengan HTML yang disamarkan
 * menjadi .xls dan memicu peringatan format.
 *
 * Mendukung beberapa sheet, lebar kolom, baris judul yang membeku, dan
 * pembungkuan teks agar isi panjang tetap terbaca.
 */
class Excel_writer
{
    /** @var array Daftar sheet yang sudah ditambahkan. */
    private $sheets = [];

    /** @var int Indeks sheet yang sedang ditambahkan. */
    private $currentIndex = -1;

    /**
     * Menambahkan sheet baru beserta judul kolom dan baris datanya.
     *
     * @param string $name    Nama sheet (maks. 31 karakter di Excel).
     * @param array  $headers Judul kolom.
     * @param array  $rows    Baris data, masing-masing array nilai.
     * @param array  $options widths  (array lebar kolom),
     *                        freeze  (bool kunci baris judul),
     *                        title   (string judul di atas tabel),
     *                        notes   (array catatan di bawah judul kolom).
     * @return void
     */
    public function add_sheet($name, array $headers, array $rows, array $options = [])
    {
        $this->sheets[] = [
            'name' => $this->safe_sheet_name($name, count($this->sheets)),
            'headers' => $headers,
            'rows' => $rows,
            'widths' => isset($options['widths']) && is_array($options['widths']) ? $options['widths'] : [],
            'freeze' => !empty($options['freeze']),
            'title' => isset($options['title']) ? (string) $options['title'] : '',
            'notes' => isset($options['notes']) && is_array($options['notes']) ? $options['notes'] : [],
        ];
        $this->currentIndex = count($this->sheets) - 1;
    }

    /**
     * Mengirim berkas ke peramban sebagai lampiran .xls.
     *
     * @param  string $filename
     * @return void
     */
    public function download($filename)
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $filename);
        if ($filename === '' || substr($filename, -4) !== '.xls') {
            $filename .= '.xls';
        }
        if (!headers_sent()) {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Expires: 0');
        }
        echo $this->build();
        exit;
    }

    /**
     * Merakit dokumen SpreadsheetML lengkap.
     *
     * @return string
     */
    public function build()
    {
        $xml = '<?xml version="1.0"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= "\t" . '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
        $xml .= "\t\t" . '<Author>Sistem Survei Pelayanan</Author>' . "\n";
        $xml .= "\t" . '</DocumentProperties>' . "\n";
        $xml .= "\t" . '<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">' . "\n";
        $xml .= "\t\t" . '<ExcelWorksheets>' . "\n";
        foreach ($this->sheets as $sheet) {
            $xml .= "\t\t\t" . '<Worksheet ss:Name="' . $this->escape($sheet['name']) . '"/>' . "\n";
        }
        $xml .= "\t\t" . '</ExcelWorksheets>' . "\n";
        $xml .= "\t" . '</ExcelWorkbook>' . "\n";
        foreach ($this->sheets as $sheet) {
            $xml .= $this->build_sheet($sheet);
        }
        $xml .= '</Workbook>';
        return $xml;
    }

    /**
     * @param  array $sheet
     * @return string
     */
    private function build_sheet(array $sheet)
    {
        $columnCount = max(
            count($sheet['headers']),
            $this->max_row_width($sheet['rows'])
        );
        $columnCount = max(1, $columnCount);

        $xml = "\t" . '<Worksheet ss:Name="' . $this->escape($sheet['name']) . '">' . "\n";
        $xml .= "\t\t" . '<Table ss:ExpandedColumnCount="' . $columnCount . '"'
            . ' ss:ExpandedRowCount="' . (count($sheet['rows']) + count($sheet['notes']) + 3) . '"'
            . ' x:FullColumns="1" x:FullRows="1">'
            . ($sheet['freeze'] ? ' ss:DefaultRowHeight="15"' : '')
            . "\n";

        if (!empty($sheet['widths'])) {
            $xml .= $this->build_columns($sheet['widths'], $columnCount);
        }

        $rowIndex = 1;

        if ($sheet['title'] !== '') {
            $xml .= "\t\t\t" . '<Row ss:Index="' . $rowIndex . '">' . "\n";
            $xml .= "\t\t\t\t" . $this->cell(
                $sheet['title'],
                1,
                'title'
            ) . "\n";
            $xml .= "\t\t\t" . '</Row>' . "\n";
            $rowIndex++;
            $xml .= "\t\t\t" . '<Row ss:Index="' . $rowIndex . '"/>' . "\n";
            $rowIndex++;
        }

        $xml .= "\t\t\t" . '<Row ss:Index="' . $rowIndex . '" ss:StyleID="sHeader">' . "\n";
        foreach ($sheet['headers'] as $column => $header) {
            $xml .= "\t\t\t\t" . $this->cell($header, $column + 1, 'header') . "\n";
        }
        $xml .= "\t\t\t" . '</Row>' . "\n";
        $headerRowIndex = $rowIndex;
        $rowIndex++;

        foreach ($sheet['rows'] as $row) {
            $xml .= "\t\t\t" . '<Row ss:Index="' . $rowIndex . '">' . "\n";
            for ($column = 0; $column < $columnCount; $column++) {
                $value = array_key_exists($column, $row) ? $row[$column] : '';
                $style = $this->row_style($value);
                $xml .= "\t\t\t\t" . $this->cell($value, $column + 1, $style) . "\n";
            }
            $xml .= "\t\t\t" . '</Row>' . "\n";
            $rowIndex++;
        }

        foreach ($sheet['notes'] as $note) {
            $xml .= "\t\t\t" . '<Row ss:Index="' . $rowIndex . '">' . "\n";
            $xml .= "\t\t\t\t" . $this->cell($note, 1, 'note') . "\n";
            $xml .= "\t\t\t" . '</Row>' . "\n";
            $rowIndex++;
        }

        $xml .= "\t\t" . '</Table>' . "\n";
        if ($sheet['freeze']) {
            $xml .= "\t\t" . '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
                . '<PageSetup><Layout x:Orientation="Landscape"/></PageSetup>'
                . '<SplitHorizontal>' . ($headerRowIndex + ($sheet['title'] !== '' ? 2 : 0)) . '</SplitHorizontal>'
                . '<TopRowBottomPane>' . ($headerRowIndex + ($sheet['title'] !== '' ? 2 : 0)) . '</TopRowBottomPane>'
                . '<ActivePane>2</ActivePane>'
                . '</WorksheetOptions>' . "\n";
        }
        $xml .= "\t" . '</Worksheet>' . "\n";
        return $xml;
    }

    /**
     * Definisi <Col> untuk lebar kolom.
     *
     * @param  array $widths
     * @param  int   $columnCount
     * @return string
     */
    private function build_columns(array $widths, $columnCount)
    {
        $xml = '';
        for ($column = 1; $column <= $columnCount; $column++) {
            $index = $column - 1;
            $width = isset($widths[$index]) ? (int) $widths[$index] : 0;
            $xml .= "\t\t\t" . '<Column ss:Index="' . $column . '"'
                . ($width > 0 ? ' ss:Width="' . $width . '"' : '')
                . ' ss:AutoFitWidth="0" ss:WrapText="0"/>' . "\n";
        }
        return $xml;
    }

    /**
     * Menentukan gaya sel berdasarkan tipe nilai.
     *
     * @param  mixed $value
     * @return string
     */
    private function row_style($value)
    {
        if ($value === '' || $value === null) {
            return 'text';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        return is_numeric($value) ? 'number' : 'text';
    }

    /**
     * Membuat satu <Cell>.
     *
     * @param  mixed  $value
     * @param  int    $column
     * @param  string $style
     * @return string
     */
    private function cell($value, $column, $style = 'text')
    {
        $index = (int) $column;
        $open = ' ss:StyleID="s' . ucfirst($style) . '"';

        if ($value === null || $value === '') {
            return '<Cell' . $open . ' ss:Index="' . $index . '"/>';
        }
        if (is_bool($value)) {
            $value = $value ? 'Ya' : 'Tidak';
        }
        if (is_int($value) || is_float($value)) {
            return '<Cell' . $open . ' ss:Index="' . $index . '">'
                . '<Data ss:Type="Number">' . $this->escape($value) . '</Data></Cell>';
        }

        $text = (string) $value;
        // Formula injection: nilai diawali =, +, -, atau @ akan dibaca Excel
        // sebagai formula, bukan teks.
        if (preg_match('/^[=+\-@]/', $text)) {
            $text = "'" . $text;
        }
        $type = is_numeric($text) ? 'Number' : 'String';
        return '<Cell' . $open . ' ss:Index="' . $index . '">'
            . '<Data ss:Type="' . $type . '">' . $this->escape($text) . '</Data></Cell>';
    }

    /**
     * @param  array $rows
     * @return int
     */
    private function max_row_width(array $rows)
    {
        $width = 0;
        foreach ($rows as $row) {
            if (is_array($row) && count($row) > $width) {
                $width = count($row);
            }
        }
        return $width;
    }

    /**
     * Nama sheet dibersihkan karena Excel membatasi 31 karakter dan
     * menolak beberapa karakter khusus.
     *
     * @param  string $name
     * @param  int    $fallbackIndex
     * @return string
     */
    private function safe_sheet_name($name, $fallbackIndex)
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', (string) $name);
        $name = trim((string) $name);
        if ($name === '') {
            $name = 'Sheet' . ($fallbackIndex + 1);
        }
        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 31, 'UTF-8');
        }
        return substr($name, 0, 31);
    }

    /**
     * @param  mixed $value
     * @return string
     */
    private function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}