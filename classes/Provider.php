<?php
interface Provider {
    function __construct($XML_PATH);
    function constructEPG($channel,$date);
    static function getPriority();
}