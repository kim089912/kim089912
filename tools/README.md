# 프로필 그림 만들기

`assets/`의 그림은 손으로 고치지 않고 `tools/build.php`로 만든다. 대문, 일하는 방식, 만든 것(머리 패널과 화면 타일 열 장), 루코, 쓰는 것이다.

## 왜 도구가 필요한가

프로필의 그림은 `<img>`로 들어가서 웹 글꼴을 불러오지 못한다. 글자를 그대로 두면 보는 사람 PC의 기본 글꼴로 나온다. 그래서 Pretendard 글꼴의 글자 모양을 도형으로 바꿔 그림에 넣는다. 어느 PC에서나 같은 모양으로 보인다.

그 대신 그림 속 글자를 고치려면 다시 만들어야 한다.

## 준비

PHP 8.1 이상이 있어야 한다. 추가 확장은 필요 없다.

글꼴 파일은 저장소에 올리지 않는다(`.gitignore`). `tools/fonts/`에 아래 네 개를 둔다.

- `Pretendard-Black.ttf`
- `Pretendard-Bold.ttf`
- `Pretendard-Medium.ttf`
- `Pretendard-Regular.ttf`

받는 곳은 Pretendard 공식 저장소(https://github.com/orioncactus/pretendard)의 `packages/pretendard/dist/public/static/alternative/`다. 라이선스는 SIL Open Font License 1.1이다.

## 만들기

```
php tools/build.php v2.0.2
```

인자는 이번에 릴리즈할 버전이다. 그림 안의 버전 표시에 쓰인다.

## 갤러리의 화면

타일에 들어가는 화면은 `tools/shots/`의 JPEG다. `build.php`가 그 파일을 그림 안에 담는다(`<img>`로 들어간 그림은 밖의 파일을 불러오지 못한다).

화면을 새로 찍었으면 PNG를 한 폴더에 모아 아래처럼 줄인다. 파일 이름이 타일의 이름이 된다(`byplus.png` → `shots/byplus.jpg` → `assets/work-byplus.svg`).

```
powershell -ExecutionPolicy Bypass -File tools\make-thumbs.ps1 <PNG가 든 폴더> [루코 그림 경로]
```

- 가로로 긴 화면은 폭 1000, 세로로 긴 화면(휴대폰)은 폭 420으로 줄인다. 세로로 긴 화면은 타일에서 휴대폰 모양 안에 들어간다.
- 로그인이 필요한 시스템은 로그인 화면만 찍는다. 이 저장소는 공개라서 로그인 뒤의 화면이나 사람의 정보가 보이는 화면은 넣지 않는다.
- 타일 두 장은 `README.md`에서 `width="50%"`로 빈칸 없이 붙여 놓는다. 사이 간격은 그림 안에 들어 있다(`TILE_GAP`). 두 `<img>` 사이에 빈칸이나 줄바꿈을 넣으면 둘째 장이 다음 줄로 넘어간다.

## 바꿀 때

| 바꿀 것 | 고칠 곳 |
| --- | --- |
| 기술 목록 | `build.php`의 `$groups` |
| 갤러리의 타일(이름, 설명, 주소, 순서) | `build.php`의 `$works`. 주소를 `null`로 두면 로그인 화면으로 표시한다. 누르면 가는 주소는 `README.md`의 `<a href>`에 따로 적는다 |
| 문구 | `build.php`에서 그 문장을 찾아 고친다 |
| 연차 | 고칠 필요 없다. 2021-10-19를 1년차의 첫날로 세어 만든 날 기준으로 계산한다. 다만 해가 바뀌는 10월 19일 뒤에 다시 만들어야 그림에 반영된다 |
| 색 | `build.php` 맨 위의 `FG`, `MUTED`, `ACCENT`와 `panel()`의 바탕색 |

글자를 고쳤으면 `README.md`의 `alt` 문구도 같은 내용으로 맞춘다. 그림을 못 보는 사람은 그 글을 읽는다.

## 파일

| 파일 | 하는 일 |
| --- | --- |
| `ttf.php` | TrueType 글꼴에서 글자의 윤곽선과 폭을 읽어 SVG 경로로 바꾼다 |
| `build.php` | 패널과 타일의 배치를 정하고 `assets/`에 쓴다 |
| `make-thumbs.ps1` | 찍은 화면(PNG)을 갤러리에 넣을 크기의 JPEG로 줄여 `shots/`에 쓴다 |
| `shots/` | 갤러리에 들어가는 화면과 루코 그림 |
