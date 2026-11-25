# 프로필 그림 만들기

`assets/`의 그림 세 장은 손으로 고치지 않고 `tools/build.php`로 만든다.

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
php tools/build.php v2.0.1
```

인자는 이번에 릴리즈할 버전이다. 그림 안의 버전 표시에 쓰인다.

## 바꿀 때

| 바꿀 것 | 고칠 곳 |
| --- | --- |
| 기술 목록 | `build.php`의 `$groups` |
| 문구 | `build.php`에서 그 문장을 찾아 고친다 |
| 연차 | 고칠 필요 없다. 2021-10-19를 1년차의 첫날로 세어 만든 날 기준으로 계산한다. 다만 해가 바뀌는 10월 19일 뒤에 다시 만들어야 그림에 반영된다 |
| 색 | `build.php` 맨 위의 `FG`, `MUTED`, `ACCENT`와 `panel()`의 바탕색 |

글자를 고쳤으면 `README.md`의 `alt` 문구도 같은 내용으로 맞춘다. 그림을 못 보는 사람은 그 글을 읽는다.

## 파일

| 파일 | 하는 일 |
| --- | --- |
| `ttf.php` | TrueType 글꼴에서 글자의 윤곽선과 폭을 읽어 SVG 경로로 바꾼다 |
| `build.php` | 패널 세 장의 배치를 정하고 `assets/`에 쓴다 |
