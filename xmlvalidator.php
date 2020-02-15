<?php
@$xml = XMLReader::open('xmltv/xmltv.xml');

// L'option de validation de l'analyseur doit être
// active pour que cette méthode fonctionne correctement
$xml->setParserProperty(XMLReader::VALIDATE, true);

if($xml->isValid())
{
    echo "XML valide";
} else {
    echo "XML non valide";
}
echo chr(10);