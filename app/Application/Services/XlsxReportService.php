<?php

namespace App\Application\Services;

class XlsxReportService
{
    private const GREEN = 'FF0C7E43';
    private const GREEN_LIGHT = 'FFE9F7EF';
    private const BORDER = 'FFDDE5E1';
    private const TEXT = 'FF172033';
    private const MUTED = 'FF667085';
    private const WHITE = 'FFFFFFFF';

    public function create(string $eventName, string $reportLabel, array $rows, array $columns, array $numericColumns, array $pointColumns, string $filterSummary): array
    {
        $columnCount = max(1, count($columns));
        $lastColumn = $this->columnLetter($columnCount);
        $sheetRows = [];
        $sheetRows[] = $this->rowXml(1, [$this->textCell('A1', $eventName, 1)]);
        $sheetRows[] = $this->rowXml(2, [$this->textCell('A2', $reportLabel, 2)]);
        $sheetRows[] = $this->rowXml(3, [$this->textCell('A3', 'Generated: ' . date('F j, Y g:i A'), 5)]);
        $sheetRows[] = $this->rowXml(4, [$this->textCell('A4', $filterSummary !== '' ? 'Filters — ' . $filterSummary : 'Filters — All records', 5)]);

        $headerCells = [];
        foreach ($columns as $index => $column) {
            $headerCells[] = $this->textCell($this->columnLetter($index + 1) . '6', $this->label($column), 3);
        }
        $sheetRows[] = $this->rowXml(6, $headerCells);

        $rowNumber = 7;
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $index => $column) {
                $reference = $this->columnLetter($index + 1) . $rowNumber;
                $value = $row[$column] ?? null;
                if ($value !== null && $value !== '' && in_array($column, $numericColumns, true) && is_numeric($value)) {
                    $style = in_array($column, $pointColumns, true) ? 4 : 6;
                    $cells[] = $this->numberCell($reference, (float) $value, $style);
                } else {
                    $cells[] = $this->textCell($reference, $value === null || $value === '' ? '—' : (string) $value, 0);
                }
            }
            $sheetRows[] = $this->rowXml($rowNumber, $cells);
            $rowNumber++;
        }
        if ($rows === []) {
            $sheetRows[] = $this->rowXml(7, [$this->textCell('A7', 'No report data matches the selected filters.', 5)]);
        }

        $lastDataRow = max(7, $rowNumber - 1);
        $mergeCells = $columnCount > 1
            ? '<mergeCells count="4"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A2:' . $lastColumn . '2"/><mergeCell ref="A3:' . $lastColumn . '3"/><mergeCell ref="A4:' . $lastColumn . '4"/></mergeCells>'
            : '';
        if ($rows === [] && $columnCount > 1) {
            $mergeCells = str_replace('count="4"', 'count="5"', $mergeCells);
            $mergeCells = str_replace('</mergeCells>', '<mergeCell ref="A7:' . $lastColumn . '7"/></mergeCells>', $mergeCells);
        }

        $columnsXml = '';
        foreach ($columns as $index => $column) {
            $width = in_array($column, ['team', 'submitted_by', 'validated_by'], true) ? 26 : (in_array($column, ['sport', 'round', 'status'], true) ? 20 : 14);
            $n = $index + 1;
            $columnsXml .= '<col min="' . $n . '" max="' . $n . '" width="' . $width . '" customWidth="1"/>';
        }

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>' . $columnsXml . '</cols><sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . $mergeCells
            . '<autoFilter ref="A6:' . $lastColumn . $lastDataRow . '"/>'
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';

        $files = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'docProps/app.xml' => $this->appPropertiesXml(),
            'docProps/core.xml' => $this->corePropertiesXml($eventName, $reportLabel),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $worksheet,
        ];

        return [
            'filename' => $this->slug($eventName) . '-' . $this->slug($reportLabel) . '-' . date('Ymd-His') . '.xlsx',
            'content' => $this->zip($files),
        ];
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>TallyTech</Application></Properties>';
    }

    private function corePropertiesXml(string $eventName, string $reportLabel): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>TallyTech</dc:creator><dc:title>' . $this->xml($eventName . ' — ' . $reportLabel) . '</dc:title><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created></cp:coreProperties>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.##"/></numFmts>'
            . '<fonts count="4"><font><sz val="10"/><name val="Arial"/><color rgb="' . self::TEXT . '"/></font><font><b/><sz val="18"/><name val="Arial"/><color rgb="' . self::WHITE . '"/></font><font><b/><sz val="11"/><name val="Arial"/><color rgb="' . self::TEXT . '"/></font><font><i/><sz val="10"/><name val="Arial"/><color rgb="' . self::MUTED . '"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="' . self::GREEN . '"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="' . self::GREEN_LIGHT . '"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="' . self::BORDER . '"/></left><right style="thin"><color rgb="' . self::BORDER . '"/></right><top style="thin"><color rgb="' . self::BORDER . '"/></top><bottom style="thin"><color rgb="' . self::BORDER . '"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    private function rowXml(int $row, array $cells): string
    {
        return '<row r="' . $row . '">' . implode('', $cells) . '</row>';
    }

    private function textCell(string $reference, string $value, int $style): string
    {
        return '<c r="' . $reference . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $this->xml($value) . '</t></is></c>';
    }

    private function numberCell(string $reference, float $value, int $style): string
    {
        return '<c r="' . $reference . '" s="' . $style . '"><v>' . rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.') . '</v></c>';
    }

    private function label(string $column): string
    {
        $labels = [
            'rank' => 'Rank', 'team' => 'Team', 'firsts' => '1st', 'seconds' => '2nd', 'thirds' => '3rd', 'points' => 'Points',
            'gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze', 'total_medals' => 'Total Medals', 'date' => 'Date',
            'sport' => 'Sport', 'category' => 'Category', 'round' => 'Round', 'score' => 'Score', 'placement' => 'Placement',
            'status' => 'Result Status', 'submitted_by' => 'Submitted By', 'submitted_at' => 'Submitted At', 'validated_by' => 'Validated By', 'validated_at' => 'Validated At',
        ];
        return $labels[$column] ?? ucwords(str_replace('_', ' ', $column));
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
        return $slug !== '' ? $slug : 'tallytech-report';
    }

    private function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosTimeDate();
        foreach ($files as $name => $data) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($data);
            $size = strlen($data);
            $nameLength = strlen($name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0) . $name;
            $local .= $localHeader . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $size;
        }
        $count = count($files);
        return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($local), 0);
    }

    private function dosTimeDate(): array
    {
        $parts = getdate();
        $year = max(1980, (int) $parts['year']);
        $time = ((int) $parts['hours'] << 11) | ((int) $parts['minutes'] << 5) | intdiv((int) $parts['seconds'], 2);
        $date = (($year - 1980) << 9) | ((int) $parts['mon'] << 5) | (int) $parts['mday'];
        return [$time, $date];
    }
}
