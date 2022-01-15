<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;


use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

class Telerama extends AbstractProvider implements ProviderInterface
{
    private static $USER_AGENT = 'okhttp/3.12.3';
    private static $API_CLE = 'apitel-g4aatlgif6qzf'; // apitel-5304b49c90511
    private static $HASH_KEY = 'uIF59SZhfrfm5Gb'; // Eufea9cuweuHeif
    private static $APPAREIL = 'android_tablette';
    private static $HOST = 'http://api.telerama.fr';
    private static $NB_PAGE = '800000';
    private static $PAGE = 1;

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath("channels_telerama.json"), $priority ?? 0.80);
    }
    public function signature($url)
    {
        foreach (array('=', '?', '&') as $char) {
            $url = str_replace($char, '', $url);
        }
        return hash_hmac('sha1', $url, self::$HASH_KEY);
    }

    public function constructEPG(string $channel, string $date)
    {
        parent::constructEPG($channel, $date);
        if (!isset($date)) {
            $date = date('Y-m-d');
        }
        if(!$this->channelExists($channel))
            return false;
        $channel_id = $this->channelsList[$channel];



        $url = '/v1/programmes/grille?appareil=' . self::$APPAREIL . '&date=' . $date . '&id_chaines=' .  $channel_id . '&nb_par_page=' .  self::$NB_PAGE . '&page=' .  self::$PAGE;
        $hash = self::signature($url);
        $url .= '&api_cle=' .  self::$API_CLE . '&api_signature=' . $hash;
        $uu = curl_init(self::$HOST . $url);
        curl_setopt($uu, CURLOPT_USERAGENT, self::$USER_AGENT);
        curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
        $get = curl_exec($uu);
        curl_close($uu);
        $json = json_decode($get, true);

        if (!isset($json['donnees'])) {
            return false;
        }
        if (isset($json['donnees'])) {
            foreach ($json['donnees'] as $donnee) {
                $program = $this->channelObj->addProgram(strtotime($donnee["horaire"]["debut"]), strtotime($donnee["horaire"]["fin"]));
                $descri = $donnee['resume'];
                if (isset($donnee["serie"])) {
                    $descri = 'Saison ' . $donnee["serie"]["saison"] . ' Episode ' . $donnee["serie"]["numero_episode"] . chr(10) . $descri;
                    $program->setEpisodeNum($donnee["serie"]["saison"], $donnee["serie"]["numero_episode"]);
                }
                if (isset($donnee["soustitre"]) && !empty($donnee["soustitre"])) {
                   $program->addSubtitle($donnee["soustitre"]);
                }
                if (isset($donnee["vignettes"]["grande169"])) {
                    $program->setIcon($donnee["vignettes"]["grande169"]);
                }
                if (isset($donnee["critique"])) {
                    $descri .= chr(10) . $donnee["critique"];
                }
                if (isset($donnee["annee_realisation"])) {
                    $descri .= chr(10) . 'Année de réalisation : ' . $donnee["annee_realisation"];
                    $program->setYear($donnee["annee_realisation"]);
                }
                $descri = str_replace('<P>', '', $descri);
                $descri = str_replace('</P>', '', $descri);
                $descri = str_replace('<I>', '', $descri);
                $descri = str_replace('</I>', '', $descri);

                if (isset($donnee["intervenants"])) {
                    $intervenants = array();
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
                        $program->addCredit($intervenant["prenom"] . ' ' . $intervenant["nom"] . $role, $libelle);
                    }
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
                }
                $program->addTitle($donnee["titre"]);
                $program->addDesc(!empty($descri)? $descri: 'Pas de description');
                $program->addCategory($donnee["genre_specifique"]);
                if($donnee["csa"] == "TP") {
                    $rating = "Tout public";
                } else {
                    $rating = "-".$donnee["csa"];
                }
                $program->setRating($rating);
            }
            return $this->channelObj;
        }
        return false;
    }

}