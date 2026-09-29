<?php

namespace Tests\Unit\Streets;

use App\Streets\StreetName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StreetNameTest extends TestCase
{
    public static function types(): array
    {
        return [
            ['Chapel Street', 'Street'],
            ['Bolton Road West', 'Road'],
            ['Manchester Road East', 'Road'],
            ['The Crescent', 'Crescent'],
            ['Broadway', 'Broadway'],
            ['Pendleton Roundabout', 'Roundabout'],
            ['The Height', 'Other'],
            ['Queensway', 'Other'],
            ['North', 'Other'],
        ];
    }

    #[DataProvider('types')]
    public function test_it_parses_the_street_type(string $name, string $type): void
    {
        $this->assertSame($type, StreetName::type($name));
    }

    public function test_it_cleans_names_and_builds_matching_keys(): void
    {
        $this->assertSame("St John's Road", StreetName::clean("  St John\u{2019}s   Road "));
        $this->assertSame(StreetName::key("St. John's Road"), StreetName::key("st john\u{2019}s road"));
        $this->assertNotSame(StreetName::key('Church Street'), StreetName::key('Church Road'));
    }

    public function test_first_letter(): void
    {
        $this->assertSame('C', StreetName::firstLetter('Chapel Street'));
        $this->assertSame('T', StreetName::firstLetter('The Crescent'));
        $this->assertSame('I', StreetName::firstLetter("Irlams o' th' Height"));
    }
}
