<?php

declare(strict_types=1);

namespace App\Tests\Controller\AdminPages;

use App\Entity\LabelSystem\LabelSheet;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('DB')]
final class LabelSheetControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $this->client->loginUser($admin);
        $this->client->catchExceptions(false);
    }

    public function testNewScreenRendersCompleteForm(): void
    {
        $crawler = $this->client->request('GET', '/en/label_sheet/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="label_sheet"]');
        self::assertSelectorExists('[data-controller="pages--label-sheet"]');
        self::assertSelectorExists('input[name="label_sheet[name]"]');
        self::assertSelectorExists('input[name="label_sheet[columns]"]');
        self::assertSelectorExists('input[name="label_sheet[rows]"]');
        self::assertSelectorExists('input[name="label_sheet[label_width]"]');
        self::assertSelectorExists('input[name="label_sheet[label_height]"]');
        self::assertSelectorTextContains('#label-sheet-standard', 'Sheet size');
        self::assertSelectorTextContains('#label-sheet-standard', 'Label size');
        self::assertCount(1, $crawler->filter('button[name="label_sheet[save]"]'));
    }

    public function testEditScreenRendersExistingSheet(): void
    {
        $sheet = $this->entityManager->getRepository(LabelSheet::class)->findOneBy([]);
        if (!$sheet instanceof LabelSheet) {
            self::markTestSkipped('No user-defined Label Sheet exists in the read-only test database.');
        }

        $this->client->request('GET', '/en/label_sheet/'.$sheet->getID().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('legend', $sheet->getName());
        self::assertInputValueSame('label_sheet[name]', $sheet->getName());
        self::assertSelectorExists('form[action="/en/label_sheet/'.$sheet->getID().'"]');
    }

    public function testInvalidSubmittedFormRendersWithErrors(): void
    {
        $crawler = $this->client->request('GET', '/en/label_sheet/new');
        $form = $crawler->selectButton('Create')->form([
            'label_sheet[name]' => '',
            'label_sheet[page_size]' => 'A4',
            'label_sheet[unit]' => 'mm',
            'label_sheet[width]' => '210',
            'label_sheet[height]' => '297',
            'label_sheet[margin_left]' => '0',
            'label_sheet[margin_top]' => '0',
            'label_sheet[gutter_width]' => '0',
            'label_sheet[gutter_height]' => '0',
            'label_sheet[columns]' => '3',
            'label_sheet[rows]' => '8',
            'label_sheet[corner_radius]' => '0',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="label_sheet"]');
        self::assertSelectorExists('.invalid-feedback');
        self::assertInputValueSame('label_sheet[columns]', '3');
        self::assertInputValueSame('label_sheet[rows]', '8');
    }

    public function testSheetWhoseGridDoesNotFitCannotBeSaved(): void
    {
        $crawler = $this->client->request('GET', '/en/label_sheet/new');
        $form = $crawler->selectButton('Create')->form([
            'label_sheet[name]' => 'Invalid grid test',
            'label_sheet[page_size]' => 'A4',
            'label_sheet[unit]' => 'mm',
            'label_sheet[width]' => '210',
            'label_sheet[height]' => '297',
            'label_sheet[label_width]' => '69',
            'label_sheet[label_height]' => '49',
            'label_sheet[margin_left]' => '0',
            'label_sheet[margin_top]' => '1',
            'label_sheet[gutter_width]' => '0',
            'label_sheet[gutter_height]' => '0.5',
            'label_sheet[columns]' => '3',
            'label_sheet[rows]' => '6',
            'label_sheet[corner_radius]' => '0',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'The configured label grid does not fit on the sheet.');
    }

    public function testLabelProfileScreenOffersVirtualDefaultSheet(): void
    {
        $this->client->request('GET', '/en/label_profile/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('select[name$="[label_sheet]"] option[value=""]', 'Default');
        self::assertSelectorExists('[data-controller="pages--label-profile-sheet"]');
        self::assertSelectorExists('select[data-action="change->pages--label-profile-sheet#toggle"]');
        self::assertSelectorExists('[data-pages--label-profile-sheet-target="labelSize"]');
        self::assertSelectorNotExists('[data-pages--label-profile-sheet-target="labelSize"].d-none');
        self::assertSelectorExists('input[name$="[options][width]"]');
        self::assertSelectorExists('input[name$="[options][height]"]');
        self::assertSelectorExists('input[data-pages--label-profile-sheet-target="labelWidth"]');
        self::assertSelectorExists('input[data-pages--label-profile-sheet-target="labelHeight"]');
        self::assertSelectorExists('select[name$="[label_sheet]"] option[data-label-width][data-label-height]');
    }

    public function testSavingProfileCopiesSelectedSheetLabelSizeIntoProfileColumns(): void
    {
        $sheet = $this->entityManager->getRepository(LabelSheet::class)->findOneBy([]);
        $profile = $this->entityManager->getRepository(LabelProfile::class)->findOneBy([]);
        if (!$sheet instanceof LabelSheet || !$profile instanceof LabelProfile) {
            self::markTestSkipped('A Label Sheet and Label Profile are required.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $connection->update('label_sheets', ['label_width' => 73, 'label_height' => 41], ['id' => $sheet->getID()]);
            $connection->update('label_profiles', [
                'label_sheet_id' => null,
                'options_width' => 11,
                'options_height' => 12,
            ], ['id' => $profile->getID()]);
            $this->entityManager->clear();

            $crawler = $this->client->request('GET', '/en/label_profile/'.$profile->getID().'/edit');
            self::assertResponseIsSuccessful();
            $select = $crawler->filter('select[name$="[label_sheet]"]');
            $selectName = $select->attr('name');
            self::assertNotNull($selectName);
            $saveButton = $crawler->filter('button[name$="[save]"]')->first();
            self::assertSame(1, $saveButton->count());
            $form = $saveButton->form();
            $form[$selectName]->select((string) $sheet->getID());
            $this->client->submit($form);

            self::assertResponseIsSuccessful();
            $stored = $connection->fetchAssociative('SELECT label_sheet_id, options_width, options_height FROM label_profiles WHERE id = ?', [$profile->getID()]);
            self::assertIsArray($stored);
            self::assertSame($sheet->getID(), (int) $stored['label_sheet_id']);
            self::assertSame(73.0, (float) $stored['options_width']);
            self::assertSame(41.0, (float) $stored['options_height']);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

}
