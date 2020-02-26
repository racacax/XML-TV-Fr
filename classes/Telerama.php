<?php
require_once 'Provider.php';
require_once 'Utils.php';
class Telerama implements Provider
{
    private $XML_PATH;
    private static $TMP_PATH = "epg/telerama/";
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;
    private static $USER_AGENT = 'okhttp/3.12.3';
    private static $API_CLE = 'apitel-g4aatlgif6qzf'; // apitel-5304b49c90511
    private static $HASH_KEY = 'uIF59SZhfrfm5Gb'; // Eufea9cuweuHeif
    private static $APPAREIL = 'android_tablette';
    private static $HOST = 'http://api.telerama.fr';
    private static $NB_PAGE = '800000';
    private static $PAGE = 1;

    public static function getPriority()
    {
        return 0.95;
    }
    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if(!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_telerama.json"))
        {
            self::$CHANNELS_LIST  = json_decode(file_get_contents("channels_per_provider/channels_telerama.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }
    public function signature($url)
    {
        foreach (array('=', '?', '&') as $char) {
            $url = str_replace($char, '', $url);
        }
        return hash_hmac('sha1', $url, self::$HASH_KEY);
    }

    public function constructEPG($channel,$date)
    {
        $xml_save = Utils::generateFilePath($this->XML_PATH,$channel,$date);
        if(file_exists( $xml_save))
            unlink( $xml_save);
        if (!isset($date)) {
            $date = date('Y-m-d');
        }
        if(!in_array($channel,self::$CHANNELS_KEY))
            return false;
        $channel_id = self::$CHANNELS_LIST[$channel];



        $url = '/v1/programmes/grille?appareil=' . self::$APPAREIL . '&date=' . $date . '&id_chaines=' .  $channel_id . '&nb_par_page=' .  self::$NB_PAGE . '&page=' .  self::$PAGE;
        $hash = self::signature($url);
        $url .= '&api_cle=' .  self::$API_CLE . '&api_signature=' . $hash;
        if (file_exists(self::$TMP_PATH . $hash)) {
            $get = file_get_contents(self::$TMP_PATH . $hash);
            $json = json_decode($get, true);
        } else {
            $uu = curl_init(self::$HOST . $url);
            curl_setopt($uu, CURLOPT_USERAGENT, self::$USER_AGENT);
            curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
            $get = curl_exec($uu);
            curl_close($uu);
            $json = json_decode($get, true);

            if (isset($json['donnees'])) {
                file_put_contents(self::$TMP_PATH . $hash, $get);
            } else {
                return false;
            }
        }
        if (isset($json['donnees'])) {
            foreach ($json['donnees'] as $donnee) {
                $balises_sup = '';
                $descri = $donnee['resume'];
                if (isset($donnee["serie"])) {
                    $descri = 'Saison ' . $donnee["serie"]["saison"] . ' Episode ' . $donnee["serie"]["numero_episode"] . chr(10) . $descri;
                    $balises_sup .= chr(10) . '	<episode-num system="xmltv_ns">' . ($donnee["serie"]["saison"] - 1) . '.' . ($donnee["serie"]["numero_episode"] - 1) . '.</episode-num>';
                }
                if (isset($donnee["soustitre"])) {
                    $balises_sup .= chr(10) . '	<sub-title lang="fr">' . htmlspecialchars($donnee["soustitre"], ENT_XML1) . '</sub-title>';
                }
                if (isset($donnee["vignettes"]["grande169"])) {
                    $balises_sup .= chr(10) . '<icon src="' . htmlspecialchars($donnee["vignettes"]["grande169"], ENT_XML1) . '" />';
                }
                if (isset($donnee["critique"])) {
                    $descri .= chr(10) . $donnee["critique"];
                }
                if (isset($donnee["annee_realisation"])) {
                    $descri .= chr(10) . 'Année de réalisation : ' . $donnee["annee_realisation"];
                }
                $descri = str_replace('<P>', '', $descri);
                $descri = str_replace('</P>', '', $descri);
                $descri = str_replace('<I>', '', $descri);
                $descri = str_replace('</I>', '', $descri);

                if (isset($donnee["intervenants"])) {
                    $intervenants = array();
                    $int2 = chr(10) . '	<credits>' . chr(10);
                    foreach ($donnee["intervenants"] as $intervenant) {
                        if (!$intervenant["libelle"]) {
                            $intervenant["libelle"] = 'Avec';
                        }
                        $intervenants[$intervenant["libelle"]][] = $intervenant["prenom"] . ' ' . $intervenant["nom"];
                        $libelle = 'guest';
                        $role = "";
                        if ($intervenant["libelle"] == 'Présentateur vedette' || $intervenant["libelle"] == 'Autre présentateur') {
                            $libelle = 'presenter';
                        }
                        if ($intervenant["libelle"] == 'Acteur') {
                            $libelle = 'actor';
                            if ($intervenant["role"] == '') {
                                $role = ' (' . $intervenant["role"] . ')';
                            }
                        }
                        if ($intervenant["libelle"] == 'Réalisateur') {
                            $libelle = 'director';
                        }
                        if ($intervenant["libelle"] == 'Scénariste' || $intervenant["libelle"] == 'Origine Scénario' || $intervenant["libelle"] == 'Scénario') {
                            $libelle = 'writer';
                        }
                        if ($intervenant["libelle"] == 'Créateur') {
                            $libelle = 'editor';
                        }
                        if ($intervenant["libelle"] == 'Musique') {
                            $libelle = 'composer';
                        }
                        if ($intervenant["libelle"] == '') {
                            if ($intervenant["role"] != '') {
                                $libelle = 'actor';
                                $role = ' (' . $intervenant["role"] . ')';
                            } else {
                                $libelle = 'director';
                            }
                        }
                        $int2 = $int2 . '		<' . $libelle . '>' . htmlspecialchars($intervenant["prenom"] . ' ' . $intervenant["nom"] . $role, ENT_XML1) . '</' . $libelle . '>' . chr(10);
                    }
                    $int2 = $int2 . '	</credits>';
                    $keys = array_keys($intervenants);
                    for ($i = 0; $i < count($intervenants); $i++) {
                        $int = '';
                        $a = $intervenants[$keys[$i]];
                        $b = '';
                        foreach ($a as $intervenant) {
                            $int = $int . $b . $intervenant;
                            $b = ', ';
                        }
                        $descri .= chr(10) . $keys[$i] . ' : ' . $int;
                    }
                    $balises_sup .= $int2;
                }

                $str_put = '<programme start="' . date('YmdHis O', (strtotime($donnee["horaire"]["debut"]))) . '" stop="' . date('YmdHis O', (strtotime($donnee["horaire"]["fin"]))) . '" channel="' . $channel . '">
	<title lang="fr">' . htmlspecialchars($donnee["titre"], ENT_XML1) . '</title>
	<desc lang="fr">' . htmlspecialchars($descri, ENT_XML1) . '</desc>
	<category lang="fr">' . htmlspecialchars($donnee["genre_specifique"], ENT_XML1) . '</category>'
                    .
                    $balises_sup
                    . '
	<rating system="csa">
      <value>-' . htmlspecialchars($donnee["csa"], ENT_XML1) . '</value>
    </rating>
</programme>
';
                $str_put = str_replace("\0",'',$str_put);
                $fp = fopen($xml_save, "a");
                fputs($fp, $str_put);
            }
            return true;
        }
    }

}