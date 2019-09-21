<?php
$xmls = glob("channels/*.xml");
if(!file_exists("channels.json"))
{
    file_put_contents('channels.json',"");
}
$channels_name = json_decode(file_get_contents("channels.json"),true);

file_put_contents("xmltv/xmltv.xml",'<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE tv SYSTEM "xmltv.dtd">
<tv source-info-url="http://allfrtv.com/" source-data-url="http://allfrtv.com/" generator-info-name="PHP" generator-info-url="http://allfrtv.com/">'.chr(10));


foreach($xmls as $xml)
{
    $id = str_replace('.xml','',$xml);
    $id = explode('/',$id);
    $id = $id[count($id)-1];
    $name = $channels_name[$id]['name'];
    $logo = $channels_name[$id]["logo"];
    if(!$name)
    {
        $name = $id;
    }
    $fp = fopen("xmltv/xmltv.xml","a");
    fputs($fp,'<channel id="'.$id.'">
    <display-name>'.htmlentities($name,ENT_XML1).'</display-name>
	<icon src="'.htmlentities($logo,ENT_XML1).'"/>
</channel>
');
    fclose( $fp );
}
foreach($xmls as $xml)
{
    @$xml_open = XMLReader::open($xml);
    $xml_open->setParserProperty(XMLReader::VALIDATE, true);
    if($xml_open->isValid()) {

        $fp = fopen("xmltv/xmltv.xml", "a");
        fputs($fp, file_get_contents($xml));
        fclose($fp);
        echo $xml." : OK".chr(10);
    } else {
        echo $xml." : HS".chr(10);
    }
}
$fp = fopen("xmltv/xmltv.xml","a");
fputs($fp,'</tv>');
fclose( $fp );
echo chr(10)."XML : OK".chr(10);

$got = file_get_contents('xmltv/xmltv.xml');
$got1 = gzencode($got,true);
file_put_contents('xmltv/xmltv.xml.gz',$got1);
echo "GZ : OK".chr(10);


$zip = new ZipArchive();
$filename = "xmltv/xmltv.zip";

if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
    echo "ZIP : HS".chr(10);
} else {
    echo "ZIP : OK".chr(10);
}
$zip->addFile("xmltv/xmltv.xml", "xmltv.xml");
$zip->close();