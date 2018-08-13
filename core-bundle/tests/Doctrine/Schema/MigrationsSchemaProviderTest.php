<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Doctrine\Schema;

use Contao\CoreBundle\Doctrine\Schema\MigrationsSchemaProvider;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;

/**
 * Tests the DcaSchemaProvider class.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class MigrationsSchemaProviderTest extends DcaSchemaProviderTest
{
    /**
     * Tests the object instantiation.
     */
    public function testCanBeInstantiated()
    {
        $provider = new MigrationsSchemaProvider(
            $this->createMock(ContaoFrameworkInterface::class),
            $this->createMock(Registry::class)
        );

        $this->assertInstanceOf('Contao\CoreBundle\Doctrine\Schema\MigrationsSchemaProvider', $provider);
    }
}