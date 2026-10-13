<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "includes/db.php";
include "includes/auth.php";

// Check if required extensions are available
$zipAvailable = extension_loaded('zip');
$debugMode = isset($_GET['debug']) ? true : false;

// Simple CSV reader and writer
class SimpleCSVHandler
{
    private $data = [];
    private $headers = [];
    private $styles = []; // Store cell styles

    public function readFile($filePath)
    {
        $this->data = [];
        $this->headers = [];
        $this->styles = [];

        if (!file_exists($filePath)) {
            if ($GLOBALS['debugMode'])
                echo "<div class='alert alert-danger'>File not found: $filePath</div>";
            return false;
        }

        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($row === 0) {
                    $this->headers = $data;
                } else {
                    $this->data[] = $data;
                }
                $row++;
            }
            fclose($handle);
            return true;
        }
        return false;
    }

    public function saveFile($filePath)
    {
        $fp = fopen($filePath, 'w');
        if ($fp === false)
            return false;

        // Write headers
        fputcsv($fp, $this->headers);

        // Write data
        foreach ($this->data as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        return true;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    public function getStyles()
    {
        return $this->styles;
    }

    public function setStyles($styles)
    {
        $this->styles = $styles;
    }
}

// Simple XLSX reader + writer (fallback if Zip is available)
class SimpleXLSXHandler
{
    private $data = [];
    private $headers = [];
    private $styles = []; // Store cell styles

    public function readFile($filePath)
    {
        if (!$GLOBALS['zipAvailable']) {
            if ($GLOBALS['debugMode'])
                echo "<div class='alert alert-warning'>Zip extension not available. XLSX files cannot be processed.</div>";
            return false;
        }

        $this->data = [];
        $this->headers = [];
        $this->styles = [];

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== TRUE) {
            return false;
        }

        $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        $stylesXml = $zip->getFromName('xl/styles.xml');
        $zip->close();

        if (!$sheetData)
            return false;

        // Parse shared strings
        $sharedStringsArray = [];
        if ($sharedStrings) {
            $xml = simplexml_load_string($sharedStrings);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStringsArray[] = (string) $si->t;
                }
            }
        }

        // Parse styles (very basic)
        $styleArray = [];
        if ($stylesXml) {
            $xml = simplexml_load_string($stylesXml);
            if ($xml) {
                // Fonts
                $fonts = [];
                if (isset($xml->fonts->font)) {
                    foreach ($xml->fonts->font as $font) {
                        $fontStyle = [];
                        $fontStyle['bold'] = isset($font->b);
                        $fontStyle['italic'] = isset($font->i);
                        $fonts[] = $fontStyle;
                    }
                }

                // Fills (background colors)
                $fills = [];
                if (isset($xml->fills->fill)) {
                    foreach ($xml->fills->fill as $fill) {
                        $fillStyle = [];
                        if (isset($fill->patternFill->fgColor['rgb'])) {
                            $fillStyle['color'] = (string) $fill->patternFill->fgColor['rgb'];
                        }
                        $fills[] = $fillStyle;
                    }
                }

                // Cell formats
                if (isset($xml->cellXfs->xf)) {
                    foreach ($xml->cellXfs->xf as $xf) {
                        $style = [];
                        $fontId = isset($xf['fontId']) ? (int) $xf['fontId'] : 0;
                        $fillId = isset($xf['fillId']) ? (int) $xf['fillId'] : 0;

                        if (isset($fonts[$fontId])) {
                            $style['bold'] = $fonts[$fontId]['bold'] ?? false;
                            $style['italic'] = $fonts[$fontId]['italic'] ?? false;
                        }

                        if (isset($fills[$fillId]['color'])) {
                            $style['bgColor'] = $fills[$fillId]['color'];
                        }

                        $styleArray[] = $style;
                    }
                }
            }
        }

        // Parse worksheet data
        $xml = simplexml_load_string($sheetData);
        if ($xml) {
            $rows = [];
            $styleRows = [];

            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                $styleRow = [];

                foreach ($row->c as $cell) {
                    $value = '';
                    $style = [];

                    if (isset($cell->v)) {
                        if (isset($cell['t']) && (string) $cell['t'] === 's') {
                            $index = (int) $cell->v;
                            $value = $sharedStringsArray[$index] ?? '';
                        } else {
                            $value = (string) $cell->v;
                        }
                    }

                    if (isset($cell['s'])) {
                        $styleIndex = (int) $cell['s'];
                        if (isset($styleArray[$styleIndex])) {
                            $style = $styleArray[$styleIndex];
                        }
                    }

                    $rowData[] = $value;
                    $styleRow[] = $style;
                }

                $rows[] = $rowData;
                $styleRows[] = $styleRow;
            }

            if (!empty($rows)) {
                $this->headers = $rows[0];
                $this->data = array_slice($rows, 1);
                $this->styles = array_slice($styleRows, 1);
                return true;
            }
        }
        return false;
    }

    public function saveFile($filePath)
    {
        if (!$GLOBALS['zipAvailable']) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml', $this->getContentTypes());
        $zip->addFromString('_rels/.rels', $this->getRels());
        $zip->addFromString('xl/workbook.xml', $this->getWorkbook());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->getWorksheet());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->getStylesXml());

        $zip->close();
        return true;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    public function getStyles()
    {
        return $this->styles;
    }

    public function setStyles($styles)
    {
        $this->styles = $styles;
    }

    private function getContentTypes()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml" />
    <Default Extension="xml" ContentType="application/xml" />
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml" />
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml" />
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml" />
</Types>';
    }

    private function getRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml" />
</Relationships>';
    }

    private function getWorkbook()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" />
    </sheets>
</workbook>';
    }

    private function getWorkbookRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml" />
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml" />
</Relationships>';
    }

    private function getStylesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="3">
        <font>
            <sz val="11" />
            <color theme="1" />
            <name val="Calibri" />
            <family val="2" />
            <scheme val="minor" />
        </font>
        <font>
            <b />
            <sz val="11" />
            <color theme="1" />
            <name val="Calibri" />
            <family val="2" />
            <scheme val="minor" />
        </font>
        <font>
            <i />
            <sz val="11" />
            <color theme="1" />
            <name val="Calibri" />
            <family val="2" />
            <scheme val="minor" />
        </font>
    </fonts>
    <fills count="5">
        <fill>
            <patternFill patternType="none" />
        </fill>
        <fill>
            <patternFill patternType="gray125" />
        </fill>
        <fill>
            <patternFill patternType="solid">
                <fgColor rgb="FFFFFF00" />
            </patternFill>
        </fill>
        <fill>
            <patternFill patternType="solid">
                <fgColor rgb="FF00FF00" />
            </patternFill>
        </fill>
        <fill>
            <patternFill patternType="solid">
                <fgColor rgb="FFFF0000" />
            </patternFill>
        </fill>
    </fills>
    <borders count="1">
        <border>
            <left />
            <right />
            <top />
            <bottom />
            <diagonal />
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" />
    </cellStyleXfs>
    <cellXfs count="6">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" />
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" />
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" />
        <xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1" />
        <xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" />
        <xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1" />
    </cellXfs>
</styleSheet>';
    }

    private function getWorksheet()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';

        // Headers row
        $xml .= '<row r="1">';
        foreach ($this->headers as $colIndex => $header) {
            $cellRef = $this->getCellReference($colIndex + 1, 1);
            $xml .= "<c r=\"$cellRef\" t=\"inlineStr\" s=\"1\">
                <is>
                    <t>" . htmlspecialchars($header) . "</t>
                </is>
            </c>";
        }
        $xml .= '</row>';

        // Data rows
        foreach ($this->data as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 2) . '">';
            foreach ($row as $colIndex => $cell) {
                $cellRef = $this->getCellReference($colIndex + 1, $rowIndex + 2);

                $styleIndex = 0;
                if (isset($this->styles[$rowIndex][$colIndex])) {
                    $style = $this->styles[$rowIndex][$colIndex];

                    if (isset($style['bold']) && $style['bold']) {
                        $styleIndex = 1;
                    } elseif (isset($style['italic']) && $style['italic']) {
                        $styleIndex = 2;
                    } elseif (isset($style['bgColor'])) {
                        if ($style['bgColor'] === 'FFFFFF00') {
                            $styleIndex = 3;
                        } elseif ($style['bgColor'] === 'FF00FF00') {
                            $styleIndex = 4;
                        } elseif ($style['bgColor'] === 'FFFF0000') {
                            $styleIndex = 5;
                        }
                    }
                }

                $xml .= "<c r=\"$cellRef\" t=\"inlineStr\" s=\"$styleIndex\">
                    <is>
                        <t>" . htmlspecialchars($cell) . "</t>
                    </is>
                </c>";
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>
</worksheet>';

        return $xml;
    }

    private function getCellReference($col, $row)
    {
        $colRef = '';
        while ($col > 0) {
            $col--;
            $colRef = chr(65 + ($col % 26)) . $colRef;
            $col = floor($col / 26);
        }
        return $colRef . $row;
    }
}

// Function to evaluate basic formulas
function evaluateFormula($formula, $data, $rowIndex, $colIndex)
{
    if (strpos($formula, '=') !== 0) {
        return $formula;
    }

    $formula = substr($formula, 1);

    // SUM
    if (preg_match('/SUM\(([^)]+)\)/i', $formula, $matches)) {
        $range = $matches[1];
        $sum = 0;

        if (strpos($range, ':') !== false) {
            list($start, $end) = explode(':', $range);
            $startCol = preg_replace('/\d/', '', $start);
            $startRow = preg_replace('/[A-Z]/', '', $start);
            $endCol = preg_replace('/\d/', '', $end);
            $endRow = preg_replace('/[A-Z]/', '', $end);

            $startColIndex = ord($startCol) - ord('A');
            $endColIndex = ord($endCol) - ord('A');

            for ($r = $startRow - 1; $r < $endRow && $r < count($data); $r++) {
                for ($c = $startColIndex; $c <= $endColIndex && $c < count($data[$r]); $c++) {
                    if (is_numeric($data[$r][$c])) {
                        $sum += floatval($data[$r][$c]);
                    }
                }
            }
        }
        return $sum;
    }

    // AVERAGE
    if (preg_match('/AVERAGE\(([^)]+)\)/i', $formula, $matches)) {
        $range = $matches[1];
        $sum = 0;
        $count = 0;

        if (strpos($range, ':') !== false) {
            list($start, $end) = explode(':', $range);
            $startCol = preg_replace('/\d/', '', $start);
            $startRow = preg_replace('/[A-Z]/', '', $start);
            $endCol = preg_replace('/\d/', '', $end);
            $endRow = preg_replace('/[A-Z]/', '', $end);

            $startColIndex = ord($startCol) - ord('A');
            $endColIndex = ord($endCol) - ord('A');

            for ($r = $startRow - 1; $r < $endRow && $r < count($data); $r++) {
                for ($c = $startColIndex; $c <= $endColIndex && $c < count($data[$r]); $c++) {
                    if (is_numeric($data[$r][$c])) {
                        $sum += floatval($data[$r][$c]);
                        $count++;
                    }
                }
            }
        }
        return $count > 0 ? $sum / $count : 0;
    }

    // COUNT
    if (preg_match('/COUNT\(([^)]+)\)/i', $formula, $matches)) {
        $range = $matches[1];
        $count = 0;

        if (strpos($range, ':') !== false) {
            list($start, $end) = explode(':', $range);
            $startCol = preg_replace('/\d/', '', $start);
            $startRow = preg_replace('/[A-Z]/', '', $start);
            $endCol = preg_replace('/\d/', '', $end);
            $endRow = preg_replace('/[A-Z]/', '', $end);

            $startColIndex = ord($startCol) - ord('A');
            $endColIndex = ord($endCol) - ord('A');

            for ($r = $startRow - 1; $r < $endRow && $r < count($data); $r++) {
                for ($c = $startColIndex; $c <= $endColIndex && $c < count($data[$r]); $c++) {
                    if (is_numeric($data[$r][$c])) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    // MAX
    if (preg_match('/MAX\(([^)]+)\)/i', $formula, $matches)) {
        $range = $matches[1];
        $max = -INF;

        if (strpos($range, ':') !== false) {
            list($start, $end) = explode(':', $range);
            $startCol = preg_replace('/\d/', '', $start);
            $startRow = preg_replace('/[A-Z]/', '', $start);
            $endCol = preg_replace('/\d/', '', $end);
            $endRow = preg_replace('/[A-Z]/', '', $end);

            $startColIndex = ord($startCol) - ord('A');
            $endColIndex = ord($endCol) - ord('A');

            for ($r = $startRow - 1; $r < $endRow && $r < count($data); $r++) {
                for ($c = $startColIndex; $c <= $endColIndex && $c < count($data[$r]); $c++) {
                    if (is_numeric($data[$r][$c])) {
                        $max = max($max, floatval($data[$r][$c]));
                    }
                }
            }
        }
        return $max === -INF ? 0 : $max;
    }

    // MIN
    if (preg_match('/MIN\(([^)]+)\)/i', $formula, $matches)) {
        $range = $matches[1];
        $min = INF;

        if (strpos($range, ':') !== false) {
            list($start, $end) = explode(':', $range);
            $startCol = preg_replace('/\d/', '', $start);
            $startRow = preg_replace('/[A-Z]/', '', $start);
            $endCol = preg_replace('/\d/', '', $end);
            $endRow = preg_replace('/[A-Z]/', '', $end);

            $startColIndex = ord($startCol) - ord('A');
            $endColIndex = ord($endCol) - ord('A');

            for ($r = $startRow - 1; $r < $endRow && $r < count($data); $r++) {
                for ($c = $startColIndex; $c <= $endColIndex && $c < count($data[$r]); $c++) {
                    if (is_numeric($data[$r][$c])) {
                        $min = min($min, floatval($data[$r][$c]));
                    }
                }
            }
        }
        return $min === INF ? 0 : $min;
    }

    // Basic IF (very limited)
    if (preg_match('/IF\(([^,]+),([^,]+),([^)]+)\)/i', $formula, $matches)) {
        $condition = trim($matches[1]);
        $trueValue = trim($matches[2]);
        $falseValue = trim($matches[3]);

        $result = false;
        if (preg_match('/([A-Z]\d+)([><]=?|!=|=)([A-Z]\d+|\d+)/', $condition, $condMatches)) {
            $cell1 = $condMatches[1];
            $operator = $condMatches[2];
            $value2 = $condMatches[3];

            $col1 = preg_replace('/\d/', '', $cell1);
            $row1 = preg_replace('/[A-Z]/', '', $cell1) - 1;
            $colIndex1 = ord($col1) - ord('A');
            $val1 = isset($data[$row1][$colIndex1]) && is_numeric($data[$row1][$colIndex1])
                ? floatval($data[$row1][$colIndex1]) : 0;

            if (preg_match('/[A-Z]\d+/', $value2)) {
                $col2 = preg_replace('/\d/', '', $value2);
                $row2 = preg_replace('/[A-Z]/', '', $value2) - 1;
                $colIndex2 = ord($col2) - ord('A');
                $val2 = isset($data[$row2][$colIndex2]) && is_numeric($data[$row2][$colIndex2])
                    ? floatval($data[$row2][$colIndex2]) : 0;
            } else {
                $val2 = floatval($value2);
            }

            switch ($operator) {
                case '>':
                    $result = $val1 > $val2;
                    break;
                case '<':
                    $result = $val1 < $val2;
                    break;
                case '>=':
                    $result = $val1 >= $val2;
                    break;
                case '<=':
                    $result = $val1 <= $val2;
                    break;
                case '=':
                case '==':
                    $result = $val1 == $val2;
                    break;
                case '!=':
                case '<>':
                    $result = $val1 != $val2;
                    break;
            }
        }
        return $result ? $trueValue : $falseValue;
    }

    // Basic cell arithmetic A1+B1 etc.
    if (preg_match('/([A-Z]\d+)([\+\-\*\/])([A-Z]\d+)/', $formula, $matches)) {
        $cell1 = $matches[1];
        $operator = $matches[2];
        $cell2 = $matches[3];

        $col1 = preg_replace('/\d/', '', $cell1);
        $row1 = preg_replace('/[A-Z]/', '', $cell1) - 1;
        $colIndex1 = ord($col1) - ord('A');

        $col2 = preg_replace('/\d/', '', $cell2);
        $row2 = preg_replace('/[A-Z]/', '', $cell2) - 1;
        $colIndex2 = ord($col2) - ord('A');

        $val1 = isset($data[$row1][$colIndex1]) && is_numeric($data[$row1][$colIndex1])
            ? floatval($data[$row1][$colIndex1]) : 0;
        $val2 = isset($data[$row2][$colIndex2]) && is_numeric($data[$row2][$colIndex2])
            ? floatval($data[$row2][$colIndex2]) : 0;

        switch ($operator) {
            case '+':
                return $val1 + $val2;
            case '-':
                return $val1 - $val2;
            case '*':
                return $val1 * $val2;
            case '/':
                return $val2 != 0 ? $val1 / $val2 : 0;
        }
    }

    return $formula; // fallback
}

// ────────────────────────────────────────────────
// Process form submissions
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = pathinfo($_FILES['excel_file']['name']);
            $fileExtension = strtolower($fileInfo['extension']);

            if ($fileExtension === 'csv' || ($fileExtension === 'xlsx' && $zipAvailable)) {
                $uploadDir = 'csv_files/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = uniqid() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $filePath)) {
                    if ($fileExtension === 'csv') {
                        $handler = new SimpleCSVHandler();
                    } else {
                        $handler = new SimpleXLSXHandler();
                    }

                    if ($handler->readFile($filePath)) {
                        $_SESSION['headers'] = $handler->getHeaders();
                        $_SESSION['data'] = $handler->getData();
                        $_SESSION['styles'] = $handler->getStyles();
                        $_SESSION['file_path'] = $filePath;
                        $_SESSION['file_type'] = $fileExtension;
                        $_SESSION['file_name'] = $fileInfo['filename'];
                        $_SESSION['success'] = 'File uploaded successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to read the file.';
                    }
                } else {
                    $_SESSION['error'] = 'Failed to upload the file.';
                }
            } else {
                $_SESSION['error'] = 'Only CSV files are supported.' .
                    ($zipAvailable ? ' XLSX files are also supported.' : ' XLSX requires Zip extension.');
            }
        } else {
            $_SESSION['error'] = 'Please select a file to upload.';
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ── save ────────────────────────────────────────
    elseif (isset($_POST['action']) && $_POST['action'] === 'save') {
        if (isset($_SESSION['file_path']) && isset($_SESSION['file_type'])) {
            $headers = json_decode($_POST['headers'], true);
            $data = json_decode($_POST['data'], true);
            $styles = isset($_POST['styles']) ? json_decode($_POST['styles'], true) : [];

            if ($_SESSION['file_type'] === 'csv') {
                $handler = new SimpleCSVHandler();
            } else {
                $handler = new SimpleXLSXHandler();
            }

            $handler->setHeaders($headers);
            $handler->setData($data);
            $handler->setStyles($styles);

            if ($handler->saveFile($_SESSION['file_path'])) {
                $_SESSION['success'] = 'File saved successfully!';
            } else {
                $_SESSION['error'] = 'Failed to save the file.';
            }
        } else {
            $_SESSION['error'] = 'No file to save.';
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ── download ────────────────────────────────────
    elseif (isset($_POST['action']) && $_POST['action'] === 'download') {
        if (isset($_SESSION['file_path']) && isset($_SESSION['file_type']) && isset($_SESSION['file_name'])) {
            $headers = json_decode($_POST['headers'], true);
            $data = json_decode($_POST['data'], true);
            $styles = isset($_POST['styles']) ? json_decode($_POST['styles'], true) : [];

            if ($_SESSION['file_type'] === 'csv') {
                $handler = new SimpleCSVHandler();
            } else {
                $handler = new SimpleXLSXHandler();
            }

            $handler->setHeaders($headers);
            $handler->setData($data);
            $handler->setStyles($styles);

            $downloadFileName = $_SESSION['file_name'] . '_modified.' . $_SESSION['file_type'];
            $tempFilePath = 'temp_' . uniqid() . '.' . $_SESSION['file_type'];

            if ($handler->saveFile($tempFilePath)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
                header('Content-Length: ' . filesize($tempFilePath));
                readfile($tempFilePath);
                unlink($tempFilePath);
                exit;
            } else {
                $_SESSION['error'] = 'Failed to create download file.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    // ── export ──────────────────────────────────────
    elseif (isset($_POST['action']) && $_POST['action'] === 'export') {
        if (isset($_SESSION['data'])) {
            $format = $_POST['export_format'];
            $headers = json_decode($_POST['headers'], true);
            $data = json_decode($_POST['data'], true);

            if ($format === 'html') {
                $html = '<!DOCTYPE html>
<html>
<head>
    <title>' . htmlspecialchars($_SESSION['file_name']) . '</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <table>
        <thead><tr>';
                foreach ($headers as $header) {
                    $html .= '<th>' . htmlspecialchars($header) . '</th>';
                }
                $html .= '</tr></thead>
        <tbody>';
                foreach ($data as $row) {
                    $html .= '<tr>';
                    foreach ($row as $cell) {
                        $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table></body></html>';

                header('Content-Type: text/html');
                header('Content-Disposition: attachment; filename="' . $_SESSION['file_name'] . '.html"');
                echo $html;
                exit;
            } elseif ($format === 'json') {
                $json = [
                    'headers' => $headers,
                    'data' => $data
                ];

                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $_SESSION['file_name'] . '.json"');
                echo json_encode($json, JSON_PRETTY_PRINT);
                exit;
            }
        }
    }
} else {
    session_start();
}

// Handle reset
if (isset($_GET['reset'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Clear session messages
$success = $error = null;
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
} ?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Syed Professional Services - Excel Editor</title>

    <?php include "includes/links.php"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .editable-cell {
            cursor: pointer;
            position: relative;
            min-height: 30px;
        }

        .editable-cell:hover {
            background-color: #f5f5f5;
        }

        .editable-cell:focus {
            outline: 2px solid #0d6efd;
            background-color: #fff;
        }

        .table-container {
            max-height: 500px;
            overflow: auto;
        }

        .formula-cell {
            background-color: #e6f7ff !important;
        }

        .header-cell {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .cell-bold {
            font-weight: bold;
        }

        .cell-italic {
            font-style: italic;
        }

        .cell-yellow {
            background-color: #ffff99 !important;
        }

        .cell-green {
            background-color: #99ff99 !important;
        }

        .cell-red {
            background-color: #ff9999 !important;
        }

        .toolbar {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .toolbar-group {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .toolbar-divider {
            width: 1px;
            height: 30px;
            background-color: #dee2e6;
        }

        .find-replace-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .find-replace-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 500px;
        }

        .sort-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .sort-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
        }
    </style>
</head>

<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <!-- Include your existing sidebar -->
    <?php include "includes/sidebar.php"; ?>

    <!-- Include your existing header -->
    <?php include "includes/header.php"; ?>

    <!-- Main Content -->
    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Excel File Editor</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Excel Editor</li>
                            </ul>
                        </div>
                        <div class="col"></div>
                    </div>
                </div>
            </div>

            <?php if ($debugMode): ?>
                <div class="debug-info">
                    <h5>Debug Information</h5>
                    <p>PHP Version: <?php echo phpversion(); ?></p>
                    <p>Zip Extension: <?php echo $zipAvailable ? 'Available' : 'Not Available'; ?></p>
                    <p>Session Status:
                        <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active'; ?>
                    </p>
                    <p>Upload Max Filesize: <?php echo ini_get('upload_max_filesize'); ?></p>
                    <p>Post Max Size: <?php echo ini_get('post_max_size'); ?></p>
                    <p>Current Directory: <?php echo __DIR__; ?></p>
                    <p>CSV Files Directory Writable: <?php echo is_writable('csv_files/') ? 'Yes' : 'No'; ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['data'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h5>Upload Excel File</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload">
                            <div class="mb-3">
                                <label for="excel_file" class="form-label">Select Excel File
                                    (CSV<?php echo $zipAvailable ? ' or XLSX' : ''; ?>)</label>
                                <input class="form-control" type="file" id="excel_file" name="excel_file"
                                    accept=".csv<?php echo $zipAvailable ? ',.xlsx' : ''; ?>" required>
                                <div class="form-text">
                                    <?php if (!$zipAvailable): ?>
                                        <span class="text-warning">Note: XLSX support requires the Zip PHP
                                            extension which is not available on this server.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Excel Data Editor</h5>
                        <div>
                            <button type="button" class="btn btn-success" id="saveBtn">
                                <i class="fas fa-save me-2"></i>Save
                            </button>
                            <button type="button" class="btn btn-info" id="downloadBtn">
                                <i class="fas fa-download me-2"></i>Download
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-file-export me-2"></i>Export
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" data-format="html">Export as
                                            HTML</a></li>
                                    <li><a class="dropdown-item" href="#" data-format="json">Export as
                                            JSON</a></li>
                                </ul>
                            </div>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?reset=1" class="btn btn-secondary">
                                <i class="fas fa-file-upload me-2"></i>Upload New File
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Toolbar -->
                        <div class="toolbar">
                            <div class="toolbar-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addRowBtn">
                                    <i class="fas fa-plus me-1"></i>Add Row
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addColumnBtn">
                                    <i class="fas fa-plus me-1"></i>Add Column
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="deleteRowBtn">
                                    <i class="fas fa-trash me-1"></i>Delete Row
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="deleteColumnBtn">
                                    <i class="fas fa-trash me-1"></i>Delete Column
                                </button>
                            </div>

                            <div class="toolbar-divider"></div>

                            <div class="toolbar-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="boldBtn">
                                    <i class="fas fa-bold"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="italicBtn">
                                    <i class="fas fa-italic"></i>
                                </button>
                            </div>

                            <div class="toolbar-divider"></div>

                            <div class="toolbar-group">
                                <button type="button" class="btn btn-sm btn-outline-warning" id="yellowBtn">
                                    <i class="fas fa-palette me-1"></i>Yellow
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="greenBtn">
                                    <i class="fas fa-palette me-1"></i>Green
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="redBtn">
                                    <i class="fas fa-palette me-1"></i>Red
                                </button>
                            </div>

                            <div class="toolbar-divider"></div>

                            <div class="toolbar-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="findReplaceBtn">
                                    <i class="fas fa-search me-1"></i>Find/Replace
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="sortBtn">
                                    <i class="fas fa-sort me-1"></i>Sort
                                </button>
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="table table-bordered table-hover" id="excelTable">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 50px;">#</th>
                                        <?php foreach ($_SESSION['headers'] as $header): ?>
                                            <th scope="col" class="header-cell">
                                                <?php echo htmlspecialchars($header); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['data'] as $rowIndex => $row): ?>
                                        <tr>
                                            <th scope="row"><?php echo $rowIndex + 1; ?></th>
                                            <?php foreach ($row as $colIndex => $cell): ?>
                                                <td class="editable-cell" data-row="<?php echo $rowIndex; ?>"
                                                    data-col="<?php echo $colIndex; ?>" contenteditable="true">
                                                    <?php
                                                    $displayValue = htmlspecialchars($cell);

                                                    // Apply styles if available
                                                    $styleClasses = '';
                                                    if (isset($_SESSION['styles'][$rowIndex][$colIndex])) {
                                                        $style = $_SESSION['styles'][$rowIndex][$colIndex];
                                                        if (isset($style['bold']) && $style['bold']) {
                                                            $styleClasses .= ' cell-bold';
                                                        }
                                                        if (isset($style['italic']) && $style['italic']) {
                                                            $styleClasses .= ' cell-italic';
                                                        }
                                                        if (isset($style['bgColor'])) {
                                                            if ($style['bgColor'] === 'FFFFFF00') {
                                                                $styleClasses .= ' cell-yellow';
                                                            } elseif ($style['bgColor'] === 'FF00FF00') {
                                                                $styleClasses .= ' cell-green';
                                                            } elseif ($style['bgColor'] === 'FFFF0000') {
                                                                $styleClasses .= ' cell-red';
                                                            }
                                                        }
                                                    }

                                                    // Check if it's a formula
                                                    if (strpos($cell, '=') === 0) {
                                                        $displayValue = evaluateFormula($cell, $_SESSION['data'], $rowIndex, $colIndex);
                                                        echo '<span class="formula-cell' . $styleClasses . '">' . $displayValue . '</span>';
                                                    } else {
                                                        echo '<span class="' . $styleClasses . '">' . $displayValue . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($debugMode): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Session Data</h5>
                    </div>
                    <div class="card-body">
                        <pre><?php print_r($_SESSION); ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Find/Replace Modal -->
    <div class="find-replace-modal" id="findReplaceModal">
        <div class="find-replace-content">
            <h5>Find and Replace</h5>
            <div class="mb-3">
                <label for="findText" class="form-label">Find</label>
                <input type="text" class="form-control" id="findText">
            </div>
            <div class="mb-3">
                <label for="replaceText" class="form-label">Replace</label>
                <input type="text" class="form-control" id="replaceText">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="matchCase">
                <label class="form-check-label" for="matchCase">Match case</label>
            </div>
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" id="cancelFindReplace">Cancel</button>
                <button type="button" class="btn btn-primary" id="executeFindReplace">Replace
                    All</button>
            </div>
        </div>
    </div>

    <!-- Sort Modal -->
    <div class="sort-modal" id="sortModal">
        <div class="sort-content">
            <h5>Sort Data</h5>
            <div class="mb-3">
                <label for="sortColumn" class="form-label">Sort by Column</label>
                <select class="form-select" id="sortColumn">
                    <?php foreach ($_SESSION['headers'] as $colIndex => $header): ?>
                        <option value="<?php echo $colIndex; ?>">
                            <?php echo htmlspecialchars($header); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Sort Order</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sortOrder" id="sortAsc" value="asc" checked>
                    <label class="form-check-label" for="sortAsc">Ascending</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sortOrder" id="sortDesc" value="desc">
                    <label class="form-check-label" for="sortDesc">Descending</label>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" id="cancelSort">Cancel</button>
                <button type="button" class="btn btn-primary" id="executeSort">Sort</button>
            </div>
        </div>
    </div>

    <!-- Include your existing footer -->
    <?php include "includes/footer.php"; ?>

    <!-- Include your existing scripts -->
    <?php include "includes/scripts.php"; ?>

    <!-- Additional JavaScript for Excel editor -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Excel table functionality
            const table = document.getElementById('excelTable');
            const saveBtn = document.getElementById('saveBtn');
            const downloadBtn = document.getElementById('downloadBtn');
            const addRowBtn = document.getElementById('addRowBtn');
            const addColumnBtn = document.getElementById('addColumnBtn');
            const deleteRowBtn = document.getElementById('deleteRowBtn');
            const deleteColumnBtn = document.getElementById('deleteColumnBtn');
            const boldBtn = document.getElementById('boldBtn');
            const italicBtn = document.getElementById('italicBtn');
            const yellowBtn = document.getElementById('yellowBtn');
            const greenBtn = document.getElementById('greenBtn');
            const redBtn = document.getElementById('redBtn');
            const findReplaceBtn = document.getElementById('findReplaceBtn');
            const sortBtn = document.getElementById('sortBtn');

            // Modals
            const findReplaceModal = document.getElementById('findReplaceModal');
            const sortModal = document.getElementById('sortModal');

            // Find/Replace elements
            const findText = document.getElementById('findText');
            const replaceText = document.getElementById('replaceText');
            const matchCase = document.getElementById('matchCase');
            const cancelFindReplace = document.getElementById('cancelFindReplace');
            const executeFindReplace = document.getElementById('executeFindReplace');

            // Sort elements
            const sortColumn = document.getElementById('sortColumn');
            const cancelSort = document.getElementById('cancelSort');
            const executeSort = document.getElementById('executeSort');

            // Export dropdown items
            const exportItems = document.querySelectorAll('[data-format]');

            // Selected cells
            let selectedCells = [];
            let isSelecting = false;

            // Initialize styles array
            let cellStyles =
                <?php echo isset($_SESSION['styles']) ? json_encode($_SESSION['styles']) : '[]'; ?>;

            if (table) {
                // Make cells editable
                const editableCells = table.querySelectorAll('.editable-cell');
                editableCells.forEach(cell => {
                    cell.addEventListener('blur', function () {
                        // Update the cell value when focus is lost
                        const row = parseInt(this.dataset.row);
                        const col = parseInt(this.dataset.col);
                        const value = this.innerText.trim();

                        // Check if it's a formula
                        if (value.startsWith('=')) {
                            // We'll evaluate formulas on the server side
                            this.innerHTML = '<span class="formula-cell">' +
                                value + '</span>';
                        }
                    });

                    cell.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.blur();

                            // Move to the next cell
                            const nextCell = this.nextElementSibling;
                            if (nextCell && nextCell.classList.contains(
                                'editable-cell')) {
                                nextCell.focus();
                            } else {
                                // Move to the first cell of the next row
                                const nextRow = this.parentElement
                                    .nextElementSibling;
                                if (nextRow) {
                                    const firstCell = nextRow.querySelector(
                                        '.editable-cell');
                                    if (firstCell) {
                                        firstCell.focus();
                                    }
                                }
                            }
                        }
                    });

                    // Cell selection
                    cell.addEventListener('mousedown', function (e) {
                        if (e.shiftKey) {
                            // Extend selection
                            selectRange(this);
                        } else {
                            // Start new selection
                            clearSelection();
                            selectCell(this);
                            isSelecting = true;
                        }
                    });

                    cell.addEventListener('mouseenter', function () {
                        if (isSelecting) {
                            selectCell(this);
                        }
                    });

                    cell.addEventListener('mouseup', function () {
                        isSelecting = false;
                    });
                });

                // Global mouse up to end selection
                document.addEventListener('mouseup', function () {
                    isSelecting = false;
                });

                // Cell selection functions
                function selectCell(cell) {
                    if (!cell.classList.contains('selected')) {
                        cell.classList.add('selected');
                        selectedCells.push(cell);
                    }
                }

                function selectRange(endCell) {
                    clearSelection();

                    if (selectedCells.length === 0) return;

                    const startCell = selectedCells[0];
                    const startRow = parseInt(startCell.dataset.row);
                    const startCol = parseInt(startCell.dataset.col);
                    const endRow = parseInt(endCell.dataset.row);
                    const endCol = parseInt(endCell.dataset.col);

                    const minRow = Math.min(startRow, endRow);
                    const maxRow = Math.max(startRow, endRow);
                    const minCol = Math.min(startCol, endCol);
                    const maxCol = Math.max(startCol, endCol);

                    for (let r = minRow; r <= maxRow; r++) {
                        for (let c = minCol; c <= maxCol; c++) {
                            const cell = table.querySelector(
                                `[data-row="${r}"][data-col="${c}"]`);
                            if (cell) {
                                selectCell(cell);
                            }
                        }
                    }
                }

                function clearSelection() {
                    selectedCells.forEach(cell => {
                        cell.classList.remove('selected');
                    });
                    selectedCells = [];
                }

                // Style functions
                function applyStyle(styleClass, styleProperty) {
                    selectedCells.forEach(cell => {
                        const row = parseInt(cell.dataset.row);
                        const col = parseInt(cell.dataset.col);
                        const span = cell.querySelector('span');

                        if (span) {
                            if (styleProperty === 'bold') {
                                if (span.classList.contains('cell-bold')) {
                                    span.classList.remove('cell-bold');
                                    updateCellStyle(row, col, 'bold', false);
                                } else {
                                    span.classList.add('cell-bold');
                                    updateCellStyle(row, col, 'bold', true);
                                }
                            } else if (styleProperty === 'italic') {
                                if (span.classList.contains('cell-italic')) {
                                    span.classList.remove('cell-italic');
                                    updateCellStyle(row, col, 'italic', false);
                                } else {
                                    span.classList.add('cell-italic');
                                    updateCellStyle(row, col, 'italic', true);
                                }
                            } else if (styleProperty === 'bgColor') {
                                // Remove other background colors
                                span.classList.remove('cell-yellow', 'cell-green',
                                    'cell-red');
                                span.classList.add(styleClass);
                                updateCellStyle(row, col, 'bgColor', styleClass);
                            }
                        }
                    });
                }

                function updateCellStyle(row, col, property, value) {
                    if (!cellStyles[row]) {
                        cellStyles[row] = [];
                    }

                    if (!cellStyles[row][col]) {
                        cellStyles[row][col] = {};
                    }

                    if (property === 'bgColor') {
                        if (value === 'cell-yellow') {
                            cellStyles[row][col].bgColor = 'FFFFFF00';
                        } else if (value === 'cell-green') {
                            cellStyles[row][col].bgColor = 'FF00FF00';
                        } else if (value === 'cell-red') {
                            cellStyles[row][col].bgColor = 'FFFF0000';
                        } else {
                            delete cellStyles[row][col].bgColor;
                        }
                    } else {
                        cellStyles[row][col][property] = value;
                    }
                }

                // Toolbar button events
                boldBtn.addEventListener('click', function () {
                    applyStyle('cell-bold', 'bold');
                });

                italicBtn.addEventListener('click', function () {
                    applyStyle('cell-italic', 'italic');
                });

                yellowBtn.addEventListener('click', function () {
                    applyStyle('cell-yellow', 'bgColor');
                });

                greenBtn.addEventListener('click', function () {
                    applyStyle('cell-green', 'bgColor');
                });

                redBtn.addEventListener('click', function () {
                    applyStyle('cell-red', 'bgColor');
                });

                // Add row button
                addRowBtn.addEventListener('click', function () {
                    const tbody = table.querySelector('tbody');
                    const rowCount = tbody.querySelectorAll('tr').length;
                    const colCount = table.querySelectorAll('thead th').length - 1;

                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `<th scope="row">${rowCount + 1}</th>`;

                    for (let i = 0; i < colCount; i++) {
                        const cell = document.createElement('td');
                        cell.className = 'editable-cell';
                        cell.dataset.row = rowCount;
                        cell.dataset.col = i;
                        cell.contentEditable = true;

                        cell.addEventListener('blur', function () {
                            const row = parseInt(this.dataset.row);
                            const col = parseInt(this.dataset.col);
                            const value = this.innerText.trim();

                            if (value.startsWith('=')) {
                                this.innerHTML = '<span class="formula-cell">' +
                                    value + '</span>';
                            }
                        });

                        cell.addEventListener('keypress', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                this.blur();

                                const nextCell = this.nextElementSibling;
                                if (nextCell && nextCell.classList.contains(
                                    'editable-cell')) {
                                    nextCell.focus();
                                } else {
                                    const nextRow = this.parentElement
                                        .nextElementSibling;
                                    if (nextRow) {
                                        const firstCell = nextRow.querySelector(
                                            '.editable-cell');
                                        if (firstCell) {
                                            firstCell.focus();
                                        }
                                    }
                                }
                            }
                        });

                        newRow.appendChild(cell);
                    }

                    tbody.appendChild(newRow);
                });

                // Add column button
                addColumnBtn.addEventListener('click', function () {
                    const colCount = table.querySelectorAll('thead th').length - 1;
                    const newColName = prompt('Enter column name:');

                    if (!newColName) return;

                    // Add header
                    const headerRow = table.querySelector('thead tr');
                    const newHeader = document.createElement('th');
                    newHeader.className = 'header-cell';
                    newHeader.textContent = newColName;
                    headerRow.appendChild(newHeader);

                    // Add cells to each row
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach((row, index) => {
                        const rowHeader = row.querySelector('th');
                        const newCell = document.createElement('td');
                        newCell.className = 'editable-cell';
                        newCell.dataset.row = index;
                        newCell.dataset.col = colCount;
                        newCell.contentEditable = true;

                        newCell.addEventListener('blur', function () {
                            const row = parseInt(this.dataset.row);
                            const col = parseInt(this.dataset.col);
                            const value = this.innerText.trim();

                            if (value.startsWith('=')) {
                                this.innerHTML =
                                    '<span class="formula-cell">' +
                                    value + '</span>';
                            }
                        });

                        newCell.addEventListener('keypress', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                this.blur();

                                const nextCell = this
                                    .nextElementSibling;
                                if (nextCell && nextCell.classList
                                    .contains('editable-cell')) {
                                    nextCell.focus();
                                } else {
                                    const nextRow = this.parentElement
                                        .nextElementSibling;
                                    if (nextRow) {
                                        const firstCell = nextRow
                                            .querySelector(
                                                '.editable-cell');
                                        if (firstCell) {
                                            firstCell.focus();
                                        }
                                    }
                                }
                            }
                        });

                        row.appendChild(newCell);
                    });
                });

                // Delete row button
                deleteRowBtn.addEventListener('click', function () {
                    if (selectedCells.length === 0) {
                        alert('Please select at least one cell to delete a row.');
                        return;
                    }

                    const rowsToDelete = new Set();
                    selectedCells.forEach(cell => {
                        rowsToDelete.add(parseInt(cell.dataset.row));
                    });

                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    // Sort in descending order to avoid index issues
                    const sortedRows = Array.from(rowsToDelete).sort((a, b) => b - a);

                    sortedRows.forEach(rowIndex => {
                        if (rows[rowIndex]) {
                            rows[rowIndex].remove();
                        }
                    });

                    // Renumber rows
                    const remainingRows = tbody.querySelectorAll('tr');
                    remainingRows.forEach((row, index) => {
                        const rowHeader = row.querySelector('th');
                        rowHeader.textContent = index + 1;

                        // Update row indices
                        const cells = row.querySelectorAll('.editable-cell');
                        cells.forEach(cell => {
                            cell.dataset.row = index;
                        });
                    });

                    clearSelection();
                });

                // Delete column button
                deleteColumnBtn.addEventListener('click', function () {
                    if (selectedCells.length === 0) {
                        alert('Please select at least one cell to delete a column.');
                        return;
                    }

                    const colsToDelete = new Set();
                    selectedCells.forEach(cell => {
                        colsToDelete.add(parseInt(cell.dataset.col));
                    });

                    // Sort in descending order to avoid index issues
                    const sortedCols = Array.from(colsToDelete).sort((a, b) => b - a);

                    sortedCols.forEach(colIndex => {
                        // Remove header
                        const headers = table.querySelectorAll('thead th');
                        if (headers[colIndex + 1]) {
                            headers[colIndex + 1].remove();
                        }

                        // Remove cells from each row
                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            const cells = row.querySelectorAll(
                                '.editable-cell');
                            if (cells[colIndex]) {
                                cells[colIndex].remove();
                            }
                        });
                    });

                    // Update column indices
                    const remainingCells = table.querySelectorAll('.editable-cell');
                    const cellsByRow = {};

                    remainingCells.forEach(cell => {
                        const row = parseInt(cell.dataset.row);
                        if (!cellsByRow[row]) {
                            cellsByRow[row] = [];
                        }
                        cellsByRow[row].push(cell);
                    });

                    Object.keys(cellsByRow).forEach(row => {
                        cellsByRow[row].forEach((cell, col) => {
                            cell.dataset.col = col;
                        });
                    });

                    clearSelection();
                });

                // Find/Replace functionality
                findReplaceBtn.addEventListener('click', function () {
                    findReplaceModal.style.display = 'flex';
                });

                cancelFindReplace.addEventListener('click', function () {
                    findReplaceModal.style.display = 'none';
                });

                executeFindReplace.addEventListener('click', function () {
                    const find = findText.value;
                    const replace = replaceText.value;
                    const caseSensitive = matchCase.checked;

                    if (!find) {
                        alert('Please enter text to find.');
                        return;
                    }

                    const cells = table.querySelectorAll('.editable-cell');
                    let count = 0;

                    cells.forEach(cell => {
                        const span = cell.querySelector('span');
                        if (span) {
                            let text = span.textContent;

                            if (!caseSensitive) {
                                const searchRegex = new RegExp(find.replace(
                                    /[.*+?^${}()|[\]\\]/g, '\\$&'),
                                    'gi');
                                if (searchRegex.test(text)) {
                                    text = text.replace(searchRegex, replace);
                                    span.textContent = text;
                                    count++;
                                }
                            } else {
                                if (text.includes(find)) {
                                    text = text.replace(new RegExp(find.replace(
                                        /[.*+?^${}()|[\]\\]/g,
                                        '\\$&'), 'g'), replace);
                                    span.textContent = text;
                                    count++;
                                }
                            }
                        }
                    });

                    alert(`Replaced ${count} occurrences.`);
                    findReplaceModal.style.display = 'none';
                });

                // Sort functionality
                sortBtn.addEventListener('click', function () {
                    sortModal.style.display = 'flex';
                });

                cancelSort.addEventListener('click', function () {
                    sortModal.style.display = 'none';
                });

                executeSort.addEventListener('click', function () {
                    const colIndex = parseInt(sortColumn.value);
                    const ascending = document.getElementById('sortAsc').checked;

                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    rows.sort((a, b) => {
                        const aCell = a.querySelector(
                            `[data-col="${colIndex}"]`);
                        const bCell = b.querySelector(
                            `[data-col="${colIndex}"]`);

                        if (!aCell || !bCell) return 0;

                        const aText = aCell.textContent.trim();
                        const bText = bCell.textContent.trim();

                        // Try to compare as numbers
                        const aNum = parseFloat(aText);
                        const bNum = parseFloat(bText);

                        if (!isNaN(aNum) && !isNaN(bNum)) {
                            return ascending ? aNum - bNum : bNum - aNum;
                        }

                        // Compare as text
                        return ascending ? aText.localeCompare(bText) : bText
                            .localeCompare(aText);
                    });

                    // Reorder rows in the DOM
                    rows.forEach(row => {
                        tbody.appendChild(row);
                    });

                    // Update row indices
                    const updatedRows = tbody.querySelectorAll('tr');
                    updatedRows.forEach((row, index) => {
                        const rowHeader = row.querySelector('th');
                        rowHeader.textContent = index + 1;

                        // Update row indices
                        const cells = row.querySelectorAll('.editable-cell');
                        cells.forEach(cell => {
                            cell.dataset.row = index;
                        });
                    });

                    sortModal.style.display = 'none';
                });

                // Export functionality
                exportItems.forEach(item => {
                    item.addEventListener('click', function (e) {
                        e.preventDefault();
                        const format = this.dataset.format;

                        const headers = Array.from(table.querySelectorAll(
                            'thead th')).slice(1).map(th => th.innerText);
                        const data = [];

                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            const rowData = Array.from(row
                                .querySelectorAll('.editable-cell'))
                                .map(cell => cell.innerText.trim());
                            data.push(rowData);
                        });

                        const form = document.createElement('form');
                        form.method = 'post';
                        form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'export';
                        form.appendChild(actionInput);

                        const formatInput = document.createElement('input');
                        formatInput.type = 'hidden';
                        formatInput.name = 'export_format';
                        formatInput.value = format;
                        form.appendChild(formatInput);

                        const headersInput = document.createElement('input');
                        headersInput.type = 'hidden';
                        headersInput.name = 'headers';
                        headersInput.value = JSON.stringify(headers);
                        form.appendChild(headersInput);

                        const dataInput = document.createElement('input');
                        dataInput.type = 'hidden';
                        dataInput.name = 'data';
                        dataInput.value = JSON.stringify(data);
                        form.appendChild(dataInput);

                        document.body.appendChild(form);
                        form.submit();
                    });
                });

                // Save button
                saveBtn.addEventListener('click', function () {
                    const headers = Array.from(table.querySelectorAll('thead th'))
                        .slice(1).map(th => th.innerText);
                    const data = [];

                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const rowData = Array.from(row.querySelectorAll(
                            '.editable-cell')).map(cell => cell.innerText
                                .trim());
                        data.push(rowData);
                    });

                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'save';
                    form.appendChild(actionInput);

                    const headersInput = document.createElement('input');
                    headersInput.type = 'hidden';
                    headersInput.name = 'headers';
                    headersInput.value = JSON.stringify(headers);
                    form.appendChild(headersInput);

                    const dataInput = document.createElement('input');
                    dataInput.type = 'hidden';
                    dataInput.name = 'data';
                    dataInput.value = JSON.stringify(data);
                    form.appendChild(dataInput);

                    const stylesInput = document.createElement('input');
                    stylesInput.type = 'hidden';
                    stylesInput.name = 'styles';
                    stylesInput.value = JSON.stringify(cellStyles);
                    form.appendChild(stylesInput);

                    document.body.appendChild(form);
                    form.submit();
                });

                // Download button
                downloadBtn.addEventListener('click', function () {
                    const headers = Array.from(table.querySelectorAll('thead th'))
                        .slice(1).map(th => th.innerText);
                    const data = [];

                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const rowData = Array.from(row.querySelectorAll(
                            '.editable-cell')).map(cell => cell.innerText
                                .trim());
                        data.push(rowData);
                    });

                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'download';
                    form.appendChild(actionInput);

                    const headersInput = document.createElement('input');
                    headersInput.type = 'hidden';
                    headersInput.name = 'headers';
                    headersInput.value = JSON.stringify(headers);
                    form.appendChild(headersInput);

                    const dataInput = document.createElement('input');
                    dataInput.type = 'hidden';
                    dataInput.name = 'data';
                    dataInput.value = JSON.stringify(data);
                    form.appendChild(dataInput);

                    const stylesInput = document.createElement('input');
                    stylesInput.type = 'hidden';
                    stylesInput.name = 'styles';
                    stylesInput.value = JSON.stringify(cellStyles);
                    form.appendChild(stylesInput);

                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });
    </script>
</body>

</html>