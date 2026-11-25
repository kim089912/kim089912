<?php
/**
 * TrueType 글꼴 파일에서 글자의 윤곽선을 읽어 SVG 경로로 바꾼다.
 *
 * 프로필의 그림은 <img>로 들어가서 웹 글꼴을 불러오지 못한다. 글자를 text로 두면
 * 보는 사람 PC의 기본 글꼴로 나오므로, 글자 모양 자체를 도형(path)으로 넣는다.
 * 어느 PC에서나 같은 모양으로 보이고, 글자의 실제 폭을 알 수 있어 배치도 정확해진다.
 *
 * 다루는 것: cmap(형식 4, 12), glyf(단순 글리프와 합성 글리프), hmtx(글자 폭).
 * 다루지 않는 것: 커닝(GPOS), 합자(GSUB), 가변 글꼴. 굵기별 고정 TTF를 쓴다.
 */
final class Ttf
{
    private string $d;
    /** @var array<string,int> 테이블 이름 => 파일 안의 위치 */
    private array $tab = [];
    public int $upm;
    private int $locFmt;
    private int $numHM;
    private int $cmapOff = 0;
    private int $cmapFmt = 0;
    private array $gidCache = [];
    private array $ptsCache = [];

    public function __construct(string $path)
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("글꼴 파일을 읽을 수 없습니다: $path");
        }
        $this->d = $data;
        $n = $this->u16(4);
        for ($i = 0; $i < $n; $i++) {
            $o = 12 + $i * 16;
            $this->tab[substr($this->d, $o, 4)] = $this->u32($o + 8);
        }
        foreach (['head', 'maxp', 'hhea', 'hmtx', 'loca', 'glyf', 'cmap'] as $need) {
            if (!isset($this->tab[$need])) {
                throw new RuntimeException("TrueType 윤곽선 글꼴이 아닙니다($need 테이블 없음): $path");
            }
        }
        $this->upm = $this->u16($this->tab['head'] + 18);
        $this->locFmt = $this->i16($this->tab['head'] + 50);
        $this->numHM = $this->u16($this->tab['hhea'] + 34);
        $this->pickCmap();
    }

    private function u8(int $o): int { return ord($this->d[$o]); }
    private function i8(int $o): int { $v = ord($this->d[$o]); return $v > 127 ? $v - 256 : $v; }
    private function u16(int $o): int { return (ord($this->d[$o]) << 8) | ord($this->d[$o + 1]); }
    private function i16(int $o): int { $v = $this->u16($o); return $v > 32767 ? $v - 65536 : $v; }
    private function u32(int $o): int { return ($this->u16($o) << 16) | $this->u16($o + 2); }

    /** 유니코드 전체를 담는 형식 12를 먼저 고르고, 없으면 형식 4를 쓴다. */
    private function pickCmap(): void
    {
        $c = $this->tab['cmap'];
        $n = $this->u16($c + 2);
        $bestScore = -1;
        for ($i = 0; $i < $n; $i++) {
            $platform = $this->u16($c + 4 + $i * 8);
            $encoding = $this->u16($c + 6 + $i * 8);
            $off = $c + $this->u32($c + 8 + $i * 8);
            $fmt = $this->u16($off);
            $score = -1;
            if ($fmt === 12 && (($platform === 3 && $encoding === 10) || $platform === 0)) {
                $score = 2;
            } elseif ($fmt === 4 && (($platform === 3 && $encoding === 1) || $platform === 0)) {
                $score = 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $this->cmapOff = $off;
                $this->cmapFmt = $fmt;
            }
        }
        if ($bestScore < 0) {
            throw new RuntimeException('쓸 수 있는 cmap이 없습니다.');
        }
    }

    /** 글자(유니코드 번호) => 글리프 번호. 없는 글자는 0(빈 네모)이다. */
    public function gid(int $cp): int
    {
        if (isset($this->gidCache[$cp])) {
            return $this->gidCache[$cp];
        }
        $o = $this->cmapOff;
        $g = 0;
        if ($this->cmapFmt === 12) {
            $lo = 0;
            $hi = $this->u32($o + 12) - 1;
            while ($lo <= $hi) {
                $m = ($lo + $hi) >> 1;
                $r = $o + 16 + $m * 12;
                $s = $this->u32($r);
                if ($cp < $s) {
                    $hi = $m - 1;
                } elseif ($cp > $this->u32($r + 4)) {
                    $lo = $m + 1;
                } else {
                    $g = $this->u32($r + 8) + ($cp - $s);
                    break;
                }
            }
        } else {
            $segX2 = $this->u16($o + 6);
            $end = $o + 14;
            $start = $end + $segX2 + 2;
            $delta = $start + $segX2;
            $range = $delta + $segX2;
            for ($i = 0; $i < $segX2; $i += 2) {
                if ($cp > $this->u16($end + $i)) {
                    continue;
                }
                $s = $this->u16($start + $i);
                if ($cp >= $s) {
                    $ro = $this->u16($range + $i);
                    if ($ro === 0) {
                        $g = ($cp + $this->i16($delta + $i)) & 0xFFFF;
                    } else {
                        $gi = $this->u16($range + $i + $ro + ($cp - $s) * 2);
                        $g = $gi === 0 ? 0 : ($gi + $this->i16($delta + $i)) & 0xFFFF;
                    }
                }
                break;
            }
        }
        return $this->gidCache[$cp] = $g;
    }

    /** 글리프의 폭(글꼴 단위). */
    public function adv(int $g): int
    {
        return $this->u16($this->tab['hmtx'] + min($g, $this->numHM - 1) * 4);
    }

    /**
     * 글리프의 윤곽선을 글꼴 단위의 점 목록으로 돌려준다.
     * 점은 [x, y, 곡선 위의 점인가]. 합성 글리프는 부품을 변환해 펼친다.
     */
    private function contours(int $g, int $depth = 0): array
    {
        if ($depth === 0 && isset($this->ptsCache[$g])) {
            return $this->ptsCache[$g];
        }
        if ($depth > 8) {
            return [];
        }
        $loca = $this->tab['loca'];
        if ($this->locFmt === 0) {
            $a = $this->u16($loca + $g * 2) * 2;
            $b = $this->u16($loca + $g * 2 + 2) * 2;
        } else {
            $a = $this->u32($loca + $g * 4);
            $b = $this->u32($loca + $g * 4 + 4);
        }
        $out = [];
        if ($a !== $b) {
            $o = $this->tab['glyf'] + $a;
            $nc = $this->i16($o);
            $o += 10;
            if ($nc >= 0) {
                $ends = [];
                for ($i = 0; $i < $nc; $i++) {
                    $ends[] = $this->u16($o + $i * 2);
                }
                $o += $nc * 2;
                $np = $nc ? $ends[$nc - 1] + 1 : 0;
                $o += 2 + $this->u16($o);
                $flags = [];
                while (count($flags) < $np) {
                    $f = $this->u8($o++);
                    $flags[] = $f;
                    if ($f & 8) {
                        for ($r = $this->u8($o++); $r > 0; $r--) {
                            $flags[] = $f;
                        }
                    }
                }
                $xs = [];
                $x = 0;
                foreach ($flags as $f) {
                    if ($f & 2) {
                        $v = $this->u8($o++);
                        $x += ($f & 16) ? $v : -$v;
                    } elseif (!($f & 16)) {
                        $x += $this->i16($o);
                        $o += 2;
                    }
                    $xs[] = $x;
                }
                $ys = [];
                $y = 0;
                foreach ($flags as $f) {
                    if ($f & 4) {
                        $v = $this->u8($o++);
                        $y += ($f & 32) ? $v : -$v;
                    } elseif (!($f & 32)) {
                        $y += $this->i16($o);
                        $o += 2;
                    }
                    $ys[] = $y;
                }
                $s = 0;
                foreach ($ends as $e) {
                    $c = [];
                    for ($i = $s; $i <= $e; $i++) {
                        $c[] = [$xs[$i], $ys[$i], (bool) ($flags[$i] & 1)];
                    }
                    $out[] = $c;
                    $s = $e + 1;
                }
            } else {
                do {
                    $fl = $this->u16($o);
                    $part = $this->u16($o + 2);
                    $o += 4;
                    if ($fl & 1) {
                        $dx = $this->i16($o);
                        $dy = $this->i16($o + 2);
                        $o += 4;
                    } else {
                        $dx = $this->i8($o);
                        $dy = $this->i8($o + 1);
                        $o += 2;
                    }
                    if (!($fl & 2)) {
                        // 점 맞추기 방식의 위치 지정은 다루지 않는다. 제자리에 둔다.
                        $dx = 0;
                        $dy = 0;
                    }
                    $ma = 1.0; $mb = 0.0; $mc = 0.0; $md = 1.0;
                    if ($fl & 8) {
                        $ma = $md = $this->i16($o) / 16384;
                        $o += 2;
                    } elseif ($fl & 0x40) {
                        $ma = $this->i16($o) / 16384;
                        $md = $this->i16($o + 2) / 16384;
                        $o += 4;
                    } elseif ($fl & 0x80) {
                        $ma = $this->i16($o) / 16384;
                        $mb = $this->i16($o + 2) / 16384;
                        $mc = $this->i16($o + 4) / 16384;
                        $md = $this->i16($o + 6) / 16384;
                        $o += 8;
                    }
                    foreach ($this->contours($part, $depth + 1) as $c) {
                        $t = [];
                        foreach ($c as [$px, $py, $on]) {
                            $t[] = [$ma * $px + $mc * $py + $dx, $mb * $px + $md * $py + $dy, $on];
                        }
                        $out[] = $t;
                    }
                } while ($fl & 0x20);
            }
        }
        if ($depth === 0) {
            $this->ptsCache[$g] = $out;
        }
        return $out;
    }

    /** 글리프 하나를 SVG 경로로. 글꼴은 y가 위로 자라고 SVG는 아래로 자라므로 y를 뒤집는다. */
    private function glyph(int $g, float $sc, float $ox, float $oy): string
    {
        $d = '';
        $f = static fn(array $p): string => self::num($ox + $p[0] * $sc) . ' ' . self::num($oy - $p[1] * $sc);
        foreach ($this->contours($g) as $c) {
            $n = count($c);
            if ($n < 2) {
                continue;
            }
            // 경로는 곡선 위의 점에서 시작해야 한다. 그런 점이 없으면 두 조절점의 가운데가 그 점이다.
            if ($c[0][2]) {
                $st = $c[0];
                $list = array_slice($c, 1);
                $list[] = $st;
            } elseif ($c[$n - 1][2]) {
                $st = $c[$n - 1];
                $list = $c;
            } else {
                $st = [($c[$n - 1][0] + $c[0][0]) / 2, ($c[$n - 1][1] + $c[0][1]) / 2, true];
                $list = $c;
                $list[] = $st;
            }
            $d .= 'M' . $f($st);
            $m = count($list);
            for ($i = 0; $i < $m; $i++) {
                $p = $list[$i];
                if ($p[2]) {
                    $d .= 'L' . $f($p);
                    continue;
                }
                $q = $list[$i + 1]; // 목록은 항상 곡선 위의 점으로 끝나므로 다음 점이 있다.
                if ($q[2]) {
                    $d .= 'Q' . $f($p) . ' ' . $f($q);
                    $i++;
                } else {
                    $d .= 'Q' . $f($p) . ' ' . $f([($p[0] + $q[0]) / 2, ($p[1] + $q[1]) / 2]);
                }
            }
            $d .= 'Z';
        }
        return $d;
    }

    private static function num(float $v): string
    {
        $s = number_format($v, 1, '.', '');
        return str_ends_with($s, '.0') ? substr($s, 0, -2) : $s;
    }

    /** UTF-8 문자열을 유니코드 번호 목록으로. mbstring 확장이 없어도 되게 직접 푼다. */
    private static function codepoints(string $s): array
    {
        $out = [];
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $b = array_values(unpack('C*', $ch));
            $out[] = match (count($b)) {
                1 => $b[0],
                2 => (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F),
                3 => (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F),
                default => (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F),
            };
        }
        return $out;
    }

    /** 글줄의 폭(px). $track은 글자 사이에 더하는 간격(px)이다. */
    public function width(string $s, float $size, float $track = 0): float
    {
        $sc = $size / $this->upm;
        $w = 0.0;
        $cps = self::codepoints($s);
        foreach ($cps as $cp) {
            $w += $this->adv($this->gid($cp)) * $sc + $track;
        }
        return $cps ? $w - $track : 0.0;
    }

    /** 글줄을 SVG 경로 데이터로. ($x, $y)는 기준선의 시작점이고 $anchor는 start, middle, end 중 하나다. */
    public function path(string $s, float $size, float $x, float $y, float $track = 0, string $anchor = 'start'): string
    {
        if ($anchor !== 'start') {
            $w = $this->width($s, $size, $track);
            $x -= $anchor === 'end' ? $w : $w / 2;
        }
        $sc = $size / $this->upm;
        $d = '';
        foreach (self::codepoints($s) as $cp) {
            $g = $this->gid($cp);
            if ($g === 0 && $cp !== 0x20) {
                throw new RuntimeException(sprintf('글꼴에 없는 글자: U+%04X ("%s"에서)', $cp, $s));
            }
            $d .= $this->glyph($g, $sc, $x, $y);
            $x += $this->adv($g) * $sc + $track;
        }
        return $d;
    }
}
