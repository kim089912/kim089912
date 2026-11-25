<?php
/**
 * 프로필 그림 세 장(assets/hero.svg, flow.svg, stack.svg)을 만든다.
 *
 * 사용:  php tools/build.php v2.0.1
 *        인자는 이번에 릴리즈할 버전이다. 그림 안의 버전 표시에 쓰인다.
 *
 * 글자는 tools/fonts 의 Pretendard(고정 굵기 TTF)에서 윤곽선을 읽어 도형으로 넣는다.
 * 글꼴 파일은 저장소에 올리지 않는다. 받는 곳은 tools/README.md 에 있다.
 * 연차는 2021-10-19(개발 시작일)를 1년차의 첫날로 세어 실행한 날 기준으로 계산한다.
 */
require __DIR__ . '/ttf.php';
date_default_timezone_set('Asia/Seoul');

$VERSION = $argv[1] ?? '';
if (!preg_match('/^v\d+\.\d+\.\d+$/', $VERSION)) {
    fwrite(STDERR, "사용: php tools/build.php v2.0.1\n");
    exit(2);
}

$fontDir = __DIR__ . '/fonts';
$F = [
    'k' => new Ttf("$fontDir/Pretendard-Black.ttf"),   // 이름, 큰 숫자
    'b' => new Ttf("$fontDir/Pretendard-Bold.ttf"),    // 제목, 내가 하는 말
    'm' => new Ttf("$fontDir/Pretendard-Medium.ttf"),  // 꼬리표, 칩
    'r' => new Ttf("$fontDir/Pretendard-Regular.ttf"), // 설명
];

const FG = '#eef5f6';      // 글자
const MUTED = '#8ea0aa';   // 보조 글자 (바탕 대비 7:1)
const ACCENT = '#2dd4bf';  // 강조. 지금, 루코, 릴리즈에만 쓴다
const W = 1280;
const PAD = 56;

/** 글줄 하나를 도형으로. */
function t(string $font, string $s, float $size, float $x, float $y, string $fill = FG, float $track = 0, string $anchor = 'start'): string
{
    global $F;
    return '<path fill="' . $fill . '" d="' . $F[$font]->path($s, $size, $x, $y, $track, $anchor) . '"/>';
}

function tw(string $font, string $s, float $size, float $track = 0): float
{
    global $F;
    return $F[$font]->width($s, $size, $track);
}

function n(float $v): string
{
    return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
}

/** 모든 패널이 같이 쓰는 바탕: 같은 색조의 어두운 면, 둥근 모서리, 가는 테두리. */
function panel(int $h, string $title, string $desc, string $css, string $defs, string $body): string
{
    $w = W;
    $bw = $w - 1.5;
    $bh = $h - 1.5;
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $w $h" role="img" aria-labelledby="t d">
<title id="t">$title</title>
<desc id="d">$desc</desc>
<style>$css</style>
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#111f2a"/><stop offset="1" stop-color="#070d13"/></linearGradient>
<clipPath id="clip"><rect width="$w" height="$h" rx="28"/></clipPath>
$defs
</defs>
<g clip-path="url(#clip)">
<rect width="$w" height="$h" fill="url(#bg)"/>
$body
</g>
<rect x=".75" y=".75" width="$bw" height="$bh" rx="27.25" fill="none" stroke="#fff" stroke-opacity=".09" stroke-width="1.5"/>
</svg>

SVG;
}

/** cubic-bezier(x1,0,x2,1) 곡선에서 진행도 p에 닿는 시간의 비율. 선의 머리가 점에 닿는 시각을 구하는 데 쓴다. */
function timeAt(float $p, float $x1, float $x2): float
{
    $lo = 0.0;
    $hi = 1.0;
    for ($i = 0; $i < 40; $i++) {
        $t = ($lo + $hi) / 2;
        if (3 * $t * $t - 2 * $t * $t * $t < $p) {
            $lo = $t;
        } else {
            $hi = $t;
        }
    }
    $u = 1 - $t;
    return 3 * $u * $u * $t * $x1 + 3 * $u * $t * $t * $x2 + $t * $t * $t;
}

// ───────────────────────────────────────────────────────────── 연차
$start = new DateTimeImmutable('2021-10-19');
$years = $start->diff(new DateTimeImmutable('today'))->y + 1; // 시작한 날부터 1년차

// ───────────────────────────────────────────────────────────── 1. 대문
// 주인공은 큰 숫자(지금 몇 년차인가)이고 이름이 그것을 받친다.
// 아래의 선은 경력을 커밋 이력처럼 그린 것이다. 점 하나가 한 해, 마지막 점이 지금.
$H = 700;
$cx = 905.0;          // 큰 숫자와 마지막 점의 가운데
$lineY = 572.0;
$numSize = 500.0;
$numW = tw('k', (string) $years, $numSize, -14);
$numX = $cx - $numW / 2;
$numBase = 520.0;

$css = <<<CSS
.ln{fill:none;stroke-width:3;stroke-linecap:round;stroke-dasharray:1}
.dot,.now,.ring,.hair{transform-box:fill-box;transform-origin:center}
.hair{transform-origin:50% 100%}
.ring{opacity:0}
@keyframes draw{from{stroke-dashoffset:1}}
@keyframes rise{from{opacity:0;transform:translateY(26px)}}
@keyframes pop{from{opacity:0;transform:scale(.2)}}
@keyframes show{from{opacity:0}}
@keyframes grow{from{transform:scaleY(0)}}
@keyframes ring{0%{opacity:0;transform:scale(.75)}28%{opacity:.9}100%{opacity:0;transform:scale(3.4)}}
.a-name{animation:rise 1.1s cubic-bezier(.18,.9,.22,1) both}
.a-say1{animation:rise 1s cubic-bezier(.2,.8,.2,1) .12s both}
.a-say2{animation:rise 1s cubic-bezier(.2,.8,.2,1) .24s both}
.a-num{animation:rise 1.25s cubic-bezier(.2,.8,.2,1) .35s both}
.a-glow{animation:show 1.6s ease-out .35s both}
.a-main{animation:draw 1.3s cubic-bezier(.65,0,.18,1) .2s both}
.now{animation:pop .7s cubic-bezier(.2,.8,.2,1) NOWs both}
.ring{animation:ring 1.6s cubic-bezier(.2,.7,.2,1) NOWs}
.hair{animation:grow .7s cubic-bezier(.2,.8,.2,1) HAIRs both}
.a-branch{animation:draw 1s cubic-bezier(.65,0,.18,1) BRs both}
.a-join{animation:show .7s ease-out JOINs both}
@media (prefers-reduced-motion:reduce){*{animation:none!important}}
CSS;

$dots = '';
$nowT = 0.0;
for ($i = 0; $i < $years; $i++) {
    $x = $years > 1 ? PAD + $i * ($cx - PAD) / ($years - 1) : $cx;
    // 선은 PAD에서 W-PAD까지 그려진다. 머리가 이 점에 닿는 시각에 점이 켜진다.
    $tm = round(0.2 + 1.3 * timeAt(($x - PAD) / (W - 2 * PAD), .65, .18), 2);
    if ($i === $years - 1) {
        $nowT = $tm;
        break;
    }
    $label = ($i + 1) . '년차';
    $lab = $i === 0
        ? t('m', $label, 21, PAD - 8, $lineY + 40, MUTED) . t('r', '2021.10.19 개발 시작', 20, PAD - 8, $lineY + 70, MUTED)
        : t('m', $label, 21, $x, $lineY + 40, MUTED, 0, 'middle');
    $css .= ".y$i .dot{animation:pop .6s cubic-bezier(.2,.8,.2,1) {$tm}s both}.y$i .lab{animation:show .6s ease-out {$tm}s both}";
    $dots .= '<g class="y' . $i . '"><circle class="dot" cx="' . n($x) . '" cy="' . $lineY . '" r="6.5" fill="' . FG . '"/><g class="lab">' . $lab . '</g></g>';
}
$css = strtr($css, ['NOW' => $nowT, 'HAIR' => $nowT + 0.08, 'BR' => $nowT + 0.1, 'JOIN' => $nowT + 0.6]);

$defs = <<<DEFS
<radialGradient id="glow" gradientUnits="userSpaceOnUse" cx="$cx" cy="330" r="430"><stop offset="0" stop-color="#2dd4bf" stop-opacity=".26"/><stop offset=".55" stop-color="#2dd4bf" stop-opacity=".07"/><stop offset="1" stop-color="#2dd4bf" stop-opacity="0"/></radialGradient>
<linearGradient id="num" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7cf0de"/><stop offset="1" stop-color="#14b8a6"/></linearGradient>
<linearGradient id="main" gradientUnits="userSpaceOnUse" x1="56" y1="0" x2="1224" y2="0"><stop offset="0" stop-color="#eef5f6" stop-opacity=".5"/><stop offset=".74" stop-color="#eef5f6" stop-opacity=".5"/><stop offset="1" stop-color="#eef5f6" stop-opacity="0"/></linearGradient>
<linearGradient id="branch" gradientUnits="userSpaceOnUse" x1="925" y1="0" x2="1224" y2="0"><stop offset="0" stop-color="#2dd4bf"/><stop offset=".72" stop-color="#2dd4bf"/><stop offset="1" stop-color="#2dd4bf" stop-opacity="0"/></linearGradient>
<pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse"><circle cx="15" cy="15" r="1.5" fill="#fff"/></pattern>
<linearGradient id="gridFade" x1="0" y1="0" x2="1" y2="0"><stop offset=".42" stop-color="#fff" stop-opacity="0"/><stop offset=".72" stop-color="#fff" stop-opacity=".06"/><stop offset="1" stop-color="#fff" stop-opacity=".06"/></linearGradient>
<mask id="gridMask"><rect width="1280" height="$H" fill="url(#gridFade)"/></mask>
DEFS;

$joinX = 1010.0;
$body = '<rect width="1280" height="' . $H . '" fill="url(#grid)" mask="url(#gridMask)"/>'
    . '<rect class="a-glow" width="1280" height="' . $H . '" fill="url(#glow)"/>'
    . t('m', 'kim089912', 22, PAD, 86, MUTED)
    . t('m', 'with Rooco on Claude Fable 5.2', 22, W - PAD, 86, MUTED, 0, 'end')
    . '<g class="a-name">' . t('k', 'RU RU', 190, PAD - 8, 290, FG, -6) . '</g>'
    . '<g class="a-say1">' . t('b', '기획부터 서버까지 혼자 만듭니다.', 36, PAD, 362, FG, -0.6) . '</g>'
    . '<g class="a-say2">' . t('r', '2026년부터는 비서관 루코와 같이.', 36, PAD, 410, MUTED, -0.6) . '</g>'
    . '<g class="a-num">' . t('k', (string) $years, $numSize, $numX, $numBase, 'url(#num)', -14)
    . t('b', '년차', 64, $numX + $numW + 16, $numBase, FG, -1.5) . '</g>'
    . '<path class="ln a-main" d="M' . PAD . ' ' . $lineY . 'H' . (W - PAD) . '" pathLength="1" stroke="url(#main)"/>'
    . $dots
    . '<path class="hair" d="M' . $cx . ' ' . ($lineY - 18) . 'V' . ($numBase + 14) . '" stroke="' . ACCENT . '" stroke-opacity=".6" stroke-width="2"/>'
    . '<circle class="ring" cx="' . $cx . '" cy="' . $lineY . '" r="12" fill="none" stroke="' . ACCENT . '" stroke-width="2"/>'
    . '<circle class="now" cx="' . $cx . '" cy="' . $lineY . '" r="12" fill="' . ACCENT . '"/>'
    . '<path class="ln a-branch" d="M' . ($cx + 20) . ' ' . $lineY . 'C' . ($cx + 52) . ' ' . $lineY . ' ' . ($cx + 52) . ' ' . ($lineY + 64) . ' ' . ($cx + 84) . ' ' . ($lineY + 64) . 'H' . (W - PAD) . '" pathLength="1" stroke="url(#branch)"/>'
    . '<g class="a-join"><circle cx="' . $joinX . '" cy="' . ($lineY + 64) . '" r="6.5" fill="' . ACCENT . '"/>'
    . t('m', '2026.11 루코 합류', 21, $joinX - 6, $lineY + 46, FG) . '</g>';

file_put_contents(__DIR__ . '/../assets/hero.svg', panel(
    $H,
    "RU RU, {$years}년차 개발자",
    "kim089912. 2021년 10월 19일에 개발을 시작해 지금 {$years}년차다. 기획부터 서버까지 혼자 만든다. 2026년 11월부터 비서관 루코(Claude Fable 5.2)와 같이 일한다.",
    $css,
    $defs,
    $body
));

// ───────────────────────────────────────────────────────────── 2. 일하는 방식
// 주인공은 가운데의 그래프다. 말풍선은 내가 하는 말, 나머지는 루코가 하는 일.
$H = 640;
$mainY = 300.0;
$workY = 420.0;

/** 내가 하는 말: 테두리가 있는 말풍선. 가운데 좌표를 받는다. */
function said(string $s, float $cx, float $cy): string
{
    $w = tw('b', $s, 24) + 40;
    return '<rect x="' . n($cx - $w / 2) . '" y="' . ($cy - 23) . '" width="' . n($w) . '" height="46" rx="23" fill="#fff" fill-opacity=".06" stroke="#fff" stroke-opacity=".28" stroke-width="1.5"/>'
        . t('b', $s, 24, $cx, $cy + 8.5, FG, -0.3, 'middle');
}

$defs = '<linearGradient id="main" gradientUnits="userSpaceOnUse" x1="56" y1="0" x2="1224" y2="0"><stop offset="0" stop-color="#eef5f6" stop-opacity=".5"/><stop offset=".9" stop-color="#eef5f6" stop-opacity=".5"/><stop offset="1" stop-color="#eef5f6" stop-opacity="0"/></linearGradient>';

$rules = ['고치기 전에 브랜치부터', '테스트 없이는 합치지 않는다', '명령은 실행 전에 말한다', '합칠 때마다 태그와 릴리즈'];
$rulesSvg = '';
foreach ($rules as $i => $rule) {
    $x = PAD + $i * 292;
    $rulesSvg .= '<circle cx="' . ($x + 5) . '" cy="589" r="4.5" fill="' . ACCENT . '"/>' . t('m', $rule, 21, $x + 22, 596, FG, -0.2);
}

$dot = static fn(float $x, float $y): string => '<circle cx="' . n($x) . '" cy="' . n($y) . '" r="6.5" fill="' . FG . '"/>';

$body = t('b', '나는 말하고 결정한다.', 44, PAD, 112, FG, -1)
    . t('b', '나머지는 루코가 한다.', 44, PAD, 168, MUTED, -1)
    . t('r', '루코는 Claude Code에서 일하는 비서관이다.', 23, W - PAD, 100, MUTED, -0.2, 'end')
    . t('r', 'Claude Fable 5.2 위에서 돌아가고,', 23, W - PAD, 134, MUTED, -0.2, 'end')
    . t('r', '이름과 말투와 일하는 기준은 내가 정했다.', 23, W - PAD, 168, MUTED, -0.2, 'end')
    // 선
    . '<path d="M' . PAD . ' ' . $mainY . 'H' . (W - PAD) . '" fill="none" stroke="url(#main)" stroke-width="3" stroke-linecap="round"/>'
    . '<path d="M200 ' . $mainY . 'C255 ' . $mainY . ' 255 ' . $workY . ' 310 ' . $workY . 'H960C1015 ' . $workY . ' 1015 ' . $mainY . ' 1070 ' . $mainY . '" fill="none" stroke="' . FG . '" stroke-opacity=".5" stroke-width="3" stroke-linecap="round"/>'
    . t('m', 'main', 20, PAD, $mainY - 16, MUTED)
    . t('m', 'work/' . $VERSION, 20, 326, $workY - 16, MUTED)
    // 위: 내가 하는 말 두 마디와 그때 루코가 하는 일
    . said('“고쳐 줘”', 200, 226) . t('r', '브랜치를 만든다', 20, 200, 276, MUTED, 0, 'middle')
    . said('“릴리즈해”', 1070, 226) . t('r', '합치고 태그를 붙여 릴리즈', 20, 1070, 276, MUTED, 0, 'middle')
    . $dot(200, $mainY)
    . '<circle cx="1070" cy="' . $mainY . '" r="12" fill="' . ACCENT . '"/>'
    . t('m', $VERSION, 20, 1092, $mainY + 34, ACCENT)
    // 아래: 브랜치에서 일어나는 일
    . $dot(440, $workY) . $dot(500, $workY) . $dot(560, $workY)
    . t('b', '커밋', 24, 500, 478, FG, -0.3, 'middle') . t('r', '답변마다 하나', 20, 500, 514, MUTED, 0, 'middle')
    . $dot(730, $workY)
    . said('“업데이트해”', 730, 470) . t('r', '깃허브로 올린다', 20, 730, 514, MUTED, 0, 'middle')
    . $dot(900, $workY)
    . t('b', '테스트', 24, 900, 478, FG, -0.3, 'middle') . t('r', '통과해야 합친다', 20, 900, 514, MUTED, 0, 'middle')
    // 지키는 것 네 가지
    . '<path d="M' . PAD . ' 548H' . (W - PAD) . '" stroke="#fff" stroke-opacity=".08" stroke-width="1.5"/>'
    . $rulesSvg;

file_put_contents(__DIR__ . '/../assets/flow.svg', panel(
    $H,
    '루코와 일하는 방식',
    '나는 말하고 결정한다. 나머지는 루코가 한다. 내가 고쳐 달라고 하면 루코가 작업 브랜치를 만들고 답변마다 커밋한다. 업데이트하라고 하면 브랜치를 깃허브에 올려 테스트하고, 릴리즈하라고 하면 main에 합쳐 태그를 붙이고 릴리즈한다. 루코는 Claude Code에서 일하는 비서관이고 Claude Fable 5.2 위에서 돌아간다.',
    '',
    $defs,
    $body
));

// ───────────────────────────────────────────────────────────── 3. 쓰는 것
$groups = [
    '언어' => ['PHP', 'Java', 'Python', 'JavaScript', 'HTML', 'CSS', 'SQL', 'Shell'],
    '프레임워크' => ['Laravel', 'Spring', 'jQuery', 'Blade'],
    '데이터베이스' => ['MariaDB', 'MySQL', 'Oracle'],
    '서버' => ['Linux', 'Rocky Linux', 'Apache', 'PHP-FPM', 'Supervisor', 'Windows'],
    '클라우드' => ['NAVER Cloud Platform', 'Object Storage'],
    '연동' => ['알리고 문자', '알리고 알림톡'],
    '도구' => ['Git', 'GitHub', 'GitHub CLI', 'Docker', 'Anaconda', 'Composer'],
    'AI' => ['Claude Code', 'Claude Fable 5.2', 'Rooco'],
];
$rowH = 60;
$top = 156;
$H = $top + count($groups) * $rowH + 86;

$body = t('b', '쓰는 것', 44, PAD, 112, FG, -1);
$row = 0;
$descParts = [];
foreach ($groups as $name => $items) {
    $y = $top + $row * $rowH;
    $isAi = $name === 'AI';
    $body .= '<path d="M' . PAD . ' ' . $y . 'H' . (W - PAD) . '" stroke="#fff" stroke-opacity=".07" stroke-width="1.5"/>'
        . t('m', $name, 21, PAD, $y + 37, $isAi ? ACCENT : MUTED);
    $x = 236.0;
    foreach ($items as $item) {
        $cw = tw('m', $item, 22, -0.2) + 36;
        $body .= '<rect x="' . n($x) . '" y="' . ($y + 9) . '" width="' . n($cw) . '" height="42" rx="21" '
            . ($isAi
                ? 'fill="' . ACCENT . '" fill-opacity=".12" stroke="' . ACCENT . '" stroke-opacity=".6"'
                : 'fill="#fff" fill-opacity=".045" stroke="#fff" stroke-opacity=".13"')
            . ' stroke-width="1.5"/>' . t('m', $item, 22, $x + 18, $y + 37.5, $isAi ? '#b6f7ec' : FG, -0.2);
        $x += $cw + 10;
    }
    $descParts[] = $name . ': ' . implode(', ', $items);
    $row++;
}
$yEnd = $top + $row * $rowH;
$body .= '<path d="M' . PAD . ' ' . $yEnd . 'H' . (W - PAD) . '" stroke="#fff" stroke-opacity=".07" stroke-width="1.5"/>'
    . t('r', '이 프로필은 루코가 작업 브랜치에서 만들고 ' . $VERSION . '으로 릴리즈했다.', 20, PAD, $yEnd + 48, MUTED);

file_put_contents(__DIR__ . '/../assets/stack.svg', panel($H, '쓰는 것', implode('. ', $descParts) . '.', '', '', $body));

foreach (['hero', 'flow', 'stack'] as $name) {
    printf("assets/%s.svg  %s bytes\n", $name, number_format(filesize(__DIR__ . "/../assets/$name.svg")));
}
printf("연차: %d년차, 버전: %s\n", $years, $VERSION);
