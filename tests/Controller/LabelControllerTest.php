<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Tests\Controller;

use App\Entity\LabelSystem\LabelOutputFormat;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LabelControllerTest extends WebTestCase
{
    public function testLabelleOutputCanBeDownloaded(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/en/label/dialog?target_type=part&target_id=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="label_dialog[output_format]"]');

        $form = $crawler->filter('button[name="label_dialog[update]"]')->form();
        $form['label_dialog[output_format]']->select('labelle');
        $form['label_dialog[options][lines]'] = '<p>Labelle test</p>';
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a[download$=".labelle"]', 'Download Labelle file');
        self::assertSelectorExists('a[href^="data:text/plain"]');
    }

    public function testPdfRemainsDefaultOutputFormat(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/en/label/dialog?target_type=part&target_id=1');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('button[name="label_dialog[update]"]')->form();
        self::assertSame('pdf', $form['label_dialog[output_format]']->getValue());
    }

    public function testProfilePreselectsItsOutputFormat(): void
    {
        $client = $this->createAuthenticatedClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $profile = $entityManager->getRepository(LabelProfile::class)->findOneBy([]);
        self::assertInstanceOf(LabelProfile::class, $profile);
        $profile->setOutputFormat(LabelOutputFormat::LABELLE);
        $entityManager->flush();

        $crawler = $client->request('GET', '/en/label/'.$profile->getID().'/dialog');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('button[name="label_dialog[update]"]')->form();
        self::assertSame('labelle', $form['label_dialog[output_format]']->getValue());
    }

    private function createAuthenticatedClient(): KernelBrowser
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        return $client;
    }
}
