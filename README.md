# MoeImgTag

为高版本 MediaWiki 添加 `<img>` 标签支持。包含防 xss 攻击的过滤。

## 安装

```php
wfLoadExtension( 'MoeImgTag' );
```

## 配置项

### 黑名单&白名单

```php
$wgMoeImgTagWhitelist = [
    'example.com',
    '*.example.com',
    'https://example.com',
    'https://*.example.com',
    'https://example.com/only/allow/this/path',
];
$wgMoeImgTagBlacklist = [
    // format is the same as whitelist
];
```

白名单和黑名单互相排斥，如果同时设置，则只会使用白名单。

匹配的规则可以参考 [Renderer::checkUrlMatch](./includes/Renderer.php) 的实现。

提示：设置白名单的时候，别忘了把本站域名加上，最简单的方式是：

```php
$wgMoeImgTagWhitelist = [
    $wgServer,
    // ...
];
```

### 修复自闭合标签

```php
$wgMoeImgTagFixSelfClosing = true;
```

若开启此选项，扩展会尝试在解析文本前修复未闭合的 `<img>` 标签。

例如：`<img src="https://example.com/image.jpg">` 会被转换为 `<img src="https://example.com/image.jpg" />`。

虽然 HTML5 规范中 `<img>` 标签是可以自闭合的，但 wikitext 解析器并不认识这种写法，因此我们提供了这种曲线救国的方式。

有可能会导致一些意外的结果，因此默认情况下不开启。
