<?php

/**
 * Bilibli Api
 * User: Mudew
 * Site: https://mudew.com
 * Date: 2018/2/26
 * Time: 14:15
 */
class BiliApi
{
    private $_tempPath = 'tmp';
    private $_loginBaseUrl = 'http://passport.bilibili.com/';
    //APP_KEY
    private $_appKey = '1d8b6e7d45233436';
    //APP_SECRET
    private $_appSecret = '560c52ccd288fed045859ed18bffd973';
    //开启使用Fiddler截包
    private $_deBug = true;
    //Api接口前缀
    private $_apiBaseUrl = 'http://api.live.bilibili.com/';
    //获取验证码模式 1:随机cookie 2:自定义cookie 3:从服务器获取cookie
    private $_getCaptchaMode = 1;
    //验证码返回模式 1:返回base64 2: 保存本地 返回一个路径 3: 直接返回资源
    private $_captchaMode = 2;
    //通用UertAgent
    private $_userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.108 Safari/537.36';

    public function loginWithCaptcha(array $account)
    {
        $url = $this->_loginBaseUrl . 'api/oauth2/login';
        $data = [
            'appkey' => $this->_appKey,
            'username' => $account['username'],
            'captcha' => $account['captcha'],
        ];
        $keyHash = json_decode($this->getKeyHash(), true);
        if ($keyHash['code'] != 0)
            return json_encode([
                'code' => $keyHash['code'],
                'message' => $keyHash['message'],
            ]);
        $data['password'] = $this->rsaEncrypt($keyHash['hash'] . $account['password'], $keyHash['key']);
        $newdata = json_decode($this->getSign($data), true);
        $raw = $this->curl($url, $newdata,false,$account['cookie']);
        $temp = json_decode($raw, true);
        if ($temp['code'] == '0')
            return json_encode([
                'code' => $temp['code'],
                'message' => 'access_token获取成功.',
                'data' => $temp['data'],
            ]);
        return json_encode($temp);
    }

    public function login(array $account)
    {
        $url = $this->_loginBaseUrl . 'api/oauth2/login';
        $data = [
            'appkey' => $this->_appKey,
            'username' => $account['username'],
        ];
        $keyHash = json_decode($this->getKeyHash(), true);
        if ($keyHash['code'] != 0)
            return json_encode([
                'code' => $keyHash['code'],
                'message' => $keyHash['message'],
            ]);
        $data['password'] = $this->rsaEncrypt($keyHash['hash'] . $account['password'], $keyHash['key']);
        $newdata = json_decode($this->getSign($data), true);
        $raw = $this->curl($url, $newdata);
        $temp = json_decode($raw, true);
        if ($temp['code'] == '0')
            return json_encode([
                'code' => $temp['code'],
                'message' => 'access_token获取成功.',
                'data' => $temp['data'],
            ]);
        return json_encode($temp);
    }

    public function getCapcha($cookie = null)
    {
        $url = $this->_loginBaseUrl . 'captcha';
        $cookie = 'sid=' . $cookie;
        switch ($this->_getCaptchaMode) {
            //获取验证码模式 1:随机cookie 2:自定义cookie 3:从服务器获取cookie
            case 1:
                //随机
                $cookie = $cookie . $this->getRandomString();
                $raw = $this->curl($url, null, false, $cookie);
                break;
            case 2:
                //自定义
                $raw = $this->curl($url, null, false, $cookie);
                break;
            case 3:
                //从服务器获取
                $cookie = null;
                $raw = $this->curl($url, null, true);
                preg_match_all('/Set-Cookie: (.*);/iU', $raw, $cookies);
                $cookie = $this->cookieAction($cookies);
                $raw = $this->curl($url, null, true, $cookie);
                break;
            default:
                $raw = null;
                break;
        }
        switch ($this->_captchaMode) {
            //验证码返回模式 1:返回base64 2: 保存本地 返回一个路径 3: 直接返回资源
            case 1:
                return json_encode([
                    'code' => 1,
                    'cookie' => $cookie,
                    'captcha' => $this->base64EncodeImage($raw),
                ]);
                break;
            case 2:
                //必须有个tmp
                if (!is_dir($this->_tempPath))
                    mkdir($this->_tempPath);
                return json_encode([
                    'code' => 2,
                    'cookie' => $cookie,
                    'captcha' => $this->saveCaptcha($raw),
                ]);
                break;
            case 3:
                return json_encode([
                    'code' => 2,
                    'cookie' => $cookie,
                    'captcha' => $raw,
                ]);
                break;
            default:
                break;
        }
    }

    public function saveCaptcha($image_file)
    {
        $captcha = $this->_tempPath . '/' . $this->getRandomString() . '.jpg';
        file_put_contents($captcha, $image_file);
        return $captcha;
    }

    public function base64EncodeImage($image_file)
    {
        $base64_image = '';
        $image_info = getimagesize($image_file);
        $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
        $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
        return $base64_image;
    }

    public function cookieAction($cookies)
    {
        $data = '';
        foreach ($cookies[1] as $cookie) {
            $data .= $cookie . ';';
        }
        return $data;
    }

    public function getRandomString($len = 6, $chars = null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }


    private function rsaEncrypt($data, $key)
    {
        $newdata = '';
        $public_key = openssl_pkey_get_public($key);
        openssl_public_encrypt($data, $newdata, $public_key);
        return base64_encode($newdata);
    }

    public function getKeyHash()
    {
        $url = $this->_loginBaseUrl . 'api/oauth2/getKey';
        $data = [
            'appkey' => $this->_appKey,
        ];
        $newdata = json_decode($this->getSign($data), true);
        $raw = $this->curl($url, $newdata);
        $temp = json_decode($raw, true);
        if ($temp['code'] != 0)
            return json_encode([
                'code' => $temp['code'],
                'message' => '获取失败',
            ]);
        $hash = $temp['data']['hash'];
        $key = str_replace('\n', '', $temp['data']['key']);
        return json_encode([
            'code' => $temp['code'],
            'message' => '获取成功',
            'hash' => $hash,
            'key' => $key,
        ]);
    }

    public function getSign($data)
    {
//        foreach ($data as $key => $value) {
//            $data[$key] = urlencode($value);
//        }
        ksort($data);
        $data_format = http_build_query($data);
        $data['sign'] = md5($data_format . $this->_appSecret);
        return json_encode($data);
    }

    private function curl($url, $data = null, $header = false, $cookie = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, $header);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->_userAgent);
        if ($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        if ($this->_deBug) {
            curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1"); //代理服务器地址
            curl_setopt($curl, CURLOPT_PROXYPORT, "8888"); //代理服务器端口
        }

        if (!empty($data)) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}

$api = new BiliApi();
print_r($api->login(['username' => '沙奈之朵', 'password' => 'wct11111']));
