<?php

namespace Moegirl\ImgTag;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\Sanitizer;

class Renderer {
  const IMG_SELF_CLOSING_MARK = 'data-img-unsafe-self-closing';

  public static function renderImgTag($input, array $args, Parser $parser, PPFrame $ppframe) {
    $src = isset($args['src']) ? trim($args['src']) : '';
    $style = isset($args['style']) ? $args['style'] : '';
    $class = isset($args['class']) ? $args['class'] : '';

    // 在某些极其抽象的使用场景下，src 会出现在 $input 中
    // 比如用户错写成 <img>$input</img> 或者 {{ #tag:img | $input }}
    // 不论如何，我们还是处理一下
    if (empty($src) && !empty($input)) {
      $src = trim($input);
    }

    // 如果 src 包含 {，则认为有可能包含模板或其他预处理内容，需要交给 Parser 处理
    // 例如：{{filepath:xxx|nowiki}} 或 /path/to/image/{{PAGENAME}}.png
    if (strpos($src, '{{') !== false) {
      $parsed = $parser->recursivePreprocess($src, $ppframe);
      // wiki 可能开启了图片链接自动渲染为 <img> 标签，我们得从中提取出 src
      if (strpos($parsed, '<img') === 0) {
        // 使用正则表达式提取 src 属性
        if (preg_match('/src=["\']?([^"\'>]+)["\']?/', $parsed, $matches)) {
          $parsed = $matches[1] ?? '';
        }
      }
      $src = $parsed;
    }

    // {{filepath:xxx|nowiki}} 可能会给出一个奇怪的 url，我们简单地处理一下
    if (strpos($src, '&#58;//') !== false) {
      // 替换 &#58; 为 :
      $src = str_replace('&#58;//', '://', $src);
    }

    $srcValidationStatus = self::validateSrc($src);
    if (!$srcValidationStatus->isGood()) {
      $givenSrc = isset($args['src']) ? $args['src'] : '';
      return Html::rawElement(
        'span',
        [
          'class' => 'error',
          'title' => "given src: {$givenSrc}\nparsed src: {$src}\ninput: {$input}",
        ],
        wfMessage('imgtag-invalid', $srcValidationStatus->getMessage()->text())->text()
      );
    }

    $class = Sanitizer::escapeClass($class);
    $style = Sanitizer::checkCss($style);

    // 暂时不做支持
    unset($args['srcset']);

    $attrs = array_merge($args, [
      'src' => $src,
      'style' => $style,
      'class' => $class,
      'data-input' => $input,
    ]);
    $attrs = Sanitizer::validateTagAttributes($attrs, 'img');

    // 如果没有设置 loading 属性，则默认为 lazy
    if (isset($args['loading']) && $args['loading'] === 'eager') {
      $attrs['loading'] = 'eager';
    } else {
      $attrs['loading'] = 'lazy';
    }

    // 添加额外属性方便维护
    $attrs['class'] .= ' moe-img-tag';

    return Html::rawElement(
      'img',
      $attrs,
      ''
    );
  }

  public static function validateSrc($src = '') {
    $src = trim($src);
    $status = Status::newGood();

    $conf = MediaWikiServices::getInstance()->getMainConfig();
    $whiteList = $conf->get('MoeImgTagWhitelist');
    $blackList = $conf->get('MoeImgTagBlacklist');

    if (empty($src)) {
      return $status->newFatal('imgtag-empty-src');
    }

    // 禁止危险协议
    if (preg_match('/^\s*(data:|blob:|javascript:|vbscript:|file:|ftp:)/i', $src)) {
      return $status->newFatal('imgtag-invalid-src');
    }

    // 检查URL编码绕过
    $decodedSrc = urldecode($src);
    if (preg_match('/^\s*(data:|blob:|javascript:|vbscript:|file:|ftp:)/i', $decodedSrc)) {
      return $status->newFatal('imgtag-invalid-src');
    }

    $url = parse_url($src, PHP_URL_HOST);
    if ($url === null) {
      return $status->newFatal('imgtag-invalid-src');
    }

    // Check against whitelist
    if (!empty($whiteList) && !self::checkUrlMatch($src, $whiteList)) {
      return $status->newFatal('imgtag-not-whitelisted-src');
    }

    // Check against blacklist
    if (!empty($blackList) && self::checkUrlMatch($src, $blackList)) {
      return $status->newFatal('imgtag-blacklisted-src');
    }

    return $status;
  }

  /**
   * 检查给定的 URL 是否与指定的域名列表匹配
   *
   * @param string $url 要检查的 URL
   * @param array $patterns
   * 域名列表，其中每一项可能是一下情况之一：
   * - 主机名，如 'example.com'
   * - 带通配符的主机名，如 '*.example.com'
   * - 完整的 URL，如 'https://example.com'
   * - 带通配符的完整 URL，如 'https://*.example.com'
   * - 带有路径限定的 URL，如 'https://example.com/path'
   * - 带通配符的路径限定 URL，如 'https://*.example.com/path'
   * 
   * 匹配的例子：
   * - 'example.com' 将匹配 'http://example.com'、'https://example.com'、或者任何位于 'example.com' 的网址如 'http://example.com/path/to/resource'。
   * - '*.example.com' 将匹配 'http://sub.example.com'、'https://another.sub.example.com'，但不会匹配 'example.com' 或 'example.org'。
   * - 'https://example.com' 将匹配 'https://example.com'，但不会匹配 'http://example.com' 或 'https://anotherdomain.com'。
   * - 'https://*.example.com' 将匹配 'https://sub.example.com'，但不会匹配 'http://example.com' 或 'https://anotherdomain.com'。
   * - 'https://example.com/path' 将匹配 'https://example.com/path/to/resource'，但不会匹配 'https://example.com/anotherpath'。
   * - 'https://*.example.com/path' 将匹配 'https://sub.example.com/path/to/resource'，但不会匹配 'https://example.com/anotherpath' 或 'http://sub.example.com/path/to/resource'。
   * 
   * @return bool 如果 URL 匹配任意一个模式，则返回 true；否则返回 false
   */
  public static function checkUrlMatch(string $url, array $patterns): bool {
    $parsedUrl = parse_url($url);
    if ($parsedUrl === false || !isset($parsedUrl['host'])) {
      return false; // 无效的 URL
    }

    $host = $parsedUrl['host'];
    $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';

    foreach ($patterns as $pattern) {
      // 处理通配符和完整 URL
      if (strpos($pattern, '://') !== false) {
        // 完整 URL
        if ($url === $pattern) {
          return true;
        }
      } elseif (strpos($pattern, '*') !== false) {
        // 带通配符的模式
        $pattern = str_replace('*', '', $pattern); // 去掉通配符
        if (stripos($host, $pattern) !== false) {
          return true;
        }
      } else {
        // 纯主机名
        if ($host === $pattern) {
          return true;
        }
      }
    }

    return false;
  }
}