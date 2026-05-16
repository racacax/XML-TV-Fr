<?php

function getProviderName(string $className): string
{
    $tmp = explode('\\', $className);

    return end($tmp);
}