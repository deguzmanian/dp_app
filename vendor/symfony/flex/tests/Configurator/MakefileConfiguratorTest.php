<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex\Tests\Configurator;

require_once __DIR__.'/TmpDirMock.php';

use PHPUnit\Framework\TestCase;
use Symfony\Flex\Configurator\MakefileConfigurator;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;
use Composer\IO\IOInterface;
use Composer\Composer;

class MakefileConfiguratorTest extends TestCase
{
    public function testConfigure()
    {
        $configurator = new MakefileConfigurator(
            $this->getMockBuilder(Composer::class)->getMock(),
            $this->getMockBuilder(IOInterface::class)->getMock(),
            new Options()
        );

        $recipe1 = $this->getMockBuilder(Recipe::class)->disableOriginalConstructor()->getMock();
        $recipe1->expects($this->any())->method('getName')->will($this->returnValue('FooBundle'));

        $recipe2 = $this->getMockBuilder(Recipe::class)->disableOriginalConstructor()->getMock();
        $recipe2->expects($this->any())->method('getName')->will($this->returnValue('BarBundle'));

        $makefile = sys_get_temp_dir().'/Makefile';
        @unlink($makefile);
        touch($makefile);

        $makefile1 = explode(
            "\n",
            <<<EOF
CONSOLE := $(shell which bin/console)
sf_console:
ifndef CONSOLE
	@printf "Run \033[32mcomposer require cli\033[39m to install the Symfony console.\n"
endif
EOF
        );
        $makefile2 = explode(
            "\n",
            <<<EOF
cache-clear:
ifdef CONSOLE
	@$(CONSOLE) cache:clear --no-warmup
else
	@rm -rf var/cache/*
endif
.PHONY: cache-clear
EOF
        );

        $makefileContents1 = "###> FooBundle ###\n".implode("\n", $makefile1)."\n###< FooBundle ###";
        $makefileContents2 = "###> BarBundle ###\n".implode("\n", $makefile2)."\n###< BarBundle ###";

        $configurator->configure($recipe1, $makefile1);
        $this->assertStringEqualsFile($makefile, "\n".$makefileContents1."\n");

        $configurator->configure($recipe2, $makefile2);
        $this->assertStringEqualsFile($makefile, "\n".$makefileContents1."\n\n".$makefileContents2."\n");

        $configurator->configure($recipe1, $makefile1);
        $configurator->configure($recipe2, $makefile2);
        $this->assertStringEqualsFile($makefile, "\n".$makefileContents1."\n\n".$makefileContents2."\n");

        $configurator->unconfigure($recipe1, $makefile1);
        $this->assertStringEqualsFile($makefile, $makefileContents2."\n");

        $configurator->unconfigure($recipe2, $makefile2);
        $this->assertFalse(is_file($makefile));
    }
}
