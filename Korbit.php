<?php
// defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Korbit
 *
 * @url     https://www.korbit.co.kr/
 * @url     https://apidocs.korbit.co.kr/ko/
 *
 * @author      KwangSeon Yun   <middleyks@hanmail.net>
 * @copyright   KwangSeon Yun
 * @license     https://raw.githubusercontent.com/yks118/Korbit-api-php/master/LICENSE     MIT License
 * @link        https://github.com/yks118/Korbit-api-php
 */
class Korbit {
	private $key = '여러분들의 API KEY';
	private $secret = '여러분들의 API SECRET';
	private $url = 'https://api.korbit.co.kr/';

	private $access_token = array();

	public function __construct() {
		if (isset($_SESSION['kobit_access_token'])) {
			if ($_SESSION['kobit_access_token']['expires_time'] <= time()) {
				// 세션 시간이 만료라면 세션 삭제
				unset($_SESSION['kobit_access_token']);
			} else {
				$this->access_token = $_SESSION['kobit_access_token'];
			}
		}
	}

	public function __destruct() {}

	/**
	 * _get
	 *
	 * @param   string      $url
	 * @param   array       $data
	 *
	 * @return  array       $response
	 */
	private function _get ($url,$data = array()) {
		$parameters = array();

		// set authorization bearer
		if (isset($this->access_token['token_type'])) {
			$authorization = 'Authorization: '.$this->access_token['token_type'].' '.$this->access_token['access_token'];
			$parameters[CURLOPT_HTTPHEADER] = array('Content-Type: application/json',$authorization);
		}

		$url = $this->url.$url;
		if (count($data)) {
			$url .= '?'.http_build_query($data);
		}

		$response = $this->get_content_curl($url,$parameters);
		return $response;
	}

	/**
	 * _post
	 *
	 * @param   string      $url
	 * @param   array       $data
	 *
	 * @return  array       $response
	 */
	private function _post ($url,$data = array()) {
		$parameters = array();
		$parameters[CURLOPT_POST] = 1;

		// set authorization bearer
		if (isset($this->access_token['token_type'])) {
			$authorization = 'Authorization: '.$this->access_token['token_type'].' '.$this->access_token['access_token'];
			$parameters[CURLOPT_HTTPHEADER] = array('Content-Type: application/json',$authorization);
		}

		if (count($data)) {
			$parameters[CURLOPT_POSTFIELDS] = http_build_query($data);
		}

		$url = $this->url.$url;
		$response = $this->get_content_curl($url,$parameters);
		return $response;
	}

	/**
	 * get_content_curl
	 *
	 * @param   string      $url
	 * @param   array       $parameters
	 *
	 * @return  array       $data
	 */
	protected function get_content_curl ($url,$parameters = array()) {
		$data = array();

		// set CURLOPT_USERAGENT
		if (!isset($parameters[CURLOPT_USERAGENT])) {
			if (isset($_SERVER['HTTP_USER_AGENT'])) {
				$parameters[CURLOPT_USERAGENT] = $_SERVER['HTTP_USER_AGENT'];
			} else {
				// default IE11
				$parameters[CURLOPT_USERAGENT] = 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko';
			}
		}

		// check curl_init
		if (function_exists('curl_init')) {
			$ch = curl_init();

			// url 설정
			curl_setopt($ch,CURLOPT_URL,$url);

			foreach ($parameters as $key => $value) {
				curl_setopt($ch,$key,$value);
			}

			// https
			if (!isset($parameters[CURLOPT_SSL_VERIFYPEER])) {
				curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
			}
			if (!isset($parameters[CURLOPT_SSLVERSION])) {
				curl_setopt($ch,CURLOPT_SSLVERSION,6);
			}

			// no header
			if (!isset($parameters[CURLOPT_HEADER])) {
				curl_setopt($ch,CURLOPT_HEADER,0);
			}

			// POST / GET (default : GET)
			if (!isset($parameters[CURLOPT_POST]) && !isset($parameters[CURLOPT_CUSTOMREQUEST])) {
				curl_setopt($ch,CURLOPT_POST,0);
			}

			// response get php value
			if (!isset($parameters[CURLOPT_RETURNTRANSFER])) {
				curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
			}

			// HTTP2
			if (!isset($parameters[CURLOPT_HTTP_VERSION])) {
				curl_setopt($ch,CURLOPT_HTTP_VERSION,3);
			}
			if (!isset($parameters[CURLINFO_HEADER_OUT])) {
				curl_setopt($ch,CURLINFO_HEADER_OUT,TRUE);
			}

			$data['html'] = json_decode(curl_exec($ch),true);
			$data['response'] = curl_getinfo($ch);

			curl_close($ch);
		}

		return $data;
	}

	/**
	 * get_access_token
	 *
	 * 인증
	 *
	 * @param   array       $param
	 *          string      $param['username']      E-Mail
	 *          string      $param['password']      password
	 *
	 * @return  string      $token
	 */
	public function get_access_token ($param) {
		$token = '';
		$time = time();
		$response = array();

		if (isset($this->access_token['access_token']) && $this->access_token['expires_time'] > $time) {
			if ($this->access_token['expires_time'] > ($time + 60)) {
				$token = $this->access_token['access_token'];
			} else {
				$param['client_id'] = $this->key;
				$param['client_secret'] = $this->secret;
				$param['refresh_token'] = $this->access_token['refresh_token'];
				$param['grant_type'] = 'refresh_token';

				unset($_SESSION['kobit_access_token']);
				$response = $this->_post('v1/oauth2/access_token',$param);
			}
		} else {
			$param['client_id'] = $this->key;
			$param['client_secret'] = $this->secret;
			$param['grant_type'] = 'password';

			unset($_SESSION['kobit_access_token']);
			$response = $this->_post('v1/oauth2/access_token',$param);
		}

		if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
			$data = $response['html'];

			if (isset($data['access_token'])) {
				$token = $data['access_token'];
				$data['expires_time'] = $time + $data['expires_in'];
				$this->access_token = $_SESSION['kobit_access_token'] = $data;
			}
		}

		return $token;
	}

	/**
	 * get_user_info
	 *
	 * 사용자 정보 가져오기
	 *
	 * @return  array       $data
	 *          string      $data['email']                              사용자의 이메일 주소.
	 *          string      $data['nameCheckedAt']                      본인인증 완료시각. 이 필드가 없으면 아직 본인인증되지 않은 사용자이다.
	 *          string      $data['name']                               사용자의 본인 인증 된 이름
	 *          string      $data['phone']                              본인 인증시 사용한 휴대전화
	 *          string      $data['birthday']                           본인 인증 결과로 받은 생년월일
	 *          string      $data['gender']                             본인 인증 결과로 받은 셩별. m:남성, f:여성
	 *          int         $data['maxCoinOutPerDay']                   하루에 coin을 전송할 수 있는 최대량
	 *          int         $data['maxFiatInPerDay']                    하루 최대 가능한 원화 입금액
	 *          int         $data['maxFiatOutPerDay']                   하루 최대 가능한 원화 출금액
	 *          int         $data['prefs']['coinOutMfaThreshold']       초과시 이중 인증을 요구하는 BTC 출금 일일 누적량. 0이면 항상 요구
	 *          bool        $data['prefs']['notifyDepositWithdrawal']   KRW, BTC의 입출금 내역 알림 받기 여부
	 *          bool        $data['prefs']['notifyTrades']              체결 내역 알림 받기 여부
	 *          bool        $data['prefs']['verifyMfaOnLogin']          로그인 시 이중 인증 요구 여부
	 *          int         $data['userLevel']                          유저 등급
	 *          int         $data['coinOutToday']                       금일 출금 금액
	 *          int         $data['coinOutWithin24h']                   24시간 출금 금액
	 */
	public function get_user_info () {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_get('v1/user/info');
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * get_ticker
	 *
	 * 최종 체결 가격
	 *
	 * @param   array       $param
	 *          string      $param['currecy_pair']      btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *
	 * @return  array       $data
	 *          int         $data['timestamp']          최종 체결 시각.
	 *          int         $data['last']               최종 체결 가격.
	 */
	public function get_ticker ($param) {
		$data = array();

		$response = $this->_get('v1/ticker',$param);
		if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
			$data = $response['html'];
		}

		return $data;
	}

	/**
	 * get_ticker_detailed
	 *
	 * 시장 현황 상세정보
	 *
	 * @param   array       $param
	 *          string      $param['currecy_pair']      btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *
	 * @return  array       $data
	 *          int         $data['timestamp']          최종 체결 시각.
	 *          int         $data['last']               최종 체결 가격.
	 *          int         $data['bid']                최우선 매수호가. 매수 주문 중 가장 높은 가격.
	 *          int         $data['ask']                최우선 매도호가. 매도 주문 중 가장 낮은 가격.
	 *          int         $data['low']                (최근 24시간) 저가. 최근 24시간 동안의 체결 가격 중 가장 낮 가격.
	 *          int         $data['high']               (최근 24시간) 고가. 최근 24시간 동안의 체결 가격 중 가장 높은 가격.
	 *          int         $data['volume']             거래량.
	 */
	public function get_ticker_detailed ($param) {
		$data = array();

		$response = $this->_get('v1/ticker/detailed',$param);
		if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
			$data = $response['html'];
		}

		return $data;
	}

	/**
	 * get_orderbook
	 *
	 * 매수/매도 호가
	 *
	 * @param   array       $param
	 *          string      $param['currecy_pair']      btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *
	 * @return  array       $data
	 *          int         $data['timestamp']          가장 마지막으로 유입된 호가의 주문 유입시각.
	 *          array       $data['asks']               [가격, 미체결잔량]으로 구성된 개별 호가를 나열한다. 3번째 값은 더이상 지원하지 않고 항상 “1"로 세팅된다.
	 *          array       $data['bids']               [가격, 미체결잔량]으로 구성된 개별 호가를 나열한다. 3번째 값은 더이상 지원하지 않고 항상 "1"로 세팅된다.
	 */
	public function get_orderbook ($param) {
		$data = array();

		$response = $this->_get('v1/orderbook',$param);
		if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
			$data = $response['html'];
		}

		return $data;
	}

	/**
	 * get_transactions
	 *
	 * 체결 내역
	 *
	 * @param   array       $param
	 *          string      $param['currecy_pair']      btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *          string      $param['time']              minute 인 경우 최근 1분, hour 인 경우 최근 1시간, day 인 경우는 최근 1일의 체결 데이터를 요청.
	 *
	 * @return  array       $data
	 *          int         $data[]['timestamp']        체결 시각.
	 *          int         $data[]['tid']              체결 일련 번호.
	 *          int         $data[]['price']            체결 가격.
	 *          int         $data[]['amount']           체결 수량.
	 */
	public function get_transactions ($param) {
		$data = array();

		$response = $this->_get('v1/transactions',$param);
		if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
			$data = $response['html'];
		}

		return $data;
	}

	/**
	 * get_constants
	 *
	 * 각종 제약조건
	 *
	 * @return  array       $data
	 *          int         $data['krwWithdrawalFee']       원화 환급 수수료 ( 1,000 KRW )
	 *          int         $data['maxKrwWithdrawal']       원화 하루 최대 출금 가능액( 10,000,000 KRW )
	 *          int         $data['minKrwWithdrawal']       원화 최소 출금가능액( 1,000 KRW )
	 *          int         $data['btcTickSize']            BTC 호가 단위( 500 KRW )
	 *          int         $data['btcWithdrawalFee']       비트코인 전산망 수수료 ( 0.0005 BTC )
	 *          int         $data['maxBtcOrder']            비트코인 매수/매도 최대 입력값 ( 100 BTC )
	 *          int         $data['maxBtcPrice']            주문가 (1BTC가격) 최대 입력값 ( 100,000,000 KRW )
	 *          int         $data['minBtcOrder']            비트코인 매수/매도 수량 최소 입력값 ( 0.01 BTC )
	 *          int         $data['minBtcPrice']            주문가 (1BTC가격) 최소 입력값 ( 1,000 KRW )
	 *          int         $data['maxBtcWithdrawal']       비트코인 출금 최대입력값 ( 3 BTC )
	 *          int         $data['minBtcWithdrawal']       비트코인 출금 최소 입력값 ( 0.0001 BTC )
	 *          int         $data['etcTickSize']            이더리움 클래식 호가 단위( 10 KRW )
	 *          int         $data['maxEtcOrder']            이더리움 클래식 매수/매도 최대 입력값 ( 5,000 ETC )
	 *          int         $data['maxEtcPrice']            주문가 (1ETC가격) 최대 입력값 ( 100,000,000 KRW )
	 *          int         $data['minEtcOrder']            이더리움 클래식 매수/매도 수량 최소 입력값 ( 0.1 ETC )
	 *          int         $data['minEtcPrice']            주문가 (1ETC가격) 최소 입력값 ( 100 KRW )
	 *          int         $data['ethTickSize']            이더리움 호가 단위( 50 KRW )
	 *          int         $data['maxEthOrder']            이더리움 매수/매도 최대 입력값 ( 20,000 ETH )
	 *          int         $data['maxEthPrice']            주문가 (1ETH가격) 최대 입력값 ( 100,000,000 KRW )
	 *          int         $data['minEthOrder']            이더리움 매수/매도 수량 최소 입력값 ( 0.5 ETH )
	 *          int         $data['minEthPrice']            주문가 (1ETH가격) 최소 입력값 ( 1,000 KRW )
	 *          int         $data['minTradableLevel']       2 등급
	 */
	public function get_constants () {
		$data = array();

		$response = $this->_get('v1/constants');
		if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
			$data = $response['html'];
		}

		return $data;
	}

	/**
	 * set_user_orders_buy
	 *
	 * 사용자 : 매수 주문
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']     btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *          string      $param['type']              주문 형태. "limit” : 지정가 주문, “market” : 시장가 주문.
	 *          int         $param['price']             비트코인의 가격(원화). 500원 단위로만 가능하다. 지정가 주문(type=limit)인 경우에만 유효하다.
	 *                                                  현재 베타 서비스로 ETH는 50원 단위, ETC는 10원 단위로 가격을 설정할 수 있다.
	 *          int         $param['coin_amount']       매수하고자 하는 코인의 수량.
	 *                                                  정가 주문인 경우에는 해당 수량을 price 파라미터에 지정한 가격으로 구매하는 주문을 생성한다.
	 *                                                  시장가 주문인 경우에는 해당 수량을 시장가에 구매하는 주문을 생성하며, price 파라미터와 fiat_amount 파라미터는 사용되지 않는다.
	 *          int         $param['fiat_amount']       코인을 구매하는데 사용하고자 하는 금액(원화).
	 *                                                  시장가 주문(type=market)인 경우에만 유효하며, 이 파라미터를 사용할 경우 price 파라미터와 coin_amount 파라미터는 사용할 수 없다.
	 *
	 * @return  array       $data
	 *          int         $data['orderId']            접수된 주문 ID
	 *          string      $data['status']             성공이면 “success”, 실패할 경우 에러 심블이 세팅된다.
	 *                                                  name_unchecked      본인인증을 하지 않은 사용자가 주문을 넣은 경우. (주문은 본인 인증 한 사용자만 넣을 수 있다.)
	 *                                                  under_age           19세 미만 사용자가 매수주문을 하는 경우.
	 *                                                  not_enough_krw      KRW 잔고가 부족하여 매수주문을 넣을 수 없는 경우.
	 *                                                  too_many_orders     사용자 당 최대 주문 건수를 초과한 경우.
	 *                                                  save_failure        기타 다른 이유로 주문이 들어가지 않은 경우. 일반적으로 발생하지 않음.
	 *          string      $data['currency_pair']      해당 주문에 사용된 거래 통화
	 */
	public function set_user_orders_buy ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_post('v1/user/orders/buy',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * set_user_orders_sell
	 *
	 * 사용자 : 매도 주문
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']     btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *          string      $param['type']              주문 형태. "limit” : 지정가 주문, “market” : 시장가 주문.
	 *          int         $param['price']             비트코인의 가격(원화). 500원 단위로만 가능하다. 지정가 주문(type=limit)인 경우에만 유효하다.
	 *                                                  현재 베타 서비스로 ETH는 50원 단위, ETC는 10원 단위로 가격을 설정할 수 있다.
	 *          int         $param['coin_amount']       매수하고자 하는 코인의 수량.
	 *                                                  정가 주문인 경우에는 해당 수량을 price 파라미터에 지정한 가격으로 구매하는 주문을 생성한다.
	 *                                                  시장가 주문인 경우에는 해당 수량을 시장가에 구매하는 주문을 생성하며, price 파라미터와 fiat_amount 파라미터는 사용되지 않는다.
	 *
	 * @return  array       $data
	 *          int         $data['orderId']            접수된 주문 ID
	 *          string      $data['status']             성공이면 “success”, 실패할 경우 에러 심블이 세팅된다.
	 *                                                  name_unchecked      본인인증을 하지 않은 사용자가 주문을 넣은 경우. (주문은 본인 인증 한 사용자만 넣을 수 있다.)
	 *                                                  under_age           19세 미만 사용자가 매수주문을 하는 경우.
	 *                                                  not_enough_btc      BTC 잔고가 부족하여 매도주문을 넣을 수 없는 경우.
	 *                                                  too_many_orders     사용자 당 최대 주문 건수를 초과한 경우.
	 *                                                  save_failure        기타 다른 이유로 주문이 들어가지 않은 경우. 일반적으로 발생하지 않음.
	 *          string      $data['currency_pair']      해당 주문에 사용된 거래 통화
	 */
	public function set_user_orders_sell ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_post('v1/user/orders/sell',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * set_user_orders_cancel
	 *
	 * 사용자 : 주문 취소
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']     btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *          int         $param['id']                취소할 주문의 ID.
	 *                                                  여러 건의 주문을 한 번에 취소할 수 있도록 id가 여러 번 올 수 있으며,
	 *                                                  v1/user/orders/open의 응답에 들어있는 id 필드의 값이나,
	 *                                                  v1/user/orders/buy 혹은 v1/user/orders/sell의 결과로 받은 orderId를 사용할 수 있다.
	 *
	 * @return  array       $data
	 *          int         $data[]['orderId']          id 파라미터로 넘긴 주문 일련번호.
	 *          string      $data[]['status']           성공이면 “success”, 실패할 경우 에러 심블이 세팅된다.
	 *                                                  under_age           19세 미만 사용자가 주문 취소를 하는 경우.
	 *                                                  not_found           해당 주문이 존재하지 않는 경우. 잘못된 주문 일련번호를 지정하면 이 에러가 발생한다.
	 *                                                  not_authorized      다른 사용자의 주문을 취소하려고 한 경우.
	 *                                                  already_filled      취소되기 전에 주문 수량 모두 체결된 경우.
	 *                                                  partially_filled    체결되지 않은 주문에 대해 주문 취소하였으나, 도중에 부분 체결된 경우.
	 *                                                  already_canceled    이미 취소된 주문인 경우.
	 */
	public function set_user_orders_cancel ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_post('v1/user/orders/cancel',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * get_user_orders_open
	 *
	 * 사용자 : 미 체결 주문내역
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']     btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *          int         $param['offset']            전체 데이터 중 offset(0부터 시작) 번 째 데이터부터 limit개를 가져오도록 지정 가능하다.
	 *                                                  offset의 기본값은 0이며, limit의 기본값은 10이다.
	 *          int         $param['limit']
	 *
	 * @return  array       $data
	 *          int         $data[]['timestamp']        주문 유입 시각
	 *          int         $data[]['id']               주문 일련번호
	 *          string      $data[]['type']             주문 종류. “bid"는 매수주문, "ask"은 매도주문
	 *          int         $data[]['price']            주문가격. price.value로 주문 가격이 들어온다.
	 *                                                  이후 원화 이외의 통화로 거래하도록 허용할 경우에 대비하여 currency 구분을 두도록 하였으나, 지금은 항상 krw로 세팅된다.
	 *          int         $data[]['total']            주문한 BTC 수량. 이 필드 아래에 currency와 value 필드가 온다.
	 *                                                  currency는 항상 ‘btc'로 들어오며, value에는 주문한 BTC 수량이 들어온다.
	 *          int         $data[]['open']             주문한 BTC 수량 중 아직 체결되지 않은 수량.
	 *                                                  이 필드 아래에 currency와 value 필드가 온다.
	 *                                                  currency는 항상 'btc'로 들어오며, value에는 아직 체결되지 않은 BTC 수량.
	 */
	public function get_user_orders_open ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_get('v1/user/orders/open',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * get_user_transactions
	 *
	 * 사용자 : 체결 / KRW입출금 / BTC입출금내역
	 *
	 * @url     https://apidocs.korbit.co.kr/ko/#사용자-:-체결-/-krw입출금-/-btc입출금내역
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']     btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *          string      $param['category']          fills(체결), fiats(KRW 입출금), coins(BTC 입출금)중 하나를 지정할 수 있으며 다른 카테고리를 여러 번 반복하는 것이 가능하다.
	 *                                                  예를 들어, category=fiats&category=coins 와 같은 조합으로 요청하는 것이 가능하다.
	 *                                                  기본 값은 세 가지를 모두 받도록 한다.
	 *          int         $param['offset']            전체 데이터 중 offset(0부터 시작) 번 째 데이터부터 limit개를 가져오도록 지정 가능하다.
	 *                                                  offset의 기본값은 0이며, limit의 기본값은 10이다.
	 *          int         $param['limit']
	 *          int         $param['order_id']          category가 fills일 때만 유효하며, 특정 주문에 대한 체결을 가져올 때 사용한다.
	 *                                                  여러 건의 주문에 대한 체결을 여러 건 조회하는 것이 가능하며,
	 *                                                  해당 주문 ID를 여러 번 지정하기 위해서는 order_id=1&order_id=2와 같이 order_id파라미터를 여러 번 반복하면 된다.
	 *
	 * @return  array       $data
	 */
	public function get_user_transactions ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_get('v1/user/transactions',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * get_user_volume
	 *
	 * 사용자 : 거래량과 거래 수수료(베타서비스)
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']     btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플) / all
	 *
	 * @return  array       $data
	 *          array       $data['currency_pair']                  해당 거래량과 거래 수수료의 거래소 통화.
	 *          int         $data['currency_pair']['volume']        해당 거래소 내에서의 30일간의 거래량(KRW).
	 *          int         $data['currency_pair']['maker_fee']     베이시스 포인트(BPS - 1/100 퍼센트 기준)로 표기된 maker 거래 수수료율.
	 *          int         $data['currency_pair']['taker_fee']     베이시스 포인트(BPS - 1/100 퍼센트 기준)로 표기된 taker 거래 수수료율.
	 *          int         $data['total_volume']                   모든 거래소의 거래량 총합(KRW).
	 *          int         $data['timestamp']                      최종 거래량 및 거래 수수료 산정 시각(매시간에 한번씩 갱신).
	 */
	public function get_user_volume ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_get('v1/user/volume',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * get_user_wallet
	 *
	 * 사용자 : 비트코인지갑 정보 가져오기
	 *
	 * @param   array       $param
	 *          string      $param['currency_pair']                 btc_krw (비트코인) / etc_krw (이더리움 클래식) / eth_krw (이더리움) / xrp_krw (리플)
	 *
	 * @return  array       $data
	 *          array       $data['in']                             비트코인 입금주소, 원화 입금 계좌정보.
	 *                                                              비트코인 입금 주소와 원화 입금 계좌를 모두 할당하지 않은 경우
	 *                                                              비트코인 입금 주소는 POST v1/user/coins/address/assign 로 할당 받을 수 있으며,
	 *                                                              원화입금 주소는 v1/user/fiats/address/assign을 통해서 할당받을 수 있다.
	 *          string      $data['in'][]['currency']               화폐 종류. 현재는 "krw”, “btc"만을 지원한다.
	 *          string      $data['in'][]['address']['bank']        은행이름. 화폐 종류가 "krw"일 때만 해당.
	 *          int         $data['in'][]['address']['account']     은행계좌. 화폐 종류가 "krw"일 때만 해당.
	 *          string      $data['in'][]['address']['owner']       예금주. 화폐 종류가 "krw"일 때만 해당.
	 *          string      $data['in'][]['address']['address']     비트코인 주소. 화폐 종류가 "btc"일 때만 해당.
	 *          array       $data['out']                            원화 출금 계좌정보.
	 *                                                              원화 출금계좌를 지정하지 않은 경우 v1/user/fiats/address/register를 통해서 지정할 수 있다.
	 *          string      $data['out'][]['currency']              화폐 종류. 현재는 "krw"만을 지원한다.
	 *          string      $data['out'][]['address']['bank']       출금 은행이름.
	 *          int         $data['out'][]['address']['account']    출금 은행계좌.
	 *          string      $data['out'][]['address']['owner']      출금 은행계좌 예금주.
	 *          string      $data['out'][]['status']                환급 계좌 상태 정보. 다음 4가지 상태를 가진다.
	 *                                                              "owner_mismatch” : 환급 계좌는 정상이지만 은행으로부터 확인한 계좌 이름과 유저 이름이 다름
	 *                                                              “submitted” : 환급 계좌정보 확인 중
	 *                                                              “confirmed” : 정상 환급 계좌정보
	 *                                                              “invalid_account” : 잘못된 환급 계좌 정보
	 *          string      $data['out'][]['registeredOwner']       은행으로부터 확인한 계좌 이름. status가 “owner_mismatch"인 경우에만 설정됨.
	 *          array       $data['balance']                        원화(krw)와 비트코인(btc) 총 잔고
	 *          array       $data['pendingOut']                     원화(krw)와 비트코인(btc) 총 잔고 중 출금 요청 중이어서 사용할 수 없는 금액
	 *          array       $data['pendingOrders']                  원화(krw)와 비트코인(btc) 총 잔고 중 매수/매도 주문이 걸려 있어서 사용할 수 없는 금액
	 *          array       $data['available']                      사용 가능한 잔고이며, balance - pendingOut - pendingOrders 로 계산할 수 있다.
	 */
	public function get_user_wallet ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_get('v1/user/wallet',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * set_user_coins_address_assign
	 *
	 * 사용자 : 비트코인 주소 할당
	 *
	 * @param   array       $param
	 *          string      $param['currency']                  필수 파라미터이며, 가상화폐 종류를 지정한다. 현재는 비트코인을 지정하는 “btc"만 지원한다.
	 *
	 * @return  array       $data
	 *          string      $data['address']                    새로 할당 받은 비트코인 주소 문자열. 이미 비트코인 주소가 할당되어 있는 경우, 기존에 할당된 비트코인 주소를 세팅한다.
	 *          string      $data['status']                     성공이면 "success”, 실패한 경우 에러 심볼이 세팅된다.
	 *                                                          already_assigned    사용자에게 이미 비트코인 주소가 할당되어 있는 경우.
	 *                                                          no_more_addresses   더 이상 할당할 수 있는 비트코인 주소가 없는 경우.
	 *                                                          save_failure        기타 다른 사유로 비트코인 주소를 할당하지 못한 경우.
	 */
	public function set_user_coins_address_assign ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_post('v1/user/coins/address/assign',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * set_user_coins_out
	 *
	 * 사용자 : 비트코인 출금
	 *
	 * @param   array       $param
	 *          string      $param['currency']                  필수 파라미터이며, 가상화폐 종류를 지정한다. 현재는 비트코인을 지정하는 “btc"만 지원한다.
	 *          int         $param['amount']                    비트코인 수량.
	 *          string      $param['address']                   비트코인 주소.
	 *          string      $param['fee_priority']              출금 수수료를 설정하는 파라미터로 "normal"일 경우 0.001, "saver"일 경우 0.0005가 적용된다.
	 *                                                          값을 설정하지 않으면 "normal"로 적용된다.(2017년 3월 17일 오후 2시 KST부터 적용)
	 *
	 * @return  array       $data
	 *          int         $data['transferId']                 접수된 비트코인 출금요청에 대한 고유 일련번호. 이후 출금요청을 취소할 때 사용 가능하다.
	 *          string      $data['status']                     성공이면 "success”, 실패한 경우 에러 심볼이 세팅된다.
	 *                                                          another_transfer_in_progress    아직 처리가 완료되지 않은 출금요청이 존재하는 경우.
	 *                                                                                          출금 요청이 처리되는데 약 10초가 소요된다.
	 *                                                                                          서버는 앞서 실행한 출금요청의 처리가 완료되어야 그 다음 출금요청을 접수받을 수 있다.
	 *                                                          exceeds_api_daily_limit         API를 이용한 하루 출금가능액보다 큰 금액을 출금하려고 했을 때.
	 *                                                          exceeds_available_size          비트코인 잔고가 부족한 경우.
	 *                                                          save_failure                    기타 다른 이유로 비트코인 출금 요청이 실패한 경우. 일반적으로 발생하지 않음.
	 */
	public function set_user_coins_out ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_post('v1/user/coins/out',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * get_user_coins_status
	 *
	 * 사용자 : 비트코인 입출금 건별 상태조회
	 *
	 * @param   array       $param
	 *          string      $param['currency']                  필수 파라미터이며, 가상화폐 종류를 지정한다. 현재는 비트코인을 지정하는 “btc"만 지원한다.
	 *          int         $param['id']                        비트코인 입금내역 혹은 출금요청의 고유일련번호.
	 *                                                          이 파라미터를 지정하지 않으면 현재 진행 중이며 아직 완료되지 않은 출금요청을 가져온다.
	 *
	 * @return  array       $data
	 *          int         $data[]['timestamp']                입금요청, 혹은 출금요청의 접수시각
	 *          int         $data[]['id']                       비트코인 입금요청 혹은 출금요청의 고유일련번호
	 *          string      $data[]['type']                     입금이면 "coin-in”, 출금이면 “coin-out”
	 *          array       $data[]['amount']                   입출금 금액. 이 안의 필드에 value로 금액이 오며, currency는 항상 “btc"로 들어온다.
	 *          string      $data[]['in']                       type이 "coin-in"인 경우(입금)에만 응답 안에 포함되며, 비트코인을 입금받은 계좌 주소를 지닌다.
	 *          string      $data[]['out']                      type이 "coin-out"인 경우(출금)에만 응답 안에 포함되며, 비트코인 출금시 지정한 상대방 주소를 지닌다.
	 *          int         $data[]['completedAt']              입금, 출금이 처리된 시각. 아직 처리되지 않은 경우, 이 필드가 오지 않는다.
	 */
	public function get_user_coins_status ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_get('v1/user/coins/status',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}

	/**
	 * set_user_coins_out_cancel
	 *
	 * 사용자 : 비트코인 출금요청 취소
	 *
	 * @param   array       $param
	 *          string      $param['currency']                  필수 파라미터이며, 가상화폐 종류를 지정한다. 현재는 비트코인을 지정하는 “btc"만 지원한다.
	 *          int         $param['id']                        비트코인 송금 API를 호출하여 받은 출금요청 일련번호
	 *
	 * @return  array       $data
	 *          string      $data['status']                     성공이면 "success”, 실패한 경우 에러 심볼이 세팅된다.
	 *                                                          not_found           id에 지정한 고유번호에 해당하는 출금요청이 존재하지 않는 경우. 잘못된 고유번호를 지정한 경우 발생한다.
	 *                                                          not_found           다른 사용자의 출금요청을 취소하려고 시도한 경우.
	 *                                                          transfer_locked     비트코인 출금이 이미 진행 중이어서 취소할 수 없는 경우.
	 *                                                          already_filled      비트코인 출금이 이미 완료된 경우.
	 */
	public function set_user_coins_out_cancel ($param) {
		$data = array();

		if (isset($this->access_token['access_token'])) {
			$response = $this->_post('v1/user/coins/out/cancel',$param);
			if (isset($response['response']['http_code']) && $response['response']['http_code'] == 200) {
				$data = $response['html'];
			}
		}

		return $data;
	}
}
