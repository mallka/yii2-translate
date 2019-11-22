<?php
/***
 * 异常
 */

namespace yii\translate;

use yii\base\Exception;

class TranslateException extends Exception
{
    public function getName()
    {
        return "Translate Exception";
    }
}
