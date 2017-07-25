# Korbit-api-php
https://www.korbit.co.kr/ 의 PHP용 API입니다.

## 사용법
1. API 파일안의 $key와 $secret값을 여러분들의 값으로 바꿔주세요.
2. require_once '파일경로/Korbit.php'; 를 작성하여, require 해주세요.
3. https://apidocs.korbit.co.kr/ko/ 에 보시면, 토큰이 필요한 부분이 있습니다.
4. $korbit = new Korbit();<br />
$korbit->get_access_token(array('username'=>'로그인시 필요한 이메일','password'=>'비밀번호'));<br />
class 내부의 get_access_token 함수를 통해서 토큰을 발행해주세요.
5. print_r($korbit->get_user_info());<br />
토큰을 발행받았다면, class내부의 함수를 사용하여, 코딩을 합니다.

## Bitcoin Address
19XnC1LiFsUGZqUJLj5Uj4a3ihkBMGcf7y
