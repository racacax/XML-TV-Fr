<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\MyCanal;

class MyCanalTest extends TestCase
{
    public function testPeopleOfAProgramAreConvertedToCredits(): void
    {
        $person = fn (string $name) => ['title' => $name, 'onClick' => ['displayName' => $name]];
        $credits = MyCanal::getCredits([
            ['prefix' => 'De :', 'personnalitiesList' => [$person('Catherine Corsini')]],
            ['prefix' => 'Avec :', 'personnalitiesList' => [$person('Cécile de France'), $person('Izïa Higelin')]],
            ['prefix' => 'Produit par :', 'personnalitiesList' => [$person('Un producteur')]],
            ['prefix' => 'Présenté par :', 'personnalitiesList' => [$person('Un présentateur')]],
            ['prefix' => 'Musique :', 'personnalitiesList' => [$person('Un compositeur')]],
            ['prefix' => 'Scénario :', 'personnalitiesList' => [$person('Un scénariste')]],
            ['prefix' => 'Auteur :', 'personnalitiesList' => [$person('Un auteur')]],
            // Without equivalent in XMLTV
            ['prefix' => 'Chorégraphie :', 'personnalitiesList' => [$person('Un chorégraphe')]],
            ['prefix' => 'Voix française :', 'personnalitiesList' => [$person('Une voix')]],
            ['personnalitiesList' => [$person('Sans rôle')]],
            ['prefix' => 'Avec :', 'personnalitiesList' => [['title' => '']]],
        ]);

        $this->assertSame([
            ['Catherine Corsini', 'director'],
            ['Cécile de France', 'actor'],
            ['Izïa Higelin', 'actor'],
            ['Un producteur', 'producer'],
            ['Un présentateur', 'presenter'],
            ['Un compositeur', 'composer'],
            ['Un scénariste', 'writer'],
            ['Un auteur', 'writer'],
        ], $credits);
    }

    public function testNoPeople(): void
    {
        $this->assertSame([], MyCanal::getCredits([]));
    }
}
