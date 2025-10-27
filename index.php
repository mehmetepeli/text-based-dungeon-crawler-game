<?php

class Room {
    public $id;
    public $description;
    public $monsters = [];
    public $items = [];
    public $exits = []; // ['north' => roomId, ...]
    public $isExit = false;
    public $visited = false;

    public function __construct($id, $desc) {
        $this->id = $id;
        $this->description = $desc;
    }
}

class Monster {
    public $name;
    public $hp;
    public $damage;

    public function __construct($name, $hp, $damage) {
        $this->name = $name;
        $this->hp = $hp;
        $this->damage = $damage;
    }

    public function isAlive() {
        return $this->hp > 0;
    }
}

class Item {
    public $name;
    public $type; // 'potion' or 'weapon'
    public $value;

    public function __construct($name, $type, $value) {
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
    }
}

class Player {
    public $hp = 30;
    public $baseDamage = 5;
    public $damage = 5;
    public $score = 0;
    public $inventory = [];
    public $currentRoom = 0;
    public $visitedRooms = [];

    public function isAlive() {
        return $this->hp > 0;
    }

    public function addItem(Item $item) {
        $this->inventory[] = $item;
    }

    public function hasWeapon() {
        foreach ($this->inventory as $item) {
            if ($item->type === 'weapon') return true;
        }
        return false;
    }

    public function reCalcDamage() {
        // Base damage plus all weapon bonuses
        $this->damage = $this->baseDamage;
        foreach ($this->inventory as $item) {
            if ($item->type === 'weapon') {
                $this->damage += $item->value;
            }
        }
    }
}

class Game {
    public $rooms = [];
    public $player;
    private $maxRooms = 10;

    public function __construct() {
        $this->player = new Player();
        $this->generateDungeon();
    }

    private function generateDungeon() {
        // Generate N rooms with descriptions, randomly connected

        $descriptions = [
            "a dark, musty chamber",
            "a glowing cavern with crystals",
            "a cold prison cell",
            "a damp cave dripping with water",
            "a dusty library with old books",
            "a room with eerie silence",
            "a hall filled with strange noises",
            "a chamber with ancient carvings",
            "a burnt room with smoldering ashes",
            "a grand hall with mossy floors",
        ];

        // Create rooms
        for ($i = 0; $i < $this->maxRooms; $i++) {
            $desc = "You are in " . $descriptions[array_rand($descriptions)] . ".";
            $this->rooms[$i] = new Room($i, $desc);
        }

        // Mark last room as exit
        $this->rooms[$this->maxRooms - 1]->isExit = true;
        $this->rooms[$this->maxRooms - 1]->description .= " There is a glowing EXIT here!";

        // Randomly connect rooms to create a solvable dungeon with at least one path from start(0) to exit(maxRooms-1)
        // We'll first create a linear path from 0 to end
        for ($i = 0; $i < $this->maxRooms - 1; $i++) {
            $this->connectRooms($i, $i+1);
        }

        // Add some extra random connections to create loops
        $directions = ['north', 'south', 'east', 'west'];
        for ($i = 0; $i < $this->maxRooms; $i++) {
            $room = $this->rooms[$i];
            // Add 0-2 extra random exits
            $numExits = count($room->exits);
            $extra = rand(0, 2);
            for ($j=0; $j < $extra; $j++) {
                $target = rand(0, $this->maxRooms - 1);
                if ($target != $i && !in_array($target, $room->exits)) {
                    // Find open direction for this room and target room
                    $dir1 = $this->findFreeDirection($room->exits);
                    $dir2 = $this->findFreeDirection($this->rooms[$target]->exits);
                    if ($dir1 !== null && $dir2 !== null) {
                        $room->exits[$dir1] = $target;
                        $this->rooms[$target]->exits[$dir2] = $i;
                    }
                }
            }
        }

        // Add monsters randomly (60% chance per room except start and exit)
        $monsterTypes = [
            new Monster("Goblin", 10, 3),
            new Monster("Skeleton", 15, 4),
            new Monster("Orc", 20, 5),
        ];

        for ($i=1; $i < $this->maxRooms -1; $i++) {
            if (rand(0, 100) < 60) {
                // One or two monsters
                $count = rand(1,2);
                for ($c=0; $c < $count; $c++) {
                    $m = clone $monsterTypes[array_rand($monsterTypes)];
                    $this->rooms[$i]->monsters[] = $m;
                }
            }
        }

        // Add items randomly (40% chance per room)
        $itemPool = [
            new Item("Small Healing Potion", "potion", 10),
            new Item("Medium Healing Potion", "potion", 20),
            new Item("Sword (+3 dmg)", "weapon", 3),
            new Item("Axe (+5 dmg)", "weapon", 5),
        ];

        for ($i=1; $i < $this->maxRooms - 1; $i++) {
            if (rand(0, 100) < 40) {
                $item = clone $itemPool[array_rand($itemPool)];
                $this->rooms[$i]->items[] = $item;
            }
        }
    }

    private function connectRooms($roomA, $roomB) {
        // Connect two rooms bidirectionally with free directions
        $room1 = $this->rooms[$roomA];
        $room2 = $this->rooms[$roomB];

        // find free directions for both rooms
        $dir1 = $this->findFreeDirection($room1->exits);
        $dir2 = $this->findFreeDirection($room2->exits);

        if ($dir1 !== null && $dir2 !== null) {
            $room1->exits[$dir1] = $roomB;
            $room2->exits[$dir2] = $roomA;
        } else {
            // fallback: assign east-west if possible
            if(!isset($room1->exits['east']) && !isset($room2->exits['west'])) {
                $room1->exits['east'] = $roomB;
                $room2->exits['west'] = $roomA;
            }
        }
    }

    private function findFreeDirection(array $exits) {
        $directions = ['north', 'south', 'east', 'west'];
        foreach ($directions as $d) {
            if (!isset($exits[$d])) return $d;
        }
        return null;
    }

    public function start() {
        echo "Welcome to the Enhanced Dungeon Crawler!\n";
        $this->player->currentRoom = 0;

        while (true) {
            $this->playTurn();

            if (!$this->player->isAlive()) {
                echo "You died! Game Over.\n";
                break;
            }
            if ($this->rooms[$this->player->currentRoom]->isExit) {
                echo "You found the exit! You win!\n";
                echo "Final Score: " . $this->player->score . "\n";
                break;
            }
        }
        echo "Thanks for playing!\n";
    }

    private function playTurn() {
        $room = $this->rooms[$this->player->currentRoom];
        $room->visited = true;
        $this->player->visitedRooms[$room->id] = true;

        echo "\n" . $room->description . "\n";

        // Show exits
        echo "Exits: " . implode(", ", array_keys($room->exits)) . "\n";

        // Show map
        $this->drawMap();

        // Show items in room
        if (!empty($room->items)) {
            echo "You see these items: ";
            $names = [];
            foreach ($room->items as $item) {
                $names[] = $item->name;
            }
            echo implode(", ", $names) . "\n";
        }

        // Pick up items prompt
        if (!empty($room->items)) {
            $input = $this->prompt("Pick up items? (yes/no): ");
            if (strtolower($input) === 'yes') {
                foreach ($room->items as $item) {
                    $this->player->addItem($item);
                    echo "Picked up: {$item->name}\n";
                    // Auto heal if potion
                    if ($item->type === 'potion') {
                        $healAmount = $item->value;
                        $this->player->hp += $healAmount;
                        echo "You drink the {$item->name} and heal $healAmount HP. Current HP: {$this->player->hp}\n";
                    }
                }
                $room->items = [];
                $this->player->reCalcDamage();
            }
        }

        // Show player's HP and damage
        echo "Your HP: {$this->player->hp} | Your Damage: {$this->player->damage}\n";

        // Handle monsters
        if (!empty($room->monsters)) {
            foreach ($room->monsters as $key => $monster) {
                if ($monster->isAlive()) {
                    echo "You encounter a {$monster->name} with {$monster->hp} HP!\n";
                    $this->combat($monster);
                    if (!$this->player->isAlive()) return; // player died in combat
                    if (!$monster->isAlive()) {
                        echo "You defeated the {$monster->name}!\n";
                        $reward = rand(10, 30);
                        echo "You loot $reward gold!\n";
                        $this->player->score += $reward;
                        unset($room->monsters[$key]);
                    }
                }
            }
            $room->monsters = array_values($room->monsters);
        }

        // Prompt for next action
        while (true) {
            $input = $this->prompt("Enter direction (north/south/east/west) or 'save'/'load': ");
            $input = strtolower(trim($input));

            if ($input === 'save') {
                $this->saveGame();
                continue;
            } elseif ($input === 'load') {
                $success = $this->loadGame();
                if ($success) {
                    echo "Game loaded successfully.\n";
                } else {
                    echo "No saved game found.\n";
                }
                continue;
            }

            if (in_array($input, ['north','south','east','west'])) {
                if (isset($room->exits[$input])) {
                    $this->player->currentRoom = $room->exits[$input];
                    echo "You move $input.\n";
                    break;
                } else {
                    echo "No exit that way! Try again.\n";
                }
            } else {
                echo "Invalid command. Try again.\n";
            }
        }
    }

    private function combat(Monster $monster) {
        echo "Combat starts! You have {$this->player->hp} HP. The {$monster->name} has {$monster->hp} HP.\n";
        while ($this->player->isAlive() && $monster->isAlive()) {
            // Player attacks first
            $monster->hp -= $this->player->damage;
            echo "You hit the {$monster->name} for {$this->player->damage} damage. Monster HP: " . max(0, $monster->hp) . "\n";
            if (!$monster->isAlive()) break;

            // Monster attacks
            $this->player->hp -= $monster->damage;
            echo "The {$monster->name} hits you for {$monster->damage} damage. Your HP: " . max(0, $this->player->hp) . "\n";
        }
    }

    private function prompt($msg) {
        echo $msg;
        return trim(fgets(STDIN));
    }

    private function drawMap() {
        // Show simple map for 10 rooms
        // We'll attempt a 5x2 grid for maxRooms=10

        echo "\nDungeon Map (visited rooms marked [X]):\n";

        $gridWidth = 5;
        $gridHeight = 2;

        // We'll display rooms by their ID from 0 to maxRooms-1 in 2 rows

        for ($row=0; $row < $gridHeight; $row++) {
            $line = "";
            for ($col=0; $col < $gridWidth; $col++) {
                $roomId = $row * $gridWidth + $col;
                if ($roomId >= $this->maxRooms) {
                    $line .= "     ";
                    continue;
                }
                $symbol = " O ";
                if ($roomId === $this->player->currentRoom) {
                    $symbol = "[P]";
                } elseif ($this->rooms[$roomId]->visited) {
                    $symbol = "[X]";
                }
                $line .= $symbol . " ";
            }
            echo $line . "\n";
        }
        echo "\n";
    }

    private function saveGame() {
        $state = [
            'player' => $this->serializePlayer(),
            'rooms' => $this->serializeRooms(),
            'maxRooms' => $this->maxRooms,
        ];
        file_put_contents("savegame.json", json_encode($state));
        echo "Game saved to savegame.json\n";
    }

    private function loadGame() {
        if (!file_exists("savegame.json")) return false;
        $json = file_get_contents("savegame.json");
        $state = json_decode($json, true);
        if (!$state) return false;

        $this->maxRooms = $state['maxRooms'];
        $this->rooms = $this->deserializeRooms($state['rooms']);
        $this->player = $this->deserializePlayer($state['player']);
        return true;
    }

    private function serializePlayer() {
        return [
            'hp' => $this->player->hp,
            'baseDamage' => $this->player->baseDamage,
            'damage' => $this->player->damage,
            'score' => $this->player->score,
            'inventory' => array_map(function($item){
                return ['name'=>$item->name, 'type'=>$item->type, 'value'=>$item->value];
            }, $this->player->inventory),
            'currentRoom' => $this->player->currentRoom,
            'visitedRooms' => $this->player->visitedRooms,
        ];
    }

    private function deserializePlayer(array $data) {
        $p = new Player();
        $p->hp = $data['hp'];
        $p->baseDamage = $data['baseDamage'];
        $p->damage = $data['damage'];
        $p->score = $data['score'];
        $p->inventory = [];
        foreach ($data['inventory'] as $itemData) {
            $p->inventory[] = new Item($itemData['name'], $itemData['type'], $itemData['value']);
        }
        $p->currentRoom = $data['currentRoom'];
        $p->visitedRooms = $data['visitedRooms'];
        return $p;
    }

    private function serializeRooms() {
        $arr = [];
        foreach ($this->rooms as $room) {
            $arr[$room->id] = [
                'id' => $room->id,
                'description' => $room->description,
                'monsters' => array_map(function($m){
                    return ['name'=>$m->name, 'hp'=>$m->hp, 'damage'=>$m->damage];
                }, $room->monsters),
                'items' => array_map(function($i){
                    return ['name'=>$i->name, 'type'=>$i->type, 'value'=>$i->value];
                }, $room->items),
                'exits' => $room->exits,
                'isExit' => $room->isExit,
                'visited' => $room->visited,
            ];
        }
        return $arr;
    }

    private function deserializeRooms(array $data) {
        $rooms = [];
        foreach ($data as $id => $roomData) {
            $room = new Room($roomData['id'], $roomData['description']);
            $room->monsters = [];
            foreach ($roomData['monsters'] as $mData) {
                $room->monsters[] = new Monster($mData['name'], $mData['hp'], $mData['damage']);
            }
            $room->items = [];
            foreach ($roomData['items'] as $iData) {
                $room->items[] = new Item($iData['name'], $iData['type'], $iData['value']);
            }
            $room->exits = $roomData['exits'];
            $room->isExit = $roomData['isExit'];
            $room->visited = $roomData['visited'] ?? false;
            $rooms[$id] = $room;
        }
        return $rooms;
    }
}

$game = new Game();
$game->start();