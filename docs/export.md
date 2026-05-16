# Formats d'export (`export_handlers`)

Le champ `export_handlers` dans `config/config.json` est un tableau d'objets définissant les formats de sortie générés après la récupération de l'EPG. Chaque objet a deux champs : `class` (nom du handler) et `params` (paramètres spécifiques au handler).

```json
"export_handlers": [
  {"class": "GZExport", "params": {}},
  {"class": "ZIPExport", "params": {}},
  {"class": "CommandLineExport", "params": {"command": "...", "extension": "XZ"}}
]
```

Plusieurs handlers peuvent être actifs simultanément. Le fichier XML brut est toujours généré en premier, puis chaque handler est appliqué dans l'ordre.

## GZExport

Compresse le fichier XML en `.gz` via la bibliothèque zlib de PHP. Aucun paramètre requis.

```json
{"class": "GZExport", "params": {}}
```

Produit : `xmltv.xml.gz`

## ZIPExport

Compresse le fichier XML en `.zip` via l'extension PHP zip. Aucun paramètre requis.

```json
{"class": "ZIPExport", "params": {}}
```

Produit : `xmltv.zip`

## CommandLineExport

Permet d'utiliser n'importe quel outil externe pour exporter le fichier XML. Utile pour des formats non supportés nativement (XZ, 7z, etc.) ou pour envoyer le fichier vers un service tiers.

```json
{"class": "CommandLineExport", "params": {
  "command": "7z a \"{exportPath}{fileName}.7z\" \"{rawXMLFilePath}\"",
  "extension": "7Z",
  "success_regex": "Everything is Ok"
}}
```

### Paramètres

| Paramètre | Obligatoire | Description |
|-----------|-------------|-------------|
| `command` | Oui | Commande shell à exécuter |
| `extension` | Non | Extension du fichier produit, affichée dans les logs (défaut : `"Inconnue"`) |
| `success_regex` | Non | Expression régulière testée sur la sortie stdout de la commande. Si absent ou vide, tout retour est considéré comme un succès. |

### Variables disponibles dans `command`

| Variable | Valeur |
|----------|--------|
| `{rawXMLFilePath}` | Chemin complet du fichier XML brut (ex: `var/export/xmltv.xml`) |
| `{fileName}` | Nom de base sans extension (ex: `xmltv`) |
| `{xmlContent}` | Contenu texte complet du fichier XML (utile pour piper vers stdin) |
| `{exportPath}` | Chemin du dossier d'export avec slash final (ex: `var/export/`) |

### Exemples

**Compression XZ avec 7zip (Windows) :**
```json
{"class": "CommandLineExport", "params": {
  "command": "\"C:\\Program Files\\7-Zip\\7z.exe\" a -txz \"{exportPath}{fileName}.xz\" \"{rawXMLFilePath}\"",
  "extension": "XZ",
  "success_regex": "Everything is Ok"
}}
```

**Compression XZ avec xz (Linux) :**
```json
{"class": "CommandLineExport", "params": {
  "command": "xz -k \"{rawXMLFilePath}\" -c > \"{exportPath}{fileName}.xz\"",
  "extension": "XZ"
}}
```

**Envoi vers un serveur FTP :**
```json
{"class": "CommandLineExport", "params": {
  "command": "curl -T \"{rawXMLFilePath}\" ftp://user:pass@monserveur.fr/xmltv.xml",
  "extension": "FTP"
}}
```

> Note : plusieurs entrées `CommandLineExport` peuvent coexister dans `export_handlers`.

## Supprimer le XML brut

Si vous ne souhaitez conserver que les fichiers compressés, activez `delete_raw_xml` dans la configuration :

```json
"delete_raw_xml": true
```

Le fichier `.xml` brut sera supprimé après que tous les handlers ont été appliqués.
