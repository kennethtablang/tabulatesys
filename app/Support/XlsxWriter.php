<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal .xlsx (Office Open XML) writer: several sheets, text and numbers,
 * a few cell styles, column widths and frozen header rows. Needs only PHP's zip extension.
 *
 * Cell values: string | int | float | null, or ['v' => value, 's' => style] where style is one of
 * 'title', 'subtitle', 'header', 'bold', 'number', 'number-bold', 'muted'.
 */
class XlsxWriter
{
    protected const STYLES = ['normal' => 0, 'title' => 1, 'subtitle' => 2, 'header' => 3, 'bold' => 4, 'number' => 5, 'number-bold' => 6, 'muted' => 7];

    /** @var array<int, array{name: string, rows: array, widths: array, freeze: int}> */
    protected array $sheets = [];

    /** @var array<int, array<int, array{path: string, x: int, y: int, w: int, h: int, type: int}>> images per sheet index (pixels) */
    protected array $images = [];

    protected int $drawings = 0;

    /**
     * Place an image (PNG/JPEG/GIF) on the most recently added sheet at absolute pixel
     * coordinates, scaled to the given height. Returns the width used, so callers can line images up.
     */
    public function addImage(string $path, int $x, int $y, int $height): int
    {
        $info = @getimagesize($path);
        if (! $this->sheets || ! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
            return 0;
        }
        $width = (int) round($info[0] * $height / max(1, $info[1]));
        $this->images[count($this->sheets) - 1][] = ['path' => $path, 'x' => $x, 'y' => $y, 'w' => $width, 'h' => $height, 'type' => $info[2]];

        return $width;
    }

    public function addSheet(string $name, array $rows, array $widths = [], int $freezeRows = 0): static
    {
        $name = trim(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name)) ?: 'Sheet';
        $name = mb_substr($name, 0, 31);
        $base = $name;
        for ($i = 2; in_array(mb_strtolower($name), array_map(fn ($s) => mb_strtolower($s['name']), $this->sheets), true); $i++) {
            $name = mb_substr($base, 0, 28).' '.$i;
        }
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'widths' => $widths, 'freeze' => $freezeRows];

        return $this;
    }

    /** Build the workbook and return its binary contents. */
    public function toString(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for Excel export.');
        }
        if (! $this->sheets) {
            $this->addSheet('Sheet1', [['No data']]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $n = count($this->sheets);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($n));
        $zip->addFromString('xl/styles.xml', $this->styles());
        $media = [];   // path => file name inside xl/media (each image stored once)
        $this->drawings = 0;
        foreach ($this->sheets as $i => $sheet) {
            $hasDrawing = ! empty($this->images[$i]);
            if ($hasDrawing) {
                $id = ++$this->drawings;
                $rels = '';
                $anchors = '';
                foreach ($this->images[$i] as $k => $img) {
                    if (! isset($media[$img['path']])) {
                        $ext = $img['type'] === IMAGETYPE_JPEG ? 'jpeg' : ($img['type'] === IMAGETYPE_GIF ? 'gif' : 'png');
                        $media[$img['path']] = 'image'.(count($media) + 1).'.'.$ext;
                        $zip->addFile($img['path'], 'xl/media/'.$media[$img['path']]);
                    }
                    $rid = 'rId'.($k + 1);
                    $rels .= '<Relationship Id="'.$rid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$media[$img['path']].'"/>';
                    $x = $img['x'] * 9525;   // EMUs per pixel
                    $y = $img['y'] * 9525;
                    $cx = $img['w'] * 9525;
                    $cy = $img['h'] * 9525;
                    $anchors .= '<xdr:absoluteAnchor><xdr:pos x="'.$x.'" y="'.$y.'"/><xdr:ext cx="'.$cx.'" cy="'.$cy.'"/>'
                        .'<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.($k + 2).'" name="Logo '.($k + 1).'"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                        .'<xdr:blipFill><a:blip r:embed="'.$rid.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                        .'<xdr:spPr><a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
                        .'<xdr:clientData/></xdr:absoluteAnchor>';
                }
                $zip->addFromString('xl/drawings/drawing'.$id.'.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    .$anchors.'</xdr:wsDr>');
                $zip->addFromString('xl/drawings/_rels/drawing'.$id.'.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
                $zip->addFromString('xl/worksheets/_rels/sheet'.($i + 1).'.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing'.$id.'.xml"/></Relationships>');
            }
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->sheet($sheet, $hasDrawing));
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes($n));
        $zip->close();

        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    protected function contentTypes(int $n): string
    {
        $sheets = '';
        for ($i = 1; $i <= $n; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        for ($i = 1; $i <= $this->drawings; $i++) {
            $sheets .= '<Override PartName="/xl/drawings/drawing'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
            .'<Default Extension="gif" ContentType="image/gif"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets.'</Types>';
    }

    protected function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $s) {
            $sheets .= '<sheet name="'.$this->esc($s['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    protected function workbookRels(int $n): string
    {
        $rels = '';
        for ($i = 1; $i <= $n; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2F4050"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD0D4D8"/></left><right style="thin"><color rgb="FFD0D4D8"/></right>'
            .'<top style="thin"><color rgb="FFD0D4D8"/></top><bottom style="thin"><color rgb="FFD0D4D8"/></bottom></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                            // normal
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                               // title
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                               // subtitle
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // header
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'                // bold
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'      // number
            .'<xf numFmtId="164" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>' // number-bold
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                               // muted
            .'</cellXfs></styleSheet>';
    }

    protected function sheet(array $sheet, bool $hasDrawing = false): string
    {
        $cols = '';
        foreach ($sheet['widths'] as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.(float) $w.'" customWidth="1"/>';
        }
        $pane = $sheet['freeze'] > 0
            ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$sheet['freeze'].'" topLeftCell="A'.($sheet['freeze'] + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            : '';

        $rows = '';
        foreach ($sheet['rows'] as $r => $row) {
            $cells = '';
            foreach (array_values((array) $row) as $c => $cell) {
                $style = 'normal';
                $value = $cell;
                if (is_array($cell)) {
                    $value = $cell['v'] ?? null;
                    $style = $cell['s'] ?? 'normal';
                } elseif (is_int($cell) || is_float($cell)) {
                    $style = 'number';
                }
                if ($value === null || $value === '') {
                    if ($style !== 'normal') {
                        $cells .= '<c r="'.$this->ref($c, $r).'" s="'.self::STYLES[$style].'"/>';
                    }

                    continue;
                }
                $s = self::STYLES[$style] ?? 0;
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$this->ref($c, $r).'" s="'.$s.'"><v>'.$value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$this->ref($c, $r).'" s="'.$s.'" t="inlineStr"><is><t xml:space="preserve">'.$this->esc((string) $value).'</t></is></c>';
                }
            }
            $rows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .$pane.($cols ? '<cols>'.$cols.'</cols>' : '').'<sheetData>'.$rows.'</sheetData>'
            .($hasDrawing ? '<drawing r:id="rId1"/>' : '').'</worksheet>';
    }

    protected function ref(int $col, int $row): string
    {
        $letters = '';
        for ($n = $col + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26).$letters;
        }

        return $letters.($row + 1);
    }

    protected function esc(string $s): string
    {
        // Strip characters XML 1.0 forbids, then escape.
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
