<?php
chdir(__DIR__."/..");
require_once "classes/Utils.php";
define('XMLTVFR_SILENT', true);
loadConfig();
require_once "tools/functions.php";
$channels = getChannelsWithProvider();
?>
<html>
<head>
<meta charset="UTF-8">
<title>XML TV Fr channels.json generator</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<style>
    body {
        font-family: Arial, serif;
    }
    td, th {
        border: black 1px solid;
    }
    .multiselect {
        display: table;
    }

    .multiselect > * {
        display: table-cell;
    }

    .options {
        vertical-align: middle;
    }

    .options input {
        width: 60px;
        display: block;
        margin: -3px auto;
    }

    .select-list {
        width: 160px;
    }

    .select2 {
        color: #999;
    }
</style>
</head>
<body>
<input type="button" value="Sauvegarder" onclick="save()" /><br/>
<span>Pour définir des choix de services différents ou restreints, déplacez et ordonnez à gauche ceux désirés (Plus haut = plus haute priorité). Laissez vide pour garder l'ordre par défaut des services.</span>
<table id="channels">
    <tr>
        <th>ID</th>
        <th>Nom</th>
        <th>Providers</th>
        <th>Logo URL</th>
        <th>Activé</th>
    </tr>
    <?php
    $ids = [];
    $channelsDefaultInfos = getDefaultChannelsInfos();
    foreach ($channels as $channel) {
        $id = md5($channel["key"]);
        $ids[] = $id;
        $is_default = false;
        $defaultName = @$channelsDefaultInfos[$channel['key']]['name'];
        if(!isset($channel['icon']) || empty($channel['icon'])) {
            $icon = @$channelsDefaultInfos[$channel['key']]['icon'];
            $is_default = true;
        } else {
            $icon = $channel['icon'];
        }
        ?>
        <tr class="channel" id="<?php echo $id; ?>" data-name="<?php echo $channel["key"]; ?>">
            <th><?php echo htmlentities($channel["key"]) ?></th>
            <th><?php if(!empty($defaultName)) { echo '<strong>Nom par défaut : </strong>'.$defaultName.'<br/>'; } ?><input name="name" value="<?php echo htmlentities(@$channel["name"]) ?>" /></th>
            <th><?php include "tools/select_template.php" ?></th>
            <th><?php if(isset($icon)) { ?> <?php if($is_default) { echo '(Logo par défaut)<br/>'; } ?><img alt="Logo" src="<?php echo $icon; ?>" style="max-width:200px; max-height:80px;width:auto; height:auto" /><br/><?php } ?><input name="icon" value="<?php echo htmlentities(@$channel["icon"]) ?>" /></th>
            <th><input name="is_active" type="checkbox" <?php echo (@$channel["is_active"]) ? 'checked' : "" ?> />
            <?php if($channel['is_dummy']) {
                ?><br/><b>Chaine vide</b><button onclick="document.getElementById('<?php echo $id; ?>').remove()">Supprimer</button><?php
            }?>
            </th>
        </tr>
    <?php
    }
    ?>
</table>
<script>
    let ids = <?php echo json_encode($ids); ?>;
    ids.forEach((id) => {
        $(document).ready(function(){
            $(document).click(function(event){
                if(!$(event.target).is('.options input, .select-list option')){
                    $('.select-list option:selected').prop('selected', false);
                }
            });
            $('#options_'+id+' input').click(function(){
                var $op1 = $('#select1_'+id+' option:selected'),
                    $op2 = $('#select2_'+id+' option:selected'),
                    $this = $(this);
                if($op1.length){
                    if($this.attr('id') == 'move-up'){$op1.first().prev().before($op1);}
                    if($this.attr('id') == 'move-down'){$op1.last().next().after($op1);}
                }
                moveOver($op1);moveOver($op2);

                var $o2 = $('#select2_'+id+' option');
                $o2.sort(function(a,b){
                    if (parseInt(a.getAttribute('data-results')) < parseInt(b.getAttribute('data-results'))) return 1;
                    if (parseInt(a.getAttribute('data-results')) > parseInt(b.getAttribute('data-results'))) return -1;
                    if (a.text.toUpperCase() > b.text.toUpperCase()) return 1;
                    if (a.text.toUpperCase() < b.text.toUpperCase()) return -1;
                    return 0;
                })
                $("#select2_"+id).empty().append($o2);

                function moveOver($op){
                    if($op.length){
                        if($this.attr('id') == 'move-over'){
                            ($op.parent().attr('id') === 'select1_'+id+'')?
                                $op.detach().appendTo('#select2_'+id+'').prop('selected', false)
                                :$op.detach().appendTo('#select1_'+id+'').prop('selected', false);
                        }
                    }
                }
            });
        });
    });
    function save() {
        let json = {}
        let channels = document.querySelectorAll(".channel");
        channels.forEach((channel) => {
            let is_active = channel.querySelector("input[name='is_active']").checked
            let key = channel.getAttribute("data-name")
            let providers = channel.querySelector('.select1');
            let name = channel.querySelector("input[name='name']").value
            let icon = channel.querySelector("input[name='icon']").value
            if(is_active) {
                let channel_json = {}
                if(name !== "") {
                    channel_json.name = name
                }
                if(icon !== "") {
                    channel_json.icon = icon
                }
                for (let i = 0; i < providers.length; i++) {
                    if(channel_json.priority === undefined) {
                        channel_json.priority = []
                    }
                    channel_json.priority.push(providers[i].value)
                }
                json[key] = channel_json;
            }

        });
        $.ajax({
            type: "POST",
            url: "save_channels.php",
            data: JSON.stringify(json),
            dataType: "json"
        }).always(function() { alert("Sauvegardé"); });
    }
</script>
</body>
</html>
