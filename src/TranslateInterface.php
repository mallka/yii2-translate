<?php
/***
 * 接口
 */

namespace yii\translate;

interface TranslateInterface
{
    //设置翻译接口地址
    public function setUrl($url);

    //设置源语言
    public function setSource($source = null);

    //设置目标语言
    public function setTarget($target);

    //文本翻译
    public function transText($content);

    //Html翻译
    public function transHtml($content);
}
