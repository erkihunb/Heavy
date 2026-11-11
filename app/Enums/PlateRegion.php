<?php

namespace App\Enums;

/**
 * Plate region prefixes. The backed value is the canonical (Ethiopic) code that
 * is stored in `vehicles.region_code` and appears in normalized plate numbers.
 *
 * PLACEHOLDER LIST: to be confirmed against the official register with the
 * Authority (see docs/DECISIONS.md). Adding a region is adding a case here.
 */
enum PlateRegion: string
{
    case Federal = 'ኢት';
    case AddisAbaba = 'አአ';
    case Afar = 'አፋ';
    case Amhara = 'አማ';
    case BenishangulGumuz = 'ቤጉ';
    case DireDawa = 'ድሬ';
    case Gambela = 'ጋም';
    case Harari = 'ሐረ';
    case Oromia = 'ኦሮ';
    case Sidama = 'ሲዳ';
    case Somali = 'ሶማ';
    case SouthernNations = 'ደሕ';
    case Tigray = 'ትግ';

    /**
     * Latin spelling of the prefix, accepted on input and mapped to the canonical code.
     */
    public function latinCode(): string
    {
        return match ($this) {
            self::Federal => 'ET',
            self::AddisAbaba => 'AA',
            self::Afar => 'AF',
            self::Amhara => 'AM',
            self::BenishangulGumuz => 'BG',
            self::DireDawa => 'DR',
            self::Gambela => 'GM',
            self::Harari => 'HR',
            self::Oromia => 'OR',
            self::Sidama => 'SD',
            self::Somali => 'SM',
            self::SouthernNations => 'SP',
            self::Tigray => 'TG',
        };
    }

    /**
     * Resolve a prefix written in either script; Latin input must already be upper-cased.
     */
    public static function fromPrefix(string $prefix): ?self
    {
        foreach (self::cases() as $region) {
            if ($region->value === $prefix || $region->latinCode() === $prefix) {
                return $region;
            }
        }

        return null;
    }
}
