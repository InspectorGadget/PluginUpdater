<?php
/**
 * Created by PhpStorm.
 * User: RTG
 * Date: 2/6/2019
 * Time: 1:32 PM
 *
 * .___   ________
 * |   | /  _____/
 * |   |/   \  ___
 * |   |\    \_\  \
 * |___| \______  /
 *              \/
 *
 * All rights reserved InspectorGadget (c) 2019
 */

namespace InspectorGadget\PluginUpdater;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as TF;

class Loader extends PluginBase {

    public function onEnable(): void {
        $this->getLogger()->info("Starting up...");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch(strtolower($command->getName())) {
            case "update":

                if (!$sender->isOp()) {
                    $sender->sendMessage(TF::RED . "You have no permission to use this command!");
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage(TF::GREEN . "[PluginUpdater] /update <plugin> | Gathers plugin update from Poggit!");
                    return true;
                }

                $this->checkForUpdate($sender, $args[0]);
                return true;
            break;
        }
    }

    public function checkForUpdate($sender, $name) {
        if ($this->returnPoggitQuery($name) === "null" || $this->returnPluginQuery($name) === "null") {
            $sender->sendMessage(TF::RED . "[PluginUpdater] Plugin name $name is not installed or doesn't exist in Poggit Database! Please verify the plugin name from /plugins");
            return true;
        }
        $newData = (array) $this->returnPoggitQuery($name);
        $currentData = (array) $this->returnPluginQuery($name);

        if ($newData['version'] > $currentData['version']) {
            $sender->sendMessage("Starting update");
            $this->replacePhar($sender, $currentData, $newData);
        } else {
            $sender->sendMessage(TF::GREEN . "[PluginUpdater] You are using the latest version ({$currentData['version']}) of {$name}, no need for an update!");
        }
    }

    public function returnPluginQuery($name) {
        $getPlugin = $this->getServer()->getPluginManager()->getPlugin($name);
        if (!$getPlugin) {
            return "null";
        }
        $currentData = array(
            "name" => $getPlugin->getDescription()->getName(),
            "version" => $getPlugin->getDescription()->getVersion()
        );

        return (array) $currentData;
    }

    public function returnPoggitQuery($name) {
        $JSON = json_decode(file_get_contents("http://poggit.pmmp.io/releases.json?name={$name}"), true);
        if (!$JSON) {
            return "null";
        }
        $pluginName = $JSON[0]['name'];
        $pluginVersion = $JSON[0]['version'];
        $downloadURL = $JSON[0]['artifact_url'] . "/{$pluginName}-{$pluginVersion}.phar";

        $data = array(
            "name" => $pluginName,
            "version" => $pluginVersion,
            "download" => $downloadURL
        );

        return (array) $data;
    }

    public function replacePhar($sender, $currentData, $newData) {
        $plugins = scandir($this->getServer()->getDataPath() . "plugins/");
        foreach ($plugins as $plugin) {
            if ($plugin === "." || $plugin === "..") {
                continue;
            }

            $oldV = $currentData['version'];
            $newV = $newData['version'];
            if ($plugin === "{$currentData['name']}.phar") {
                $sender->sendMessage(TF::GREEN . "[PluginUpdater] Success! Found .phar for plugin NetBan Updating from V {$oldV} -> V {$newV}");
                file_put_contents($this->getDataFolder() . "{$newData['name']}.phar", file_get_contents($newData['download']));
                if (file_exists($this->getServer()->getDataPath() . "plugins/{$currentData['name']}.phar")
                    || file_exists(strtolower($this->getServer()->getDataPath() . "plugins/{$currentData['name']}.phar")) // For files with lowercase names
                )
                {
                    unlink($this->getServer()->getDataPath() . "plugins/{$currentData['name']}.phar");
                    rename($this->getDataFolder() . "{$newData['name']}.phar", $this->getServer()->getDataPath() . "plugins/{$currentData['name']}.phar");
                    $sender->sendMessage("[PluginUpdater] Plugin {$currentData['name']} has been updated! Reboot the server to take action!");
                    return true;
                }
            } else {
            	$sender->sendMessage(TF::RED . "[PluginUpdater] Ermm, contact your Server Administrator. Please follow the Plugin Name Guidelines!");
            }
            continue;
        }
    }

    public function onDisable(): void { }

}