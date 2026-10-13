<?php
/**
 * Тесты правил без Yii: php tests/rules_test.php
 *  1) формулы CharacterRules (создание персонажа, производные показатели, урон, ремонт) на примерах из ТЗ;
 *  2) паритет с C#: формулы навыков и каталог предметов извлекаются прямо из исходников Unity
 *     (Client/Assets/Scripts/Shared) и сравниваются с PHP-версией.
 */

require __DIR__ . '/../components/rules/CharacterRules.php';
require __DIR__ . '/../components/rules/ItemCatalog.php';

use app\components\rules\CharacterRules as R;
use app\components\rules\ItemCatalog;

$failed = 0; $passed = 0;
function check(string $name, $actual, $expected): void
{
    global $failed, $passed;
    if ($actual === $expected) { $passed++; return; }
    $failed++;
    echo "FAIL: $name\n  ожидалось: " . var_export($expected, true) . "\n  получено:  " . var_export($actual, true) . "\n";
}

// ---------- SPECIAL и экран создания ----------
$ones = [1, 1, 1, 1, 1, 1, 1];
check('33 свободных очка при всех 1', R::freePoints($ones), 33);
$fives = [5, 5, 5, 5, 5, 5, 5];
check('5 свободных очков при всех 5', R::freePoints($fives), 5);
$valid = [6, 6, 6, 6, 6, 5, 5]; // сумма 40
check('корректный персонаж', R::validateCreation('Вася Пупкин', $valid, ['small_guns', 'speech', 'lockpick'], ['gifted', 'small_frame']), []);
check('нераспределённые очки', isset(R::validateCreation('Name', $fives, ['small_guns', 'speech', 'lockpick'], [])['stats']), true);
check('стат > 10', isset(R::validateCreation('Name', [10, 10, 10, 5, 3, 1, 1], ['small_guns', 'speech', 'lockpick'], [])['stats']), false);
check('стат 11 запрещён', isset(R::validateCreation('Name', [11, 10, 9, 4, 3, 2, 1], ['small_guns', 'speech', 'lockpick'], [])['stats']), true);
check('2 тега — ошибка', isset(R::validateCreation('Name', $valid, ['small_guns', 'speech'], [])['tag_skills']), true);
check('повтор тега — ошибка', isset(R::validateCreation('Name', $valid, ['speech', 'speech', 'barter'], [])['tag_skills']), true);
check('3 особенности — ошибка', isset(R::validateCreation('Name', $valid, ['small_guns', 'speech', 'barter'], ['gifted', 'skilled', 'jinxed'])['traits']), true);
check('имя короткое', R::validateName('Ab') !== null, true);
check('имя с символами', R::validateName('<script>') !== null, true);
check('имя кириллица', R::validateName('Избранный'), null);

// ---------- Производные показатели (раздел 1.2) ----------
$d = R::derived($fives, []);
check('HP при всех 5', $d['max_hp'], 15 + 5 + 10);
check('КБ = Ловкость', $d['armor_class'], 5);
check('ОД = 5 + Ловкость/2', $d['action_points'], 7);
check('груз = 25 × Сила', $d['carry_weight'], 125);
check('урон рукопашной', $d['melee_damage'], 1);
check('сопр. ядам 5×ВЫН', $d['poison_resistance'], 25);
check('сопр. радиации 2×ВЫН', $d['radiation_resistance'], 10);
check('лечение', $d['healing_rate'], 1);
check('крит = Удача', $d['critical_chance'], 5);
check('Sequence = 2×ВОС', $d['sequence'], 10);
$d2 = R::derived($fives, ['small_frame', 'bruiser']);
check('Шустрик: груз 25+15×СИЛ (СИЛ 7)', $d2['carry_weight'], 25 + 15 * 7);
check('Вышибала: −2 ОД (ЛВК 6)', $d2['action_points'], 5 + 3 - 2);
$d3 = R::derived($fives, ['kamikaze', 'fast_metabolism']);
check('Камикадзе: КБ 0', $d3['armor_class'], 0);
check('Камикадзе: +5 Sequence', $d3['sequence'], 15);
check('Уск. обмен: яды 0', $d3['poison_resistance'], 0);
check('Уск. обмен: +2 лечение', $d3['healing_rate'], 3);
check('Одарённый: SPECIAL +1 и ограничение 10', R::effectiveStats([10, 9, 1, 1, 1, 9, 9], ['gifted']), [10, 10, 2, 2, 2, 10, 10]);

// ---------- Навыки ----------
check('Лёгкое оружие 5+4×ЛВК', R::skillValue('small_guns', $fives, [], []), 25);
check('Отмеченный навык +20%', R::skillValue('small_guns', $fives, [], ['small_guns']), 45);
check('Отмеченный растёт ×2', R::skillValue('small_guns', $fives, [], ['small_guns'], [10]), 65);
check('Обычный растёт ×1', R::skillValue('small_guns', $fives, [], [], [10]), 35);
check('Торговля 20+2×ХАР', R::skillValue('barter', $fives, [], []), 30);
check('Добродушный: −10 боевым', R::skillValue('small_guns', $fives, ['good_natured'], []), 15);
check('Добродушный: +15 Красноречию', R::skillValue('speech', $fives, ['good_natured'], []), 40);
check('Одарённый: −10 ко всем (с +1 SPECIAL)', R::skillValue('small_guns', $fives, ['gifted'], []), 5 + 4 * 6 - 10);

// ---------- Урон (пример из ТЗ, раздел 4.5) ----------
check('.44 Magnum JHP по броне DT5/DR40: 14 → 10', R::applyArmor(14, 2, 1, 20, 5, 40), 10);
check('урон не уходит в минус', R::applyArmor(3, 1, 1, 0, 12, 40), 0);
check('крит игнорирует DT', R::applyArmor(10, 1, 1, 0, 5, 0, false, true), 10);
check('Меткость −30%', R::applyArmor(10, 1, 1, 0, 0, 0, true), 7);
check('DR ограничен 90%', R::applyArmor(100, 1, 1, 50, 0, 80), 10);

// ---------- Перки ----------
$dp = R::derived($fives, [], 6, null, ['action_boy', 'strong_back', 'strong_back', 'toughness', 'lifegiver', 'educated', 'gain_agility']);
check('Шустрый малый +1 ОД (ЛВК 6)', $dp['action_points'], 5 + 3 + 1);
check('Крепкий хребет ×2 +100 груза', $dp['carry_weight'], 125 + 100);
check('Крепкая шкура +10% СУ', $dp['damage_resistance'], 10);
check('Живчик +4×уровень HP', $dp['max_hp'], 15 + 5 + 10 + 5 * (2 + 2) + 4 * 6);
check('Образованный +2 ОН/уровень', $dp['skill_points_per_level'], 5 + 10 + 2);
check('Вор +10% к Взлому', R::skillValue('lockpick', $fives, [], [], [], ['thief']), 20 + 10);
check('Прибавка к Ловкости → Лёгкое оружие', R::skillValue('small_guns', $fives, [], [], [], ['gain_agility']), 5 + 4 * 6);

// ---------- Ремонт и опыт ----------
check('ремонт 80+40, навык 50', R::repairCombine(80, 40, 50), (int)floor(80 + 2 + 5 + 7.5));
check('ремонт не выше 100', R::repairCombine(95, 90, 100), 100);
check('опыт до 2 уровня', R::experienceForLevel(2), 1000);
check('опыт до 3 уровня', R::experienceForLevel(3), 3000);

// ======================== Паритет с C# ========================
$shared = __DIR__ . '/../../Client/Assets/Scripts/Shared';
if (is_dir($shared)) {
    // 1. Формулы базовых значений навыков: берём лямбды из Skills.cs и вычисляем их в PHP
    $src = file_get_contents("$shared/Skills.cs");
    preg_match_all('/Code = "(\w+)".*?Base = s => (.+?) \}/s', $src, $m, PREG_SET_ORDER);
    check('в Skills.cs 18 навыков', count($m), 18);
    $map = ['Strength' => 0, 'Perception' => 1, 'Endurance' => 2, 'Charisma' => 3, 'Intelligence' => 4, 'Agility' => 5, 'Luck' => 6];
    $samples = [[1, 1, 1, 1, 1, 1, 1], [5, 5, 5, 5, 5, 5, 5], [10, 2, 7, 3, 9, 4, 8], [3, 10, 1, 10, 2, 10, 6]];
    foreach ($m as [, $code, $expr]) {
        $php = preg_replace_callback('/s\[Stat\.(\w+)\]/', fn($x) => '$e[' . $map[$x[1]] . ']', $expr);
        $fn = eval("return function(\$e) { return intdiv((int)($php) * 1, 1); };");
        foreach ($samples as $e) {
            check("C#=PHP навык $code " . implode(',', $e), $fn($e), R::skillBase($code, $e));
        }
    }

    // 2. Каталог предметов: id, вес и цена совпадают
    $items = file_get_contents("$shared/Items.cs");
    $cat = ItemCatalog::all();
    preg_match_all('/Armor\("(\w+)", "[^"]+", "[^"]+", (\d+), (\d+), (\d+)/', $items, $am, PREG_SET_ORDER);
    foreach ($am as [, $id, $ac, $w, $v]) {
        check("броня $id есть в PHP", isset($cat[$id]), true);
        if (isset($cat[$id])) check("броня $id вес/цена/КБ", [$cat[$id]['weight'], $cat[$id]['value'], $cat[$id]['data']['ac']], [(int)$w, (int)$v, (int)$ac]);
    }
    preg_match_all('/Weapon\("(\w+)", "[^"]+", "[^"]+", WeaponCategory\.\w+, (\d+), (\d+), (\d+), (\d+), [^,]+, (\d+), (\d+), (\d+), (\d+)/', $items, $wm, PREG_SET_ORDER);
    check('в Items.cs найдено оружие', count($wm) >= 20, true);
    foreach ($wm as [, $id, $dmin, $dmax, $ap, $range, $mag, $minSt, $w, $v]) {
        check("оружие $id есть в PHP", isset($cat[$id]), true);
        if (!isset($cat[$id])) continue;
        $d = $cat[$id]['data'];
        check("оружие $id параметры", [$d['dmin'], $d['dmax'], $d['ap'], $d['range'], $d['mag'], $d['minSt'], $cat[$id]['weight'], $cat[$id]['value']],
            [(int)$dmin, (int)$dmax, (int)$ap, (int)$range, (int)$mag, (int)$minSt, (int)$w, (int)$v]);
    }
    preg_match_all('/Ammo\("(\w+)", "[^"]+", "[^"]+", (-?\d+), (-?\d+), (-?\d+), (-?\d+), (\d+)\)/', $items, $pm, PREG_SET_ORDER);
    foreach ($pm as [, $id, $dm, $dd, $drm, $acm, $v]) {
        check("патроны $id", isset($cat[$id]) ? [$cat[$id]['data']['dm'], $cat[$id]['data']['dd'], $cat[$id]['data']['drm'], $cat[$id]['data']['acm'], $cat[$id]['value']] : null,
            [(int)$dm, (int)$dd, (int)$drm, (int)$acm, (int)$v]);
    }
    preg_match_all('/(?:Consumable|Tool)\("(\w+)"/', $items, $cm);
    foreach ($cm[1] as $id) check("расходник/инструмент $id есть в PHP", isset($cat[$id]), true);
    preg_match_all('/Id = "(\w+)"/', $items, $om);
    foreach ($om[1] as $id) check("предмет $id есть в PHP", isset($cat[$id]), true);

    // 2б. Радиус взрыва и ловушки
    preg_match_all('/Weapon\("(\w+)".*?blast: (\d+)\)/', $items, $bm, PREG_SET_ORDER);
    check('в Items.cs есть взрывчатка', count($bm) >= 3, true);
    foreach ($bm as [, $id, $blast]) check("радиус взрыва $id", $cat[$id]['data']['blast'] ?? null, (int)$blast);
    preg_match_all('/Id = "(\w+)"[^;]*?Trap = new TrapStats \{ DamageMin = (\d+), DamageMax = (\d+)/', $items, $trm, PREG_SET_ORDER);
    check('в Items.cs две ловушки', count($trm), 2);
    foreach ($trm as [, $id, $dmin, $dmax]) check("ловушка $id", [$cat[$id]['data']['trap']['dmin'] ?? null, $cat[$id]['data']['trap']['dmax'] ?? null], [(int)$dmin, (int)$dmax]);

    // 2в. Перки: список id совпадает
    preg_match_all('/new PerkDef \{ Id = "(\w+)"/', file_get_contents("$shared/Perks.cs"), $pk);
    check('перки C#=PHP', $pk[1], R::PERKS);

    // 3. Порядок навыков и особенностей (часть протокола и схемы БД)
    preg_match_all('/Code = "(\w+)", NameRu/', file_get_contents("$shared/Traits.cs"), $tm);
    check('порядок особенностей C#=PHP', $tm[1], R::TRAITS);
    check('порядок навыков C#=PHP', array_column($m, 1), R::SKILLS);
} else {
    echo "Пропуск сверки с C#: каталог $shared не найден\n";
}

echo "\nПройдено: $passed, провалено: $failed\n";
exit($failed > 0 ? 1 : 0);
