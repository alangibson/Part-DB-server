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
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parts\Category;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Formatters\SIFormatter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see \App\Tests\Services\LabelSystem\PlaceholderProviders\PartProviderTest
 */
final readonly class PartProvider implements PlaceholderProviderInterface
{
    private MarkdownConverter $inlineConverter;

    public function __construct(private SIFormatter $siFormatter, private TranslatorInterface $translator,
        private AttachmentURLGenerator $attachmentURLGenerator, private AttachmentManager $attachmentManager)
    {
        $environment = new Environment();
        $environment->addExtension(new InlinesOnlyExtension());
        $this->inlineConverter = new MarkdownConverter($environment);
    }

    public function replace(string $placeholder, object $part, array $options = []): ?string
    {
        if (!$part instanceof Part) {
            return null;
        }

        if ('[[CATEGORY]]' === $placeholder) {
            return $part->getCategory() instanceof Category ? htmlspecialchars($part->getCategory()->getName()) : '';
        }

        if ('[[CATEGORY_FULL]]' === $placeholder) {
            return $part->getCategory() instanceof Category ? htmlspecialchars($part->getCategory()->getFullPath()) : '';
        }

        if ('[[NEAREST_CATEGORY_PREVIEW]]' === $placeholder) {
            $category = $part->getCategory();

            while ($category instanceof Category) {
                $preview = $category->getMasterPictureAttachment();
                if ($preview !== null) {
                    $file = $this->attachmentManager->attachmentToFile($preview);
                    if ($file !== null) {
                        $contents = file_get_contents($file->getPathname());
                        $mime = mime_content_type($file->getPathname());
                        $url = $contents !== false && $mime !== false
                            ? sprintf('data:%s;base64,%s', $mime, base64_encode($contents))
                            : null;
                    } else {
                        $url = $this->attachmentURLGenerator->getThumbnailURL($preview, 'thumbnail_md');
                    }

                    if ($url !== null) {
                        return sprintf(
                            '<img src="%s" width="100%%" alt="%s"/>',
                            htmlspecialchars($url, ENT_QUOTES),
                            htmlspecialchars($category->getName(), ENT_QUOTES),
                        );
                    }
                }

                $category = $category->getParent();
            }

            return '';
        }

        if ('[[MANUFACTURER]]' === $placeholder) {
            return $part->getManufacturer() instanceof Manufacturer ? htmlspecialchars($part->getManufacturer()->getName()) : '';
        }

        if ('[[MANUFACTURER_FULL]]' === $placeholder) {
            return $part->getManufacturer() instanceof Manufacturer ? htmlspecialchars($part->getManufacturer()->getFullPath()) : '';
        }

        if ('[[FOOTPRINT]]' === $placeholder) {
            return $part->getFootprint() instanceof Footprint ? htmlspecialchars($part->getFootprint()->getName()) : '';
        }

        if ('[[FOOTPRINT_FULL]]' === $placeholder) {
            return $part->getFootprint() instanceof Footprint ? htmlspecialchars($part->getFootprint()->getFullPath()) : '';
        }

        if ('[[MASS]]' === $placeholder) {
            return $part->getMass() ? $this->siFormatter->format($part->getMass(), 'g', 1) : '';
        }

        if ('[[MPN]]' === $placeholder) {
            return htmlspecialchars($part->getManufacturerProductNumber());
        }

        if ('[[IPN]]' === $placeholder) {
            return $part->getIpn() ? htmlspecialchars($part->getIpn()) : '';
        }

        if ('[[TAGS]]' === $placeholder) {
            return htmlspecialchars($part->getTags());
        }

        if ('[[M_STATUS]]' === $placeholder) {
            if (null === $part->getManufacturingStatus()) {
                return '';
            }

            return $this->translator->trans($part->getManufacturingStatus()->toTranslationKey());
        }

        if ('[[DESCRIPTION]]' === $placeholder) {
            return trim($this->inlineConverter->convert($part->getDescription())->getContent());
        }

        if ('[[DESCRIPTION_T]]' === $placeholder) {
            return trim(strip_tags($this->inlineConverter->convert($part->getDescription())->getContent()));
        }

        if ('[[COMMENT]]' === $placeholder) {
            return trim($this->inlineConverter->convert($part->getComment())->getContent());
        }

        if ('[[COMMENT_T]]' === $placeholder) {
            return trim(strip_tags($this->inlineConverter->convert($part->getComment())->getContent()));
        }

        return null;
    }
}
