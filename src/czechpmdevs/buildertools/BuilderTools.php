<?php

declare(strict_types=1);

namespace czechpmdevs\buildertools;

use czechpmdevs\buildertools\commands\biome\BiomeCommand;
use czechpmdevs\buildertools\commands\clipboard\ClearClipboardCommand;
use czechpmdevs\buildertools\commands\clipboard\CopyCommand;
use czechpmdevs\buildertools\commands\clipboard\CutCommand;
use czechpmdevs\buildertools\commands\clipboard\FlipCommand;
use czechpmdevs\buildertools\commands\clipboard\MergeCommand;
use czechpmdevs\buildertools\commands\clipboard\PasteCommand;
use czechpmdevs\buildertools\commands\clipboard\RotateCommand;
use czechpmdevs\buildertools\commands\generation\CubeCommand;
use czechpmdevs\buildertools\commands\generation\CylinderCommand;
use czechpmdevs\buildertools\commands\generation\DrawCommand;
use czechpmdevs\buildertools\commands\generation\HollowCubeCommand;
use czechpmdevs\buildertools\commands\generation\HollowCylinderCommand;
use czechpmdevs\buildertools\commands\generation\HollowPyramidCommand;
use czechpmdevs\buildertools\commands\generation\HollowSphereCommand;
use czechpmdevs\buildertools\commands\generation\IslandCommand;
use czechpmdevs\buildertools\commands\generation\PyramidCommand;
use czechpmdevs\buildertools\commands\generation\SphereCommand;
use czechpmdevs\buildertools\commands\generation\TreeCommand;
use czechpmdevs\buildertools\commands\HelpCommand;
use czechpmdevs\buildertools\commands\history\RedoCommand;
use czechpmdevs\buildertools\commands\history\UndoCommand;
use czechpmdevs\buildertools\commands\region\CenterCommand;
use czechpmdevs\buildertools\commands\region\DecorationCommand;
use czechpmdevs\buildertools\commands\region\DrainCommand;
use czechpmdevs\buildertools\commands\region\FillCommand;
use czechpmdevs\buildertools\commands\region\LineCommand;
use czechpmdevs\buildertools\commands\region\MoveCommand;
use czechpmdevs\buildertools\commands\region\NaturalizeCommand;
use czechpmdevs\buildertools\commands\region\OutlineCommand;
use czechpmdevs\buildertools\commands\region\ReplaceCommand;
use czechpmdevs\buildertools\commands\region\StackCommand;
use czechpmdevs\buildertools\commands\region\WallsCommand;
use czechpmdevs\buildertools\commands\schematics\SchematicCommand;
use czechpmdevs\buildertools\commands\selection\ChunkCommand;
use czechpmdevs\buildertools\commands\selection\FirstPositionCommand;
use czechpmdevs\buildertools\commands\selection\SecondPositionCommand;
use czechpmdevs\buildertools\commands\selection\SelectionCommand;
use czechpmdevs\buildertools\commands\selection\WandCommand;
use czechpmdevs\buildertools\commands\utility\BlockInfoCommand;
use czechpmdevs\buildertools\commands\utility\ClearInventoryCommand;
use czechpmdevs\buildertools\commands\utility\FixCommand;
use czechpmdevs\buildertools\commands\utility\IdCommand;
use czechpmdevs\buildertools\commands\utility\MaskCommand;
use czechpmdevs\buildertools\event\listener\EventListener;
use czechpmdevs\buildertools\schematics\SchematicsManager;
use pocketmine\command\Command;
use pocketmine\item\ItemFactory;
use pocketmine\item\VanillaItems;
use pocketmine\item\StringToItemParser;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\AssumptionFailedError;
use function array_key_exists;
use function is_dir;
use function is_file;
use function mkdir;
use function unlink;

class BuilderTools extends PluginBase {
    public const CURRENT_CONFIG_VERSION = "1.4.0.2";

    private static BuilderTools $instance;
    private static Configuration $configuration;
    private static Limits $limits;

    /** @var Command[] */
    private static array $commands = [];

    protected function onLoad(): void {
        $this->registerItems();
    }

    protected function onEnable(): void {
        BuilderTools::$instance = $this;

        $this->initConfig();
        $this->cleanCache();
        $this->registerCommands();
        $this->initListener();
        $this->sendWarnings();
        $this->loadSchematicsManager();
    }

    protected function onDisable(): void {
        $this->cleanCache();
    }

    private function initConfig(): void {
        if (!is_dir($this->getDataFolder() . "schematics")) {
            @mkdir($this->getDataFolder() . "schematics");
        }
        if (!is_dir($this->getDataFolder() . "sessions")) {
            @mkdir($this->getDataFolder() . "sessions");
        }
        if (!is_file($this->getDataFolder() . "data/bedrock_block_states_map.json")) {
            $this->saveResource("data/bedrock_block_states_map.json");
        }
        if (!is_file($this->getDataFolder() . "data/java_block_states_map.json")) {
            $this->saveResource("data/java_block_states_map.json");
        }

        $configuration = $this->getConfig()->getAll();
        self::$configuration = new Configuration($this->getConfig()->getAll());
        self::$limits = new Limits(
            self::$configuration->getIntProperty("clipboard-limit"),
            self::$configuration->getIntProperty("fill-limit")
        );
    }

    private function initListener(): void {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
    }

    private function registerCommands(): void {
        $map = $this->getServer()->getCommandMap();
        BuilderTools::$commands = [
            new BiomeCommand, new BlockInfoCommand, new CenterCommand, new ChunkCommand, new ClearClipboardCommand,
            new ClearInventoryCommand, new CopyCommand, new CubeCommand, new CutCommand, new CylinderCommand,
            new DecorationCommand, new DrainCommand, new DrawCommand, new FillCommand, new FirstPositionCommand,
            new FixCommand, new FlipCommand, new HelpCommand, new HollowCubeCommand, new HollowCylinderCommand,
            new IdCommand, new IslandCommand, new LineCommand, new MergeCommand, new MaskCommand, new MoveCommand,
            new NaturalizeCommand, new OutlineCommand, new PasteCommand, new PyramidCommand, new RedoCommand,
            new ReplaceCommand, new RotateCommand, new SchematicCommand, new SecondPositionCommand,
            new SelectionCommand, new SphereCommand, new StackCommand, new TreeCommand, new UndoCommand,
            new WallsCommand, new WandCommand
        ];

        foreach (self::$commands as $command) {
            $map->register("BuilderTools", $command);
        }

        HelpCommand::buildPages();
    }

    public function registerItems(): void {
        // Only register if it's not already in the parser
        if (StringToItemParser::getInstance()->lookup("wooden_axe") === null) {
            StringToItemParser::getInstance()->register("wooden_axe", fn() => VanillaItems::WOODEN_AXE());
        }
    }


    private function sendWarnings(): void {
        if ($this->getServer()->getConfigGroup()->getProperty("memory.async-worker-hard-limit") !== 0) {
            $this->getServer()->getLogger()->warning("We recommend disabling 'memory.async-worker-hard-limit' in pocketmine.yml.");
        }
    }

    private function loadSchematicsManager(): void {
        SchematicsManager::lazyInit();
    }
}
