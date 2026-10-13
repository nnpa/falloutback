<?php

namespace app\components\rules;

/**
 * Правила персонажа — зеркало C# F2O.Shared (CharacterSheet / CharacterCreationRules / SkillInfo / TraitInfo).
 * Бэкенд никогда не доверяет клиенту: при создании персонажа всё пересчитывается здесь.
 * Формулы должны совпадать с C# — это проверяет tests/rules_test.php.
 *
 * Не зависит от Yii, поэтому тестируется обычным php.
 */
final class CharacterRules
{
    public const STAT_CODES = ['ST', 'PE', 'EN', 'CH', 'IN', 'AG', 'LK'];
    public const STAT_MIN = 1;
    public const STAT_MAX = 10;
    public const FREE_POINTS_AT_ALL_ONES = 33;
    public const TOTAL_POINTS = 7 * self::STAT_MIN + self::FREE_POINTS_AT_ALL_ONES; // 40

    public const TAG_COUNT = 3;
    public const TAG_BONUS = 20;
    public const MAX_TRAITS = 2;
    public const NAME_MIN = 3;
    public const NAME_MAX = 20;

    // Индексы SPECIAL
    public const ST = 0, PE = 1, EN = 2, CH = 3, IN = 4, AG = 5, LK = 6;

    /** Порядок навыков совпадает с enum Skill в C#. */
    public const SKILLS = [
        'small_guns', 'big_guns', 'energy_weapons', 'unarmed', 'melee_weapons', 'throwing',
        'first_aid', 'doctor', 'sneak', 'lockpick', 'steal', 'traps', 'science', 'repair',
        'speech', 'barter', 'gambling', 'outdoorsman',
    ];
    public const COMBAT_SKILLS = ['small_guns', 'big_guns', 'energy_weapons', 'unarmed', 'melee_weapons', 'throwing'];

    /** Порядок особенностей совпадает с enum Trait в C#. */
    public const TRAITS = [
        'bloody_mess', 'bruiser', 'chem_reliant', 'chem_resistant', 'fast_metabolism', 'fast_shot', 'finesse',
        'gifted', 'good_natured', 'heavy_handed', 'jinxed', 'kamikaze', 'one_hander', 'sex_appeal', 'skilled', 'small_frame',
    ];

    public static function clampStat(int $v): int
    {
        return max(self::STAT_MIN, min(self::STAT_MAX, $v));
    }

    public static function freePoints(array $stats): int
    {
        return self::TOTAL_POINTS - array_sum($stats);
    }

    /** Перки «Прибавка к ...» по индексу SPECIAL. */
    public const GAIN_PERKS = ['gain_strength', 'gain_perception', 'gain_endurance', 'gain_charisma', 'gain_intelligence', 'gain_agility', 'gain_luck'];

    /** Число рангов перка (повтор id в списке = следующий ранг). */
    public static function ranks(array $perks, string $id): int
    {
        return count(array_filter($perks, fn($p) => $p === $id));
    }

    /** SPECIAL с учётом особенностей (Вышибала +2 СИЛ, Шустрик +1 ЛВК, Одарённый +1 ко всем) и перков «Прибавка», 1..10. */
    public static function effectiveStats(array $stats, array $traits, array $perks = []): array
    {
        $out = [];
        foreach ($stats as $i => $v) {
            $m = self::ranks($perks, self::GAIN_PERKS[$i]);
            if (in_array('gifted', $traits, true)) $m += 1;
            if ($i === self::ST && in_array('bruiser', $traits, true)) $m += 2;
            if ($i === self::AG && in_array('small_frame', $traits, true)) $m += 1;
            $out[$i] = self::clampStat($v + $m);
        }
        return $out;
    }

    public static function skillBase(string $skill, array $e): int
    {
        switch ($skill) {
            case 'small_guns': return 5 + 4 * $e[self::AG];
            case 'big_guns': return 2 * $e[self::AG];
            case 'energy_weapons': return 2 * $e[self::AG];
            case 'unarmed': return 30 + 2 * ($e[self::AG] + $e[self::ST]);
            case 'melee_weapons': return 20 + 2 * ($e[self::AG] + $e[self::ST]);
            case 'throwing': return 4 * $e[self::AG];
            case 'first_aid': return 2 * ($e[self::PE] + $e[self::IN]);
            case 'doctor': return 5 + $e[self::PE] + $e[self::IN];
            case 'sneak': return 5 + 3 * $e[self::AG];
            case 'lockpick': return 10 + $e[self::PE] + $e[self::AG];
            case 'steal': return 3 * $e[self::AG];
            case 'traps': return 10 + $e[self::PE] + $e[self::AG];
            case 'science': return 4 * $e[self::IN];
            case 'repair': return 3 * $e[self::IN];
            case 'speech': return 5 * $e[self::CH];
            case 'barter': return 20 + 2 * $e[self::CH];
            case 'gambling': return 5 * $e[self::LK];
            case 'outdoorsman': return 2 * ($e[self::EN] + $e[self::IN]);
        }
        throw new \InvalidArgumentException("Неизвестный навык: $skill");
    }

    public static function skillValue(string $skill, array $stats, array $traits, array $tags, array $spent = [], array $perks = []): int
    {
        $e = self::effectiveStats($stats, $traits, $perks);
        $v = self::skillBase($skill, $e);
        $tagged = in_array($skill, $tags, true);
        if ($tagged) $v += self::TAG_BONUS;
        $idx = array_search($skill, self::SKILLS, true);
        $points = (int)($spent[$idx] ?? 0);
        $v += $points * ($tagged ? 2 : 1);
        if (in_array('gifted', $traits, true)) $v -= 10;
        if (in_array('good_natured', $traits, true)) {
            if (in_array($skill, ['first_aid', 'doctor', 'speech', 'barter'], true)) $v += 15;
            elseif (in_array($skill, self::COMBAT_SKILLS, true)) $v -= 10;
        }
        if (self::ranks($perks, 'thief') && in_array($skill, ['sneak', 'lockpick', 'steal', 'traps'], true)) $v += 10;
        if (self::ranks($perks, 'medic') && in_array($skill, ['first_aid', 'doctor'], true)) $v += 10;
        if (self::ranks($perks, 'negotiator') && in_array($skill, ['speech', 'barter'], true)) $v += 10;
        if (self::ranks($perks, 'ranger') && $skill === 'outdoorsman') $v += 15;
        return max(0, min(300, $v));
    }

    /** Производные показатели — те же, что на экране создания персонажа. */
    public static function derived(array $stats, array $traits, int $level = 1, ?array $armor = null, array $perks = []): array
    {
        $e = self::effectiveStats($stats, $traits, $perks);
        $r = fn(string $id) => self::ranks($perks, $id);
        $has = fn(string $t) => in_array($t, $traits, true);
        $fastMeta = $has('fast_metabolism');
        $intdiv = fn(int $a, int $b) => intdiv($a, $b);
        return [
            'max_hp' => 15 + $e[self::ST] + 2 * $e[self::EN] + ($level - 1) * (2 + $intdiv($e[self::EN], 2)) + 4 * $r('lifegiver') * $level,
            'armor_class' => ($has('kamikaze') ? 0 : $e[self::AG]) + (int)($armor['ac'] ?? 0),
            'action_points' => max(1, 5 + $intdiv($e[self::AG], 2) - ($has('bruiser') ? 2 : 0) + $r('action_boy')),
            'carry_weight' => ($has('small_frame') ? 25 + 15 * $e[self::ST] : 25 * $e[self::ST]) + 50 * $r('strong_back'),
            'melee_damage' => max(1, $e[self::ST] - 5) + ($has('heavy_handed') ? 4 : 0) + 2 * $r('bonus_hth_damage'),
            'damage_resistance' => (int)($armor['dr'] ?? 0) + 10 * $r('toughness'),
            'poison_resistance' => $fastMeta ? 0 : 5 * $e[self::EN],
            'radiation_resistance' => $fastMeta ? 0 : 2 * $e[self::EN],
            'healing_rate' => max(1, $intdiv($e[self::EN], 3)) + ($fastMeta ? 2 : 0) + 2 * $r('faster_healing'),
            'critical_chance' => $e[self::LK] + ($has('finesse') ? 10 : 0) + 5 * $r('more_criticals'),
            'sequence' => 2 * $e[self::PE] + ($has('kamikaze') ? 5 : 0) + 2 * $r('earlier_sequence'),
            'skill_points_per_level' => max(0, 5 + 2 * $e[self::IN] + ($has('skilled') ? 5 : 0) - ($has('gifted') ? 5 : 0) + 2 * $r('educated')),
            'perk_rate' => $has('skilled') ? 4 : 3,
        ];
    }

    /** Список id перков (для проверки данных от игрового сервера). */
    public const PERKS = [
        'awareness', 'bonus_hth_damage', 'earlier_sequence', 'faster_healing', 'quick_pockets', 'strong_back', 'swift_learner', 'thief',
        'toughness', 'bonus_ranged_damage', 'educated', 'more_criticals', 'medic', 'negotiator', 'ranger', 'pathfinder', 'silent_running',
        'better_criticals', 'sharpshooter', 'action_boy', 'lifegiver', 'master_trader',
        'gain_strength', 'gain_perception', 'gain_endurance', 'gain_charisma', 'gain_intelligence', 'gain_agility', 'gain_luck',
    ];

    public static function experienceForLevel(int $level): int
    {
        return intdiv(1000 * ($level - 1) * $level, 2);
    }

    public static function validateName(?string $name): ?string
    {
        $name = trim((string)$name);
        $len = mb_strlen($name);
        if ($len < self::NAME_MIN || $len > self::NAME_MAX) {
            return 'Имя должно быть от ' . self::NAME_MIN . ' до ' . self::NAME_MAX . ' символов';
        }
        if (!preg_match('/^[A-Za-zА-Яа-яЁё0-9][A-Za-zА-Яа-яЁё0-9 _\-]*[A-Za-zА-Яа-яЁё0-9]$/u', $name)) {
            return 'Имя может содержать буквы, цифры, пробел, дефис и подчёркивание';
        }
        return null;
    }

    /**
     * Проверка данных экрана создания персонажа.
     * @return array<string, string[]> поле => ошибки (пусто — всё верно)
     */
    public static function validateCreation(?string $name, $stats, $tags, $traits): array
    {
        $errors = [];
        if ($err = self::validateName($name)) $errors['name'][] = $err;

        if (!is_array($stats) || count($stats) !== 7 || array_keys($stats) !== range(0, 6)) {
            $errors['stats'][] = 'Нужно ровно 7 значений SPECIAL';
        } else {
            foreach ($stats as $i => $v) {
                if (!is_int($v) || $v < self::STAT_MIN || $v > self::STAT_MAX) {
                    $errors['stats'][] = self::STAT_CODES[$i] . ': значение должно быть от 1 до 10';
                }
            }
            if (empty($errors['stats'])) {
                $free = self::freePoints($stats);
                if ($free < 0) $errors['stats'][] = 'Распределено больше очков характеристик, чем доступно';
                if ($free > 0) $errors['stats'][] = "Остались нераспределённые очки характеристик: $free";
            }
        }

        if (!is_array($tags) || count($tags) !== self::TAG_COUNT) {
            $errors['tag_skills'][] = 'Нужно выбрать ровно 3 основных навыка';
        } else {
            if (count(array_unique($tags)) !== count($tags)) $errors['tag_skills'][] = 'Основные навыки не должны повторяться';
            foreach ($tags as $t) if (!in_array($t, self::SKILLS, true)) $errors['tag_skills'][] = "Неизвестный навык: $t";
        }

        if (!is_array($traits)) {
            $errors['traits'][] = 'Некорректный список особенностей';
        } else {
            if (count($traits) > self::MAX_TRAITS) $errors['traits'][] = 'Можно выбрать не более 2 особенностей';
            if (count(array_unique($traits)) !== count($traits)) $errors['traits'][] = 'Особенности не должны повторяться';
            foreach ($traits as $t) if (!in_array($t, self::TRAITS, true)) $errors['traits'][] = "Неизвестная особенность: $t";
        }
        return $errors;
    }

    /** Формула урона из ТЗ (раздел 4.5) — используется в тестах для сверки с C#. */
    public static function applyArmor(int $base, int $dmNum, int $dmDen, int $drm, int $dt, int $dr, bool $finesse = false, bool $critical = false): int
    {
        if ($critical) $dt = 0;
        $raw = intdiv($base * $dmNum, max(1, $dmDen));
        $afterDt = $raw - $dt;
        if ($afterDt <= 0) return 0;
        $pct = max(0, min(90, $dr + $drm));
        $final = $afterDt - intdiv($afterDt * $pct, 100);
        if ($finesse) $final = intdiv($final * 70, 100);
        return max(0, $final);
    }

    /** Формула ремонта двух экземпляров (раздел «Износ»). */
    public static function repairCombine(int $a, int $b, int $repairSkill): int
    {
        $best = max($a, $b);
        $worst = min($a, $b);
        return (int)min(100, floor($best + 0.05 * $worst + 5 + 0.15 * $repairSkill));
    }
}
