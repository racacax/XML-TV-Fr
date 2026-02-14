<?php

namespace racacax\XmlTv\Component\Export;

/**
 * To use it in config, add this to export_handlers:
 * {"class": "CommandLineExport", "params": {"command": "your_command", "description": "command_description"}}
 * command can contain some variables:
 * - {rawXMLFilePath}: Full source path of uncompressed XML File
 * - {fileName}: Base filename provided in config (e.g: xmltv). Should be without extension
 * - {xmlContent}: String containing all the content of uncompressed XML File
 * - {exportPath}: Path to export directory
 * Note: You don't need to use these variables (e.g. You most likely won't need both xmlContent and rawXMLFilePath, only one of them)
 * Example with 7zip with XZ compression:
 * {"class": "CommandLineExport", "params": {"command": "\"C:\\Program\\7zip\\7z_cli.exe\" a -t7z \"{exportPath}{fileName}.xz\" \"{rawXMLFilePath}\"", "extension": "XZ", "success_regex": ""}}
 * Note: You can add multiple entries of CommandLineExport in config
 */
class CommandLineExport extends AbstractExport implements ExportInterface
{
    private string $command;
    private string $extension;
    private ?string $successRegex;
    public function __construct(array $params)
    {
        if (!isset($params['command'])) {
            throw new \Exception('Missing "command" parameter for class CommandLineExport');
        }
        $this->command = $params['command'];
        $this->extension = @$params['extension'] ? $params['extension'] : 'Inconnue';
        $this->successRegex = @$params['success_regex'];
    }

    public function export(string $exportPath, string $fileName, string $xmlContent): bool
    {
        $command = str_replace('{rawXMLFilePath}', $exportPath.$fileName.'.xml', $this->command);
        $command = str_replace('{xmlContent}', $xmlContent, $command);
        $command = str_replace('{exportPath}', $exportPath, $command);
        $command = str_replace('{fileName}', $fileName, $command);
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $this->setStatus('Commande lancée');
        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $this->setStatus("Résultat: $output");

            return !$this->successRegex || preg_match($this->successRegex, $output);
        }

        return false;
    }
    public function getExtension(): string
    {
        return $this->extension;
    }
}
