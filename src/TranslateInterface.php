<?php
/***
 * 接口
 */
namespace yii\translate;

interface TranslateInterface
{
    public function setUrl($url);

    public function setSource($source = null);

    public function setTarget($target);

    public function setOptions($options = null);

    public function transText($content);
}
