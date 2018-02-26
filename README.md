# bilibili-api
PHP Promised Bilibili Android API

## 使用
```php
require 'BilibiliApi.php';
$api = new BiliApi();
$account = [
    'username' => 'x',
    'password' => 'x',
];
$api->login($account);
```

```json
{
	"code": 0,
	"message": "access_token获取成功.",
	"data": {
		"mid": 41330000,
		"access_token": "d0fd97086xxxxxxxxxxxxxxxx",
		"refresh_token": "1da5bc92xxxxxxxxxxxxxx",
		"expires_in": 25920000
	}
}
```
## 接口

* `login` 参数: `['username' => String,'password' => String]`.

## TODO
- 验证码登陆接口(进行中)
- 待定


End.
