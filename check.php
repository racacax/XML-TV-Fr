<?php
unlink("tmp/hs.txt");
foreach(scandir("channels/old") as $file)
{
    if(!in_array($file,scandir("channels/")))
    {
        file_put_contents("tmp/hs.txt",file_get_contents("tmp/hs.txt").$file.chr(10));
        rename("channels/old/".$file,"channels/".$file);
    }
}
