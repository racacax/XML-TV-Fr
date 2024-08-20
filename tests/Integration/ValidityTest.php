<?php

namespace racacax\XmlTvTest\Integration;

use PHPUnit\Framework\TestCase;
use racacax\XmlTvTest\Utils;

/**
 * @group validity
 */
class ValidityTest extends TestCase
{
    public function testIntegrity()
    {
        $this->assertEquals(
            file_get_contents('integrity.sha256'),
            Utils::generateHash(),
            "Hashes don't match. If all other tests passed, they will match in the next run.
            If you're running from the pipeline, tests need to be run locally first to be validated."
        );
    }

}
