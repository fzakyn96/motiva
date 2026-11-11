<?php

namespace App\Services\Signature;

/**
 * Pure-PHP ISO/IEC 18004 QR Code Generator (Byte Mode, ECC Level L).
 * Generates PNG (via GD for DomPDF compatibility) and SVG Data URIs with zero external network dependencies.
 */
class QrCodeGenerator
{
    /**
     * Version specifications for ECC Level L (Versions 1 to 10).
     * [totalDataCodewords, ecCodewordsPerBlock, numBlocksGroup1, dataCodewordsGroup1, numBlocksGroup2, dataCodewordsGroup2, alignmentCenters]
     */
    private const VERSION_SPECS = [
        1  => [19,  7,  1, 19,  0, 0,  []],
        2  => [34,  10, 1, 34,  0, 0,  [6, 18]],
        3  => [55,  15, 1, 55,  0, 0,  [6, 22]],
        4  => [80,  20, 1, 80,  0, 0,  [6, 26]],
        5  => [108, 26, 1, 108, 0, 0,  [6, 30]],
        6  => [136, 18, 2, 68,  0, 0,  [6, 34]],
        7  => [156, 20, 2, 78,  0, 0,  [6, 22, 38]],
        8  => [194, 24, 2, 97,  0, 0,  [6, 24, 42]],
        9  => [232, 30, 2, 116, 0, 0,  [6, 26, 46]],
        10 => [274, 18, 2, 68,  2, 69, [6, 28, 50]],
    ];

    /**
     * Generate a PNG base64 Data URI (ideal for DomPDF and HTML <img>).
     */
    public static function toPngDataUri(string $text, int $scale = 4, int $margin = 2): string
    {
        $matrix = self::encodeMatrix($text);
        $n = count($matrix);

        if (function_exists('imagecreatetruecolor')) {
            $imgSize = ($n + $margin * 2) * $scale;
            $img = imagecreatetruecolor($imgSize, $imgSize);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 15, 23, 42);

            imagefilledrectangle($img, 0, 0, $imgSize - 1, $imgSize - 1, $white);

            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    if ($matrix[$r][$c] === 1) {
                        $x1 = ($c + $margin) * $scale;
                        $y1 = ($r + $margin) * $scale;
                        imagefilledrectangle($img, $x1, $y1, $x1 + $scale - 1, $y1 + $scale - 1, $black);
                    }
                }
            }

            ob_start();
            imagepng($img);
            $pngData = (string) ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,' . base64_encode($pngData);
        }

        return self::toSvgDataUri($text, ($n + $margin * 2) * $scale);
    }

    /**
     * Generate an SVG base64 Data URI.
     */
    public static function toSvgDataUri(string $text, int $pixelSize = 140, int $margin = 2): string
    {
        $matrix = self::encodeMatrix($text);
        $n = count($matrix);
        $viewSize = $n + ($margin * 2);

        $rects = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $rects[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"1\" height=\"1\" fill=\"#0f172a\"/>";
                }
            }
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $viewSize . ' ' . $viewSize . '" width="' . $pixelSize . '" height="' . $pixelSize . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . implode('', $rects)
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Build the 2D QR Code matrix (0 = light, 1 = dark) for the given text.
     *
     * @return array<int, array<int, int>>
     */
    public static function encodeMatrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text));
        $len = count($bytes);

        // Select smallest version that fits
        $version = 10;
        foreach (self::VERSION_SPECS as $ver => $spec) {
            $headerBits = ($ver <= 9) ? 12 : 20; // 4 mode bits + 8 or 16 length bits
            $maxBytes = (int) floor(($spec[0] * 8 - $headerBits) / 8);
            if ($len <= $maxBytes) {
                $version = $ver;
                break;
            }
        }

        $spec = self::VERSION_SPECS[$version];
        $totalDataBytes = $spec[0];

        // If input is longer than v10 capacity, truncate safely
        $maxCap = (int) floor(($totalDataBytes * 8 - ($version <= 9 ? 12 : 20)) / 8);
        if ($len > $maxCap) {
            $bytes = array_slice($bytes, 0, $maxCap);
            $len = count($bytes);
        }

        // Build bit stream: Mode 0100 (Byte)
        $bits = '0100';
        $lenBitsCount = ($version <= 9) ? 8 : 16;
        $bits .= str_pad(decbin($len), $lenBitsCount, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        // Terminator up to 4 zero bits
        $maxDataBits = $totalDataBytes * 8;
        $rem = $maxDataBits - strlen($bits);
        if ($rem > 0) {
            $bits .= str_repeat('0', min(4, $rem));
        }
        // Pad to byte boundary
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Convert to data codewords and pad with 0xEC, 0x11
        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }
        $padToggle = true;
        while (count($dataCodewords) < $totalDataBytes) {
            $dataCodewords[] = $padToggle ? 0xEC : 0x11;
            $padToggle = ! $padToggle;
        }

        // Split into blocks and compute Reed-Solomon ECC for each block
        $ecPerBlock = $spec[1];
        $blocksData = [];
        $blocksEc = [];
        $offset = 0;

        for ($b = 0; $b < $spec[2]; $b++) {
            $chunk = array_slice($dataCodewords, $offset, $spec[3]);
            $offset += $spec[3];
            $blocksData[] = $chunk;
            $blocksEc[] = self::computeReedSolomon($chunk, $ecPerBlock);
        }
        for ($b = 0; $b < $spec[4]; $b++) {
            $chunk = array_slice($dataCodewords, $offset, $spec[5]);
            $offset += $spec[5];
            $blocksData[] = $chunk;
            $blocksEc[] = self::computeReedSolomon($chunk, $ecPerBlock);
        }

        // Interleave data codewords, then EC codewords
        $finalCodewords = [];
        $maxBlockLen = max(array_map('count', $blocksData));
        for ($i = 0; $i < $maxBlockLen; $i++) {
            foreach ($blocksData as $block) {
                if (isset($block[$i])) {
                    $finalCodewords[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($blocksEc as $ecBlock) {
                if (isset($ecBlock[$i])) {
                    $finalCodewords[] = $ecBlock[$i];
                }
            }
        }

        // Initialize matrix
        $size = 17 + ($version * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Place finder patterns + separators
        self::placeFinderPattern($matrix, $reserved, 0, 0, $size);
        self::placeFinderPattern($matrix, $reserved, $size - 7, 0, $size);
        self::placeFinderPattern($matrix, $reserved, 0, $size - 7, $size);

        // Place alignment patterns
        $centers = $spec[6];
        foreach ($centers as $cy) {
            foreach ($centers as $cx) {
                if ($reserved[$cy][$cx]) {
                    continue;
                }
                self::placeAlignmentPattern($matrix, $reserved, $cy, $cx);
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            if (! $reserved[6][$i]) {
                $matrix[6][$i] = $bit;
                $reserved[6][$i] = true;
            }
            if (! $reserved[$i][6]) {
                $matrix[$i][6] = $bit;
                $reserved[$i][6] = true;
            }
        }

        // Dark module & reserve format information areas
        $matrix[4 * $version + 9][8] = 1;
        $reserved[4 * $version + 9][8] = true;

        for ($i = 0; $i < 9; $i++) {
            if ($i < $size) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // Convert final codewords to bit array
        $allBits = [];
        foreach ($finalCodewords as $cw) {
            for ($b = 7; $b >= 0; $b--) {
                $allBits[] = ($cw >> $b) & 1;
            }
        }

        // Upward/downward 2-column zigzag placement
        $bitIdx = 0;
        $totalBits = count($allBits);
        $upward = true;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                $r = $upward ? ($size - 1 - $vert) : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $c = $right - $j;
                    if (! $reserved[$r][$c]) {
                        $bit = ($bitIdx < $totalBits) ? $allBits[$bitIdx++] : 0;
                        // Apply Mask 0: (r + c) % 2 == 0
                        if (($r + $c) % 2 === 0) {
                            $bit ^= 1;
                        }
                        $matrix[$r][$c] = $bit;
                    }
                }
            }
            $upward = ! $upward;
        }

        // Place Format Information for ECC Level L (01) + Mask 0 (000) => 0x77C4 (30660)
        $formatBits = 0x77C4;
        for ($i = 0; $i <= 5; $i++) {
            $matrix[8][$i] = ($formatBits >> $i) & 1;
        }
        $matrix[8][7] = ($formatBits >> 6) & 1;
        $matrix[8][8] = ($formatBits >> 7) & 1;
        $matrix[7][8] = ($formatBits >> 8) & 1;
        for ($i = 9; $i < 15; $i++) {
            $matrix[14 - $i][8] = ($formatBits >> $i) & 1;
        }

        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 1 - $i] = ($formatBits >> $i) & 1;
        }
        for ($i = 8; $i < 15; $i++) {
            $matrix[$size - 15 + $i][8] = ($formatBits >> $i) & 1;
        }

        return $matrix;
    }

    private static function placeFinderPattern(array &$matrix, array &$reserved, int $row, int $col, int $size): void
    {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $row + $dr;
                $c = $col + $dc;
                if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                    continue;
                }
                if (
                    ($dr >= 0 && $dr <= 6 && ($dc === 0 || $dc === 6)) ||
                    ($dc >= 0 && $dc <= 6 && ($dr === 0 || $dr === 6)) ||
                    ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)
                ) {
                    $matrix[$r][$c] = 1;
                } else {
                    $matrix[$r][$c] = 0;
                }
                $reserved[$r][$c] = true;
            }
        }
    }

    private static function placeAlignmentPattern(array &$matrix, array &$reserved, int $centerRow, int $centerCol): void
    {
        for ($dr = -2; $dr <= 2; $dr++) {
            for ($dc = -2; $dc <= 2; $dc++) {
                $r = $centerRow + $dr;
                $c = $centerCol + $dc;
                $dist = max(abs($dr), abs($dc));
                $matrix[$r][$c] = ($dist === 2 || $dist === 0) ? 1 : 0;
                $reserved[$r][$c] = true;
            }
        }
    }

    /**
     * Compute Reed-Solomon error correction codewords over GF(256) with primitive polynomial 0x11D.
     *
     * @param  array<int, int>  $data
     * @return array<int, int>
     */
    private static function computeReedSolomon(array $data, int $degree): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $exp[$i + 255] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        // Build generator polynomial
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            $root = $exp[$i];
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                if ($gen[$j] !== 0) {
                    $next[$j + 1] ^= $exp[$log[$gen[$j]] + $log[$root]];
                }
            }
            $gen = $next;
        }

        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            if ($factor !== 0) {
                for ($j = 0; $j < $degree; $j++) {
                    if ($gen[$j + 1] !== 0) {
                        $result[$j] ^= $exp[$log[$gen[$j + 1]] + $log[$factor]];
                    }
                }
            }
        }

        return $result;
    }
}

