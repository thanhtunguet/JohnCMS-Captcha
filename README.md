My PHP Library
=========================

MyPHP Library with composer package

Written by: [thanhtunguet](https://github.com/thanhtunguet)

Contact email: [thanhtung.uet@gmail.com](mailto:<thanhtung.uet@gmail.com>)

Installation
------------
* Install via composer:

```bash
composer require thanhtunguet/myphp
```
* Install via Git CLI:

```bash
git clone https://github.com/thanhtunguet/MyPHP.git
```

Usage
-----

* Curl Request

```php
use thanhtunguet\MyPHP\Curl_Request();

$curl = new Curl_Request();

$curl->get('http://example.com', function ($curl) {
    $response = $curl->response;
    echo $response;
});
```

* JohnCMS Captcha (written by [JohnCMS](https://johncms.com))

```php
use thanhtunguet\MyPHP\JohnCMS_Captcha;

$captcha = new JohnCMS_Captcha();
// Create captcha image and key string
$captcha->create_captcha();
// Get md5 hashed key string
$key_string = $captcha->get_key_string();
// Output to browser
$captcha->output();
```
