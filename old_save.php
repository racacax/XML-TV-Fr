<?php
foreach(glob('channels/old/*') as $file)
{
unlink($file);
}

foreach(scandir('channels/') as $file)
{
    if($file != "old" && $file != "." && $file != "..")
        rename('channels/'.$file,'channels/old/'.$file);
}