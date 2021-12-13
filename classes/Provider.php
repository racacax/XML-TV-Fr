<?php
interface Provider {
    function __construct();
    function constructEPG($channel,$date);
    static function getPriority();
}