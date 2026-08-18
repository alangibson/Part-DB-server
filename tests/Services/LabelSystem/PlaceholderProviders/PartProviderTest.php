<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Entity\Parts\ManufacturingStatus;
use Doctrine\ORM\EntityManager;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Entity\Attachments\CategoryAttachment;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Formatters\SIFormatter;
use App\Services\LabelSystem\PlaceholderProviders\PartProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('DB')]
final class PartProviderTest extends WebTestCase
{
    /**
     * @var PartProvider
     */
    protected PartProvider $service;

    protected Part $target;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(PartProvider::class);
        $this->target = new Part();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $this->target->setCategory($em->find(Category::class, 6));
        $this->target->setFootprint($em->find(Footprint::class, 6));
        $this->target->setManufacturer(null);

        $this->target->setMass(1234.2);
        $this->target->setTags('SMD, Tag1, Tag2');
        $this->target->setManufacturerProductNumber('MPN123');
        $this->target->setManufacturingStatus(ManufacturingStatus::ACTIVE);

        $this->target->setDescription('<b>Bold</b> *Italic*');
        $this->target->setComment('<b>Bold</b> *Italic*');
    }

    public static function dataProvider(): \Iterator
    {
        yield ['Node 2.1', '[[CATEGORY]]'];
        yield ['Node 2 → Node 2.1', '[[CATEGORY_FULL]]'];
        yield ['Node 2.1', '[[FOOTPRINT]]'];
        yield ['Node 2 → Node 2.1', '[[FOOTPRINT_FULL]]'];
        yield ['', '[[MANUFACTURER]]'];
        yield ['', '[[MANUFACTURER_FULL]]'];
        yield ['1.2 kg', '[[MASS]]'];
        yield ['MPN123', '[[MPN]]'];
        yield ['SMD, Tag1, Tag2', '[[TAGS]]'];
        yield ['Active', '[[M_STATUS]]'];
        yield ['<b>Bold</b> <em>Italic</em>', '[[DESCRIPTION]]'];
        yield ['Bold Italic', '[[DESCRIPTION_T]]'];
        yield ['<b>Bold</b> <em>Italic</em>', '[[COMMENT]]'];
        yield ['Bold Italic', '[[COMMENT_T]]'];
    }

    #[DataProvider('dataProvider')]
    public function testReplace(string $expected, string $placeholder): void
    {
        $this->assertSame($expected, $this->service->replace($placeholder, $this->target));
    }

    public function testNearestCategoryPreviewFallsBackToParent(): void
    {
        $parent = new Category();
        $parent->setName('Parent & category');
        $preview = new CategoryAttachment();
        $parent->setMasterPictureAttachment($preview);

        $child = new Category();
        $child->setName('Child');
        $child->setParent($parent);
        $this->target->setCategory($child);

        $urlGenerator = $this->createMock(AttachmentURLGenerator::class);
        $urlGenerator->expects($this->once())
            ->method('getThumbnailURL')
            ->with($preview, 'thumbnail_md')
            ->willReturn('/preview/image?a=1&b=2');

        $service = new PartProvider(
            self::getContainer()->get(SIFormatter::class),
            self::getContainer()->get(TranslatorInterface::class),
            $urlGenerator,
            self::getContainer()->get(AttachmentManager::class),
        );

        $this->assertSame(
            '<img src="/preview/image?a=1&amp;b=2" width="100%" alt="Parent &amp; category"/>',
            $service->replace('[[NEAREST_CATEGORY_PREVIEW]]', $this->target),
        );
    }

    public function testNearestCategoryPreviewIsEmptyWhenNoCategoryHasPreview(): void
    {
        $parent = new Category();
        $child = new Category();
        $child->setParent($parent);
        $this->target->setCategory($child);

        $this->assertSame('', $this->service->replace('[[NEAREST_CATEGORY_PREVIEW]]', $this->target));
    }
}
