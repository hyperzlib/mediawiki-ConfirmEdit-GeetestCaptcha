# ConfirmEdit的极验验证码扩展
## 用法
首先启动ConfirmEdit扩展，具体请参考：[ConfirmEdit](https://www.mediawiki.org/wiki/Extension:ConfirmEdit)

然后在配置文件加上
```php
wfLoadExtension('GeetestCaptcha');

$wgCaptchaClass = 'GeetestCaptcha';
$wgGeetestID = '你的极验ID';
$wgGeetestKey = '你的极验KEY';
```

申请KEY请前往：[http://www.geetest.com/](http://www.geetest.com/)