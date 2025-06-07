<?php

namespace Moegirl\ImgTag;

use MediaWiki\Hook\ParserBeforeStripHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;

class Hooks implements ParserFirstCallInitHook, ParserBeforeStripHook {
  public function onParserFirstCallInit($parser) {
    $parser->setHook('img', [Renderer::class, 'renderImgTag']);
  }

  /**
   * 曲线救国：尝试修复 <img ...> 标签的自闭合问题
   * <img ...> → <img ... />
   */
  public function onParserBeforeStrip($parser, &$text, $state) {
    $conf = MediaWikiServices::getInstance()->getMainConfig();
    if (!$conf->get('MoeImgTagFixSelfClosing')) {
      return true; // 如果配置未开启，则不进行处理
    }

    $text = preg_replace_callback(
      '/<img\b([^<>]*?)>/i',
      static function ($m) {
        // 已经含 /> 就原样返回
        if (preg_match('/\/\s*>$/', $m[0])) {
          return $m[0];
        }

        $attributes = $m[1];
        $mark = Renderer::IMG_SELF_CLOSING_MARK;
        return "<img{$attributes} {$mark} />";
      },
      $text
    );
  }
}