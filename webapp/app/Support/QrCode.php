<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A QR code encoder of our own (ISO/IEC 18004), so the payment QR on an
 * invoice needs no outside package and no outside service — the same
 * reasoning as the sign-in picture.
 *
 * Byte mode (the text as UTF-8), error correction L, M, Q or H, versions
 * 1–40, and the eight masks scored by the standard's penalty rules. The
 * procedure and tables follow the standard as laid out in Project Nayuki's
 * reference implementation. The tests check the Reed–Solomon arithmetic and
 * the format and version words against the standard's published values,
 * and read every symbol back with a separate decoder.
 */
final class QrCode
{
    /** Error-correction level => [table row, the two bits written in the format word]. */
    private const LEVELS = ['L' => [0, 1], 'M' => [1, 0], 'Q' => [2, 3], 'H' => [3, 2]];

    /** Error-correction codewords per block, by level then version (index 0 unused). */
    private const ECC_CODEWORDS_PER_BLOCK = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Error-correction blocks, by level then version (index 0 unused). */
    private const NUM_ERROR_CORRECTION_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private const PENALTY_N1 = 3;
    private const PENALTY_N2 = 3;
    private const PENALTY_N3 = 40;
    private const PENALTY_N4 = 10;

    public readonly int $size;

    public readonly int $mask;

    /** @var array<int, array<int, bool>> [row][column], true = dark */
    private array $modules = [];

    /** @var array<int, array<int, bool>> [row][column], true = part of a fixed pattern */
    private array $isFunction = [];

    private function __construct(public readonly int $version, public readonly string $level, array $dataCodewords, ?int $mask)
    {
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules    = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);

        $this->drawFunctionPatterns();
        $this->drawCodewords($this->addEccAndInterleave($dataCodewords));

        if ($mask === null) {
            $best = PHP_INT_MAX;
            for ($m = 0; $m < 8; $m++) {
                $this->applyMask($m);
                $this->drawFormatBits($m);
                $penalty = $this->penaltyScore();
                if ($penalty < $best) {
                    $mask = $m;
                    $best = $penalty;
                }
                $this->applyMask($m);   // the mask is its own inverse
            }
        }

        $this->mask = $mask;
        $this->applyMask($mask);
        $this->drawFormatBits($mask);
    }

    /**
     * The smallest symbol that holds the text at the requested level.
     *
     * @throws InvalidArgumentException when the text is too long for any version
     */
    public static function encode(string $text, string $level = 'M', ?int $mask = null): self
    {
        $level = strtoupper($level);
        if (! isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException("Unknown error-correction level {$level}.");
        }
        if ($mask !== null && ($mask < 0 || $mask > 7)) {
            throw new InvalidArgumentException('The mask is 0 to 7.');
        }

        $bytes = array_values(unpack('C*', $text) ?: []);
        $count = count($bytes);

        for ($version = 1; ; $version++) {
            if ($version > 40) {
                throw new InvalidArgumentException('The text is too long for a QR code.');
            }
            $capacityBits = self::numDataCodewords($version, $level) * 8;
            $usedBits     = 4 + ($version < 10 ? 8 : 16) + 8 * $count;
            if (($version < 10 ? $count < 256 : $count < 65536) && $usedBits <= $capacityBits) {
                break;
            }
        }

        // Mode indicator (byte = 0100), the character count, the bytes.
        $bits = [];
        self::appendBits($bits, 0b0100, 4);
        self::appendBits($bits, $count, $version < 10 ? 8 : 16);
        foreach ($bytes as $byte) {
            self::appendBits($bits, $byte, 8);
        }

        // Terminator, padding to a byte, then the two pad bytes in turn.
        self::appendBits($bits, 0, min(4, $capacityBits - count($bits)));
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacityBits; $pad ^= 0xEC ^ 0x11) {
            self::appendBits($bits, $pad, 8);
        }

        $codewords = array_fill(0, intdiv(count($bits), 8), 0);
        foreach ($bits as $i => $bit) {
            $codewords[$i >> 3] |= $bit << (7 - ($i & 7));
        }

        return new self($version, $level, $codewords, $mask);
    }

    /** @return array<int, array<int, bool>> [row][column], true = dark */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * The symbol as an SVG: one path for the dark modules, crisp at any
     * size, the quiet zone included. Sized by the element that holds it.
     */
    public function svg(int $quiet = 4, string $dark = '#111827', string $label = 'QR code'): string
    {
        $path = '';
        foreach ($this->modules as $y => $row) {
            $x = 0;
            while ($x < $this->size) {
                if (! $row[$x]) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $this->size && $row[$x]) {
                    $x++;
                }
                $path .= 'M' . ($start + $quiet) . ' ' . ($y + $quiet) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
            }
        }

        $box = $this->size + 2 * $quiet;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $box . ' ' . $box . '" role="img" aria-label="' . e($label) . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#FFFFFF"/>'
            . '<path fill="' . e($dark) . '" d="' . $path . '"/></svg>';
    }

    // ---- Building the symbol ---------------------------------------------------

    private function drawFunctionPatterns(): void
    {
        // Timing patterns.
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }

        // The three finder patterns, their separators included.
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        // Alignment patterns, except where a finder already sits.
        $positions = self::alignmentPositions($this->version);
        $n = count($positions);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignment($positions[$i], $positions[$j]);
            }
        }

        // Reserve the format areas (written for real once the mask is known) and the version areas.
        $this->drawFormatBits(0);
        $this->drawVersion();
    }

    private function drawFinder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunction($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $bits = self::formatBits($this->level, $mask);

        // First copy, around the top-left finder.
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, self::bit($bits, $i));
        }
        $this->setFunction(8, 7, self::bit($bits, 6));
        $this->setFunction(8, 8, self::bit($bits, 7));
        $this->setFunction(7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, self::bit($bits, $i));
        }

        // Second copy, split between the other two finders.
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, self::bit($bits, $i));
        }
        $this->setFunction(8, $this->size - 8, true);   // always dark
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $bits = self::versionBits($this->version);
        for ($i = 0; $i < 18; $i++) {
            $bit = self::bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $bit);
            $this->setFunction($b, $a, $bit);
        }
    }

    /** @return int[] the codewords with error correction added, blocks interleaved */
    private function addEccAndInterleave(array $data): array
    {
        [$row] = self::LEVELS[$this->level];
        $numBlocks      = self::NUM_ERROR_CORRECTION_BLOCKS[$row][$this->version];
        $blockEccLen    = self::ECC_CODEWORDS_PER_BLOCK[$row][$this->version];
        $rawCodewords   = intdiv(self::numRawDataModules($this->version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen  = intdiv($rawCodewords, $numBlocks);

        $divisor = self::rsDivisor($blockEccLen);
        $blocks  = [];
        for ($i = 0, $k = 0; $i < $numBlocks; $i++) {
            $length = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat    = array_slice($data, $k, $length);
            $k     += $length;
            $block  = array_pad($dat, $shortBlockLen + 1, 0);
            $ecc    = self::rsRemainder($dat, $divisor);
            array_splice($block, count($block) - $blockEccLen, $blockEccLen, $ecc);
            $blocks[] = $block;
        }

        $result = [];
        for ($i = 0, $len = count($blocks[0]); $i < $len; $i++) {
            foreach ($blocks as $j => $block) {
                // A short block has no codeword at the last data position.
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }

    /** The data bits in the standard's zigzag, two columns at a time from the bottom right. */
    private function drawCodewords(array $codewords): void
    {
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;   // the vertical timing column is skipped
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (! $this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = self::bit($codewords[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = ! $this->modules[$y][$x];
                }
            }
        }
    }

    private function penaltyScore(): int
    {
        $result = 0;
        $size   = $this->size;

        // Runs of one colour and finder-like patterns, along rows then columns.
        foreach ([false, true] as $columns) {
            for ($a = 0; $a < $size; $a++) {
                $runColor = false;
                $runLength = 0;
                $history = array_fill(0, 7, 0);
                for ($b = 0; $b < $size; $b++) {
                    $color = $columns ? $this->modules[$b][$a] : $this->modules[$a][$b];
                    if ($color === $runColor) {
                        $runLength++;
                        if ($runLength === 5) {
                            $result += self::PENALTY_N1;
                        } elseif ($runLength > 5) {
                            $result++;
                        }
                    } else {
                        $this->finderHistoryAdd($runLength, $history);
                        if (! $runColor) {
                            $result += $this->finderPatterns($history) * self::PENALTY_N3;
                        }
                        $runColor = $color;
                        $runLength = 1;
                    }
                }
                $result += $this->finderTerminate($runColor, $runLength, $history) * self::PENALTY_N3;
            }
        }

        // 2×2 blocks of one colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $result += self::PENALTY_N2;
                }
            }
        }

        // The balance of dark and light.
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $c) {
                $dark += $c ? 1 : 0;
            }
        }
        $total = $size * $size;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;

        return $result + $k * self::PENALTY_N4;
    }

    private function finderPatterns(array $h): int
    {
        $n = $h[1];
        $core = $n > 0 && $h[2] === $n && $h[3] === $n * 3 && $h[4] === $n && $h[5] === $n;

        return ($core && $h[0] >= $n * 4 && $h[6] >= $n ? 1 : 0) + ($core && $h[6] >= $n * 4 && $h[0] >= $n ? 1 : 0);
    }

    private function finderTerminate(bool $runColor, int $runLength, array &$history): int
    {
        if ($runColor) {
            $this->finderHistoryAdd($runLength, $history);
            $runLength = 0;
        }
        $runLength += $this->size;   // the light border after the last run
        $this->finderHistoryAdd($runLength, $history);

        return $this->finderPatterns($history);
    }

    private function finderHistoryAdd(int $runLength, array &$history): void
    {
        if ($history[0] === 0) {
            $runLength += $this->size;   // the light border before the first run
        }
        array_pop($history);
        array_unshift($history, $runLength);
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x]    = $dark;
        $this->isFunction[$y][$x] = true;
    }

    // ---- The standard's arithmetic (public for the tests) ----------------------

    /** The 15-bit format word for a level and mask, BCH-protected and masked. */
    public static function formatBits(string $level, int $mask): int
    {
        $data = (self::LEVELS[$level][1] << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }

        return (($data << 10) | $rem) ^ 0x5412;
    }

    /** The 18-bit version word (versions 7 and up). */
    public static function versionBits(int $version): int
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }

        return ($version << 12) | $rem;
    }

    /** @return int[] centres of the alignment patterns along each axis */
    public static function alignmentPositions(int $version): array
    {
        if ($version === 1) {
            return [];
        }

        $numAlign = intdiv($version, 7) + 2;
        $step = $version === 32 ? 26 : intdiv($version * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;
        $result = [];
        for ($i = 0, $pos = $version * 4 + 10; $i < $numAlign - 1; $i++, $pos -= $step) {
            array_unshift($result, $pos);
        }
        array_unshift($result, 6);

        return $result;
    }

    /** Data modules in a symbol once every fixed pattern is taken out. */
    public static function numRawDataModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    public static function numDataCodewords(int $version, string $level): int
    {
        [$row] = self::LEVELS[$level];

        return intdiv(self::numRawDataModules($version), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$row][$version] * self::NUM_ERROR_CORRECTION_BLOCKS[$row][$version];
    }

    /** @return array{0: int, 1: int, 2: int} [blocks, ecc codewords per block, raw codewords] */
    public static function blockLayout(int $version, string $level): array
    {
        [$row] = self::LEVELS[$level];

        return [self::NUM_ERROR_CORRECTION_BLOCKS[$row][$version], self::ECC_CODEWORDS_PER_BLOCK[$row][$version], intdiv(self::numRawDataModules($version), 8)];
    }

    /** The Reed–Solomon generator polynomial of a degree, highest power first, leading 1 left out. */
    public static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }

        return $result;
    }

    /** @return int[] the error-correction codewords for the data */
    public static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ $result[0];
            array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return $result;
    }

    /** Multiplication in GF(2^8) modulo x^8 + x^4 + x^3 + x^2 + 1. */
    public static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function bit(int $value, int $i): bool
    {
        return (($value >> $i) & 1) !== 0;
    }
}
