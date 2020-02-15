<?php
class Utils
{
    public static function generateFilePath($xmlpath,$channel,$date)
    {
        return $xmlpath.$channel."_".$date.".xml";
    }
}