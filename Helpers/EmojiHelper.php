<?php


namespace Helpers;


use Discord\Parts\Channel\Message;
use IntlChar;

class EmojiHelper
{
    public static function emojiToUnicode($emoji)
    {
        $emoji   = mb_convert_encoding($emoji, 'UTF-32', 'UTF-8');
        $unicode = strtoupper(preg_replace("/^[0]{3}/", "U+", bin2hex($emoji)));

        return $unicode;
    }


    public static function possibleDecodingViaIntl(Message $message){
        $content = $message->content;
        $out     = '';
        $test    = [];
        for ($i = 0; $i < mb_strlen($content); $i++) {
            $char      = mb_substr($content, $i, 1);
            $blockCode = IntlChar::getBlockCode(IntlChar::ord($char));
            $isEmoji   = $blockCode == IntlChar::BLOCK_CODE_EMOTICONS
                || $blockCode == 298
                || $blockCode == 261
                || $blockCode
                == IntlChar::BLOCK_CODE_MISCELLANEOUS_SYMBOLS_AND_PICTOGRAPHS
                || $blockCode
                == IntlChar::BLOCK_CODE_MISCELLANEOUS_SYMBOLS
            ;
            $test[]    = [
                'char'      => $char,
                'blockCode' => $blockCode
            ];
            $out       .= $isEmoji ? EmojiHelper::emojiToUnicode($char)
                : $char;
        }
        $message->reply('unicode conversion '. $out);
    }
}