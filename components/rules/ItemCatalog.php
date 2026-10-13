<?php

namespace app\components\rules;

/**
 * Каталог предметов — зеркало C# ItemDatabase. Загружается в таблицу `item` миграцией/командой `yii seed/items`.
 * id совпадают с C# (строковые коды), поэтому клиент, сервер и БД ссылаются на одни и те же предметы.
 */
final class ItemCatalog
{
    /** @return array<string, array> id => [name_ru, name_en, type, weight, value, data] */
    public static function all(): array
    {
        $items = [];
        $add = function (string $id, string $ru, string $en, string $type, int $weight, int $value, array $data = []) use (&$items) {
            $items[$id] = ['id' => $id, 'name_ru' => $ru, 'name_en' => $en, 'item_type' => $type, 'weight' => $weight, 'value' => $value, 'data' => $data];
        };
        $armor = fn(int $ac, array $p) => ['ac' => $ac, 'protection' => array_combine(['normal', 'laser', 'fire', 'plasma', 'explosion'], $p)];
        $weapon = fn(string $cat, int $dmin, int $dmax, int $ap, int $range, ?string $cal, int $mag, int $minSt, bool $two = false, string $dt = 'normal', int $burstAp = 0, int $burst = 0, int $blast = 0)
            => compact('cat', 'dmin', 'dmax', 'ap', 'range', 'cal', 'mag', 'minSt', 'two', 'dt', 'burstAp', 'burst', 'blast');

        // Броня: [DT, DR] по типам урона
        $add('robes', 'Роба', 'Robes', 'armor', 10, 90, $armor(5, [[0, 20], [0, 25], [0, 10], [0, 10], [0, 20]]));
        $add('leather_jacket', 'Кожаная куртка', 'Leather Jacket', 'armor', 5, 250, $armor(8, [[0, 20], [0, 20], [0, 10], [0, 10], [0, 20]]));
        $add('leather_armor', 'Кожаная броня', 'Leather Armor', 'armor', 8, 700, $armor(15, [[2, 25], [0, 20], [0, 20], [0, 10], [0, 20]]));
        $add('metal_armor', 'Металлическая броня', 'Metal Armor', 'armor', 35, 1100, $armor(10, [[4, 30], [6, 75], [4, 10], [4, 20], [4, 25]]));
        $add('combat_armor', 'Боевая броня', 'Combat Armor', 'armor', 20, 5000, $armor(20, [[5, 40], [8, 50], [4, 30], [4, 50], [6, 40]]));
        $add('power_armor', 'Силовая броня', 'Power Armor', 'armor', 85, 12500, $armor(25, [[12, 40], [18, 80], [12, 60], [10, 40], [20, 50]]));

        // Патроны: калибр, DM, DRM, мод. КБ
        foreach ([
            ['ammo_10mm_jhp', '10mm JHP', '10mm', 2, 1, 25, 0, 75], ['ammo_10mm_ap', '10mm AP', '10mm', 1, 2, -25, 0, 100],
            ['ammo_44_jhp', '.44 Magnum JHP', '.44', 2, 1, 20, 0, 50], ['ammo_44_fmj', '.44 Magnum FMJ', '.44', 1, 1, -20, -1, 60],
            ['ammo_12ga', '12 ga. картечь', '12ga', 1, 1, 0, -10, 225], ['ammo_5mm_jhp', '5mm JHP', '5mm', 2, 1, 35, 0, 120],
            ['ammo_5mm_ap', '5mm AP', '5mm', 1, 2, -35, 0, 120], ['ammo_mfc', 'Микроплавильный элемент', 'MFC', 1, 1, 0, 0, 1000],
            ['ammo_flamer_fuel', 'Топливо для огнемёта', 'FlamerFuel', 1, 1, 0, -20, 250], ['ammo_rocket_ap', 'Бронебойная ракета', 'RocketAP', 1, 1, -50, -15, 400],
        ] as [$id, $name, $cal, $dm, $dd, $drm, $acm, $val]) {
            $add($id, $name, $name, 'ammo', 0, $val, ['cal' => $cal, 'dm' => $dm, 'dd' => $dd, 'drm' => $drm, 'acm' => $acm]);
        }

        // Оружие
        $add('crowbar', 'Монтировка', 'Crowbar', 'weapon', 5, 65, $weapon('melee', 3, 10, 4, 1, null, 0, 5, true));
        $add('combat_knife', 'Боевой нож', 'Combat Knife', 'weapon', 2, 200, $weapon('melee', 3, 10, 3, 1, null, 0, 2));
        $add('louisville_slugger', 'Луисвилльская бита', 'Louisville Slugger', 'weapon', 4, 2700, $weapon('melee', 12, 30, 3, 1, null, 0, 4, true));
        $add('sledgehammer', 'Кувалда', 'Sledgehammer', 'weapon', 12, 120, $weapon('melee', 4, 9, 4, 1, null, 0, 6, true));
        $add('brass_knuckles', 'Кастет', 'Brass Knuckles', 'weapon', 1, 40, $weapon('unarmed', 2, 5, 3, 1, null, 0, 1));
        $add('spiked_knuckles', 'Шипастый кастет', 'Spiked Knuckles', 'weapon', 1, 250, $weapon('unarmed', 4, 10, 3, 1, null, 0, 1));
        $add('power_fist', 'Силовой кулак', 'Power Fist', 'weapon', 7, 2200, $weapon('unarmed', 12, 24, 3, 1, null, 0, 1));
        $add('10mm_pistol', '10-мм пистолет', '10mm Pistol', 'weapon', 3, 250, $weapon('small_guns', 5, 12, 5, 22, '10mm', 12, 3));
        $add('desert_eagle', 'Desert Eagle', 'Desert Eagle', 'weapon', 4, 1000, $weapon('small_guns', 10, 16, 5, 25, '.44', 8, 4));
        $add('shotgun', 'Дробовик', 'Shotgun', 'weapon', 5, 800, $weapon('small_guns', 12, 22, 5, 14, '12ga', 2, 4, true));
        $add('combat_shotgun', 'Боевой дробовик', 'Combat Shotgun', 'weapon', 10, 2250, $weapon('small_guns', 15, 25, 5, 22, '12ga', 12, 5, true, 'normal', 6, 3));
        $add('44_magnum', '.44 Магнум', '.44 Magnum', 'weapon', 4, 600, $weapon('small_guns', 12, 18, 5, 15, '.44', 6, 5));
        $add('flamer', 'Огнемёт', 'Flamer', 'weapon', 20, 2000, $weapon('big_guns', 45, 90, 6, 5, 'FlamerFuel', 5, 6, true, 'fire'));
        $add('rocket_launcher', 'Ракетница', 'Rocket Launcher', 'weapon', 15, 2300, $weapon('big_guns', 35, 100, 6, 40, 'RocketAP', 1, 6, true, 'explosion', 0, 0, 1));
        $add('minigun', 'Миниган', 'Minigun', 'weapon', 25, 3800, $weapon('big_guns', 7, 11, 0, 35, '5mm', 120, 7, true, 'normal', 6, 40));
        $add('laser_pistol', 'Лазерный пистолет', 'Laser Pistol', 'weapon', 4, 1400, $weapon('energy_weapons', 10, 22, 5, 35, 'MFC', 12, 3, false, 'laser'));
        $add('plasma_rifle', 'Плазменная винтовка', 'Plasma Rifle', 'weapon', 8, 4000, $weapon('energy_weapons', 20, 40, 5, 25, 'MFC', 10, 6, true, 'plasma'));
        $add('gatling_laser', 'Гатлинг-лазер', 'Gatling Laser', 'weapon', 20, 7500, $weapon('energy_weapons', 20, 40, 0, 40, 'MFC', 30, 6, true, 'laser', 6, 10));
        $add('rock', 'Камень', 'Rock', 'weapon', 1, 0, $weapon('throwing', 1, 4, 3, 10, null, 0, 1));
        $add('spear', 'Копьё', 'Spear', 'weapon', 4, 80, $weapon('throwing', 3, 10, 6, 2, null, 0, 4, true));
        $add('grenade_frag', 'Осколочная граната', 'Frag Grenade', 'weapon', 1, 150, $weapon('throwing', 20, 35, 4, 15, null, 0, 3, false, 'explosion', 0, 0, 2));
        $add('grenade_plasma', 'Плазменная граната', 'Plasma Grenade', 'weapon', 1, 300, $weapon('throwing', 40, 90, 4, 15, null, 0, 4, false, 'plasma', 0, 0, 2));

        // Расходники
        foreach ([
            ['stimpak', 'Стимулятор', 'Stimpak', 'drug', 0, 175, 10, 20], ['super_stimpak', 'Суперстимулятор', 'Super Stimpak', 'drug', 1, 225, 75, 75],
            ['hypo', 'Гипо', 'Hypo', 'drug', 0, 500, 75, 100], ['rad_x', 'Рад-Икс', 'Rad-X', 'drug', 0, 300, 0, 0],
            ['radaway', 'Антирадин', 'RadAway', 'drug', 0, 500, 0, 0], ['beer', 'Пиво', 'Beer', 'food', 1, 15, 2, 2],
            ['booze', 'Самогон', 'Booze', 'food', 1, 30, 4, 4], ['fruit', 'Фрукт', 'Fruit', 'food', 1, 10, 4, 4],
            ['iguana_stick', 'Игуана на палочке', 'Iguana-on-a-stick', 'food', 1, 25, 3, 3],
        ] as [$id, $ru, $en, $type, $w, $val, $hmin, $hmax]) {
            $add($id, $ru, $en, $type, $w, $val, ['heal_min' => $hmin, 'heal_max' => $hmax]);
        }

        // Инструменты
        foreach ([
            ['first_aid_kit', 'Аптечка', 'First Aid Kit', 'first_aid', 20, 2, 200], ['doctors_bag', 'Докторская сумка', "Doctor's Bag", 'doctor', 20, 5, 500],
            ['lockpicks', 'Отмычки', 'Lockpicks', 'lockpick', 20, 1, 150], ['exp_lockpick_set', 'Профессиональные отмычки', 'Expanded Lockpick Set', 'lockpick', 40, 1, 500],
            ['electronic_lockpick', 'Электронная отмычка', 'Electronic Lockpick', 'lockpick', 20, 2, 375], ['elec_lockpick_mk2', 'Электронная отмычка Mk II', 'Electronic Lockpick MKII', 'lockpick', 40, 2, 1000],
            ['tool', 'Инструменты', 'Tool', 'repair', 20, 5, 200], ['super_tool_kit', 'Суперинструменты', 'Super Tool Kit', 'repair', 40, 5, 1000],
        ] as [$id, $ru, $en, $skill, $bonus, $w, $val]) {
            $add($id, $ru, $en, 'tool', $w, $val, ['skill' => $skill, 'bonus' => $bonus, 'electronic' => str_starts_with($id, 'elec')]);
        }

        $add('caps', 'Крышки', 'Bottle Caps', 'misc', 0, 1);
        $add('rope', 'Верёвка', 'Rope', 'misc', 10, 25);

        // Ловушки игрока и трофеи
        $add('bear_trap', 'Капкан', 'Bear Trap', 'tool', 6, 120, ['trap' => ['dmin' => 10, 'dmax' => 25, 'blast' => 0, 'knockdown' => true]]);
        $add('land_mine', 'Мина', 'Land Mine', 'tool', 4, 400, ['trap' => ['dmin' => 30, 'dmax' => 60, 'blast' => 1, 'knockdown' => false]]);
        $add('gecko_pelt', 'Шкура геккона', 'Gecko Pelt', 'misc', 1, 25);
        $add('radscorpion_tail', 'Хвост радскорпиона', 'Radscorpion Tail', 'misc', 1, 60);
        $add('deathclaw_hand', 'Лапа когтя смерти', 'Deathclaw Hand', 'misc', 3, 500);
        return $items;
    }

    public static function exists(string $id): bool
    {
        static $ids = null;
        $ids ??= array_fill_keys(array_keys(self::all()), true);
        return isset($ids[$id]);
    }

    /** Стартовый инвентарь нового персонажа. */
    public static function starterInventory(): array
    {
        return [
            ['item_id' => 'combat_knife', 'quantity' => 1, 'slot' => 'item1'],
            ['item_id' => 'stimpak', 'quantity' => 2, 'slot' => ''],
            ['item_id' => 'caps', 'quantity' => 100, 'slot' => ''],
            ['item_id' => 'rock', 'quantity' => 3, 'slot' => ''],
        ];
    }
}
